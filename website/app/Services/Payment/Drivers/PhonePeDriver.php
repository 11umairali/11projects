<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * PhonePe payment gateway driver (India).
 * @see https://developer.phonepe.com/docs/pg-api-reference/
 */
class PhonePeDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    private const PROD_BASE    = 'https://api.phonepe.com/apis/hermes';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text',     'required' => true],
            ['key' => 'salt_key',    'label' => 'Salt Key',    'type' => 'password', 'required' => true],
            ['key' => 'salt_index',  'label' => 'Salt Index',  'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantId = $this->credential('merchant_id');
        $saltKey = $this->credential('salt_key');
        $saltIndex = $this->credential('salt_index');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $merchantTransactionId = 'PP_' . $payment->id . '_' . time();

        $payload = [
            'merchantId' => $merchantId, 'merchantTransactionId' => $merchantTransactionId,
            'merchantUserId' => 'USER_' . ($payment->workspace_id ?? 'GUEST'),
            'amount' => $this->amountInSmallestUnit($payment), 'redirectUrl' => $callbackUrl, 'redirectMode' => 'REDIRECT',
            'callbackUrl' => route('payment.webhook', ['gateway' => 'phonepe']),
            'paymentInstrument' => ['type' => 'PAY_PAGE'],
        ];

        $base64Payload = base64_encode(json_encode($payload));
        $apiEndpoint = '/pg/v1/pay';
        $checksum = hash('sha256', $base64Payload . $apiEndpoint . $saltKey) . '###' . $saltIndex;

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $baseUrl . $apiEndpoint, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['request' => $base64Payload]), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-VERIFY: ' . $checksum], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("PhonePe cURL error: {$error}"); }
        $json = json_decode($body, true);

        if (($json['success'] ?? false) === true) {
            $redirectUrl = $json['data']['instrumentResponse']['redirectInfo']['url'] ?? null;
            if ($redirectUrl) { return ['redirect_url' => $redirectUrl]; }
        }
        throw new \RuntimeException("PhonePe: " . ($json['message'] ?? 'Failed to initiate payment.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $responseData = $payload['response'] ?? null;
        if (! $responseData) { return PaymentResult::pending('PhonePe payment status unknown.'); }

        $decoded = json_decode(base64_decode($responseData), true);
        $merchantTransactionId = $decoded['data']['merchantTransactionId'] ?? null;
        if (! $merchantTransactionId) { return PaymentResult::failure('Missing merchantTransactionId.'); }

        $merchantId = $this->credential('merchant_id');
        $saltKey = $this->credential('salt_key');
        $saltIndex = $this->credential('salt_index');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $apiEndpoint = "/pg/v1/status/{$merchantId}/{$merchantTransactionId}";
        $checksum = hash('sha256', $apiEndpoint . $saltKey) . '###' . $saltIndex;

        $response = $this->httpRequest('GET', $baseUrl . $apiEndpoint, [], ['X-VERIFY: ' . $checksum, 'X-MERCHANT-ID: ' . $merchantId]);
        $json = $response['json'] ?? [];
        $code = $json['code'] ?? '';

        if ($code === 'PAYMENT_SUCCESS') { return PaymentResult::success(transactionId: $json['data']['transactionId'] ?? $merchantTransactionId, message: 'Payment verified.'); }
        if ($code === 'PAYMENT_PENDING') { return PaymentResult::pending('PhonePe payment pending.', $merchantTransactionId); }
        return PaymentResult::failure("PhonePe payment status: {$code}");
    }

    public function handleWebhook(array $payload): PaymentResult { return $this->handleCallback($payload); }

    public function verify(Payment $payment): PaymentResult
    {
        $merchantId = $this->credential('merchant_id');
        $saltKey = $this->credential('salt_key');
        $saltIndex = $this->credential('salt_index');
        $transactionId = $payment->gateway_transaction_id ?? null;
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        if (! $transactionId) { return PaymentResult::failure('No transaction ID available.'); }
        $apiEndpoint = "/pg/v1/status/{$merchantId}/{$transactionId}";
        $checksum = hash('sha256', $apiEndpoint . $saltKey) . '###' . $saltIndex;
        $response = $this->httpRequest('GET', $baseUrl . $apiEndpoint, [], ['X-VERIFY: ' . $checksum, 'X-MERCHANT-ID: ' . $merchantId]);
        $json = $response['json'] ?? [];
        if (($json['code'] ?? '') === 'PAYMENT_SUCCESS') { return PaymentResult::success(transactionId: $json['data']['transactionId'] ?? $transactionId, message: 'Payment verified.'); }
        return PaymentResult::failure("PhonePe verification: " . ($json['code'] ?? 'unknown'));
    }
}
