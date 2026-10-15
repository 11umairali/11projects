<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Offline / manual payment driver.
 *
 * Displays custom instructions configured by the admin. The payment stays
 * pending until the admin manually marks it as paid.
 */
class OfflineDriver extends AbstractGatewayDriver
{
    public static function credentialFields(): array
    {
        return [
            ['key' => 'payment_instructions', 'label' => 'Payment Instructions', 'type' => 'textarea', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $instructions = $this->credential('payment_instructions', 'Please contact us for payment instructions.');
        $amount = number_format((float) $payment->amount, 2);
        $currency = strtoupper($payment->currency ?? 'USD');
        $escapedInstructions = nl2br(htmlspecialchars($instructions, ENT_QUOTES, 'UTF-8'));
        $escapedPaymentId = htmlspecialchars($payment->id, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <div class="offline-payment-details" style="max-width:500px;margin:0 auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px;">
            <h3 style="margin-top:0;">Offline Payment</h3>
            <p>Amount due: <strong>{$currency} {$amount}</strong></p>
            <p>Payment reference: <strong>{$escapedPaymentId}</strong></p>
            <div style="margin-top:16px;padding:16px;background:#f9f9f9;border-radius:4px;">{$escapedInstructions}</div>
            <p style="margin-top:16px;color:#666;font-size:0.9em;">Your subscription will be activated once the payment is confirmed by our team.</p>
            <form method="POST" action="{$callbackUrl}" style="margin-top:16px;">
                <input type="hidden" name="_token" value="{$this->csrfToken()}" />
                <input type="hidden" name="payment_id" value="{$escapedPaymentId}" />
                <input type="hidden" name="method" value="offline" />
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;cursor:pointer;">Confirm Order</button>
            </form>
        </div>
        HTML;

        return ['html' => $html];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $paymentId = $payload['payment_id'] ?? '';
        return PaymentResult::pending(message: 'Order placed. Awaiting offline payment confirmation.', transactionId: 'OFFLINE_' . $paymentId . '_' . time(), metadata: ['payment_method' => 'offline', 'status' => 'pending']);
    }

    public function handleWebhook(array $payload): PaymentResult { return PaymentResult::pending('Offline payments do not have webhooks.'); }

    public function verify(Payment $payment): PaymentResult
    {
        return PaymentResult::pending('Offline payment must be verified manually by an administrator.', $payment->gateway_transaction_id ?? null);
    }

    private function csrfToken(): string
    {
        try { return csrf_token(); } catch (\Exception $e) { return ''; }
    }
}
