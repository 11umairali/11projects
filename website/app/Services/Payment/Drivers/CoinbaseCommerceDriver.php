<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Coinbase Commerce payment gateway driver (Crypto).
 *
 * Creates a charge via the Coinbase Commerce API and redirects
 * the customer to the Coinbase-hosted payment page for crypto payments.
 *
 * @see https://docs.cloud.coinbase.com/commerce/reference/createcharge
 */
class CoinbaseCommerceDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.commerce.coinbase.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',        'label' => 'API Key',              'type' => 'password', 'required' => true],
            ['key' => 'webhook_secret', 'label' => 'Webhook Shared Secret', 'type' => 'password', 'required' => false,
             'hint' => 'Found in Coinbase Commerce Dashboard under Settings > Webhook subscriptions.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');

        $payload = [
            'name'         => $this->paymentDescription($payment),
            'description'  => config('app.name') . " subscription payment #{$payment->id}",
            'pricing_type' => 'fixed_price',
            'local_price'  => [
                'amount'   => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => strtoupper($payment->currency ?? 'USD'),
            ],
            'metadata' => [
                'payment_id'   => $payment->id,
                'workspace_id' => $payment->workspace_id,
            ],
            'redirect_url' => $callbackUrl . '?status=success',
            'cancel_url'   => $callbackUrl . '?status=cancelled',
        ];

        $response = $this->httpRequest('POST', self::API_BASE . '/charges', $payload, [
            'X-CC-Api-Key: ' . $apiKey,
            'X-CC-Version: 2018-03-22',
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['data']['hosted_url'])) {
            return ['redirect_url' => $json['data']['hosted_url']];
        }

        $errorMsg = $json['error']['message'] ?? 'Failed to create Coinbase Commerce charge.';
        throw new \RuntimeException("Coinbase Commerce: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';

        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }

        // Coinbase Commerce redirect does not include payment confirmation.
        // The webhook handles the actual confirmation.
        return PaymentResult::pending('Awaiting Coinbase Commerce payment confirmation via webhook.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookSecret = $this->credential('webhook_secret');
        if (! $webhookSecret) {
            \Illuminate\Support\Facades\Log::warning('Coinbase Commerce webhook secret not configured -- skipping signature verification.');
            return true;
        }

        $signature = $headers['x-cc-webhook-signature'] ?? $headers['X-CC-Webhook-Signature'] ?? '';
        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event'] ?? [];
        $type  = $event['type'] ?? '';
        $data  = $event['data'] ?? [];

        if ($type === 'charge:confirmed' || $type === 'charge:completed') {
            $chargeId = $data['id'] ?? $data['code'] ?? '';

            return PaymentResult::success(
                transactionId: $chargeId,
                message: 'Coinbase Commerce crypto payment confirmed.',
                metadata: [
                    'coinbase_event' => $type,
                    'coinbase_code'  => $data['code'] ?? null,
                    'payments'       => $data['payments'] ?? [],
                ],
            );
        }

        if ($type === 'charge:pending') {
            $chargeId = $data['id'] ?? $data['code'] ?? '';
            return PaymentResult::pending('Crypto payment detected, awaiting blockchain confirmation.', $chargeId);
        }

        if ($type === 'charge:failed') {
            return PaymentResult::failure('Coinbase Commerce charge failed or expired.');
        }

        return PaymentResult::failure("Unhandled Coinbase Commerce event: {$type}");
    }
}
