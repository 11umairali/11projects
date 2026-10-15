<?php

use App\Support\UiTheme;

it('preserves custom accents and gives their buttons and links readable foregrounds', function (string $brand) {
    $v = UiTheme::variables($brand);
    expect($v['--color-brand'])->toBe(strtolower($brand));
    expect(UiTheme::contrast($brand, $v['--color-on-brand']))->toBeGreaterThanOrEqual(4.5);
    expect(UiTheme::contrast($v['--color-brand-strong'], $v['--color-on-brand-hover']))->toBeGreaterThanOrEqual(4.5);
    expect(UiTheme::contrast($v['--color-link'], '#ffffff'))->toBeGreaterThanOrEqual(4.5);
    expect(UiTheme::contrast($v['--color-link-dark'], '#181f29'))->toBeGreaterThanOrEqual(4.5);
    expect(array_keys($v))->not->toContain('--color-surface', '--color-chat-sent', '--color-control');
})->with(['#ec4899', '#ffb8dc', '#2563eb', '#ffffff', '#000000', '#808080', '#8b5cf6']);

it('accepts short hex colors and rejects values that cannot be safe CSS colors', function () {
    expect(UiTheme::variables('#f0a')['--color-brand'])->toBe('#ff00aa');
    foreach (['red', '#123456; background: pink', '</style><script>', '#12345678', ''] as $value) {
        expect(UiTheme::variables($value)['--color-brand'])->toBe('#2563eb');
    }
});
