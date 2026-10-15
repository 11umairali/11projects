<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * iyzico payment gateway driver (Turkey).
 *
 * @see https://dev.iyzipay.com/en/checkout-form
 */
class IyzicoDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox-api.iyzipay.com';
    private const PROD_BASE    = 'https://api.iyzipay.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',    'label' => 'API Key',    'type' => 'text',     'required' => true],
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');
        $secretKey = $this->credential('secret_key');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';
        $price = number_format((float) $payment->amount, 2, '.', '');

        $payload = [
            'locale' => 'en', 'conversationId' => 'iyz_' . $payment->id,
            'price' => $price, 'paidPrice' => $price, 'currency' => strtoupper($payment->currency ?? 'TRY'),
            'basketId' => (string) $payment->id, 'paymentGroup' => 'PRODUCT', 'callbackUrl' => $callbackUrl,
            'enabledInstallments' => [1],
            'buyer' => ['id' => (string) ($payment->workspace_id ?? 'GUEST'), 'name' => $name, 'surname' => 'User', 'email' => $email, 'identityNumber' => '11111111111', 'registrationAddress' => 'N/A', 'city' => 'N/A', 'country' => 'N/A', 'ip' => request()->ip() ?? '127.0.0.1'],
            'shippingAddress' => ['contactName' => $name, 'city' => 'N/A', 'country' => 'N/A', 'address' => 'N/A'],
            'billingAddress' => ['contactName' => $name, 'city' => 'N/A', 'country' => 'N/A', 'address' => 'N/A'],
            'basketItems' => [['id' => 'ITEM_' . $payment->id, 'name' => $this->paymentDescription($payment), 'category1' => 'Service', 'itemType' => 'VIRTUAL', 'price' => $price]],
        ];

        $authHeaders = $this->iyzicoAuthHeaders($apiKey, $secretKey, $payload);
        $response = $this->httpRequest('POST', $baseUrl . '/payment/iyzipos/checkoutform/initialize/auth/ecom', $payload, $authHeaders);
        $json = $response['json'] ?? [];

        if (($json['status'] ?? '') === 'success' && isset($json['paymentPageUrl'])) { return ['redirect_url' => $json['paymentPageUrl']]; }
        if (isset($json['checkoutFormContent'])) { return ['html' => $json['checkoutFormContent']]; }
        throw new \RuntimeException("iyzico: " . ($json['errorMessage'] ?? 'Failed to initialize checkout.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $token = $payload['token'] ?? null;
        if (! $token) { return PaymentResult::failure('Missing iyzico token.'); }

        $apiKey = $this->credential('api_key');
        $secretKey = $this->credential('secret_key');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $checkPayload = ['locale' => 'en', 'token' => $token];
        $authHeaders = $this->iyzicoAuthHeaders($apiKey, $secretKey, $checkPayload);
        $response = $this->httpRequest('POST', $baseUrl . '/payment/iyzipos/checkoutform/auth/ecom/detail', $checkPayload, $authHeaders);
        $json = $response['json'] ?? [];
        $paymentStatus = $json['paymentStatus'] ?? $json['status'] ?? '';

        if ($paymentStatus === 'SUCCESS' || ($json['status'] ?? '') === 'success') {
            return PaymentResult::success(transactionId: $json['paymentId'] ?? $token, message: 'Payment completed.',
                metadata: ['iyzico_payment_id' => $json['paymentId'] ?? null]);
        }
        return PaymentResult::failure("iyzico: " . ($json['errorMessage'] ?? "Payment status: {$paymentStatus}"));
    }

    private function iyzicoAuthHeaders(string $apiKey, string $secretKey, array $payload): array
    {
        $pkiString = $this->buildPkiString($payload);
        $randomKey = (string) (microtime(true) . mt_rand(1, 999999));
        $hashStr = $apiKey . $randomKey . $secretKey . $pkiString;
        $hash = base64_encode(hash('sha1', $hashStr, true));
        return ['Authorization: IYZWS ' . $apiKey . ':' . $hash, 'x-iyzi-rnd: ' . $randomKey];
    }

    private function buildPkiString(array $data): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (array_keys($value) === range(0, count($value) - 1)) {
                    $subParts = [];
                    foreach ($value as $item) { $subParts[] = is_array($item) ? '[' . $this->buildPkiPairs($item) . ']' : (string) $item; }
                    $parts[] = $key . '=[' . implode(', ', $subParts) . ']';
                } else { $parts[] = $key . '=[' . $this->buildPkiPairs($value) . ']'; }
            } else { $parts[] = $key . '=' . (string) $value; }
        }
        return '[' . implode(', ', $parts) . ']';
    }

    private function buildPkiPairs(array $data): string
    {
        $pairs = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                if (array_keys($v) === range(0, count($v) - 1)) {
                    $sub = [];
                    foreach ($v as $item) { $sub[] = is_array($item) ? '[' . $this->buildPkiPairs($item) . ']' : (string) $item; }
                    $pairs[] = $k . '=[' . implode(', ', $sub) . ']';
                } else { $pairs[] = $k . '=[' . $this->buildPkiPairs($v) . ']'; }
            } else { $pairs[] = $k . '=' . (string) $v; }
        }
        return implode(', ', $pairs);
    }
}
