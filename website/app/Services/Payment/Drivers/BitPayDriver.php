<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * BitPay payment gateway driver (Crypto).
 *
 * Uses the BitPay Invoices API to create a crypto payment invoice.
 *
 * @see https://bitpay.com/api/
 */
class BitPayDriver extends AbstractGatewayDriver
{
    private const API_BASE     = 'https://bitpay.com';
    private const SANDBOX_BASE = 'https://test.bitpay.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true,
             'hint' => 'Generate a pairing token from your BitPay dashboard.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiToken = $this->credential('api_token');
        $baseUrl  = $this->isSandbox() ? self::SANDBOX_BASE : self::API_BASE;

        $payload = [
            'token'          => $apiToken,
            'price'          => (float) $payment->amount,
            'currency'       => strtoupper($payment->currency ?? 'USD'),
            'orderId'        => (string) $payment->id,
            'redirectURL'    => $callbackUrl . '?status=success',
            'closeURL'       => $callbackUrl . '?status=cancelled',
            'notificationURL' => route('payment.webhook', ['gateway' => 'bitpay']),
            'buyer'          => [
                'email' => $payment->workspace->owner()?->email ?? 'customer@example.com',
                'name'  => $payment->workspace->owner()?->name ?? 'Customer',
            ],
            'itemDesc'       => $this->paymentDescription($payment),
            'transactionSpeed' => 'medium',
            'fullNotifications' => true,
        ];

        $response = $this->httpRequest('POST', $baseUrl . '/invoices', $payload);
        $json     = $response['json'] ?? [];

        if (isset($json['data']['url'])) {
            return ['redirect_url' => $json['data']['url']];
        }

        $errorMsg = $json['error'] ?? $json['errors'][0]['error'] ?? 'Failed to create BitPay invoice.';
        throw new \RuntimeException("BitPay: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting BitPay invoice confirmation via webhook.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $apiToken = $this->credential('api_token');
        if (! $apiToken) {
            return true;
        }

        $signature = $headers['x-signature'] ?? $headers['X-Signature'] ?? '';
        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $apiToken);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event']['name'] ?? $payload['status'] ?? '';
        $data  = $payload['data'] ?? $payload;

        $invoiceId = $data['id'] ?? '';
        $status    = $data['status'] ?? $event;

        if (in_array($status, ['confirmed', 'complete'])) {
            return PaymentResult::success(
                transactionId: $invoiceId,
                message: 'BitPay crypto payment confirmed.',
                metadata: [
                    'bitpay_invoice_id' => $invoiceId,
                    'bitpay_status'     => $status,
                    'price'             => $data['price'] ?? null,
                    'currency'          => $data['currency'] ?? null,
                ],
            );
        }

        if ($status === 'paid') {
            return PaymentResult::pending('BitPay invoice paid, awaiting confirmation.', $invoiceId);
        }

        if (in_array($status, ['expired', 'invalid'])) {
            return PaymentResult::failure("BitPay invoice {$status}.");
        }

        return PaymentResult::pending("BitPay invoice status: {$status}", $invoiceId);
    }
}
