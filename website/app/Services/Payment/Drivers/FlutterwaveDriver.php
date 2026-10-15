<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Flutterwave (Rave) payment gateway driver.
 *
 * @see https://developer.flutterwave.com/docs/collecting-payments/standard/
 */
class FlutterwaveDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.flutterwave.com/v3';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'public_key',     'label' => 'Public Key',     'type' => 'text',     'required' => true],
            ['key' => 'secret_key',     'label' => 'Secret Key',     'type' => 'password', 'required' => true],
            ['key' => 'encryption_key', 'label' => 'Encryption Key', 'type' => 'password', 'required' => false],
            ['key' => 'secret_hash',    'label' => 'Webhook Secret Hash', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $secretKey = $this->credential('secret_key');
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';
        $txRef = 'FLW_' . $payment->id . '_' . time();

        $payload = [
            'tx_ref'       => $txRef,
            'amount'       => (float) $payment->amount,
            'currency'     => strtoupper($payment->currency ?? 'NGN'),
            'redirect_url' => $callbackUrl,
            'payment_options' => 'card,mobilemoney,ussd,banktransfer',
            'customer' => ['email' => $email, 'name' => $name],
            'customizations' => [
                'title'       => config('app.name'),
                'description' => $this->paymentDescription($payment),
            ],
            'meta' => ['payment_id' => $payment->id],
        ];

        $response = $this->flwRequest('POST', '/payments', $payload, $secretKey);
        $json     = $response['json'] ?? [];

        if (($json['status'] ?? '') === 'success' && isset($json['data']['link'])) {
            return ['redirect_url' => $json['data']['link']];
        }

        $errorMsg = $json['message'] ?? 'Failed to create Flutterwave payment.';
        throw new \RuntimeException("Flutterwave: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status        = $payload['status'] ?? '';
        $transactionId = $payload['transaction_id'] ?? null;
        $txRef         = $payload['tx_ref'] ?? null;

        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled by the customer.');
        }

        if (! $transactionId && ! $txRef) {
            return PaymentResult::failure('Missing Flutterwave transaction ID.');
        }

        $secretKey = $this->credential('secret_key');

        if ($transactionId) {
            $response = $this->flwRequest('GET', "/transactions/{$transactionId}/verify", [], $secretKey);
        } else {
            $response = $this->flwRequest('GET', "/transactions/verify_by_reference?tx_ref=" . urlencode($txRef), [], $secretKey);
        }

        $json = $response['json'] ?? [];

        if (($json['status'] ?? '') === 'success' && ($json['data']['status'] ?? '') === 'successful') {
            return PaymentResult::success(
                transactionId: (string) ($json['data']['id'] ?? $transactionId ?? $txRef),
                message: 'Payment verified successfully.',
                metadata: [
                    'flw_ref'        => $json['data']['flw_ref'] ?? null,
                    'tx_ref'         => $json['data']['tx_ref'] ?? null,
                    'payment_type'   => $json['data']['payment_type'] ?? null,
                    'amount_settled' => $json['data']['amount_settled'] ?? null,
                ],
            );
        }

        $txStatus = $json['data']['status'] ?? 'unknown';
        return PaymentResult::failure("Flutterwave transaction not successful. Status: {$txStatus}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secretHash = $this->credential('secret_hash');
        if (! $secretHash) { return true; }
        $signature = $headers['verif-hash'] ?? $headers['Verif-Hash'] ?? '';
        return hash_equals($secretHash, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event = $payload['event'] ?? '';
        $data  = $payload['data'] ?? [];

        if ($event === 'charge.completed' && ($data['status'] ?? '') === 'successful') {
            return PaymentResult::success(
                transactionId: (string) ($data['id'] ?? ''),
                message: 'Webhook confirmed successful charge.',
                metadata: ['flw_ref' => $data['flw_ref'] ?? null, 'tx_ref' => $data['tx_ref'] ?? null],
            );
        }

        return PaymentResult::failure("Unhandled Flutterwave webhook event: {$event}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $transactionId = $payment->gateway_transaction_id ?? null;
        if (! $transactionId) { return PaymentResult::failure('No transaction ID available.'); }
        return $this->handleCallback(['transaction_id' => $transactionId]);
    }

    private function flwRequest(string $method, string $path, array $data, string $secretKey): array
    {
        $url = self::API_BASE . $path;
        $ch  = curl_init();
        $headers = ['Authorization: Bearer ' . $secretKey, 'Content-Type: application/json', 'Accept: application/json'];
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("Flutterwave cURL error: {$error}"); }
        return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
    }
}
