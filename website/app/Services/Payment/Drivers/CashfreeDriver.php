<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Cashfree payment gateway driver (India).
 * @see https://docs.cashfree.com/docs/payment-gateway
 */
class CashfreeDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox.cashfree.com/pg';
    private const PROD_BASE    = 'https://api.cashfree.com/pg';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'app_id',     'label' => 'App ID',     'type' => 'text',     'required' => true],
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $appId = $this->credential('app_id');
        $secretKey = $this->credential('secret_key');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';
        $orderId = 'CF_' . $payment->id . '_' . time();

        $payload = [
            'order_id' => $orderId, 'order_amount' => (float) $payment->amount, 'order_currency' => strtoupper($payment->currency ?? 'INR'),
            'customer_details' => ['customer_id' => (string) ($payment->workspace_id ?? 'guest_' . time()), 'customer_name' => $name, 'customer_email' => $email, 'customer_phone' => '9999999999'],
            'order_meta' => ['return_url' => $callbackUrl . '?order_id={order_id}', 'notify_url' => route('payment.webhook', ['gateway' => 'cashfree'])],
            'order_note' => $this->paymentDescription($payment),
        ];

        $response = $this->httpRequest('POST', $baseUrl . '/orders', $payload, ['x-client-id: ' . $appId, 'x-client-secret: ' . $secretKey, 'x-api-version: 2023-08-01']);
        $json = $response['json'] ?? [];

        if (isset($json['payment_session_id'])) {
            $env = $this->isSandbox() ? 'sandbox' : 'production';
            $jsEnv = json_encode($env, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
            $jsSessionId = json_encode($json['payment_session_id'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
            $html = "<script src=\"https://sdk.cashfree.com/js/v3/cashfree.js\"></script><script>const cashfree = Cashfree({ mode: {$jsEnv} }); cashfree.checkout({ paymentSessionId: {$jsSessionId}, redirectTarget: '_self' });</script>";
            return ['html' => $html];
        }
        throw new \RuntimeException("Cashfree: " . ($json['message'] ?? 'Failed to create order.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId = $payload['order_id'] ?? null;
        if (! $orderId) { return PaymentResult::failure('Missing Cashfree order ID.'); }
        $appId = $this->credential('app_id');
        $secretKey = $this->credential('secret_key');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $response = $this->httpRequest('GET', $baseUrl . "/orders/{$orderId}", [], ['x-client-id: ' . $appId, 'x-client-secret: ' . $secretKey, 'x-api-version: 2023-08-01']);
        $json = $response['json'] ?? [];
        $status = $json['order_status'] ?? '';
        if ($status === 'PAID') { return PaymentResult::success(transactionId: $json['cf_order_id'] ?? $orderId, message: 'Payment successful.'); }
        return PaymentResult::failure("Cashfree order status: {$status}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secretKey = $this->credential('secret_key');
        if (! $secretKey) { return false; }
        $timestamp = $headers['x-cashfree-timestamp'] ?? $headers['X-Cashfree-Timestamp'] ?? '';
        $signature = $headers['x-cashfree-signature'] ?? $headers['X-Cashfree-Signature'] ?? '';
        if (! $timestamp || ! $signature) { return false; }
        $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $secretKey, true));
        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data'] ?? [];
        if ($type === 'PAYMENT_SUCCESS_WEBHOOK') {
            $order = $data['order'] ?? [];
            return PaymentResult::success(transactionId: $order['order_id'] ?? '', message: 'Cashfree webhook confirmed payment.');
        }
        return PaymentResult::failure("Unhandled Cashfree webhook: {$type}");
    }
}
