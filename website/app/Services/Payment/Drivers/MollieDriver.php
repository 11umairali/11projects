<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Mollie payment gateway driver.
 *
 * @see https://docs.mollie.com/reference/v2/payments-api/create-payment
 */
class MollieDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.mollie.com/v2';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');

        $payload = [
            'amount' => [
                'currency' => strtoupper($payment->currency ?? 'EUR'),
                'value'    => number_format((float) $payment->amount, 2, '.', ''),
            ],
            'description' => $this->paymentDescription($payment),
            'redirectUrl' => $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'payment_id=' . $payment->id,
            'webhookUrl'  => route('payment.webhook', ['gateway' => 'mollie']),
            'metadata'    => [
                'payment_id' => $payment->id,
            ],
        ];

        $response = $this->mollieRequest('POST', '/payments', $payload, $apiKey);
        $json     = $response['json'] ?? [];

        if (isset($json['_links']['checkout']['href'])) {
            return ['redirect_url' => $json['_links']['checkout']['href']];
        }

        $errorMsg = $json['detail'] ?? $json['title'] ?? 'Failed to create Mollie payment.';
        throw new \RuntimeException("Mollie: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $paymentId = $payload['id'] ?? $payload['payment_id'] ?? null;

        if (! $paymentId) {
            return PaymentResult::pending('Redirected from Mollie. Awaiting webhook confirmation.');
        }

        return $this->checkPaymentStatus($paymentId);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $paymentId = $payload['id'] ?? null;
        if (! $paymentId) {
            return PaymentResult::failure('Missing Mollie payment ID in webhook.');
        }

        return $this->checkPaymentStatus($paymentId);
    }

    public function verify(Payment $payment): PaymentResult
    {
        $paymentId = $payment->gateway_transaction_id ?? null;
        if (! $paymentId) {
            return PaymentResult::failure('No transaction ID available for verification.');
        }

        return $this->checkPaymentStatus($paymentId);
    }

    private function checkPaymentStatus(string $paymentId): PaymentResult
    {
        $apiKey   = $this->credential('api_key');
        $response = $this->mollieRequest('GET', "/payments/{$paymentId}", [], $apiKey);
        $json     = $response['json'] ?? [];
        $status   = $json['status'] ?? '';

        if ($status === 'paid') {
            return PaymentResult::success(
                transactionId: $paymentId,
                message: 'Payment completed.',
                metadata: [
                    'mollie_payment_id' => $paymentId,
                    'method'            => $json['method'] ?? null,
                    'paid_at'           => $json['paidAt'] ?? null,
                ],
            );
        }

        if (in_array($status, ['pending', 'open', 'authorized'])) {
            return PaymentResult::pending("Mollie payment status: {$status}", $paymentId);
        }

        return PaymentResult::failure("Mollie payment not completed. Status: {$status}");
    }

    private function mollieRequest(string $method, string $path, array $data, string $apiKey): array
    {
        $url = self::API_BASE . $path;
        $ch  = curl_init();

        $headers = [
            'Authorization: Bearer ' . $apiKey,
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
            throw new \RuntimeException("Mollie cURL error: {$error}");
        }

        return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
    }
}
