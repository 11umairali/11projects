<?php

namespace App\Mailbox;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends through the server's own SMTP submission port (Postfix), signed in
 * as the user, exactly like a desktop mail client would.
 */
class MailSender
{
    /**
     * @param  array{host: string, port: int, implicit_tls: bool, verify_peer: bool}  $server
     */
    public function __construct(private readonly array $server) {}

    /**
     * Returns the raw message that was sent, for saving to Sent.
     *
     * @throws MailboxException
     */
    public function send(OutgoingMessage $message, string $password): string
    {
        $email = $this->build($message);

        $transport = new EsmtpTransport($this->server['host'], $this->server['port'], $this->server['implicit_tls']);
        $transport->setUsername($message->fromEmail);
        $transport->setPassword($password);

        if (! $this->server['verify_peer']) {
            $stream = $transport->getStream();
            $stream->setStreamOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        }

        $unique = [];
        foreach ([...$message->to, ...$message->cc, ...$message->bcc] as $address) {
            $unique[strtolower(trim($address))] ??= new Address(trim($address));
        }
        $recipients = array_values($unique);

        try {
            $sent = $transport->send($email, new Envelope(new Address($message->fromEmail), $recipients));
        } catch (TransportExceptionInterface $e) {
            if (preg_match('/\b535\b|authentication/i', $e->getMessage())) {
                throw MailboxException::authFailed();
            }

            throw new MailboxException('The mail server did not accept the message: '.$e->getMessage(), 0, $e);
        } finally {
            $transport->stop();
        }

        // Bcc recipients are only in the envelope, never in the headers.
        return $sent?->toString() ?? $email->toString();
    }

    public function build(OutgoingMessage $message): Email
    {
        $email = (new Email)
            ->from(new Address($message->fromEmail, $message->fromName))
            ->subject($message->subject)
            ->text($message->body);

        foreach ($message->to as $address) {
            $email->addTo($address);
        }
        foreach ($message->cc as $address) {
            $email->addCc($address);
        }

        foreach ($message->attachments as $file) {
            $email->attachFromPath($file->getRealPath(), $file->getClientOriginalName(), $file->getClientMimeType() ?: null);
        }
        foreach ($message->forwarded as $attachment) {
            $email->attach((string) $attachment->content, $attachment->name, $attachment->mime);
        }

        $headers = $email->getHeaders();
        if ($message->inReplyTo) {
            $headers->addIdHeader('In-Reply-To', $message->inReplyTo);
            $references = trim(($message->references ?? '').' <'.$message->inReplyTo.'>');
            $ids = array_values(array_filter(array_map(fn ($r) => trim($r, '<> '), preg_split('/\s+/', $references))));
            $headers->addIdHeader('References', array_slice(array_unique($ids), -20));
        }
        $headers->addTextHeader('X-Mailer', config('app.name'));

        return $email;
    }
}
