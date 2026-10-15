<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * 12 words from the BIP-39 English list (2048 words, 11 bits each),
 * giving 132 bits of randomness. Only a hash of the phrase is stored.
 */
class RecoveryPhrase
{
    /** @var list<string>|null */
    private static ?array $words = null;

    /**
     * @return list<string>
     */
    public function generate(int $count = 12): array
    {
        $list = self::wordlist();

        return array_map(fn () => $list[random_int(0, count($list) - 1)], range(1, $count));
    }

    public function hash(array|string $phrase): string
    {
        return Hash::make($this->normalize($phrase));
    }

    public function check(string $input, ?string $hash): bool
    {
        return $hash !== null && Hash::check($this->normalize($input), $hash);
    }

    /**
     * Case, extra spaces, commas and line breaks don't matter when typing
     * the phrase back in.
     */
    public function normalize(array|string $phrase): string
    {
        if (is_array($phrase)) {
            $phrase = implode(' ', $phrase);
        }

        $words = preg_split('/[\s,]+/', strtolower(trim($phrase)), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', $words);
    }

    /**
     * @return list<string>
     */
    private static function wordlist(): array
    {
        if (self::$words === null) {
            $words = file(resource_path('data/wordlist.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($words === false || count($words) !== 2048) {
                throw new RuntimeException('The recovery wordlist is missing or incomplete.');
            }

            self::$words = $words;
        }

        return self::$words;
    }
}
