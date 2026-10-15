<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Tap Payments gateway driver (Middle East).
 * @see https://developers.tap.company/reference/create-a-charge
 */
class TapDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.tap.company/v2';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'secret_key',      'label' => 'Secret Key',      'type' => 'password', 'required' => true],
            ['key' => 'publishable_key', 'label' => 'Publishable Key', 'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $secretKey = $this->credential('secret_key');
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';
        $payload = [
            'amount' => (float) $payment->amount, 'currency' => strtoupper($payment->currency ?? 'KWD'),
            'customer' => ['first_name' => $name, 'email' => $email],
            'source' => ['id' => 'src_all'], 'redirect' => ['url' => $callbackUrl],
            'post' => ['url' => route('payment.webhook', ['gateway' => 'tap'])],
            'reference' => ['transaction' => (string) $payment->id, 'order' => (string) $payment->id],
            'description' => $this->paymentDescription($payment), 'metadata' => ['payment_id' => $payment->id],
        ];

        $response = $this->httpRequest('POST', self::API_BASE . '/charges', $payload, ['Authorization: Bearer ' . $secretKey]);
        $json = $response['json'] ?? [];
        if (isset($json['transaction']['url'])) { return ['redirect_url' => $json['transaction']['url']]; }
        throw new \RuntimeException("Tap: " . ($json['errors'][0]['description'] ?? 'Failed to create charge.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $tapId = $payload['tap_id'] ?? $payload['id'] ?? null;
        if (! $tapId) { return PaymentResult::failure('Missing Tap charge ID.'); }
        $secretKey = $this->credential('secret_key');
        $response = $this->httpRequest('GET', self::API_BASE . "/charges/{$tapId}", [], ['Authorization: Bearer ' . $secretKey]);
        $json = $response['json'] ?? [];
        $status = $json['status'] ?? '';
        if ($status === 'CAPTURED') {
            return PaymentResult::success(transactionId: $json['id'] ?? $tapId, message: 'Payment captured.',
                metadata: ['tap_charge_id' => $json['id'] ?? null, 'tap_receipt' => $json['receipt']['id'] ?? null]);
        }
        return PaymentResult::failure("Tap charge status: {$status}");
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $chargeId = $payload['id'] ?? '';
        if (! $chargeId) { return PaymentResult::failure('Missing Tap charge ID in webhook.'); }
        $secretKey = $this->credential('secret_key');
        $response = $this->httpRequest('GET', self::API_BASE . "/charges/{$chargeId}", [], ['Authorization: Bearer ' . $secretKey]);
        $json = $response['json'] ?? [];
        $status = $json['status'] ?? '';
        if ($status === 'CAPTURED') { return PaymentResult::success(transactionId: $json['id'] ?? $chargeId, message: 'Tap webhook confirmed payment.'); }
        return PaymentResult::failure("Tap webhook status: {$status}");
    }
}
