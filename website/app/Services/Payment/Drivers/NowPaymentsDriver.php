<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * NOWPayments payment gateway driver (Crypto).
 *
 * @see https://documenter.getpostman.com/view/7907941/S1a32n38
 */
class NowPaymentsDriver extends AbstractGatewayDriver
{
    private const API_BASE         = 'https://api.nowpayments.io/v1';
    private const SANDBOX_API_BASE = 'https://api-sandbox.nowpayments.io/v1';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key',    'label' => 'API Key',    'type' => 'password', 'required' => true],
            ['key' => 'ipn_secret', 'label' => 'IPN Secret', 'type' => 'password', 'required' => false,
             'hint' => 'Used for webhook signature verification. Set in NOWPayments dashboard.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey  = $this->credential('api_key');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_API_BASE : self::API_BASE;

        $payload = [
            'price_amount'      => (float) $payment->amount,
            'price_currency'    => strtolower($payment->currency ?? 'usd'),
            'pay_currency'      => 'btc',
            'order_id'          => (string) $payment->id,
            'order_description' => $this->paymentDescription($payment),
            'ipn_callback_url'  => route('payment.webhook', ['gateway' => 'nowpayments']),
            'success_url'       => $callbackUrl . '?status=success',
            'cancel_url'        => $callbackUrl . '?status=cancelled',
        ];

        $response = $this->httpRequest('POST', $baseUrl . '/invoice', $payload, [
            'x-api-key: ' . $apiKey,
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['invoice_url'])) {
            return ['redirect_url' => $json['invoice_url']];
        }

        // Fallback to direct payment creation
        $paymentResponse = $this->httpRequest('POST', $baseUrl . '/payment', $payload, [
            'x-api-key: ' . $apiKey,
        ]);

        $payJson = $paymentResponse['json'] ?? [];

        if (isset($payJson['payment_id'])) {
            $payAddress = $payJson['pay_address'] ?? '';
            $payAmount  = $payJson['pay_amount'] ?? '';
            $payCurrency = strtoupper($payJson['pay_currency'] ?? 'BTC');

            $html = <<<HTML
            <div style="max-width:500px;margin:0 auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px;text-align:center;">
                <h3>Send Crypto Payment</h3>
                <p>Send exactly <strong>{$payAmount} {$payCurrency}</strong> to:</p>
                <code style="display:block;padding:12px;background:#f5f5f5;border-radius:4px;word-break:break-all;margin:16px 0;">{$payAddress}</code>
                <p style="color:#666;font-size:0.9em;">Payment will be confirmed automatically after blockchain verification.</p>
                <a href="{$callbackUrl}?status=pending" class="btn btn-primary" style="display:inline-block;padding:10px 24px;margin-top:16px;">I Have Sent Payment</a>
            </div>
            HTML;

            return ['html' => $html];
        }

        $errorMsg = $json['message'] ?? $payJson['message'] ?? 'Failed to create NOWPayments invoice.';
        throw new \RuntimeException("NOWPayments: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting NOWPayments confirmation.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $ipnSecret = $this->credential('ipn_secret');
        if (! $ipnSecret) {
            return true;
        }

        $signature = $headers['x-nowpayments-sig'] ?? $headers['X-Nowpayments-Sig'] ?? '';
        if (! $signature) {
            return false;
        }

        $sortedData = json_decode($rawBody, true);
        if (! is_array($sortedData)) {
            return false;
        }
        ksort($sortedData);
        $expected = hash_hmac('sha512', json_encode($sortedData), $ipnSecret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $paymentStatus = $payload['payment_status'] ?? '';
        $paymentId     = $payload['payment_id'] ?? '';
        $orderId       = $payload['order_id'] ?? '';

        if (in_array($paymentStatus, ['finished', 'confirmed'])) {
            return PaymentResult::success(
                transactionId: (string) $paymentId,
                message: 'NOWPayments crypto payment confirmed.',
                metadata: [
                    'nowpayments_id'   => $paymentId,
                    'order_id'         => $orderId,
                    'pay_amount'       => $payload['pay_amount'] ?? null,
                    'pay_currency'     => $payload['pay_currency'] ?? null,
                    'actually_paid'    => $payload['actually_paid'] ?? null,
                ],
            );
        }

        if (in_array($paymentStatus, ['waiting', 'confirming', 'sending'])) {
            return PaymentResult::pending("NOWPayments: {$paymentStatus}", (string) $paymentId);
        }

        if (in_array($paymentStatus, ['failed', 'expired', 'refunded'])) {
            return PaymentResult::failure("NOWPayments payment {$paymentStatus}.");
        }

        return PaymentResult::failure("Unhandled NOWPayments status: {$paymentStatus}");
    }
}
