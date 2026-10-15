<?php
namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Locales accepted by Symfony's translator follow this format
     * (https://symfony.com/doc/current/translation.html#locale-format):
     *   - Must start with a letter
     *   - Followed by letters, digits, underscores, or hyphens
     *   - No spaces, dots, or special chars
     *
     * Anything else (e.g. faker-seeded "dolores re" values) is rejected
     * here BEFORE we hand it to app()->setLocale(), which would otherwise
     * throw InvalidArgumentException and 500 the entire request.
     */
    private const VALID_LOCALE_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]{0,15}$/';

    public function handle(Request $request, Closure $next): Response
    {
        // Skip during installation (tables don't exist yet)
        if (!file_exists(storage_path('installed')) && env('INSTALLED', '0') !== '1') {
            return $next($request);
        }

        // Priority: user preference > session > browser > default
        $locale = null;

        // 1. Authenticated user's language preference
        if ($request->user() && $request->user()->language) {
            $locale = $this->sanitize($request->user()->language);
        }

        // 2. Session
        if (!$locale && session()->has('locale')) {
            $locale = $this->sanitize(session('locale'));
        }

        // 3. Browser Accept-Language
        if (!$locale) {
            $locale = $this->sanitize(substr($request->getPreferredLanguage() ?? 'en', 0, 2));
        }

        // Verify locale exists and is active. Note that Language::findByCode
        // could itself return a row whose `code` is malformed (faker-seeded
        // test data), so we re-sanitize the resolved code below.
        $language = $locale ? Language::findByCode($locale) : null;
        if (!$language || !$language->is_active || !$this->sanitize($language->code)) {
            $language = Language::getDefault();
        }

        $finalLocale = $language ? $this->sanitize($language->code) : null;
        if (!$finalLocale) {
            $finalLocale = 'en';
        }

        // Defense in depth: if for any reason the resolved locale still
        // doesn't pass Symfony's validation, fall back silently to 'en'
        // and log it. We NEVER want this middleware to throw.
        try {
            app()->setLocale($finalLocale);
        } catch (\Throwable $e) {
            Log::warning('SetLocale: invalid locale rejected, falling back to en', [
                'attempted_locale' => $finalLocale,
                'error' => $e->getMessage(),
            ]);
            app()->setLocale('en');
        }

        // Share language direction for RTL support
        view()->share('currentLanguage', $language);
        view()->share('textDirection', $language?->direction ?? 'ltr');

        return $next($request);
    }

    /**
     * Return the input only if it matches the Symfony-acceptable locale
     * format. Anything else (spaces, mixed text, lorem-ipsum garbage, etc.)
     * comes back as null so the caller can move on to the next fallback.
     */
    private function sanitize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || !preg_match(self::VALID_LOCALE_PATTERN, $trimmed)) {
            return null;
        }
        return $trimmed;
    }
}
