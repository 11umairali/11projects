<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Triple-A payment gateway driver (Crypto).
 *
 * @see https://developers.triple-a.io/docs/triplea-api-doc/
 */
class TripleADriver extends AbstractGatewayDriver
{
    private const API_BASE     = 'https://api.triple-a.io/api/v2';
    private const SANDBOX_BASE = 'https://api.sandbox.triple-a.io/api/v2';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
            ['key' => 'merchant_key',  'label' => 'Merchant Key',  'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $accessToken = $this->getAccessToken();
        $merchantKey = $this->credential('merchant_key');
        $baseUrl     = $this->isSandbox() ? self::SANDBOX_BASE : self::API_BASE;

        $payload = [
            'type'           => 'widget',
            'merchant_key'   => $merchantKey,
            'order_currency' => strtoupper($payment->currency ?? 'USD'),
            'order_amount'   => number_format((float) $payment->amount, 2, '.', ''),
            'payer_id'       => (string) ($payment->workspace_id ?? 'guest'),
            'order_id'       => (string) $payment->id,
            'success_url'    => $callbackUrl . '?status=success',
            'cancel_url'     => $callbackUrl . '?status=cancelled',
            'notify_url'     => route('payment.webhook', ['gateway' => 'triple_a']),
        ];

        $response = $this->httpRequest('POST', $baseUrl . '/payment', $payload, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['hosted_url'])) {
            return ['redirect_url' => $json['hosted_url']];
        }

        if (isset($json['payment_url'])) {
            return ['redirect_url' => $json['payment_url']];
        }

        $errorMsg = $json['message'] ?? $json['error'] ?? 'Failed to create Triple-A payment.';
        throw new \RuntimeException("Triple-A: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting Triple-A payment confirmation.');
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        // Triple-A uses payment_status (v2: good/hold/none/short/invalid)
        // and order_status (v1: paid/paid_too_much/failed_paid_too_little/paid_expired)
        $status     = $payload['payment_status'] ?? $payload['status'] ?? '';
        $orderStatus = $payload['order_status'] ?? '';
        $txStatus   = $payload['tx_status'] ?? '';
        $paymentRef = $payload['payment_reference'] ?? '';
        $orderId    = $payload['order_id'] ?? '';

        // v2 API statuses
        if ($status === 'good' || in_array($orderStatus, ['paid', 'paid_too_much'])) {
            return PaymentResult::success(
                transactionId: $paymentRef ?: $orderId,
                message: 'Triple-A crypto payment confirmed.',
                metadata: [
                    'triplea_payment_ref' => $paymentRef,
                    'order_id'            => $orderId,
                    'crypto_amount'       => $payload['crypto_amount'] ?? null,
                    'crypto_currency'     => $payload['crypto_currency'] ?? null,
                    'payment_status'      => $status,
                    'order_status'        => $orderStatus,
                ],
            );
        }

        if (in_array($status, ['none', 'hold']) || $txStatus === 'unconfirmed') {
            return PaymentResult::pending("Triple-A: {$status}", $paymentRef);
        }

        if (in_array($status, ['short', 'invalid']) || in_array($orderStatus, ['failed_paid_too_little', 'paid_expired'])) {
            $reason = $status ?: $orderStatus;
            return PaymentResult::failure("Triple-A payment {$reason}.");
        }

        return PaymentResult::pending("Triple-A status: {$status}", $paymentRef);
    }

    private function getAccessToken(): string
    {
        $clientId     = $this->credential('client_id');
        $clientSecret = $this->credential('client_secret');
        $baseUrl      = $this->isSandbox() ? self::SANDBOX_BASE : self::API_BASE;

        $response = $this->httpFormPost($baseUrl . '/oauth/token', [
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['access_token'])) {
            return $json['access_token'];
        }

        throw new \RuntimeException('Triple-A: Unable to obtain access token.');
    }
}
