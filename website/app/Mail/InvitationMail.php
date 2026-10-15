<?php

namespace App\Mail;

use App\Models\Invite;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invite $invite,
        public Workspace $workspace,
        public string $inviterName,
    ) {}

    public function envelope(): Envelope
    {
        $siteName = DB::table('system_settings')
            ->where('key', 'site_name')
            ->value('value') ?? config('app.name');

        return new Envelope(
            subject: "You've been invited to join \"{$this->workspace->name}\" on {$siteName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'acceptUrl' => url("/invite/{$this->invite->token}"),
                'workspaceName' => $this->workspace->name,
                'inviterName' => $this->inviterName,
                'role' => ucfirst($this->invite->role ?? 'agent'),
                'expiresAt' => $this->invite->expires_at?->format('F j, Y'),
            ],
        );
    }
}
