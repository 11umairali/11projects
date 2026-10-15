<?php

namespace App\Support;

/** The mail domain in use, plus any the service moved away from. */
class MailDomains
{
    public static function current(): string
    {
        return strtolower((string) config('dahify.domain'));
    }

    /** @return list<string> */
    public static function old(): array
    {
        return array_values(array_diff(array_map('strtolower', (array) config('dahify.old_domains', [])), [self::current()]));
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [self::current(), ...self::old()];
    }

    /**
     * "sara", "sara@idahi.com" (an old domain) or "sara@dahimail.com" →
     * the username, or null for an address on some other domain.
     */
    public static function username(string $login): ?string
    {
        $login = strtolower(trim($login));
        if (! str_contains($login, '@')) {
            return $login;
        }
        [$user, $domain] = explode('@', $login, 2);

        return in_array($domain, self::all(), true) ? $user : null;
    }
}
