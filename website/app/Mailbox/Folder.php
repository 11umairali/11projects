<?php

namespace App\Mailbox;

/**
 * The fixed set of folders the app shows. "starred" is not a real IMAP
 * folder: it lists flagged messages from the inbox.
 */
enum Folder: string
{
    case Inbox = 'inbox';
    case Starred = 'starred';
    case Sent = 'sent';
    case Drafts = 'drafts';
    case Spam = 'spam';
    case Trash = 'trash';

    public function title(): string
    {
        return match ($this) {
            self::Inbox => 'Inbox',
            self::Starred => 'Starred',
            self::Sent => 'Sent',
            self::Drafts => 'Drafts',
            self::Spam => 'Spam',
            self::Trash => 'Trash',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Inbox => 'inbox',
            self::Starred => 'star',
            self::Sent => 'sent',
            self::Drafts => 'drafts',
            self::Spam => 'spam',
            self::Trash => 'trash',
        };
    }

    public function emptyTitle(): string
    {
        return match ($this) {
            self::Inbox => 'Your inbox is empty',
            self::Starred => 'No starred messages',
            self::Sent => 'Nothing sent yet',
            self::Drafts => 'No drafts',
            self::Spam => 'No spam',
            self::Trash => 'Trash is empty',
        };
    }

    public function emptyHint(): string
    {
        return match ($this) {
            self::Inbox => 'New messages sent to your address will show up here.',
            self::Starred => 'Star important messages to find them quickly.',
            self::Sent => 'Messages you send will appear here.',
            self::Drafts => 'Unfinished messages are saved here.',
            self::Spam => 'Suspicious messages are moved here automatically.',
            self::Trash => 'Deleted messages stay here until you empty the trash.',
        };
    }

    /** The real IMAP folder this view reads from. */
    public function storage(): self
    {
        return $this === self::Starred ? self::Inbox : $this;
    }

    /** Messages shown here show the recipient instead of the sender. */
    public function showsRecipient(): bool
    {
        return in_array($this, [self::Sent, self::Drafts], true);
    }

    /** Deleting from here removes the message for good. */
    public function deletesPermanently(): bool
    {
        return in_array($this, [self::Trash, self::Spam], true);
    }
}
