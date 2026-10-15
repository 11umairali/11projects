<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Skrill (Moneybookers) payment gateway driver.
 * @see https://www.skrill.com/fileadmin/content/pdf/Skrill_Quick_Checkout_Guide.pdf
 */
class SkrillDriver extends AbstractGatewayDriver
{
    private const CHECKOUT_URL = 'https://pay.skrill.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id',  'label' => 'Merchant ID (Email)',  'type' => 'text',     'required' => true],
            ['key' => 'secret_word', 'label' => 'Secret Word',         'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantId = $this->credential('merchant_id');
        $payload = [
            'pay_to_email' => $merchantId, 'transaction_id' => $payment->id . '_' . time(),
            'return_url' => $callbackUrl . '?status=success', 'cancel_url' => $callbackUrl . '?status=cancelled',
            'status_url' => route('payment.webhook', ['gateway' => 'skrill']), 'language' => 'EN',
            'amount' => number_format((float) $payment->amount, 2, '.', ''), 'currency' => strtoupper($payment->currency ?? 'USD'),
            'detail1_description' => 'Payment ID', 'detail1_text' => (string) $payment->id,
            'merchant_fields' => 'payment_id', 'payment_id' => $payment->id,
        ];
        $html = '<form id="skrill-form" method="POST" action="' . self::CHECKOUT_URL . '">';
        foreach ($payload as $key => $value) {
            $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $html .= '<input type="hidden" name="' . $key . '" value="' . $escaped . '" />';
        }
        $html .= '</form><script>document.getElementById("skrill-form").submit();</script>';
        return ['html' => $html];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') { return PaymentResult::failure('Payment was cancelled.'); }
        return PaymentResult::pending('Awaiting Skrill status notification.');
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        $transactionId = $payload['mb_transaction_id'] ?? '';
        $merchantTxId = $payload['transaction_id'] ?? '';
        $md5sig = $payload['md5sig'] ?? '';
        $secretWord = $this->credential('secret_word');
        $merchantId = $this->credential('merchant_id');
        $mbAmount = $payload['mb_amount'] ?? '';
        $mbCurrency = $payload['mb_currency'] ?? '';

        $secretWordUpper = strtoupper(md5($secretWord));
        $signString = $merchantId . $merchantTxId . $secretWordUpper . $mbAmount . $mbCurrency . $status;
        $expectedSig = strtoupper(md5($signString));
        if (! $md5sig || ! hash_equals($expectedSig, strtoupper($md5sig))) { return PaymentResult::failure('Invalid Skrill webhook signature.'); }
        if ($status === '2') {
            return PaymentResult::success(transactionId: $transactionId ?: $merchantTxId, message: 'Skrill payment confirmed.',
                metadata: ['skrill_transaction_id' => $transactionId, 'skrill_merchant_txid' => $merchantTxId]);
        }
        if ($status === '0') { return PaymentResult::pending('Skrill payment pending.', $transactionId); }
        return PaymentResult::failure("Skrill status code: {$status}");
    }
}
