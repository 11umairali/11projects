<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Fondy (CloudIPSP) payment gateway driver (Eastern Europe).
 * @see https://docs.fondy.eu/
 */
class FondyDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://pay.fondy.eu/api';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id',  'label' => 'Merchant ID',  'type' => 'text',     'required' => true],
            ['key' => 'payment_key', 'label' => 'Payment Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantId = $this->credential('merchant_id');
        $paymentKey = $this->credential('payment_key');
        $orderId = 'FONDY_' . $payment->id . '_' . time();
        $requestData = [
            'order_id' => $orderId, 'merchant_id' => $merchantId, 'order_desc' => $this->paymentDescription($payment),
            'amount' => $this->amountInSmallestUnit($payment), 'currency' => strtoupper($payment->currency ?? 'USD'),
            'response_url' => $callbackUrl, 'server_callback_url' => route('payment.webhook', ['gateway' => 'fondy']),
        ];
        $signatureData = array_filter($requestData, fn($v) => $v !== '' && $v !== null);
        ksort($signatureData);
        $signatureString = $paymentKey . '|' . implode('|', $signatureData);
        $requestData['signature'] = sha1($signatureString);

        $response = $this->httpRequest('POST', self::API_BASE . '/checkout/url/', ['request' => $requestData]);
        $json = $response['json'] ?? [];
        $respData = $json['response'] ?? [];
        if (($respData['response_status'] ?? '') === 'success' && isset($respData['checkout_url'])) { return ['redirect_url' => $respData['checkout_url']]; }
        throw new \RuntimeException("Fondy: " . ($respData['error_message'] ?? 'Failed to create checkout.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderStatus = $payload['order_status'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $paymentId = $payload['payment_id'] ?? '';
        $paymentKey = $this->credential('payment_key');
        $signature = $payload['signature'] ?? '';
        $signData = $payload;
        unset($signData['signature'], $signData['response_signature_string']);
        $signData = array_filter($signData, fn($v) => $v !== '' && $v !== null);
        ksort($signData);
        $expectedSignature = sha1($paymentKey . '|' . implode('|', $signData));
        if (! $signature || ! hash_equals($expectedSignature, $signature)) { return PaymentResult::failure('Invalid Fondy signature.'); }
        if ($orderStatus === 'approved') {
            return PaymentResult::success(transactionId: $paymentId ?: $orderId, message: 'Payment approved.',
                metadata: ['fondy_order_id' => $orderId, 'fondy_payment_id' => $paymentId]);
        }
        return PaymentResult::failure("Fondy order status: {$orderStatus}");
    }
}
