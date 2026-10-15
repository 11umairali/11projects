<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Paystack payment gateway driver.
 *
 * @see https://paystack.com/docs/api/transaction/
 */
class PaystackDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.paystack.co';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'public_key', 'label' => 'Public Key', 'type' => 'text',     'required' => true],
            ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $secretKey = $this->credential('secret_key');
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';

        $payload = [
            'email'        => $email,
            'amount'       => $this->amountInSmallestUnit($payment),
            'currency'     => strtoupper($payment->currency ?? 'NGN'),
            'reference'    => $payment->id . '_' . time(),
            'callback_url' => $callbackUrl,
            'metadata'     => [
                'payment_id' => $payment->id,
            ],
        ];

        $response = $this->paystackRequest('POST', '/transaction/initialize', $payload, $secretKey);
        $json     = $response['json'] ?? [];

        if (($json['status'] ?? false) === true && isset($json['data']['authorization_url'])) {
            return ['redirect_url' => $json['data']['authorization_url']];
        }

        $errorMsg = $json['message'] ?? 'Failed to initialize Paystack transaction.';
        throw new \RuntimeException("Paystack: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $reference = $payload['reference'] ?? $payload['trxref'] ?? null;

        if (! $reference) {
            return PaymentResult::failure('Missing Paystack transaction reference.');
        }

        $secretKey = $this->credential('secret_key');
        $response  = $this->paystackRequest('GET', "/transaction/verify/" . urlencode($reference), [], $secretKey);
        $json      = $response['json'] ?? [];

        if (($json['status'] ?? false) === true && ($json['data']['status'] ?? '') === 'success') {
            return PaymentResult::success(
                transactionId: (string) ($json['data']['id'] ?? $reference),
                message: 'Payment successful.',
                metadata: [
                    'paystack_reference'  => $reference,
                    'paystack_channel'    => $json['data']['channel'] ?? null,
                    'gateway_response'    => $json['data']['gateway_response'] ?? null,
                ],
            );
        }

        $status = $json['data']['status'] ?? 'unknown';
        return PaymentResult::failure("Paystack transaction not successful. Status: {$status}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secretKey = $this->credential('secret_key');
        if (! $secretKey) { return true; }

        $signature = $headers['x-paystack-signature'] ?? $headers['X-Paystack-Signature'] ?? '';
        if (! $signature) { return false; }

        $expected = hash_hmac('sha512', $rawBody, $secretKey);
        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event'] ?? '';
        $data  = $payload['data'] ?? [];

        if ($event === 'charge.success' && ($data['status'] ?? '') === 'success') {
            return PaymentResult::success(
                transactionId: (string) ($data['id'] ?? $data['reference'] ?? ''),
                message: 'Webhook confirmed successful charge.',
                metadata: [
                    'paystack_event'     => $event,
                    'paystack_reference' => $data['reference'] ?? null,
                ],
            );
        }

        return PaymentResult::failure("Unhandled Paystack webhook event: {$event}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $reference = $payment->gateway_transaction_id ?? null;
        if (! $reference) {
            return PaymentResult::failure('No transaction reference available for verification.');
        }

        return $this->handleCallback(['reference' => $reference]);
    }

    private function paystackRequest(string $method, string $path, array $data, string $secretKey): array
    {
        $url = self::API_BASE . $path;
        $ch  = curl_init();

        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("Paystack cURL error: {$error}");
        }

        return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
    }
}
