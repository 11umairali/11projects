<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * OxaPay payment gateway driver (Crypto).
 *
 * @see https://docs.oxapay.com/
 */
class OxaPayDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.oxapay.com/merchants/request';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_api_key', 'label' => 'Merchant API Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('merchant_api_key');

        $payload = [
            'merchant'    => $apiKey,
            'amount'      => (float) $payment->amount,
            'currency'    => strtoupper($payment->currency ?? 'USD'),
            'lifeTime'    => 60, // minutes
            'feePaidByPayer' => 0,
            'underPaidCover' => 2.5,
            'callbackUrl' => route('payment.webhook', ['gateway' => 'oxapay']),
            'returnUrl'   => $callbackUrl . '?status=success',
            'description' => $this->paymentDescription($payment),
            'orderId'     => (string) $payment->id,
        ];

        $response = $this->httpRequest('POST', self::API_BASE, $payload);
        $json     = $response['json'] ?? [];

        if (($json['result'] ?? 0) == 100 && isset($json['payLink'])) {
            return ['redirect_url' => $json['payLink']];
        }

        $errorMsg = $json['message'] ?? 'Failed to create OxaPay payment.';
        throw new \RuntimeException("OxaPay: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting OxaPay payment confirmation.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $apiKey = $this->credential('merchant_api_key');
        if (! $apiKey) {
            return true;
        }

        // OxaPay sends HMAC in the header
        $signature = $headers['hmac'] ?? $headers['HMAC'] ?? '';
        if (! $signature) {
            return true; // Not all OxaPay callbacks include HMAC
        }

        $expected = hash_hmac('sha512', $rawBody, $apiKey);
        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status   = $payload['status'] ?? '';
        $trackId  = $payload['trackId'] ?? '';
        $orderId  = $payload['orderId'] ?? '';

        if ($status === 'Paid') {
            return PaymentResult::success(
                transactionId: $trackId ?: $orderId,
                message: 'OxaPay crypto payment confirmed.',
                metadata: [
                    'oxapay_track_id'  => $trackId,
                    'order_id'         => $orderId,
                    'amount'           => $payload['amount'] ?? null,
                    'currency'         => $payload['currency'] ?? null,
                    'payAmount'        => $payload['payAmount'] ?? null,
                    'payCurrency'      => $payload['payCurrency'] ?? null,
                    'network'          => $payload['network'] ?? null,
                ],
            );
        }

        if (in_array($status, ['Waiting', 'Confirming', 'Paying'])) {
            return PaymentResult::pending("OxaPay: {$status}", $trackId);
        }

        if (in_array($status, ['Expired', 'Failed'])) {
            return PaymentResult::failure("OxaPay payment {$status}.");
        }

        return PaymentResult::pending("OxaPay status: {$status}", $trackId);
    }
}
