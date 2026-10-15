<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * CinetPay payment gateway driver (West Africa).
 * @see https://docs.cinetpay.com/
 */
class CinetPayDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api-checkout.cinetpay.com/v2';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',    'label' => 'API Key',    'type' => 'text',     'required' => true],
            ['key' => 'site_id',    'label' => 'Site ID',    'type' => 'text',     'required' => true],
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');
        $siteId = $this->credential('site_id');
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';
        $transactionId = 'CINET_' . $payment->id . '_' . time();

        $payload = [
            'apikey' => $apiKey, 'site_id' => $siteId, 'transaction_id' => $transactionId,
            'amount' => (int) round($payment->amount), 'currency' => strtoupper($payment->currency ?? 'XOF'),
            'description' => $this->paymentDescription($payment), 'return_url' => $callbackUrl,
            'notify_url' => route('payment.webhook', ['gateway' => 'cinetpay']), 'channels' => 'ALL',
            'metadata' => json_encode(['payment_id' => $payment->id]),
            'customer_name' => $name, 'customer_email' => $email, 'customer_surname' => 'User',
        ];

        $response = $this->httpRequest('POST', self::API_BASE . '/payment', $payload);
        $json = $response['json'] ?? [];
        if (($json['code'] ?? '') === '201' && isset($json['data']['payment_url'])) { return ['redirect_url' => $json['data']['payment_url']]; }
        throw new \RuntimeException("CinetPay: " . ($json['message'] ?? 'Failed to create payment.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $transactionId = $payload['transaction_id'] ?? $payload['cpm_trans_id'] ?? null;
        if (! $transactionId) { return PaymentResult::failure('Missing CinetPay transaction ID.'); }

        $apiKey = $this->credential('api_key');
        $siteId = $this->credential('site_id');
        $response = $this->httpRequest('POST', self::API_BASE . '/payment/check', ['apikey' => $apiKey, 'site_id' => $siteId, 'transaction_id' => $transactionId]);
        $json = $response['json'] ?? [];
        $respCode = $json['code'] ?? '';
        $status = $json['data']['status'] ?? '';

        if ($respCode !== '00') { return PaymentResult::failure("CinetPay check error (code: {$respCode})"); }
        if ($status === 'ACCEPTED') {
            return PaymentResult::success(transactionId: $transactionId, message: 'Payment accepted.',
                metadata: ['cinetpay_transaction_id' => $transactionId, 'cinetpay_payment_method' => $json['data']['payment_method'] ?? null]);
        }
        return PaymentResult::failure("CinetPay transaction status: {$status}");
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $transactionId = $payload['cpm_trans_id'] ?? null;
        if (! $transactionId) { return PaymentResult::failure('Missing CinetPay transaction ID in webhook.'); }
        return $this->handleCallback(['transaction_id' => $transactionId]);
    }
}
