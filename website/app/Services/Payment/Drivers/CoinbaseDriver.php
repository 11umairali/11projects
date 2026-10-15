<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Coinbase Commerce payment gateway driver.
 *
 * @see https://docs.cloud.coinbase.com/commerce/reference/createcharge
 */
class CoinbaseDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.commerce.coinbase.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',        'label' => 'API Key',        'type' => 'password', 'required' => true],
            ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');
        $payload = [
            'name' => $this->paymentDescription($payment),
            'description' => "Payment #{$payment->id}",
            'pricing_type' => 'fixed_price',
            'local_price' => ['amount' => number_format((float) $payment->amount, 2, '.', ''), 'currency' => strtoupper($payment->currency ?? 'USD')],
            'metadata' => ['payment_id' => $payment->id],
            'redirect_url' => $callbackUrl . '?status=success',
            'cancel_url'   => $callbackUrl . '?status=cancelled',
        ];

        $response = $this->httpRequest('POST', self::API_BASE . '/charges', $payload, ['X-CC-Api-Key: ' . $apiKey, 'X-CC-Version: 2018-03-22']);
        $json = $response['json'] ?? [];

        if (isset($json['data']['hosted_url'])) { return ['redirect_url' => $json['data']['hosted_url']]; }
        $errorMsg = $json['error']['message'] ?? 'Failed to create Coinbase charge.';
        throw new \RuntimeException("Coinbase: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') { return PaymentResult::failure('Payment was cancelled.'); }
        return PaymentResult::pending('Awaiting Coinbase payment confirmation via webhook.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookSecret = $this->credential('webhook_secret');
        if (! $webhookSecret) { return true; }
        $signature = $headers['x-cc-webhook-signature'] ?? $headers['X-CC-Webhook-Signature'] ?? '';
        if (! $signature) { return false; }
        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);
        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event'] ?? [];
        $type  = $event['type'] ?? '';
        $data  = $event['data'] ?? [];

        if ($type === 'charge:confirmed' || $type === 'charge:completed') {
            return PaymentResult::success(transactionId: $data['id'] ?? $data['code'] ?? '', message: 'Coinbase payment confirmed.',
                metadata: ['coinbase_event' => $type, 'coinbase_code' => $data['code'] ?? null]);
        }
        if ($type === 'charge:pending') { return PaymentResult::pending('Coinbase payment detected, awaiting confirmation.', $data['id'] ?? ''); }
        if ($type === 'charge:failed') { return PaymentResult::failure('Coinbase charge failed.'); }
        return PaymentResult::failure("Unhandled Coinbase event: {$type}");
    }
}
