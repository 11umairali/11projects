<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Paddle payment gateway driver.
 * @see https://developer.paddle.com/api-reference/
 */
class PaddleDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox-api.paddle.com';
    private const PROD_BASE    = 'https://api.paddle.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',        'label' => 'API Key',            'type' => 'password', 'required' => true],
            ['key' => 'product_id',     'label' => 'Product ID',         'type' => 'text',     'required' => true],
            ['key' => 'webhook_secret', 'label' => 'Webhook Secret Key', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');
        $productId = $this->credential('product_id');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;

        $payload = [
            'items' => [['price' => ['description' => $this->paymentDescription($payment), 'product_id' => $productId, 'billing_cycle' => null, 'unit_price' => ['amount' => (string) $this->amountInSmallestUnit($payment), 'currency_code' => strtoupper($payment->currency ?? 'USD')]], 'quantity' => 1]],
            'custom_data' => ['payment_id' => $payment->id],
        ];

        $response = $this->httpRequest('POST', $baseUrl . '/transactions', $payload, ['Authorization: Bearer ' . $apiKey]);
        $json = $response['json'] ?? [];
        if (isset($json['data']['checkout']['url'])) { return ['redirect_url' => $json['data']['checkout']['url']]; }
        throw new \RuntimeException("Paddle: " . ($json['error']['detail'] ?? 'Failed to create transaction.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $transactionId = $payload['transaction_id'] ?? $payload['_ptxn'] ?? null;
        $status = $payload['status'] ?? '';
        if (! $transactionId) { return PaymentResult::pending('Awaiting Paddle webhook confirmation.'); }
        if ($status === 'completed' || $status === 'paid') { return PaymentResult::success(transactionId: $transactionId, message: 'Paddle payment completed.'); }
        return PaymentResult::pending('Paddle payment pending confirmation.', $transactionId);
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookSecret = $this->credential('webhook_secret');
        if (! $webhookSecret) { return true; }
        $signature = $headers['paddle-signature'] ?? $headers['Paddle-Signature'] ?? '';
        if (! $signature) { return false; }
        $parts = [];
        foreach (explode(';', $signature) as $item) { [$key, $value] = explode('=', $item, 2) + [1 => '']; $parts[$key] = $value; }
        $timestamp = $parts['ts'] ?? ''; $hash = $parts['h1'] ?? '';
        if (! $timestamp || ! $hash) { return false; }
        $expected = hash_hmac('sha256', $timestamp . ':' . $rawBody, $webhookSecret);
        return hash_equals($expected, $hash);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $eventType = $payload['event_type'] ?? '';
        $data = $payload['data'] ?? [];
        if ($eventType === 'transaction.completed') {
            return PaymentResult::success(transactionId: $data['id'] ?? '', message: 'Paddle webhook confirmed payment.');
        }
        return PaymentResult::failure("Unhandled Paddle webhook event: {$eventType}");
    }
}
