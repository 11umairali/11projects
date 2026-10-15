<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Bank Transfer (manual) payment driver.
 *
 * Displays the admin's bank account details so the customer can
 * make a manual transfer. The payment stays in "pending_verification"
 * until the admin confirms receipt.
 */
class BankTransferDriver extends AbstractGatewayDriver
{
    public static function credentialFields(): array
    {
        return [
            ['key' => 'bank_name',      'label' => 'Bank Name',      'type' => 'text',     'required' => true],
            ['key' => 'account_name',   'label' => 'Account Name',   'type' => 'text',     'required' => true],
            ['key' => 'account_number', 'label' => 'Account Number', 'type' => 'text',     'required' => true],
            ['key' => 'routing_number', 'label' => 'Routing Number', 'type' => 'text',     'required' => false],
            ['key' => 'swift_code',     'label' => 'SWIFT Code',     'type' => 'text',     'required' => false],
            ['key' => 'iban',           'label' => 'IBAN',           'type' => 'text',     'required' => false],
            ['key' => 'instructions',   'label' => 'Additional Instructions', 'type' => 'textarea', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $bankName = $this->credential('bank_name', '');
        $accountName = $this->credential('account_name', '');
        $accountNumber = $this->credential('account_number', '');
        $routingNumber = $this->credential('routing_number', '');
        $swiftCode = $this->credential('swift_code', '');
        $iban = $this->credential('iban', '');
        $instructions = $this->credential('instructions', '');
        $amount = number_format((float) $payment->amount, 2);
        $currency = strtoupper($payment->currency ?? 'USD');

        $htmlParts = [];
        $htmlParts[] = '<div class="bank-transfer-details" style="max-width:500px;margin:0 auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px;">';
        $htmlParts[] = '<h3 style="margin-top:0;">Bank Transfer Details</h3>';
        $htmlParts[] = "<p>Please transfer <strong>{$currency} {$amount}</strong> to the following bank account:</p>";
        $htmlParts[] = '<table style="width:100%;border-collapse:collapse;">';

        $fields = ['Bank Name' => $bankName, 'Account Name' => $accountName, 'Account Number' => $accountNumber];
        if ($routingNumber) { $fields['Routing Number'] = $routingNumber; }
        if ($swiftCode) { $fields['SWIFT Code'] = $swiftCode; }
        if ($iban) { $fields['IBAN'] = $iban; }
        $fields['Reference'] = (string) $payment->id;

        foreach ($fields as $label => $value) {
            $eLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $eValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $htmlParts[] = "<tr><td style=\"padding:8px 4px;border-bottom:1px solid #f0f0f0;font-weight:600;\">{$eLabel}</td><td style=\"padding:8px 4px;border-bottom:1px solid #f0f0f0;\">{$eValue}</td></tr>";
        }
        $htmlParts[] = '</table>';
        if ($instructions) { $htmlParts[] = '<div style="margin-top:16px;padding:12px;background:#f9f9f9;border-radius:4px;"><strong>Instructions:</strong><br>' . nl2br(htmlspecialchars($instructions, ENT_QUOTES, 'UTF-8')) . '</div>'; }
        $htmlParts[] = '<p style="margin-top:16px;color:#666;font-size:0.9em;">Your subscription will be activated once we confirm receipt of the payment.</p>';
        $htmlParts[] = "<form method=\"POST\" action=\"{$callbackUrl}\" style=\"margin-top:16px;\"><input type=\"hidden\" name=\"_token\" value=\"" . csrf_token() . "\" /><input type=\"hidden\" name=\"payment_id\" value=\"" . htmlspecialchars($payment->id, ENT_QUOTES, 'UTF-8') . "\" /><input type=\"hidden\" name=\"method\" value=\"bank_transfer\" /><button type=\"submit\" class=\"btn btn-primary\" style=\"padding:10px 24px;cursor:pointer;\">I Have Made the Transfer</button></form>";
        $htmlParts[] = '</div>';
        return ['html' => implode("\n", $htmlParts)];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $paymentId = $payload['payment_id'] ?? '';
        return PaymentResult::pending(message: 'Bank transfer initiated. Awaiting admin verification.', transactionId: 'BT_' . $paymentId . '_' . time(), metadata: ['payment_method' => 'bank_transfer', 'status' => 'pending_verification']);
    }

    public function handleWebhook(array $payload): PaymentResult { return PaymentResult::pending('Bank transfer requires manual verification.'); }

    public function verify(Payment $payment): PaymentResult
    {
        return PaymentResult::pending('Bank transfer verification is manual. Check your bank account.', $payment->gateway_transaction_id ?? null);
    }
}
