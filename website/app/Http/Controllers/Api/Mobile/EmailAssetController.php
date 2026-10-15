<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Images inside e-mails, for the mobile app.
 *
 * The app's HTML renderer cannot send a login token with image requests and Android blocks plain http:// images,
 * so the message-body endpoint rewrites every image to a SIGNED https URL on this server:
 *   • inline images (cid:…)  → email-asset/inline/{id}
 *   • remote images          → email-asset/proxy?u=<base64 url>   (fetched server-side: no http problem,
 *                                                                   no hot-link blocking, sender cannot see the phone's IP)
 * A signature only allows exactly that one image, so no login is needed and nothing else is exposed.
 */
class EmailAssetController extends Controller
{
    /** Public base URL used in image links (APP_URL, or this request's host when APP_URL is missing/localhost). */
    private static function base(): string
    {
        $b = rtrim((string) config('app.url'), '/');
        if ($b === '' || str_contains($b, 'localhost') || str_contains($b, '127.0.0.1')) {
            $b = 'https://' . request()->getHost();
        }
        return $b;
    }

    /**
     * One fixed expiry for the whole calendar week (Monday 00:00 + 14 days → every link is valid for 7–14 days).
     * A NEW expiry on every request produced a NEW url for the same image each time an e-mail was opened, so the phone
     * could never reuse its cached copy. With a stable expiry the url is identical on every open.
     */
    private static function expiry()
    {
        return now()->startOfWeek()->addDays(14);
    }

    /**
     * Signed link. The signature covers only the PATH + query ("relative" signature), so it stays valid behind
     * Cloudflare / a reverse proxy where the server sees http:// or another host name internally.
     */
    private static function signed(string $route, $expires, array $params): string
    {
        $path = URL::temporarySignedRoute($route, $expires, $params, false);
        return self::base() . $path;
    }

    /**
     * Rewrites <img> sources of an e-mail body into signed https URLs.
     * IMPORTANT: call this on the RAW body BEFORE sanitising – the sanitiser drops `cid:` sources.
     */
    public static function rewrite(string $html, $message): string
    {
        $expires = self::expiry();
        $own = self::base() . '/api/v1/email-asset/';

        // cid: → attachment stored on this server
        $byCid = [];
        foreach ($message->attachments()->get() as $a) {
            if (!empty($a->content_id)) $byCid[strtolower(trim($a->content_id, '<> '))] = $a->id;
        }
        $html = preg_replace_callback('/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])cid:([^"\']+)\2/i', function ($m) use ($byCid, $expires) {
            $id = $byCid[strtolower(trim($m[3], '<> '))] ?? null;
            if (!$id) return $m[1] . $m[2] . 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' . $m[2];
            return $m[1] . $m[2] . htmlspecialchars(self::signed('mobile.email.inline', $expires, ['id' => $id]), ENT_QUOTES) . $m[2];
        }, $html) ?? $html;

        // http(s):// or //host images → signed proxy (skip links that already point at this proxy)
        $html = preg_replace_callback('/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])((?:https?:)?\/\/[^"\']+)\2/i', function ($m) use ($expires, $own) {
            $url = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5);
            if (str_starts_with($url, $own)) return $m[0];
            if (str_starts_with($url, '//')) $url = 'https:' . $url;
            $u = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
            return $m[1] . $m[2] . htmlspecialchars(self::signed('mobile.email.proxy', $expires, ['u' => $u]), ENT_QUOTES) . $m[2];
        }, $html) ?? $html;

        return $html;
    }

    /** GET email-asset/inline/{id} (signed) */
    public function inline(Request $request, int $id)
    {
        $a = Attachment::find($id);
        if (!$a || !$a->storage_path || !str_starts_with((string) $a->mime_type, 'image/')) abort(404);
        foreach ([Storage::disk('local')->path($a->storage_path), Storage::disk('public')->path($a->storage_path), storage_path('app/' . $a->storage_path)] as $p) {
            if (is_file($p)) {
                $r = response()->file($p, ['Content-Type' => $a->mime_type, 'Cache-Control' => 'private, max-age=604800, immutable']);
                $r->setAutoEtag();
                if ($r->isNotModified($request)) return $r; // 304 – nothing is sent again
                return $r;
            }
        }
        abort(404);
    }

    /** GET email-asset/proxy?u=… (signed) */
    public function proxy(Request $request)
    {
        $url = base64_decode(strtr((string) $request->query('u'), '-_', '+/'), true);
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) abort(400);
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) abort(400);

        // SSRF protection (same rules as the website's /image-proxy)
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')
            || $host === '169.254.169.254' || $host === 'metadata.google.internal') abort(403);
        $ips = gethostbynamel($host) ?: (filter_var($host, FILTER_VALIDATE_IP) ? [$host] : []);
        if (!$ips) abort(403);
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) abort(403);
        }

        $key = sha1($url);
        $disk = Storage::disk('local');
        $bin = "email-asset-cache/{$key}.bin";
        $meta = "email-asset-cache/{$key}.type";
        $headers = ['Cache-Control' => 'public, max-age=604800, immutable', 'X-Content-Type-Options' => 'nosniff', 'ETag' => '"' . $key . '"'];

        // Already downloaded before (by anyone opening this e-mail) → serve from our disk, no request to the sender at all.
        if ($disk->exists($bin) && $disk->exists($meta)) {
            if (trim((string) $request->header('If-None-Match')) === '"' . $key . '"') return response('', 304, $headers);
            return response($disk->get($bin), 200, $headers + ['Content-Type' => trim($disk->get($meta))]);
        }

        try {
            $ua = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Mobile Safari/537.36';
            $r = Http::timeout(12)->withoutRedirecting()->withHeaders(['User-Agent' => $ua, 'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8', 'Referer' => parse_url($url, PHP_URL_SCHEME) . '://' . $host . '/'])->get($url);
            // follow a single redirect manually (re-validated) – many CDNs redirect
            if ($r->redirect() && ($loc = $r->header('Location'))) {
                $loc = filter_var($loc, FILTER_VALIDATE_URL) ? $loc : null;
                $lh = $loc ? strtolower((string) parse_url($loc, PHP_URL_HOST)) : '';
                $lips = $lh ? (gethostbynamel($lh) ?: []) : [];
                foreach ($lips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) abort(403);
                if (!$lips || !in_array(parse_url($loc, PHP_URL_SCHEME), ['http', 'https'], true)) abort(404);
                $r = Http::timeout(12)->withoutRedirecting()->withHeaders(['User-Agent' => $ua])->get($loc);
            }
            if (!$r->successful()) { \Log::info("email-asset proxy: {$host} answered HTTP {$r->status()}"); abort(404); }
            $body = $r->body();
            $type = strtolower(trim(explode(';', (string) $r->header('Content-Type'))[0]));
            // Some hosts label images as application/octet-stream – trust the real file content instead.
            if (!str_starts_with($type, 'image/')) {
                $info = @getimagesizefromstring($body);
                $type = $info['mime'] ?? '';
            }
            if (!str_starts_with($type, 'image/') || str_contains($type, 'svg')) { \Log::info("email-asset proxy: {$host} is not an image ({$type})"); abort(403); } // svg can carry script
            if (strlen($body) > 8 * 1024 * 1024) abort(413);

            // keep a copy (only real images that were fetched successfully)
            try {
                $disk->put($bin, $body);
                $disk->put($meta, $type);
                if (random_int(1, 100) === 1) self::pruneCache($disk); // occasional clean-up of copies older than 30 days
            } catch (\Throwable $e) { /* caching is best-effort */ }

            return response($body, 200, $headers + ['Content-Type' => $type]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::info('email-asset proxy failed for ' . $host . ': ' . $e->getMessage());
            abort(404);
        }
    }

    /** Deletes cached image copies that have not been written for 30 days. */
    private static function pruneCache($disk): void
    {
        $limit = now()->subDays(30)->getTimestamp();
        foreach ($disk->files('email-asset-cache') as $f) {
            if ($disk->lastModified($f) < $limit) $disk->delete($f);
        }
    }
}
