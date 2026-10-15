<?php

namespace App\Mailbox;

/**
 * A signed-in user's mailbox.
 */
interface Mailbox
{
    /**
     * Unread and total counts per folder, keyed by Folder value.
     *
     * @return array<string, array{unread: int, total: int}>
     */
    public function counts(): array;

    public function list(Folder $folder, int $page, int $perPage, ?string $search = null): MessagePage;

    /**
     * @throws MessageNotFound
     */
    public function message(Folder $folder, int $uid, bool $markSeen = true): MessageDetail;

    /**
     * @throws MessageNotFound
     */
    public function attachment(Folder $folder, int $uid, int $index): AttachmentInfo;

    public function setSeen(Folder $folder, int $uid, bool $seen): void;

    public function setFlagged(Folder $folder, int $uid, bool $flagged): void;

    public function setAnswered(Folder $folder, int $uid): void;

    public function move(Folder $from, int $uid, Folder $to): void;

    /** Moves to Trash, or removes for good when already in Trash or Spam. */
    public function delete(Folder $folder, int $uid): void;

    /**
     * @param  list<string>  $flags  e.g. ['\Seen']
     */
    public function append(Folder $folder, string $rawMessage, array $flags = []): void;

    /**
     * Total size of all folders in bytes, or null if the server can't say.
     */
    public function usageBytes(): ?int;

    /**
     * Deletes everything in a folder for good. Returns how many messages.
     */
    public function emptyFolder(Folder $folder): int;

    /**
     * The complete original message (headers and body) as received.
     *
     * @throws MessageNotFound
     */
    public function raw(Folder $folder, int $uid): string;

    /**
     * UIDs of messages whose header $name equals $value.
     *
     * @return list<int>
     */
    public function findByHeader(Folder $folder, string $name, string $value): array;

    /**
     * Removes messages for good, without going through Trash.
     *
     * @param  list<int>  $uids
     */
    public function purge(Folder $folder, array $uids): void;
}
