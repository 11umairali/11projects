<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Xendit payment gateway driver (Southeast Asia).
 * @see https://docs.xendit.co/api-reference
 */
class XenditDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.xendit.co';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'secret_key',    'label' => 'Secret Key',    'type' => 'password', 'required' => true],
            ['key' => 'public_key',    'label' => 'Public Key',    'type' => 'text',     'required' => true],
            ['key' => 'webhook_token', 'label' => 'Webhook Token', 'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $secretKey = $this->credential('secret_key');
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $payload = [
            'external_id' => $payment->id . '_' . time(), 'amount' => (float) $payment->amount,
            'currency' => strtoupper($payment->currency ?? 'IDR'), 'description' => $this->paymentDescription($payment),
            'payer_email' => $email, 'success_redirect_url' => $callbackUrl . '?status=success', 'failure_redirect_url' => $callbackUrl . '?status=failed',
        ];
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => self::API_BASE . '/v2/invoices', CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERPWD => $secretKey . ':', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("Xendit cURL error: {$error}"); }
        $json = json_decode($body, true);
        if (isset($json['invoice_url'])) { return ['redirect_url' => $json['invoice_url']]; }
        throw new \RuntimeException("Xendit: " . ($json['message'] ?? 'Failed to create invoice.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'failed') { return PaymentResult::failure('Xendit payment failed.'); }
        return PaymentResult::pending('Awaiting Xendit webhook confirmation.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookToken = $this->credential('webhook_token');
        if (! $webhookToken) { return true; }
        $headerToken = $headers['x-callback-token'] ?? $headers['X-Callback-Token'] ?? '';
        return hash_equals($webhookToken, $headerToken);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        $invoiceId = $payload['id'] ?? '';
        if ($status === 'PAID' || $status === 'SETTLED') {
            return PaymentResult::success(transactionId: $invoiceId, message: 'Xendit payment confirmed.',
                metadata: ['xendit_invoice_id' => $invoiceId, 'xendit_status' => $status, 'payment_method' => $payload['payment_method'] ?? null]);
        }
        return PaymentResult::failure("Xendit webhook status: {$status}");
    }
}
