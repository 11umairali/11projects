<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    protected $fillable = ['name', 'code', 'native_name', 'direction', 'flag', 'is_active', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }

    public static function getDefault(): ?self
    {
        return Cache::remember('default_language', 3600, fn() => static::where('is_default', true)->first());
    }

    public static function getActive(): \Illuminate\Support\Collection
    {
        return Cache::remember('active_languages', 3600, fn() => static::where('is_active', true)->orderBy('sort_order')->get());
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    protected static function booted(): void
    {
        static::saved(fn() => Cache::forget('active_languages'));
        static::saved(fn() => Cache::forget('default_language'));
        static::deleted(fn() => Cache::forget('active_languages'));
    }
}
