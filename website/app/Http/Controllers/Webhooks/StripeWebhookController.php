<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    /**
     * Handle incoming Stripe webhook.
     *
     * POST /stripe/webhook
     *
     * Stripe sends events to this endpoint. The signature is verified
     * against the webhook secret before processing.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            Log::warning('Stripe webhook received without signature header');
            return response('Missing Stripe-Signature header.', 400);
        }

        try {
            $event = $this->stripeService->handleWebhook($payload, $signature);

            // Idempotency: if this event ID was already processed, return 200 immediately.
            // This guards against Stripe retrying or sending duplicate webhook deliveries.
            $cacheKey = "stripe_webhook:{$event->id}";
            if (Cache::has($cacheKey)) {
                Log::debug('Stripe webhook: duplicate event skipped', ['event_id' => $event->id]);
                return response('Webhook already processed.', 200);
            }

            $this->stripeService->dispatchEvent($event);

            // Cache the event ID for 24 hours to prevent reprocessing
            Cache::put($cacheKey, true, now()->addHours(24));

            return response('Webhook handled.', 200);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Invalid Stripe webhook signature')) {
                Log::warning('Stripe webhook signature verification failed', [
                    'ip' => $request->ip(),
                ]);
                return response('Invalid signature.', 400);
            }

            Log::error('Stripe webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent Stripe from retrying for application errors.
            // The error is logged for manual investigation.
            return response('Webhook received but encountered an error.', 200);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook unexpected error', [
                'error' => $e->getMessage(),
            ]);

            // Return 500 to trigger Stripe retry for unexpected failures
            return response('Internal server error.', 500);
        }
    }
}
