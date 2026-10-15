<?php

use App\Helpers\HtmlSanitizer;

test('strips form elements completely', function () {
    $html = '<form action="/steal"><input type="text"><button>Submit</button></form>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->not->toContain('<form');
    expect($clean)->not->toContain('<input');
    expect($clean)->not->toContain('<button');
});

test('preserves email layout CSS while removing event attributes from style blocks', function () {
    $html = '<style onload="alert(1)">.email { color: navy; }</style><p>Visible</p>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->toContain('<style>');
    expect($clean)->toContain('.email { color: navy; }');
    expect($clean)->not->toContain('onload');
    expect($clean)->toContain('Visible');
});

test('strips CSS expression injection', function () {
    $html = '<div style="background: expression(alert(1))">test</div>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->not->toContain('expression');
});

test('preserves table structure', function () {
    $html = '<table><thead><tr><th>Header</th></tr></thead><tbody><tr><td>Cell</td></tr></tbody></table>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->toContain('<table');
    expect($clean)->toContain('<th>');
    expect($clean)->toContain('<td>');
});

test('handles nested dangerous elements', function () {
    $html = '<div><p>Safe <a href="javascript:void(0)" onclick="alert(1)">Link</a></p></div>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->not->toContain('javascript:');
    expect($clean)->not->toContain('onclick');
    expect($clean)->toContain('Link');
});

test('strips meta, base, link tags', function () {
    $html = '<meta http-equiv="refresh" content="0;url=evil"><base href="https://evil.com"><link rel="stylesheet" href="https://evil.com/steal.css"><p>Content</p>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->not->toContain('<meta');
    expect($clean)->not->toContain('<base');
    expect($clean)->not->toContain('<link');
    expect($clean)->toContain('Content');
});

test('handles deeply nested content without crashing', function () {
    $html = str_repeat('<div>', 100) . 'Deep' . str_repeat('</div>', 100);
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->toContain('Deep');
});

test('unwraps unknown tags but keeps children', function () {
    $html = '<custom-tag><p>Keep this</p></custom-tag>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->not->toContain('custom-tag');
    expect($clean)->toContain('<p>Keep this</p>');
});

test('blocks vbscript URI', function () {
    $html = '<a href="vbscript:MsgBox(1)">click</a>';
    $clean = HtmlSanitizer::sanitize($html);

    expect($clean)->not->toContain('vbscript');
});


test('removes executable CSS from both style blocks and inline attributes', function () {
    $clean = HtmlSanitizer::sanitize('<style>.email { width: expression(alert(1)); }</style><p style="behavior:url(payload.htc)">Visible</p>');
    expect($clean)->not->toContain('<style')->not->toContain('expression')->not->toContain('behavior')->toContain('Visible');
});
