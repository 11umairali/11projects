<?php

namespace App\Services;

class FileSecurityResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly string $message,
        public readonly ?string $sanitizedFilename = null,
    ) {}
}
