<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Plisio payment gateway driver (Crypto).
 *
 * @see https://plisio.net/documentation
 */
class PlisioDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.plisio.net/api/v1';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',      'label' => 'API Key',      'type' => 'password', 'required' => true],
            ['key' => 'secret_key',   'label' => 'Secret Key',   'type' => 'password', 'required' => false,
             'hint' => 'Used for webhook signature verification.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');

        $params = [
            'api_key'      => $apiKey,
            'currency'     => 'BTC',
            'amount'       => number_format((float) $payment->amount, 2, '.', ''),
            'source_currency' => strtoupper($payment->currency ?? 'USD'),
            'order_name'   => $this->paymentDescription($payment),
            'order_number' => (string) $payment->id,
            'callback_url' => route('payment.webhook', ['gateway' => 'plisio']),
            'success_url'  => $callbackUrl . '?status=success',
            'cancel_url'   => $callbackUrl . '?status=cancelled',
            'email'        => $payment->workspace->owner()?->email ?? 'customer@example.com',
        ];

        $url = self::API_BASE . '/invoices/new?' . http_build_query($params);
        $response = $this->httpRequest('GET', $url);
        $json     = $response['json'] ?? [];

        if (($json['status'] ?? '') === 'success' && isset($json['data']['invoice_url'])) {
            return ['redirect_url' => $json['data']['invoice_url']];
        }

        $errorMsg = $json['data']['message'] ?? $json['message'] ?? 'Failed to create Plisio invoice.';
        throw new \RuntimeException("Plisio: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting Plisio payment confirmation.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secretKey = $this->credential('secret_key');
        if (! $secretKey) {
            return true;
        }

        // Plisio sends verify_hash in the POST data
        $data = json_decode($rawBody, true) ?? [];
        $verifyHash = $data['verify_hash'] ?? '';
        if (! $verifyHash) {
            parse_str($rawBody, $formData);
            $verifyHash = $formData['verify_hash'] ?? '';
        }

        if (! $verifyHash) {
            return false;
        }

        // Remove verify_hash from data, sort, and compute HMAC using serialize() per Plisio docs
        unset($data['verify_hash']);
        if (empty($data)) {
            parse_str($rawBody, $data);
            unset($data['verify_hash']);
        }
        ksort($data);
        $message = serialize($data);
        $expected = hash_hmac('sha1', $message, $secretKey);

        return hash_equals($expected, $verifyHash);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status   = $payload['status'] ?? '';
        $txnId    = $payload['txn_id'] ?? $payload['id'] ?? '';
        $orderId  = $payload['order_number'] ?? '';

        if ($status === 'completed') {
            return PaymentResult::success(
                transactionId: $txnId ?: $orderId,
                message: 'Plisio crypto payment confirmed.',
                metadata: [
                    'plisio_txn_id'   => $txnId,
                    'order_number'    => $orderId,
                    'source_amount'   => $payload['source_amount'] ?? null,
                    'source_currency' => $payload['source_currency'] ?? null,
                ],
            );
        }

        if ($status === 'mismatch') {
            return PaymentResult::pending('Plisio: payment amount mismatch — review required.', $txnId);
        }

        if (in_array($status, ['new', 'pending'])) {
            return PaymentResult::pending("Plisio: {$status}", $txnId);
        }

        if (in_array($status, ['expired', 'cancelled', 'error'])) {
            return PaymentResult::failure("Plisio payment {$status}.");
        }

        return PaymentResult::failure("Unhandled Plisio status: {$status}");
    }
}
