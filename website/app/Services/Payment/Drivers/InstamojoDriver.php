<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Instamojo payment gateway driver (India).
 * @see https://docs.instamojo.com/reference/create-a-payment-request
 */
class InstamojoDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://test.instamojo.com/api/1.1';
    private const PROD_BASE    = 'https://www.instamojo.com/api/1.1';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',    'label' => 'API Key',    'type' => 'text',     'required' => true],
            ['key' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password', 'required' => true],
            ['key' => 'salt',       'label' => 'Salt',       'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');
        $authToken = $this->credential('auth_token');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';

        $payload = [
            'purpose' => $this->paymentDescription($payment), 'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'buyer_name' => $name, 'email' => $email, 'redirect_url' => $callbackUrl,
            'webhook' => route('payment.webhook', ['gateway' => 'instamojo']),
            'allow_repeated_payments' => false, 'send_email' => false,
        ];

        $response = $this->httpFormPost($baseUrl . '/payment-requests/', $payload, ['X-Api-Key: ' . $apiKey, 'X-Auth-Token: ' . $authToken]);
        $json = $response['json'] ?? [];
        if (($json['success'] ?? false) === true && isset($json['payment_request']['longurl'])) { return ['redirect_url' => $json['payment_request']['longurl']]; }
        throw new \RuntimeException("Instamojo: " . (isset($json['message']) ? json_encode($json['message']) : 'Failed to create payment request.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $paymentId = $payload['payment_id'] ?? null;
        $paymentRequestId = $payload['payment_request_id'] ?? null;
        if (! $paymentId || ! $paymentRequestId) { return PaymentResult::failure('Missing Instamojo payment parameters.'); }

        $apiKey = $this->credential('api_key');
        $authToken = $this->credential('auth_token');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $response = $this->httpRequest('GET', $baseUrl . "/payment-requests/{$paymentRequestId}/{$paymentId}/", [], ['X-Api-Key: ' . $apiKey, 'X-Auth-Token: ' . $authToken]);
        $json = $response['json'] ?? [];
        $status = $json['payment_request']['payment']['status'] ?? '';

        if ($status === 'Credit') {
            return PaymentResult::success(transactionId: $paymentId, message: 'Payment successful.',
                metadata: ['instamojo_payment_id' => $paymentId, 'instamojo_request_id' => $paymentRequestId]);
        }
        return PaymentResult::failure("Instamojo payment status: {$status}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $salt = $this->credential('salt');
        if (! $salt) { return true; }
        parse_str($rawBody, $params);
        $mac = $params['mac'] ?? '';
        if (! $mac) { return false; }
        unset($params['mac']); ksort($params);
        $message = implode('|', $params);
        return hash_equals(hash_hmac('sha1', $message, $salt), $mac);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $paymentId = $payload['payment_id'] ?? null;
        $status = $payload['status'] ?? '';
        if ($status === 'Credit') { return PaymentResult::success(transactionId: $paymentId ?? '', message: 'Instamojo webhook confirmed payment.'); }
        return PaymentResult::failure("Instamojo webhook status: {$status}");
    }
}
