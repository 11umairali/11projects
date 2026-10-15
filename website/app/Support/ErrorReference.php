<?php

namespace App\Support;

use Illuminate\Support\Str;

final class ErrorReference
{
    public static function current(): string
    {
        $request = request();
        if (!$request->attributes->has('error_reference')) {
            $request->attributes->set('error_reference', (string) Str::uuid());
        }
        return $request->attributes->get('error_reference');
    }
}
