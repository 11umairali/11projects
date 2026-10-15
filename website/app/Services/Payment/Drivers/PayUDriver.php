<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * PayU payment gateway driver (India / LatAm).
 * @see https://devguide.payu.in/docs/payu-checkout-integration/
 */
class PayUDriver extends AbstractGatewayDriver
{
    private const SANDBOX_URL = 'https://sandboxsecure.payu.in/_payment';
    private const PROD_URL    = 'https://secure.payu.in/_payment';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_key',  'label' => 'Merchant Key',  'type' => 'text',     'required' => true],
            ['key' => 'merchant_salt', 'label' => 'Merchant Salt', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantKey = $this->credential('merchant_key');
        $merchantSalt = $this->credential('merchant_salt');
        $email = $payment->workspace->owner()?->email ?? 'customer@example.com';
        $firstName = $payment->workspace->owner()?->name ?? 'Customer';
        $txnId = 'PAYU_' . $payment->id . '_' . time();
        $amount = number_format((float) $payment->amount, 2, '.', '');
        $productInfo = $this->paymentDescription($payment);
        $udf1 = (string) $payment->id;

        $hashString = "{$merchantKey}|{$txnId}|{$amount}|{$productInfo}|{$firstName}|{$email}|{$udf1}|||||||||{$merchantSalt}";
        $hash = strtolower(hash('sha512', $hashString));
        $paymentUrl = $this->isSandbox() ? self::SANDBOX_URL : self::PROD_URL;

        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
        <form id="payu-form" method="POST" action="{$e($paymentUrl)}">
            <input type="hidden" name="key" value="{$e($merchantKey)}" />
            <input type="hidden" name="txnid" value="{$e($txnId)}" />
            <input type="hidden" name="amount" value="{$e($amount)}" />
            <input type="hidden" name="productinfo" value="{$e($productInfo)}" />
            <input type="hidden" name="firstname" value="{$e($firstName)}" />
            <input type="hidden" name="email" value="{$e($email)}" />
            <input type="hidden" name="phone" value="9999999999" />
            <input type="hidden" name="surl" value="{$e($callbackUrl)}" />
            <input type="hidden" name="furl" value="{$e($callbackUrl . '?status=failed')}" />
            <input type="hidden" name="hash" value="{$e($hash)}" />
            <input type="hidden" name="udf1" value="{$e($payment->id)}" />
        </form>
        <script>document.getElementById('payu-form').submit();</script>
        HTML;
        return ['html' => $html];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        $txnId = $payload['txnid'] ?? null;
        $mihpayid = $payload['mihpayid'] ?? null;
        $responseHash = $payload['hash'] ?? '';
        if (! $txnId) { return PaymentResult::failure('Missing PayU transaction ID.'); }

        $merchantKey = $this->credential('merchant_key');
        $merchantSalt = $this->credential('merchant_salt');
        $amount = $payload['amount'] ?? '';
        $productInfo = $payload['productinfo'] ?? '';
        $firstName = $payload['firstname'] ?? '';
        $email = $payload['email'] ?? '';
        $udf1 = $payload['udf1'] ?? '';

        $reverseHashString = "{$merchantSalt}|{$status}||||||||||{$udf1}|{$email}|{$firstName}|{$productInfo}|{$amount}|{$txnId}|{$merchantKey}";
        $expectedHash = strtolower(hash('sha512', $reverseHashString));
        if (! $responseHash || ! hash_equals($expectedHash, $responseHash)) { return PaymentResult::failure('Invalid PayU response hash.'); }

        if ($status === 'success') {
            return PaymentResult::success(transactionId: $mihpayid ?? $txnId, message: 'Payment successful.',
                metadata: ['payu_txnid' => $txnId, 'payu_mihpayid' => $mihpayid]);
        }
        return PaymentResult::failure("PayU: " . ($payload['error_Message'] ?? "Payment status: {$status}"));
    }
}
