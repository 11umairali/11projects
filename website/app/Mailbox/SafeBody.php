<?php

namespace App\Mailbox;

final class SafeBody
{
    public function __construct(
        public readonly string $html,
        public readonly bool $blockedRemoteImages,
    ) {}

    /**
     * A full document for the iframe's srcdoc. The CSP forbids scripts,
     * forms and plugins even if something slips past the sanitizer.
     */
    public function document(): string
    {
        $csp = "default-src 'none'; img-src https: http: data: 'self'; style-src 'unsafe-inline'; font-src https: data:";

        return '<!DOCTYPE html><html><head><meta charset="utf-8">'
            .'<meta http-equiv="Content-Security-Policy" content="'.$csp.'">'
            .'<base target="_blank">'
            .'<style>html,body{margin:0;padding:0;background:#fff;color:#14151c;font:14.5px/1.6 -apple-system,system-ui,"Segoe UI",sans-serif;word-wrap:break-word;overflow-wrap:anywhere}'
            .'body{padding:4px 2px}img{max-width:100%;height:auto}table{max-width:100%}a{color:#4f53e0}pre{white-space:pre-wrap}blockquote{margin:0 0 0 .8em;padding-left:.8em;border-left:2px solid #ddd;color:#555}</style>'
            .'</head><body>'.$this->html.'</body></html>';
    }
}
