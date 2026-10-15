<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Represents a configured payment gateway.
 *
 * Credentials are stored as AES-256-CBC encrypted JSON so that
 * API keys and secrets are never kept in plaintext.
 */
class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'credentials',
        'is_active',
        'is_sandbox',
        'supported_currencies',
        'min_amount',
        'max_amount',
        'processing_fee',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'            => 'boolean',
            'is_sandbox'           => 'boolean',
            'supported_currencies' => 'array',
            'min_amount'           => 'decimal:2',
            'max_amount'           => 'decimal:2',
            'processing_fee'       => 'decimal:2',
            'sort_order'           => 'integer',
        ];
    }

    // ---------------------------------------------------------------
    //  Credential helpers
    // ---------------------------------------------------------------

    /**
     * Get all decrypted credentials as an associative array.
     */
    public function getDecryptedCredentials(): array
    {
        if (empty($this->credentials)) {
            return [];
        }

        try {
            $decrypted = Crypt::decryptString($this->credentials);
            return json_decode($decrypted, true) ?: [];
        } catch (\Exception $e) {
            // Might be stored unencrypted during dev/seeding
            $decoded = json_decode($this->credentials, true);
            return is_array($decoded) ? $decoded : [];
        }
    }

    /**
     * Get a single decrypted credential value by key.
     */
    public function getCredential(string $key, $default = null)
    {
        $creds = $this->getDecryptedCredentials();
        return $creds[$key] ?? $default;
    }

    /**
     * Store credentials as encrypted JSON.
     */
    public function setEncryptedCredentials(array $credentials): void
    {
        $this->credentials = Crypt::encryptString(json_encode($credentials));
        $this->save();
    }

    /**
     * Whether this gateway is configured for sandbox/test mode.
     */
    public function isSandbox(): bool
    {
        return (bool) $this->is_sandbox;
    }

    // ---------------------------------------------------------------
    //  Scopes
    // ---------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
