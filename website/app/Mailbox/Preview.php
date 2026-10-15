<?php

namespace App\Mailbox;

use Throwable;

/**
 * Makes the short grey preview shown under the subject in message lists
 * from the first few KB of a message. Best effort: when the start of the
 * message isn't readable text (e.g. it begins with an attachment), the
 * preview is empty.
 */
class Preview
{
    public const FETCH_BYTES = 4096;

    public const LENGTH = 160;

    public static function fromPartial(string $headers, string $body, int $depth = 0): string
    {
        try {
            [$type, $params, $encoding] = self::contentType($headers);

            if (str_starts_with($type, 'multipart/') && ($boundary = $params['boundary'] ?? '') !== '' && $depth < 3) {
                $candidates = [];
                foreach (self::parts($body, $boundary) as [$partHeaders, $partBody]) {
                    [$partType] = self::contentType($partHeaders);
                    if (str_starts_with($partType, 'multipart/') || in_array($partType, ['text/plain', 'text/html'], true)) {
                        if (stripos($partHeaders, 'attachment') !== false && ! str_starts_with($partType, 'multipart/')) {
                            continue;
                        }
                        $candidates[] = [$partType, $partHeaders, $partBody];
                    }
                }
                // Prefer plain text, like other mail apps.
                usort($candidates, fn ($a, $b) => ($a[0] === 'text/plain' ? 0 : 1) <=> ($b[0] === 'text/plain' ? 0 : 1));
                foreach ($candidates as [, $partHeaders, $partBody]) {
                    if (($text = self::fromPartial($partHeaders, $partBody, $depth + 1)) !== '') {
                        return $text;
                    }
                }

                return '';
            }

            if ($type !== '' && ! in_array($type, ['text/plain', 'text/html'], true)) {
                return '';
            }

            $text = self::decode($body, $encoding);
            $charset = strtolower($params['charset'] ?? 'utf-8');
            if (! in_array($charset, ['utf-8', 'utf8', 'us-ascii'], true)) {
                $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
                $text = is_string($converted) ? $converted : $text;
            }

            if ($type === 'text/html') {
                $text = preg_replace('~<(style|script|head|title)[^>]*>.*?(</\1>|$)~is', ' ', $text) ?? $text;
                $text = preg_replace('~<[^>]*>?~', ' ', $text) ?? $text;
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            return self::tidy($text);
        } catch (Throwable) {
            return '';
        }
    }

    /** @return array{0: string, 1: array<string, string>, 2: string} type, parameters, transfer encoding */
    private static function contentType(string $headers): array
    {
        $headers = preg_replace("/\r?\n[ \t]+/", ' ', $headers) ?? $headers; // unfold
        $type = '';
        $params = [];
        if (preg_match('/^content-type:\s*([^;\r\n]+)(.*)$/im', $headers, $m)) {
            $type = strtolower(trim($m[1]));
            preg_match_all('/;\s*([a-z0-9*_-]+)\s*=\s*("([^"]*)"|[^;\s]+)/i', $m[2], $pm, PREG_SET_ORDER);
            foreach ($pm as $p) {
                $params[strtolower($p[1])] = ($p[3] ?? '') !== '' ? $p[3] : trim($p[2], '"');
            }
        }
        $encoding = preg_match('/^content-transfer-encoding:\s*([^\s;]+)/im', $headers, $m) ? strtolower($m[1]) : '7bit';

        return [$type, $params, $encoding];
    }

    /** @return list<array{0: string, 1: string}> headers and body of each part */
    private static function parts(string $body, string $boundary): array
    {
        $chunks = preg_split('/^--'.preg_quote($boundary, '/').'(?:--)?[ \t]*\r?$/m', $body) ?: [];
        array_shift($chunks); // preamble
        $parts = [];
        foreach ($chunks as $chunk) {
            $chunk = ltrim($chunk, "\r\n");
            $split = preg_split("/\r?\n\r?\n/", $chunk, 2);
            if ($split !== false && count($split) === 2) {
                $parts[] = [$split[0], $split[1]];
            }
        }

        return $parts;
    }

    private static function decode(string $body, string $encoding): string
    {
        return match ($encoding) {
            'quoted-printable' => quoted_printable_decode(preg_replace('/=[0-9A-Fa-f]?$/', '', rtrim($body)) ?? $body),
            'base64' => (string) base64_decode(substr($clean = preg_replace('/[^A-Za-z0-9+\/=]/', '', $body) ?? '', 0, intdiv(strlen($clean), 4) * 4)),
            default => $body,
        };
    }

    private static function tidy(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Skip quoted replies and signature separators.
        $lines = array_filter(explode("\n", $text), fn ($l) => ! str_starts_with(ltrim($l), '>'));
        $text = implode(' ', $lines);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim(mb_scrub($text, 'UTF-8'));

        return mb_strlen($text) > self::LENGTH ? rtrim(mb_substr($text, 0, self::LENGTH - 1)).'…' : $text;
    }
}
