<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    const BUILTIN_TYPES = ['terms', 'privacy', 'refund', 'contact'];

    protected $fillable = [
        'title', 'slug', 'content', 'sections', 'meta_title',
        'meta_description', 'meta_image', 'is_published', 'type',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'sections' => 'array',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Page $page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBuiltIn($query)
    {
        return $query->whereIn('type', self::BUILTIN_TYPES);
    }
}
