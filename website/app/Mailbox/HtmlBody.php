<?php

namespace App\Mailbox;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Turns an untrusted email body into HTML that is safe to show. The result
 * is also rendered inside a sandboxed iframe with scripts disabled, so a
 * sanitizer miss still can't run code or reach the app's pages.
 */
class HtmlBody
{
    /**
     * @param  array<string, string>  $inlineImages  content-id => URL
     */
    public function fromHtml(string $html, array $inlineImages, bool $allowRemoteImages): SafeBody
    {
        // Inline images (cid:) are served by the app itself.
        $html = preg_replace_callback('/(["\'])cid:([^"\']+)\1/i', function (array $m) use ($inlineImages) {
            $url = $inlineImages[trim($m[2], '<> ')] ?? null;

            return $url ? $m[1].$url.$m[1] : $m[1].$m[1];
        }, $html) ?? $html;

        $hasRemoteImages = (bool) preg_match('/<img[^>]+src\s*=\s*["\']?\s*(https?:)?\/\//i', $html)
            || (bool) preg_match('/url\(\s*["\']?\s*(https?:)?\/\//i', $html);

        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('font', ['color', 'face', 'size'])
            ->allowElement('center')
            ->allowAttribute('style', '*')
            ->allowAttribute('align', '*')
            ->allowAttribute('bgcolor', '*')
            ->allowAttribute('valign', '*')
            ->allowAttribute('width', '*')
            ->allowAttribute('height', '*')
            ->allowAttribute('cellpadding', 'table')
            ->allowAttribute('cellspacing', 'table')
            ->allowAttribute('border', ['table', 'img'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowMediaSchemes(['https', 'http', 'data'])
            ->allowRelativeMedias()
            ->forceAttribute('a', 'target', '_blank')
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->withMaxInputLength(2_000_000);

        // Inline images use relative URLs to this app, so blocking every
        // remote host still keeps them.
        if (! $allowRemoteImages) {
            $config = $config->allowMediaHosts([]);
        }

        $clean = (new HtmlSanitizer($config))->sanitize($html);

        // Inline style values can still load remote content (background:url()).
        if (! $allowRemoteImages) {
            $clean = preg_replace('/url\(\s*[\'"]?\s*(?:https?:)?\/\/[^)]*\)/i', 'none', $clean) ?? $clean;
        }
        $clean = preg_replace('/expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', '', $clean) ?? $clean;

        return new SafeBody($clean, $hasRemoteImages && ! $allowRemoteImages);
    }

    public function fromText(string $text): SafeBody
    {
        $escaped = e($text);
        $linked = preg_replace(
            '~\b(https?://[^\s<>"\']+[^\s<>"\'.,;:!?)\]])~i',
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow">$1</a>',
            $escaped
        ) ?? $escaped;

        return new SafeBody('<div style="white-space:pre-wrap;word-wrap:break-word">'.$linked.'</div>', false);
    }
}
