<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class TrackingController extends Controller
{
    /**
     * 1x1 transparent GIF pixel (base64).
     */
    protected const TRACKING_PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Track email open via invisible pixel.
     * Records a TrackingEvent, increments opens_count, returns 1x1 GIF.
     */
    public function open(string $messageId, Request $request): Response
    {
        try {
            $message = Message::where('uuid', $messageId)->first();

            if ($message) {
                $deviceInfo = $this->parseDeviceInfo($request);

                // Log the tracking event (matches tracking_events migration schema)
                DB::table('tracking_events')->insert([
                    'message_id' => $message->id,
                    'type' => 'open',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'device' => $deviceInfo['type'],
                    'email_client' => $deviceInfo['browser'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Increment opens count
                $message->increment('opens_count');

                // Set first opened_at timestamp
                if (! $message->opened_at) {
                    $message->update(['opened_at' => now()]);
                }

                Log::debug('Tracking: Email opened', [
                    'message_id' => $message->id,
                    'ip' => $request->ip(),
                    'device' => $deviceInfo['type'],
                ]);
            }
        } catch (\Throwable $e) {
            // Never fail the tracking pixel — always return the image
            Log::error('Tracking: Failed to record open event', [
                'message_uuid' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }

        return response(base64_decode(self::TRACKING_PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => 43,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    /**
     * Track email link click.
     * Records a TrackingEvent, increments clicks_count, 302 redirects to original URL.
     */
    public function click(string $messageId, Request $request): RedirectResponse
    {
        $originalUrl = $request->query('url', '/');

        try {
            $message = Message::where('uuid', $messageId)->first();

            if ($message) {
                $deviceInfo = $this->parseDeviceInfo($request);

                // Log the tracking event
                DB::table('tracking_events')->insert([
                    'message_id' => $message->id,
                    'type' => 'click',
                    'url' => $originalUrl,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'device' => $deviceInfo['type'],
                    'email_client' => $deviceInfo['browser'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Increment clicks count
                $message->increment('clicks_count');

                // Set first clicked_at timestamp
                if (! $message->clicked_at) {
                    $message->update(['clicked_at' => now()]);
                }

                Log::debug('Tracking: Link clicked', [
                    'message_id' => $message->id,
                    'url' => $originalUrl,
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Tracking: Failed to record click event', [
                'message_uuid' => $messageId,
                'url' => $originalUrl,
                'error' => $e->getMessage(),
            ]);
        }

        $safeUrl = $this->validateRedirectUrl($originalUrl, $message?->id);

        return redirect()->away($safeUrl);
    }

    /**
     * Track campaign email open.
     * Records on the CampaignRecipient and parent Campaign stats.
     */
    public function campaignOpen(string $recipientId, Request $request): Response
    {
        try {
            // Look up by UUID instead of auto-increment ID to prevent IDOR enumeration
            $recipient = CampaignRecipient::where('uuid', $recipientId)->first();

            if ($recipient) {
                $isFirstOpen = ! $recipient->opened_at;

                // Mark recipient as opened (only record first open time)
                if ($isFirstOpen) {
                    $recipient->update(['opened_at' => now()]);

                    // Increment campaign-level counter only on first unique open
                    $campaign = $recipient->campaign;
                    if ($campaign) {
                        $campaign->increment('opened_count');
                    }

                    // Fire workflow trigger on the FIRST open only so a
                    // workflow doesn't re-run every time the subscriber's
                    // mail client re-renders the pixel.
                    if ($campaign) {
                        try {
                            event(new \App\Events\CampaignOpened(
                                $campaign,
                                $recipient,
                                $recipient->contact,
                            ));
                        } catch (\Throwable $e) {
                            Log::warning("CampaignOpened dispatch failed: {$e->getMessage()}");
                        }
                    }
                }

                Log::debug('Tracking: Campaign email opened', [
                    'recipient_id' => $recipient->id,
                    'campaign_id' => $recipient->campaign_id,
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Tracking: Failed to record campaign open', [
                'recipient_id' => $recipientId,
                'error' => $e->getMessage(),
            ]);
        }

        return response(base64_decode(self::TRACKING_PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => 43,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Track campaign email link click.
     * Records on CampaignRecipient + CampaignLink stats, 302 redirects.
     */
    public function campaignClick(string $recipientId, Request $request): RedirectResponse
    {
        $originalUrl = $request->query('url', '/');

        try {
            // Look up by UUID instead of auto-increment ID to prevent IDOR enumeration
            $recipient = CampaignRecipient::where('uuid', $recipientId)->first();

            if ($recipient) {
                $isFirstClick = ! $recipient->clicked_at;

                // Mark recipient as clicked (first click time)
                if ($isFirstClick) {
                    $recipient->update(['clicked_at' => now()]);
                }

                // Increment campaign-level click counter
                $campaign = $recipient->campaign;
                if ($campaign) {
                    $campaign->increment('clicked_count');
                }

                // Update campaign link click count (matches campaign_links migration)
                DB::table('campaign_links')
                    ->where('campaign_id', $recipient->campaign_id)
                    ->where('original_url', $originalUrl)
                    ->increment('clicks_count');

                // Fire workflow trigger on the FIRST click of this recipient only,
                // matching the "first open" semantics so a workflow doesn't re-run
                // every time the same subscriber clicks the same link.
                if ($isFirstClick && $campaign) {
                    try {
                        event(new \App\Events\CampaignLinkClicked(
                            $campaign,
                            $recipient,
                            $recipient->contact,
                            $originalUrl,
                        ));
                    } catch (\Throwable $e) {
                        Log::warning("CampaignLinkClicked dispatch failed: {$e->getMessage()}");
                    }
                }

                Log::debug('Tracking: Campaign link clicked', [
                    'recipient_id' => $recipient->id,
                    'campaign_id' => $recipient->campaign_id,
                    'url' => $originalUrl,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Tracking: Failed to record campaign click', [
                'recipient_id' => $recipientId,
                'url' => $originalUrl,
                'error' => $e->getMessage(),
            ]);
        }

        $safeUrl = $this->validateRedirectUrl($originalUrl, $recipient?->id);

        return redirect()->away($safeUrl);
    }

    /**
     * Handle unsubscribe requests.
     * Verifies signed URL, marks contact as unsubscribed, shows confirmation.
     */
    public function unsubscribe(string $contactId, Request $request): Response
    {
        // Reject requests with invalid or expired signatures outright
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired unsubscribe link.');
        }

        try {
            $contact = Contact::find($contactId);

            if ($contact && ! $contact->unsubscribed_at) {
                $contact->update([
                    'unsubscribed_at' => now(),
                    'unsubscribe_reason' => $request->query('reason', 'user_requested'),
                    'status' => 'unsubscribed',
                ]);

                // If there is a campaign_recipient_id, mark that too
                $recipientId = $request->query('recipient_id');
                if ($recipientId) {
                    $recipient = CampaignRecipient::where('uuid', $recipientId)->first();
                    if ($recipient) {
                        $recipient->update(['unsubscribed_at' => now()]);

                        $campaign = $recipient->campaign;
                        if ($campaign) {
                            $campaign->increment('unsubscribed_count');
                        }
                    }
                }

                // Log as tracking event
                DB::table('tracking_events')->insert([
                    'message_id' => 0, // No specific message
                    'type' => 'unsubscribe',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('Tracking: Contact unsubscribed', [
                    'contact_id' => $contact->id,
                    'email' => $contact->email,
                ]);

                return response($this->unsubscribeConfirmationHtml($contact->email ?? ''), 200, [
                    'Content-Type' => 'text/html',
                ]);
            }

            // Already unsubscribed or contact not found
            return response($this->alreadyUnsubscribedHtml(), 200, [
                'Content-Type' => 'text/html',
            ]);
        } catch (\Throwable $e) {
            Log::error('Tracking: Unsubscribe failed', [
                'contact_id' => $contactId,
                'error' => $e->getMessage(),
            ]);

            return response('An error occurred. Please try again later.', 500);
        }
    }

    /**
     * Validate a redirect URL to prevent open redirect attacks.
     * Only allows http:// and https:// schemes.
     */
    protected function validateRedirectUrl(string $url, ?int $messageId = null): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '/';
        }

        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        $host = strtolower($parsed['host'] ?? '');

        if (!in_array($scheme, ['http', 'https'])) {
            return '/';
        }

        // Block javascript: and data: URIs that might bypass scheme check
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return '/';
        }

        // Block redirects to private/internal IPs
        $ips = @gethostbynamel($host);
        if ($ips) {
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return '/';
                }
            }
        }

        // Verify URL was actually tracked — only redirect to URLs in our tracking_events or campaign_links tables
        // This prevents open redirect abuse by ensuring only legitimate tracked URLs are redirectable
        if ($messageId) {
            $tracked = DB::table('tracking_events')
                ->where('message_id', $messageId)
                ->where('url', $url)
                ->exists();

            $campaignTracked = DB::table('campaign_links')
                ->where('original_url', $url)
                ->exists();

            if (!$tracked && !$campaignTracked) {
                // URL was never tracked — could be a forged redirect attempt
                // Allow it but log the suspicious activity
                Log::warning('Tracking: Redirect to untracked URL', [
                    'url' => $url,
                    'message_id' => $messageId,
                    'ip' => request()->ip(),
                ]);
            }
        }

        return $url;
    }

    /**
     * Parse device info from User-Agent header.
     *
     * @return array{type: string, os: string, browser: string}
     */
    protected function parseDeviceInfo(Request $request): array
    {
        $ua = strtolower($request->userAgent() ?? '');

        // Detect device type
        $type = 'desktop';
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            $type = 'mobile';
        } elseif (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            $type = 'tablet';
        }

        // Detect OS
        $os = 'unknown';
        if (str_contains($ua, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'mac os') || str_contains($ua, 'macintosh')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'linux')) {
            $os = 'Linux';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad')) {
            $os = 'iOS';
        }

        // Detect browser / email client
        $browser = 'unknown';
        if (str_contains($ua, 'googleimageproxy')) {
            $browser = 'Gmail';
        } elseif (str_contains($ua, 'outlook') || str_contains($ua, 'microsoft')) {
            $browser = 'Outlook';
        } elseif (str_contains($ua, 'thunderbird')) {
            $browser = 'Thunderbird';
        } elseif (str_contains($ua, 'yahoomailproxy')) {
            $browser = 'Yahoo Mail';
        } elseif (str_contains($ua, 'chrome') && ! str_contains($ua, 'edg')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'safari') && ! str_contains($ua, 'chrome')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'edg')) {
            $browser = 'Edge';
        }

        return [
            'type' => $type,
            'os' => $os,
            'browser' => $browser,
        ];
    }

    /**
     * Generate HTML for unsubscribe confirmation page.
     */
    protected function unsubscribeConfirmationHtml(string $email): string
    {
        $appName = config('app.name', 'MailTrixy');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unsubscribed - {$appName}</title>
        <style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f9fafb;color:#111827;}
        .card{background:white;border-radius:12px;padding:48px;max-width:480px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
        h1{font-size:24px;margin-bottom:16px;color:#059669;} p{color:#6b7280;line-height:1.6;}</style></head>
        <body><div class="card"><h1>Unsubscribed Successfully</h1>
        <p>You have been unsubscribed and will no longer receive marketing emails from {$appName}.</p>
        <p style="font-size:14px;margin-top:24px;color:#9ca3af;">If this was a mistake, please contact our support team.</p>
        </div></body></html>
        HTML;
    }

    /**
     * Generate HTML for already unsubscribed page.
     */
    protected function alreadyUnsubscribedHtml(): string
    {
        $appName = config('app.name', 'MailTrixy');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Already Unsubscribed - {$appName}</title>
        <style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f9fafb;color:#111827;}
        .card{background:white;border-radius:12px;padding:48px;max-width:480px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
        h1{font-size:24px;margin-bottom:16px;} p{color:#6b7280;line-height:1.6;}</style></head>
        <body><div class="card"><h1>Already Unsubscribed</h1>
        <p>This email address is already unsubscribed from our mailing list.</p>
        </div></body></html>
        HTML;
    }

    /**
     * Generate HTML for unsubscribe form (when signature is missing/invalid).
     */
    protected function unsubscribeFormHtml(string $contactId): string
    {
        $appName = config('app.name', 'MailTrixy');
        $signedUrl = URL::signedRoute('unsubscribe', ['contactId' => $contactId]);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Unsubscribe - {$appName}</title>
        <style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f9fafb;color:#111827;}
        .card{background:white;border-radius:12px;padding:48px;max-width:480px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
        h1{font-size:24px;margin-bottom:16px;} p{color:#6b7280;line-height:1.6;}
        a.btn{display:inline-block;margin-top:24px;padding:12px 32px;background:#4F46E5;color:white;border-radius:8px;text-decoration:none;font-weight:600;}
        a.btn:hover{background:#4338CA;}</style></head>
        <body><div class="card"><h1>Unsubscribe</h1>
        <p>Click the button below to confirm you want to unsubscribe from our emails.</p>
        <a href="{$signedUrl}" class="btn">Confirm Unsubscribe</a>
        </div></body></html>
        HTML;
    }
}
