<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Mercado Pago payment gateway driver.
 *
 * @see https://www.mercadopago.com/developers/en/docs/checkout-pro/integrate-preferences
 */
class MercadoPagoDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.mercadopago.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'access_token',   'label' => 'Access Token',   'type' => 'password', 'required' => true],
            ['key' => 'public_key',     'label' => 'Public Key',     'type' => 'text',     'required' => true],
            ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $accessToken = $this->credential('access_token');
        $payload = [
            'items' => [['title' => $this->paymentDescription($payment), 'quantity' => 1, 'unit_price' => (float) $payment->amount, 'currency_id' => strtoupper($payment->currency ?? 'BRL')]],
            'back_urls' => ['success' => $callbackUrl . '?status=approved', 'failure' => $callbackUrl . '?status=rejected', 'pending' => $callbackUrl . '?status=pending'],
            'auto_return' => 'approved',
            'external_reference' => (string) $payment->id,
            'notification_url' => route('payment.webhook', ['gateway' => 'mercadopago']),
        ];

        $response = $this->httpRequest('POST', self::API_BASE . '/checkout/preferences', $payload, ['Authorization: Bearer ' . $accessToken]);
        $json = $response['json'] ?? [];
        $redirectKey = $this->isSandbox() ? 'sandbox_init_point' : 'init_point';
        if (isset($json[$redirectKey])) { return ['redirect_url' => $json[$redirectKey]]; }
        throw new \RuntimeException("MercadoPago: " . ($json['message'] ?? 'Failed to create preference.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status    = $payload['status'] ?? $payload['collection_status'] ?? '';
        $paymentId = $payload['payment_id'] ?? $payload['collection_id'] ?? null;
        if ($status === 'approved' && $paymentId) {
            return PaymentResult::success(transactionId: (string) $paymentId, message: 'Payment approved.',
                metadata: ['mercadopago_payment_id' => $paymentId, 'mercadopago_status' => $status]);
        }
        if ($status === 'pending') { return PaymentResult::pending('MercadoPago payment is pending.', $paymentId); }
        return PaymentResult::failure("MercadoPago payment not approved. Status: {$status}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookSecret = $this->credential('webhook_secret');
        if (! $webhookSecret) { return true; }
        $xSignature = $headers['x-signature'] ?? $headers['X-Signature'] ?? '';
        $xRequestId = $headers['x-request-id'] ?? $headers['X-Request-Id'] ?? '';
        if (! $xSignature || ! $xRequestId) { return false; }
        $parts = [];
        foreach (explode(',', $xSignature) as $part) { $kv = explode('=', trim($part), 2); if (count($kv) === 2) { $parts[$kv[0]] = $kv[1]; } }
        $ts = $parts['ts'] ?? ''; $v1 = $parts['v1'] ?? '';
        if (! $ts || ! $v1) { return false; }
        $bodyData = json_decode($rawBody, true);
        $dataId = $bodyData['data']['id'] ?? '';
        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $expected = hash_hmac('sha256', $manifest, $webhookSecret);
        return hash_equals($expected, $v1);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type = $payload['type'] ?? $payload['topic'] ?? '';
        $dataId = $payload['data']['id'] ?? $payload['id'] ?? null;
        if ($type === 'payment' && $dataId) {
            $accessToken = $this->credential('access_token');
            $response = $this->httpRequest('GET', self::API_BASE . "/v1/payments/{$dataId}", [], ['Authorization: Bearer ' . $accessToken]);
            $json = $response['json'] ?? [];
            $status = $json['status'] ?? '';
            if ($status === 'approved') { return PaymentResult::success(transactionId: (string) $dataId, message: 'Webhook confirmed payment.'); }
            if (in_array($status, ['pending', 'in_process', 'authorized'])) { return PaymentResult::pending("MercadoPago webhook: payment {$status}.", (string) $dataId); }
            return PaymentResult::failure("MercadoPago webhook: payment status {$status}");
        }
        return PaymentResult::failure("Unhandled MercadoPago webhook type: {$type}");
    }
}
