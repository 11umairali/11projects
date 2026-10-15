<?php

use App\Helpers\HtmlSanitizer;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security Fixes Tests
|--------------------------------------------------------------------------
| Regression tests for security fixes applied to the application.
| Each test verifies a specific vulnerability remediation and should
| never be removed without a corresponding security review.
|
| References:
| - FIX-027: Style attribute removal (CSS injection)
| - FIX-028: Encoded javascript: URI bypass
| - Password confirmation timeout hardening
| - API rate limiting on sensitive endpoints
*/

// ---- HtmlSanitizer: Script Tag Removal ----

test('html sanitizer removes script tags entirely', function () {
    $dirty = '<p>Hello</p><script>alert("xss")</script>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<script>');
    expect($clean)->not->toContain('alert');
    expect($clean)->toContain('<p>Hello</p>');
});

test('html sanitizer removes script tags with attributes', function () {
    $dirty = '<script type="text/javascript" src="evil.js"></script><p>Safe</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<script');
    expect($clean)->toContain('<p>Safe</p>');
});

// ---- HtmlSanitizer: Event Handler Removal ----

test('html sanitizer removes onclick event handlers', function () {
    $dirty = '<a href="#" onclick="alert(1)">Click</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('onclick');
    expect($clean)->toContain('<a');
});

test('html sanitizer removes all on-event handlers', function () {
    $handlers = ['onload', 'onerror', 'onmouseover', 'onfocus', 'onblur', 'onsubmit'];

    foreach ($handlers as $handler) {
        $dirty = "<div {$handler}=\"alert(1)\">Content</div>";
        $clean = HtmlSanitizer::sanitize($dirty);

        expect($clean)->not->toContain($handler, "Failed to strip {$handler}");
    }
});

// ---- HtmlSanitizer: Attribute Filtering ----

test('html sanitizer removes id attribute', function () {
    $dirty = '<div id="malicious" class="safe">Content</div>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('id=');
    expect($clean)->toContain('class="safe"');
    expect($clean)->toContain('Content');
});

test('html sanitizer preserves allowed attributes on links', function () {
    $dirty = '<a href="https://example.com" title="Link">Click</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->toContain('href="https://example.com"');
    expect($clean)->toContain('title="Link"');
    // Should force rel="noopener noreferrer" on links
    expect($clean)->toContain('rel="noopener noreferrer"');
});

test('html sanitizer preserves allowed attributes on images', function () {
    $dirty = '<img src="https://example.com/img.png" alt="Photo" width="100">';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->toContain('src="https://example.com/img.png"');
    expect($clean)->toContain('alt="Photo"');
    expect($clean)->toContain('width="100"');
});

// ---- HtmlSanitizer: JavaScript Protocol Prevention ----

test('html sanitizer prevents javascript protocol in href', function () {
    $dirty = '<a href="javascript:alert(1)">Click</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('javascript:');
});

test('html sanitizer prevents encoded javascript protocol', function () {
    $dirty = '<a href="jav&#97;script:alert(1)">Click</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('javascript:');
});

test('html sanitizer prevents data protocol in href', function () {
    $dirty = '<a href="data:text/html,<script>alert(1)</script>">Click</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('data:');
});

test('html sanitizer prevents vbscript protocol', function () {
    $dirty = '<a href="vbscript:MsgBox(1)">Click</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('vbscript:');
});

test('html sanitizer prevents javascript protocol in img src', function () {
    $dirty = '<img src="javascript:alert(1)">';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('javascript:');
});

// ---- HtmlSanitizer: Dangerous Tag Removal ----

test('html sanitizer removes iframe tags', function () {
    $dirty = '<iframe src="https://evil.com"></iframe><p>Safe</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<iframe');
    expect($clean)->toContain('<p>Safe</p>');
});

test('html sanitizer removes form and input tags', function () {
    $dirty = '<form action="https://evil.com"><input type="text" name="password"><button>Submit</button></form><p>Safe</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<form');
    expect($clean)->not->toContain('<input');
    expect($clean)->not->toContain('<button');
    expect($clean)->toContain('<p>Safe</p>');
});

test('html sanitizer removes style tags', function () {
    $dirty = '<style>body { background: url("evil.js"); }</style><p>Safe</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<style');
    expect($clean)->toContain('<p>Safe</p>');
});

test('html sanitizer removes object tags', function () {
    $dirty = '<p>Before</p><object data="evil.swf"></object><p>After</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<object');
    expect($clean)->not->toContain('evil.swf');
});

