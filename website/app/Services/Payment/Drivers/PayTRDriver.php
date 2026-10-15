<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * PayTR payment gateway driver (Turkey).
 * @see https://dev.paytr.com/en/iframe-api
 */
class PayTRDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://www.paytr.com/odeme/api/get-token';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id',   'label' => 'Merchant ID',   'type' => 'text',     'required' => true],
            ['key' => 'merchant_key',  'label' => 'Merchant Key',  'type' => 'password', 'required' => true],
            ['key' => 'merchant_salt', 'label' => 'Merchant Salt', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantId = $this->credential('merchant_id');
        $merchantKey = $this->credential('merchant_key');
        $merchantSalt = $this->credential('merchant_salt');
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $name  = $payment->workspace->owner()?->name ?? 'Customer';
        $merchantOid = 'PAYTR_' . $payment->id . '_' . time();
        $amount = (int) round($payment->amount * 100);
        $currency = strtoupper($payment->currency ?? 'TL');
        $testMode = $this->isSandbox() ? '1' : '0';
        $noInstallment = '1'; $maxInstallment = '0';
        $userIp = request()->ip() ?? '127.0.0.1';

        $basket = base64_encode(json_encode([[$this->paymentDescription($payment), $amount, 1]]));
        $hashStr = $merchantId . $userIp . $merchantOid . $email . $amount . $basket . $noInstallment . $maxInstallment . $currency . $testMode;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashStr . $merchantSalt, $merchantKey, true));

        $payload = [
            'merchant_id' => $merchantId, 'user_ip' => $userIp, 'merchant_oid' => $merchantOid, 'email' => $email,
            'payment_amount' => $amount, 'paytr_token' => $paytrToken, 'user_basket' => $basket,
            'debug_on' => $this->isSandbox() ? '1' : '0', 'no_installment' => $noInstallment, 'max_installment' => $maxInstallment,
            'user_name' => $name, 'user_address' => 'N/A', 'user_phone' => '0000000000',
            'merchant_ok_url' => $callbackUrl . '?status=success', 'merchant_fail_url' => $callbackUrl . '?status=failed',
            'timeout_limit' => '30', 'currency' => $currency, 'test_mode' => $testMode,
        ];

        $response = $this->httpFormPost(self::API_BASE, $payload);
        $json = $response['json'] ?? [];
        if (($json['status'] ?? '') === 'success' && isset($json['token'])) {
            $eIframeSrc = htmlspecialchars('https://www.paytr.com/odeme/guvenli/' . $json['token'], ENT_QUOTES, 'UTF-8');
            $html = "<iframe src=\"{$eIframeSrc}\" id=\"paytriframe\" frameborder=\"0\" scrolling=\"no\" style=\"width:100%;min-height:600px;\"></iframe>";
            return ['html' => $html];
        }
        throw new \RuntimeException("PayTR: " . ($json['reason'] ?? 'Failed to generate token.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        $merchantOid = $payload['merchant_oid'] ?? null;
        $totalAmount = $payload['total_amount'] ?? '';
        $hash = $payload['hash'] ?? '';
        if ($status === 'failed') { return PaymentResult::failure('PayTR payment failed.'); }
        $merchantKey = $this->credential('merchant_key');
        $merchantSalt = $this->credential('merchant_salt');
        $hashStr = $merchantOid . $merchantSalt . $status . $totalAmount;
        $expectedHash = base64_encode(hash_hmac('sha256', $hashStr, $merchantKey, true));
        if (! $hash || ! hash_equals($expectedHash, $hash)) { return PaymentResult::failure('Invalid PayTR callback hash.'); }
        if ($status === 'success') {
            return PaymentResult::success(transactionId: $merchantOid ?? '', message: 'Payment successful.',
                metadata: ['paytr_merchant_oid' => $merchantOid, 'paytr_total_amount' => $totalAmount]);
        }
        return PaymentResult::failure("PayTR status: {$status}");
    }
}
