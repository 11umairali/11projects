<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Names admins keep for official or brand use, e.g. "facebook". A
 * "contains" entry also blocks look-alikes such as "facebook-support".
 */
class ReservedUsername extends Model
{
    public const EXACT = 'exact';

    public const CONTAINS = 'contains';

    /** A "contains" rule needs at least this many letters, or it blocks too much. */
    public const MIN_CONTAINS_LENGTH = 4;

    protected $fillable = ['name', 'match', 'note', 'created_by'];

    /**
     * Well-known brands and services people like to impersonate.
     *
     * @var list<string>
     */
    public const BRANDS = [
        'facebook', 'instagram', 'whatsapp', 'messenger', 'meta', 'google', 'gmail', 'youtube', 'android',
        'apple', 'icloud', 'iphone', 'microsoft', 'outlook', 'hotmail', 'office365', 'windows', 'xbox',
        'amazon', 'paypal', 'netflix', 'spotify', 'tiktok', 'twitter', 'linkedin', 'snapchat', 'telegram',
        'discord', 'yahoo', 'ebay', 'alibaba', 'aliexpress', 'binance', 'coinbase', 'visa', 'mastercard',
        'samsung', 'huawei', 'uber', 'careem', 'daraz', 'jazzcash', 'easypaisa', 'openai', 'chatgpt',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** Lower-case letters and digits only, so "Face.Book" matches "facebook". */
    public static function normalize(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
    }

    /**
     * The rule that blocks this username, if any.
     */
    public static function blocking(string $username): ?array
    {
        $username = strtolower(trim($username));
        $flat = self::normalize($username);

        foreach (self::rules() as $rule) {
            $hit = $rule['match'] === self::CONTAINS
                ? $rule['flat'] !== '' && str_contains($flat, $rule['flat'])
                : $rule['name'] === $username || $rule['flat'] === $flat;

            if ($hit) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, flat: string, match: string}>
     */
    public static function rules(): array
    {
        return Cache::rememberForever('reserved-usernames', fn () => self::query()
            ->get(['name', 'match'])
            ->map(fn ($r) => ['name' => $r->name, 'flat' => self::normalize($r->name), 'match' => $r->match])
            ->all());
    }

    public static function forgetCache(): void
    {
        Cache::forget('reserved-usernames');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }
}
