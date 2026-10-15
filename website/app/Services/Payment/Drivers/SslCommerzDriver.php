<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * SSLCommerz payment gateway driver (Bangladesh).
 * @see https://developer.sslcommerz.com/doc/v4/
 */
class SslCommerzDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://sandbox.sslcommerz.com';
    private const PROD_BASE    = 'https://securepay.sslcommerz.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'store_id',       'label' => 'Store ID',       'type' => 'text',     'required' => true],
            ['key' => 'store_password', 'label' => 'Store Password', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $storeId = $this->credential('store_id');
        $storePass = $this->credential('store_password');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';

        $payload = [
            'store_id' => $storeId, 'store_passwd' => $storePass,
            'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => strtoupper($payment->currency ?? 'BDT'),
            'tran_id' => 'SSL_' . $payment->id . '_' . time(),
            'success_url' => $callbackUrl, 'fail_url' => $callbackUrl . '?status=failed', 'cancel_url' => $callbackUrl . '?status=cancelled',
            'ipn_url' => route('payment.webhook', ['gateway' => 'sslcommerz']),
            'cus_name' => $name, 'cus_email' => $email, 'cus_phone' => '0000000000',
            'cus_add1' => 'N/A', 'cus_city' => 'N/A', 'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO', 'product_name' => $this->paymentDescription($payment),
            'product_category' => 'Service', 'product_profile' => 'non-physical-goods',
            'value_a' => (string) $payment->id,
        ];

        $response = $this->httpFormPost($baseUrl . '/gwprocess/v4/api.php', $payload);
        $json = $response['json'] ?? [];
        if (($json['status'] ?? '') === 'SUCCESS' && isset($json['GatewayPageURL'])) { return ['redirect_url' => $json['GatewayPageURL']]; }
        throw new \RuntimeException("SSLCommerz: " . ($json['failedreason'] ?? 'Failed to create session.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') { return PaymentResult::failure('Payment was cancelled.'); }
        if ($status === 'failed') { return PaymentResult::failure('SSLCommerz payment failed.'); }

        $valId = $payload['val_id'] ?? null;
        $tranId = $payload['tran_id'] ?? null;
        if (! $valId) { return PaymentResult::failure('Missing SSLCommerz validation ID.'); }

        $storeId = $this->credential('store_id');
        $storePass = $this->credential('store_password');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $validationUrl = $baseUrl . '/validator/api/validationserverAPI.php?' . http_build_query(['val_id' => $valId, 'store_id' => $storeId, 'store_passwd' => $storePass, 'format' => 'json']);

        $validationResponse = $this->httpRequest('GET', $validationUrl);
        $valJson = $validationResponse['json'] ?? [];
        $valStatus = $valJson['status'] ?? '';

        if ($valStatus === 'VALID' || $valStatus === 'VALIDATED') {
            return PaymentResult::success(transactionId: $valId, message: 'Payment validated.',
                metadata: ['ssl_tran_id' => $tranId, 'ssl_val_id' => $valId]);
        }
        return PaymentResult::failure("SSLCommerz validation status: {$valStatus}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        parse_str($rawBody, $params);
        $verifySign = $params['verify_sign'] ?? ''; $verifyKey = $params['verify_key'] ?? '';
        if (! $verifySign || ! $verifyKey) { return false; }
        $storePass = $this->credential('store_password');
        $keys = explode(',', $verifyKey); $data = [];
        foreach ($keys as $key) { if (isset($params[$key])) { $data[$key] = $params[$key]; } }
        ksort($data);
        $hashString = '';
        foreach ($data as $k => $v) { $hashString .= $k . '=' . $v . '&'; }
        $hashString .= 'store_passwd=' . md5($storePass);
        return hash_equals(md5($hashString), $verifySign);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? ''; $valId = $payload['val_id'] ?? ''; $tranId = $payload['tran_id'] ?? '';
        if ($status === 'VALID' || $status === 'VALIDATED') {
            return PaymentResult::success(transactionId: $valId ?: $tranId, message: 'SSLCommerz IPN confirmed payment.');
        }
        return PaymentResult::failure("SSLCommerz IPN status: {$status}");
    }
}
