<?php

namespace App\Services\Payment;

/**
 * Value object representing the outcome of a payment gateway operation.
 *
 * Immutable after construction -- drivers return one of these from
 * handleCallback(), handleWebhook(), and verify().
 */
class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly ?string $transactionId = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Convenience factory for a successful payment.
     */
    public static function success(string $transactionId, string $message = 'Payment successful', array $metadata = []): self
    {
        return new self(
            success: true,
            message: $message,
            transactionId: $transactionId,
            metadata: $metadata,
        );
    }

    /**
     * Convenience factory for a failed payment.
     */
    public static function failure(string $message, array $metadata = []): self
    {
        return new self(
            success: false,
            message: $message,
            metadata: $metadata,
        );
    }

    /**
     * Convenience factory for a pending/awaiting-verification payment.
     */
    public static function pending(string $message = 'Payment pending verification', ?string $transactionId = null, array $metadata = []): self
    {
        return new self(
            success: false,
            message: $message,
            transactionId: $transactionId,
            metadata: array_merge($metadata, ['status' => 'pending_verification']),
        );
    }

    /**
     * Check if this result represents a pending/awaiting-verification payment.
     */
    public function isPending(): bool
    {
        return ! $this->success && ($this->metadata['status'] ?? '') === 'pending_verification';
    }
}
