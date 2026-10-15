<?php

namespace App\Mailbox;

/**
 * Builds the quoted text for replies and forwards.
 */
class QuotedText
{
    public const FORWARD_MARKER = '---------- Forwarded message ----------';

    public static function plain(MessageDetail $message): string
    {
        if (trim($message->text) !== '') {
            return str_replace(["\r\n", "\r"], "\n", $message->text);
        }

        $html = (string) $message->html;
        $html = preg_replace('~<(script|style|head)[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~<br\s*/?>|</p>|</div>|</tr>|</h[1-6]>|</li>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? $text) ?? $text);
    }

    public static function reply(MessageDetail $message): string
    {
        $who = self::person($message->summary->fromName, $message->summary->fromEmail);
        $when = $message->summary->date?->format('D, j M Y \a\t H:i') ?? 'an earlier date';
        $quoted = implode("\n", array_map(fn ($line) => '> '.$line, explode("\n", self::plain($message))));

        return "On {$when}, {$who} wrote:\n{$quoted}";
    }

    public static function forward(MessageDetail $message): string
    {
        $to = implode(', ', array_map(fn ($a) => self::person($a['name'], $a['email']), $message->to));

        return self::FORWARD_MARKER."\n"
            .'From: '.self::person($message->summary->fromName, $message->summary->fromEmail)."\n"
            .'Date: '.($message->summary->date?->format('D, j M Y H:i') ?? '')."\n"
            .'Subject: '.$message->summary->subject."\n"
            .'To: '.$to."\n\n"
            .self::plain($message);
    }

    /**
     * Splits a saved draft back into what the user wrote, their signature
     * and the quoted message.
     *
     * @return array{body: string, include_signature: bool, quoted: string}
     */
    public static function split(string $text, string $signature = ''): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $quoted = '';

        $at = strpos($text, self::FORWARD_MARKER);
        if ($at === false && preg_match('/^On .{3,200} wrote:\n>/m', $text, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1];
        }
        if ($at !== false) {
            $quoted = rtrim(substr($text, $at));
            $text = substr($text, 0, $at);
        }

        $text = rtrim($text);
        $withSignature = false;
        $signature = trim(str_replace(["\r\n", "\r"], "\n", $signature));
        if ($signature !== '' && str_ends_with($text, "-- \n".$signature)) {
            $text = rtrim(substr($text, 0, -strlen("-- \n".$signature)));
            $withSignature = true;
        }

        return ['body' => $text, 'include_signature' => $withSignature, 'quoted' => $quoted];
    }

    public static function person(string $name, string $email): string
    {
        return $name !== '' ? "{$name} <{$email}>" : $email;
    }
}
