<?php

use App\Helpers\HtmlSanitizer;

test('html sanitizer blocks onerror XSS', function () {
    $dirty = '<img src=x onerror="alert(document.cookie)">';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('onerror');
    expect($clean)->toContain('<img');
    expect($clean)->toContain('src');
});

test('html sanitizer blocks javascript: URI in href', function () {
    $dirty = '<a href="javascript:alert(1)">click me</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('javascript:');
    expect($clean)->toContain('click me');
});

test('html sanitizer blocks onclick attribute', function () {
    $dirty = '<div onclick="fetch(\'https://evil.com\')">hover</div>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('onclick');
    expect($clean)->toContain('hover');
});

test('html sanitizer removes script tags completely', function () {
    $dirty = '<p>Hello</p><script>alert(1)</script><p>World</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('<script');
    expect($clean)->not->toContain('alert');
    expect($clean)->toContain('Hello');
    expect($clean)->toContain('World');
});

test('html sanitizer removes iframe tags', function () {
    $dirty = '<p>Before</p><iframe src="https://evil.com"></iframe><p>After</p>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('iframe');
    expect($clean)->toContain('Before');
    expect($clean)->toContain('After');
});

test('html sanitizer preserves safe HTML', function () {
    $safe = '<p>Hello <strong>World</strong></p><ul><li>Item 1</li></ul>';
    $clean = HtmlSanitizer::sanitize($safe);

    expect($clean)->toContain('<p>');
    expect($clean)->toContain('<strong>');
    expect($clean)->toContain('<ul>');
    expect($clean)->toContain('<li>');
});

test('html sanitizer blocks data: URI in img src', function () {
    $dirty = '<img src="data:text/html,<script>alert(1)</script>">';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('data:');
});

test('html sanitizer forces noopener on links', function () {
    $dirty = '<a href="https://example.com">Link</a>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->toContain('rel="noopener noreferrer"');
});

test('html sanitizer blocks onmouseover attribute', function () {
    $dirty = '<div onmouseover="alert(1)">test</div>';
    $clean = HtmlSanitizer::sanitize($dirty);

    expect($clean)->not->toContain('onmouseover');
});

test('html sanitizer handles empty input', function () {
    expect(HtmlSanitizer::sanitize(''))->toBe('');
    expect(HtmlSanitizer::sanitize(null))->toBe('');
});

test('SSRF protection blocks private IPs in KB scraper', function () {
    $this->actingAs(\App\Models\User::factory()->create());

    // These should all be blocked
    $blockedUrls = [
        'http://127.0.0.1/secret',
        'http://localhost/admin',
        'http://169.254.169.254/latest/meta-data/',
    ];

    foreach ($blockedUrls as $url) {
        $response = $this->postJson('/api/knowledge-base/scrape', [
            'url' => $url,
        ]);

        expect($response->status())->toBeIn([401, 403, 422]);
    }
});
