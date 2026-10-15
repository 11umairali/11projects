<?php
namespace App\Support;

use App\Models\User;

final class Username
{
    public static function plain(?string $value): string
    {
        return ltrim(trim($value ?? ''), '@');
    }

    // Portable across MySQL and SQLite; preserve the stored identity.
    public const SQL = "LOWER(CASE WHEN SUBSTR(username, 1, 1) = '@' THEN SUBSTR(username, 2) ELSE username END)";

    public static function find(string $value): User
    {
        $plain = mb_strtolower(self::plain($value));
        abort_if($plain === '' || str_contains($plain, '@'), 404);
        $matches = User::whereRaw(self::SQL.' = ?', [$plain])->limit(2)->get();
        // Never guess between two legacy accounts with the same normalized name.
        abort_unless($matches->count() === 1, 404);
        return $matches->first();
    }
}
