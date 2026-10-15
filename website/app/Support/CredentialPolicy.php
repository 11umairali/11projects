<?php

namespace App\Support;

/** One policy for web/mobile signup, password changes and recovery. */
class CredentialPolicy
{
    public static function publicRules(): array
    {
        $s = SecuritySettings::get();
        $out = [];
        foreach ($s as $key => $value) {
            if (!str_starts_with($key, 'password_') && !str_starts_with($key, 'username_')) continue;
            $out[$key] = str_ends_with($key, '_length') ? (int) $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        $out['password_help'] = self::passwordHelp($out);
        $out['username_help'] = self::usernameHelp($out);
        return $out;
    }

    private static function passwordHelp(array $p): string
    {
        $parts = [];
        foreach (['uppercase' => 'an uppercase letter', 'lowercase' => 'a lowercase letter', 'numbers' => 'a number', 'symbols' => 'a symbol'] as $key => $label) {
            if ($p['password_require_'.$key]) $parts[] = $label;
        }
        return "Use {$p['password_min_length']}–{$p['password_max_length']} characters" . ($parts ? ', including '.implode(', ', $parts) : '') . '. Maximum 72 bytes; accented characters may use more than one byte.';
    }

    private static function usernameHelp(array $p): string
    {
        $characters = ['letters'];
        foreach (['numbers' => 'numbers', 'dots' => 'dots', 'dashes' => 'dashes', 'underscores' => 'underscores'] as $key => $label) {
            if ($p['username_allow_'.$key]) $characters[] = $label;
        }
        return "Use {$p['username_min_length']}–{$p['username_max_length']} characters: ".implode(', ', $characters).'. Separators must be single and between letters or numbers. '.($p['username_allow_uppercase'] ? 'Capital letters are saved as lowercase.' : 'Use lowercase letters only.');
    }

    public static function passwordRules(bool $required = true): array
    {
        $p = self::publicRules();
        return [$required ? 'required' : 'nullable', 'string', 'confirmed', 'min:'.$p['password_min_length'], 'max:'.$p['password_max_length'],
            function ($attribute, $value, $fail) use ($p) {
                if (!is_string($value)) return;
                if (strlen($value) > 72 || str_contains($value, "\0")) { $fail('The password must fit within 72 bytes and cannot contain a null character.'); return; }
                foreach (['uppercase' => '/\p{Lu}/u', 'lowercase' => '/\p{Ll}/u', 'numbers' => '/[0-9]/', 'symbols' => '/[^\p{L}\p{N}\s]/u'] as $key => $pattern) {
                    if ($p['password_require_'.$key] && !preg_match($pattern, $value)) { $fail($p['password_help']); return; }
                }
            },
        ];
    }

    public static function usernameProblem(string $username): ?string
    {
        $p = self::publicRules();
        $username = trim($username);
        if (!$p['username_allow_uppercase'] && preg_match('/[A-Z]/', $username)) return $p['username_help'];
        $username = strtolower($username);
        $letters = 'a-z'.($p['username_allow_numbers'] ? '0-9' : '');
        $separators = ($p['username_allow_dots'] ? '.' : '').($p['username_allow_dashes'] ? '\-' : '').($p['username_allow_underscores'] ? '_' : '');
        $pattern = '/^['.$letters.'](?:['.$letters.']'.($separators ? '|['.$separators.'](?=['.$letters.'])' : '').')*$/D';
        return strlen($username) < $p['username_min_length'] || strlen($username) > $p['username_max_length'] || !preg_match($pattern, $username) ? $p['username_help'] : null;
    }
}
