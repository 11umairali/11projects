<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Midtrans payment gateway driver (Indonesia).
 * @see https://docs.midtrans.com/reference/api-methods
 */
class MidtransDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://app.sandbox.midtrans.com/snap/v1';
    private const PROD_BASE    = 'https://app.midtrans.com/snap/v1';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'server_key', 'label' => 'Server Key', 'type' => 'password', 'required' => true],
            ['key' => 'client_key', 'label' => 'Client Key', 'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $serverKey = $this->credential('server_key');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';
        $orderId = 'MID_' . $payment->id . '_' . time();

        $payload = [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => (int) round($payment->amount)],
            'customer_details' => ['first_name' => $name, 'email' => $email],
            'callbacks' => ['finish' => $callbackUrl . '?order_id=' . $orderId],
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $baseUrl . '/transactions', CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($serverKey . ':'), 'Content-Type: application/json', 'Accept: application/json'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("Midtrans cURL error: {$error}"); }
        $json = json_decode($body, true);

        if (isset($json['redirect_url'])) { return ['redirect_url' => $json['redirect_url']]; }
        if (isset($json['token'])) {
            $clientKey = $this->credential('client_key');
            $snapUrl = $this->isSandbox() ? 'https://app.sandbox.midtrans.com/snap/snap.js' : 'https://app.midtrans.com/snap/snap.js';
            $jsSnapToken = json_encode($json['token'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
            $jsCallbackUrl = json_encode($callbackUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
            $jsOrderId = json_encode($orderId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
            $eSnapUrl = htmlspecialchars($snapUrl, ENT_QUOTES, 'UTF-8');
            $eClientKey = htmlspecialchars($clientKey, ENT_QUOTES, 'UTF-8');
            $html = "<script src=\"{$eSnapUrl}\" data-client-key=\"{$eClientKey}\"></script><script>snap.pay({$jsSnapToken}, { onSuccess: function(r) { window.location.href = {$jsCallbackUrl} + '?order_id=' + {$jsOrderId} + '&transaction_status=settlement'; }, onPending: function(r) { window.location.href = {$jsCallbackUrl} + '?order_id=' + {$jsOrderId} + '&transaction_status=pending'; }, onError: function(r) { window.location.href = {$jsCallbackUrl} + '?order_id=' + {$jsOrderId} + '&transaction_status=deny'; }, onClose: function() { window.location.href = {$jsCallbackUrl} + '?order_id=' + {$jsOrderId} + '&transaction_status=cancelled'; } });</script>";
            return ['html' => $html];
        }
        throw new \RuntimeException("Midtrans: " . ($json['error_messages'][0] ?? 'Failed to create transaction.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId = $payload['order_id'] ?? null;
        if (! $orderId) { return PaymentResult::failure('Missing Midtrans order ID.'); }
        $serverKey = $this->credential('server_key');
        $coreBase = $this->isSandbox() ? 'https://api.sandbox.midtrans.com/v2' : 'https://api.midtrans.com/v2';
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $coreBase . '/' . urlencode($orderId) . '/status', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($serverKey . ':'), 'Accept: application/json'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("Midtrans status check error: {$error}"); }
        $json = json_decode($body, true);
        $transactionStatus = $json['transaction_status'] ?? '';
        $fraudStatus = $json['fraud_status'] ?? 'accept';
        if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
            return PaymentResult::success(transactionId: $json['transaction_id'] ?? $orderId, message: 'Payment successful.', metadata: ['midtrans_order_id' => $orderId, 'transaction_status' => $transactionStatus, 'payment_type' => $json['payment_type'] ?? null]);
        }
        if ($transactionStatus === 'pending') { return PaymentResult::pending('Midtrans payment pending.', $orderId); }
        return PaymentResult::failure("Midtrans payment status: {$transactionStatus}");
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $transactionStatus = $payload['transaction_status'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $serverKey = $this->credential('server_key');
        $signatureKey = $payload['signature_key'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        if (! $signatureKey || ! hash_equals($expectedSignature, $signatureKey)) { return PaymentResult::failure('Invalid Midtrans webhook signature.'); }
        $fraudStatus = $payload['fraud_status'] ?? 'accept';
        if ($transactionStatus === 'settlement' || ($transactionStatus === 'capture' && $fraudStatus === 'accept')) {
            return PaymentResult::success(transactionId: $payload['transaction_id'] ?? $orderId, message: 'Midtrans webhook confirmed payment.');
        }
        if ($transactionStatus === 'pending') { return PaymentResult::pending("Midtrans webhook: payment pending.", $orderId); }
        return PaymentResult::failure("Midtrans webhook status: {$transactionStatus}");
    }
}