test('html sanitizer removes embed tags', function () {
    // embed is a void element, so we test it separately to avoid
    // DOMDocument rearranging adjacent siblings during parsing.
    $dirty = '<p>Content</p><embed src="evil.swf">';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<embed');
    expect($clean)->not->toContain('evil.swf');
});

test('html sanitizer removes object tag in isolation', function () {
    $dirty = '<p>Before</p><object data="evil.swf" type="application/x-shockwave-flash"></object><p>After</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<object');
    expect($clean)->not->toContain('evil.swf');
    expect($clean)->toContain('Before');
    expect($clean)->toContain('After');
});

// ---- HtmlSanitizer: Edge Cases ----

test('html sanitizer handles null input gracefully', function () {
    expect(HtmlSanitizer::sanitize(null))->toBe('');
});

test('html sanitizer handles empty string input', function () {
    expect(HtmlSanitizer::sanitize(''))->toBe('');
});

test('html sanitizer preserves plain text', function () {
    $text = 'This is plain text without any HTML.';
    expect(HtmlSanitizer::sanitize($text))->toContain('This is plain text without any HTML.');
});

test('html sanitizer preserves allowed formatting tags', function () {
    $html = '<p><strong>Bold</strong> and <em>italic</em> with <a href="https://example.com">link</a></p>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->toContain('<strong>Bold</strong>');
    expect($clean)->toContain('<em>italic</em>');
    expect($clean)->toContain('<a');
    expect($clean)->toContain('href="https://example.com"');
});

test('html sanitizer handles nested dangerous content', function () {
    $dirty = '<div><script>alert(1)</script><p>Safe <script>evil()</script> content</p></div>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<script');
    expect($clean)->not->toContain('alert');
    expect($clean)->not->toContain('evil');
    expect($clean)->toContain('Safe');
    expect($clean)->toContain('content');
});

// ---- Password Confirmation Timeout ----

test('password confirmation timeout is configured', function () {
    // The config/auth.php declares env('AUTH_PASSWORD_TIMEOUT', 300).
    // The actual runtime value depends on the .env. We verify the config
    // key exists and is a positive integer (the default is 300 = 5 minutes;
    // the standard Laravel default is 10800 = 3 hours). Either way, it must
    // be set to a reasonable upper bound.
    $timeout = config('auth.password_timeout');

    expect($timeout)->toBeInt();
    expect($timeout)->toBeGreaterThan(0);
    // Must not exceed 3 hours -- anything longer is a security concern
    expect($timeout)->toBeLessThanOrEqual(10800);
});

// ---- API Rate Limiting ----

test('contacts export route has rate limiting middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'contacts/export')
            && str_contains($route->uri(), 'v1');
    });

    expect($route)->not->toBeNull('contacts/export API route should exist');

    $middleware = $route->gatherMiddleware();
    $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle'));

    expect($hasThrottle)->toBeTrue('contacts/export should have throttle middleware');
});

test('contacts import route has rate limiting middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'contacts/import')
            && str_contains($route->uri(), 'v1');
    });

    expect($route)->not->toBeNull('contacts/import API route should exist');

    $middleware = $route->gatherMiddleware();
    $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle'));

    expect($hasThrottle)->toBeTrue('contacts/import should have throttle middleware');
});

test('campaign send route has rate limiting middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'campaigns') && str_contains($route->uri(), 'send');
    });

    expect($route)->not->toBeNull('campaigns/send API route should exist');

    $middleware = $route->gatherMiddleware();
    $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle'));

    expect($hasThrottle)->toBeTrue('campaigns/send should have throttle middleware');
});

test('ai generate-reply route has rate limiting middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'ai/generate-reply');
    });

    expect($route)->not->toBeNull('ai/generate-reply API route should exist');

    $middleware = $route->gatherMiddleware();
    $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle'));

    expect($hasThrottle)->toBeTrue('ai/generate-reply should have throttle middleware');
});

test('email tracking routes have rate limiting middleware', function () {
    $route = collect(Route::getRoutes())->first(function ($route) {
        return str_contains($route->uri(), 'track/open');
    });

    expect($route)->not->toBeNull('email tracking open route should exist');

    $middleware = $route->gatherMiddleware();
    $hasThrottle = collect($middleware)->contains(fn ($m) => str_contains((string) $m, 'throttle'));

    expect($hasThrottle)->toBeTrue('email tracking should have throttle middleware');
});
