<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class DynamicEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string>  $toAddresses
     * @param  array<string>  $ccAddresses
     * @param  array<string>  $bccAddresses
     * @param  string  $emailSubject
     * @param  string  $bodyHtml
     * @param  array<array{path: string, name: string, mime: string}>  $attachmentPaths
     * @param  ?string  $signatureHtml
     * @param  string  $fromName
     * @param  string  $fromEmail
     * @param  ?string  $inReplyTo
     * @param  array<string>  $referencesHeader
     */
    public function __construct(
        public readonly array $toAddresses,
        public readonly array $ccAddresses,
        public readonly array $bccAddresses,
        public readonly string $emailSubject,
        public readonly string $bodyHtml,
        public readonly array $attachmentPaths = [],
        public readonly ?string $signatureHtml = null,
        public readonly string $fromName = '',
        public readonly string $fromEmail = '',
        public readonly ?string $inReplyTo = null,
        public readonly array $referencesHeader = [],
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $to = collect($this->toAddresses)->map(fn (string $email) => new Address($email))->toArray();
        $cc = collect($this->ccAddresses)->map(fn (string $email) => new Address($email))->toArray();
        $bcc = collect($this->bccAddresses)->map(fn (string $email) => new Address($email))->toArray();

        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            to: $to,
            cc: $cc,
            bcc: $bcc,
            subject: $this->emailSubject,
            // HTML-only mail is penalised by spam filters; always ship a text part too.
            using: [fn (Email $message) => $message->text($this->plainTextBody())],
        );
    }

    /**
     * Plain-text version of the message (same content as the HTML part).
     */
    public function plainTextBody(): string
    {
        $html = $this->buildFullHtml();

        // Drop non-visible blocks
        $html = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html);

        // Keep link targets visible: "label (https://...)"
        $html = preg_replace_callback(
            '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            function (array $m) {
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $label = trim(strip_tags($m[2]));

                if (str_starts_with($url, 'mailto:')) {
                    return $label !== '' ? $label : substr($url, 7);
                }

                return ($label === '' || $label === $url) ? $url : "{$label} ({$url})";
            },
            $html
        );

        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|h[1-6]|blockquote)>#i', "\n\n", $html);
        $html = preg_replace('#</(div|tr|li)>#i', "\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Get the message headers — includes In-Reply-To and References for threading.
     */
    public function headers(): Headers
    {
        return new Headers(
            references: array_map(fn ($id) => trim($id, '<>'), $this->referencesHeader),
            text: $this->inReplyTo ? ['In-Reply-To' => $this->inReplyTo] : [],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildFullHtml(),
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->attachmentPaths as $att) {
            if (file_exists($att['path'])) {
                $attachments[] = Attachment::fromPath($att['path'])
                    ->as($att['name'])
                    ->withMime($att['mime']);
            }
        }

        return $attachments;
    }

    /**
     * Build the full HTML body including signature.
     */
    protected function buildFullHtml(): string
    {
        $html = $this->bodyHtml;

        if ($this->signatureHtml) {
            // Insert signature before </body> or append
            if (stripos($html, '</body>') !== false) {
                $html = str_ireplace(
                    '</body>',
                    '<div class="email-signature" style="margin-top:20px;padding-top:10px;border-top:1px solid #e5e7eb;">'
                    . $this->signatureHtml
                    . '</div></body>',
                    $html
                );
            } else {
                $html .= '<div class="email-signature" style="margin-top:20px;padding-top:10px;border-top:1px solid #e5e7eb;">'
                    . $this->signatureHtml
                    . '</div>';
            }
        }

        return $html;
    }
}
