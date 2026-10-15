<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Cryptomus payment gateway driver (Crypto).
 *
 * @see https://doc.cryptomus.com/
 */
class CryptomusDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.cryptomus.com/v1';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id', 'label' => 'Merchant UUID', 'type' => 'text',     'required' => true],
            ['key' => 'api_key',     'label' => 'API Key',       'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantId = $this->credential('merchant_id');
        $apiKey     = $this->credential('api_key');

        $payload = [
            'amount'       => number_format((float) $payment->amount, 2, '.', ''),
            'currency'     => strtoupper($payment->currency ?? 'USD'),
            'order_id'     => (string) $payment->id,
            'url_callback' => route('payment.webhook', ['gateway' => 'cryptomus']),
            'url_return'   => $callbackUrl . '?status=success',
            'url_success'  => $callbackUrl . '?status=success',
            'is_payment_multiple' => false,
            'lifetime'     => 3600,
        ];

        $jsonPayload = json_encode($payload);
        $sign = md5(base64_encode($jsonPayload) . $apiKey);

        $response = $this->httpRequest('POST', self::API_BASE . '/payment', $payload, [
            'merchant: ' . $merchantId,
            'sign: ' . $sign,
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['result']['url'])) {
            return ['redirect_url' => $json['result']['url']];
        }

        $errorMsg = $json['message'] ?? 'Failed to create Cryptomus payment.';
        throw new \RuntimeException("Cryptomus: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting Cryptomus confirmation.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $apiKey = $this->credential('api_key');
        if (! $apiKey) {
            return true;
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            return false;
        }

        $receivedSign = $data['sign'] ?? '';
        if (! $receivedSign) {
            return false;
        }

        // Remove sign from data before computing
        unset($data['sign']);
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        $expected = md5(base64_encode($jsonData) . $apiKey);

        return hash_equals($expected, $receivedSign);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status    = $payload['status'] ?? '';
        $uuid      = $payload['uuid'] ?? '';
        $orderId   = $payload['order_id'] ?? '';

        if (in_array($status, ['paid', 'paid_over'])) {
            return PaymentResult::success(
                transactionId: $uuid ?: $orderId,
                message: 'Cryptomus crypto payment confirmed.',
                metadata: [
                    'cryptomus_uuid'     => $uuid,
                    'order_id'           => $orderId,
                    'payer_amount'       => $payload['payer_amount'] ?? null,
                    'payer_currency'     => $payload['payer_currency'] ?? null,
                ],
            );
        }

        if (in_array($status, ['process', 'check', 'confirm_check', 'system_fail'])) {
            return PaymentResult::pending("Cryptomus: {$status}", $uuid);
        }

        if (in_array($status, ['fail', 'wrong_amount', 'cancel', 'locked'])) {
            return PaymentResult::failure("Cryptomus payment {$status}.");
        }

        return PaymentResult::failure("Unhandled Cryptomus status: {$status}");
    }
}
