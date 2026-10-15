<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Square payment gateway driver.
 *
 * @see https://developer.squareup.com/docs/checkout-api/
 */
class SquareDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://connect.squareupsandbox.com/v2';
    private const PROD_BASE    = 'https://connect.squareup.com/v2';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'application_id', 'label' => 'Application ID', 'type' => 'text',     'required' => true],
            ['key' => 'access_token',   'label' => 'Access Token',   'type' => 'password', 'required' => true],
            ['key' => 'location_id',    'label' => 'Location ID',    'type' => 'text',     'required' => true],
            ['key' => 'webhook_signature_key', 'label' => 'Webhook Signature Key', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $accessToken = $this->credential('access_token');
        $locationId  = $this->credential('location_id');
        $idempotencyKey = 'sq_' . $payment->id . '_' . time();

        $payload = [
            'idempotency_key' => $idempotencyKey,
            'order' => [
                'location_id' => $locationId,
                'line_items' => [[
                    'name' => $this->paymentDescription($payment), 'quantity' => '1',
                    'base_price_money' => ['amount' => $this->amountInSmallestUnit($payment), 'currency' => strtoupper($payment->currency ?? 'USD')],
                ]],
                'reference_id' => (string) $payment->id,
            ],
            'checkout_options' => ['redirect_url' => $callbackUrl, 'ask_for_shipping_address' => false, 'allow_tipping' => false],
        ];

        $response = $this->squareRequest('POST', '/online-checkout/payment-links', $payload, $accessToken);
        $json     = $response['json'] ?? [];

        if (isset($json['payment_link']['url'])) { return ['redirect_url' => $json['payment_link']['url']]; }

        $errors = $json['errors'] ?? [];
        $errorMsg = ! empty($errors) ? $errors[0]['detail'] ?? 'Unknown error' : 'Failed to create Square payment link.';
        throw new \RuntimeException("Square: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId = $payload['orderId'] ?? $payload['order_id'] ?? null;
        $transactionId = $payload['transactionId'] ?? $payload['checkoutId'] ?? null;
        if (! $orderId && ! $transactionId) { return PaymentResult::failure('Missing Square order/transaction ID.'); }

        if ($orderId) {
            $accessToken = $this->credential('access_token');
            $response = $this->squareRequest('GET', "/orders/{$orderId}", [], $accessToken);
            $json = $response['json'] ?? [];
            $orderData = $json['order'] ?? [];
            $state = $orderData['state'] ?? '';
            if ($state === 'COMPLETED') {
                $tenders = $orderData['tenders'] ?? [];
                $tenderId = ! empty($tenders) ? $tenders[0]['id'] ?? '' : '';
                return PaymentResult::success(transactionId: $tenderId ?: $orderId, message: 'Payment completed.',
                    metadata: ['square_order_id' => $orderId, 'square_state' => $state]);
            }
            return PaymentResult::failure("Square order not completed. State: {$state}");
        }
        return PaymentResult::pending('Square payment pending verification.', $transactionId);
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $signatureKey = $this->credential('webhook_signature_key');
        if (! $signatureKey) { return true; }
        $signature = $headers['x-square-hmacsha256-signature'] ?? $headers['X-Square-HmacSha256-Signature'] ?? '';
        if (! $signature) { return false; }
        $notificationUrl = route('payment.webhook', ['gateway' => 'square']);
        $expected = base64_encode(hash_hmac('sha256', $notificationUrl . $rawBody, $signatureKey, true));
        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data']['object'] ?? $payload['data'] ?? [];
        if ($type === 'payment.created' || $type === 'payment.updated') {
            $paymentData = $data['payment'] ?? $data;
            $status = $paymentData['status'] ?? '';
            if ($status === 'COMPLETED') {
                return PaymentResult::success(transactionId: $paymentData['id'] ?? '', message: 'Webhook confirmed payment.',
                    metadata: ['square_event_type' => $type, 'square_order_id' => $paymentData['order_id'] ?? null]);
            }
        }
        return PaymentResult::failure("Unhandled Square webhook event: {$type}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $transactionId = $payment->gateway_transaction_id ?? null;
        if (! $transactionId) { return PaymentResult::failure('No transaction ID available.'); }
        $accessToken = $this->credential('access_token');
        $response = $this->squareRequest('GET', "/payments/{$transactionId}", [], $accessToken);
        $json = $response['json'] ?? [];
        $paymentData = $json['payment'] ?? [];
        $status = $paymentData['status'] ?? '';
        if ($status === 'COMPLETED') { return PaymentResult::success(transactionId: $transactionId, message: 'Payment verified.'); }
        return PaymentResult::failure("Payment not verified. Status: {$status}");
    }

    private function squareRequest(string $method, string $path, array $data, string $accessToken): array
    {
        $url = $this->baseUrl() . $path;
        $ch = curl_init();
        $headers = ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json', 'Accept: application/json', 'Square-Version: 2024-01-18'];
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("Square cURL error: {$error}"); }
        return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
    }

    private function baseUrl(): string { return $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE; }
}
