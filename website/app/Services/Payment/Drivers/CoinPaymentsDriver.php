<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * CoinPayments payment gateway driver (Crypto).
 *
 * Uses the CoinPayments API to create a transaction for crypto payments.
 * Supports 2000+ cryptocurrencies.
 *
 * @see https://www.coinpayments.net/apidoc
 */
class CoinPaymentsDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://www.coinpayments.net/api.php';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id',  'label' => 'Merchant ID',  'type' => 'text',     'required' => true],
            ['key' => 'public_key',   'label' => 'Public Key',   'type' => 'text',     'required' => true],
            ['key' => 'private_key',  'label' => 'Private Key',  'type' => 'password', 'required' => true],
            ['key' => 'ipn_secret',   'label' => 'IPN Secret',   'type' => 'password', 'required' => false,
             'hint' => 'Used for webhook signature verification. Set in CoinPayments IPN settings.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $publicKey  = $this->credential('public_key');
        $privateKey = $this->credential('private_key');

        $fields = [
            'version'    => 1,
            'cmd'        => 'create_transaction',
            'key'        => $publicKey,
            'amount'     => number_format((float) $payment->amount, 8, '.', ''),
            'currency1'  => strtoupper($payment->currency ?? 'USD'),
            'currency2'  => 'BTC',
            'buyer_email' => $payment->workspace->owner()?->email ?? 'customer@example.com',
            'buyer_name'  => $payment->workspace->owner()?->name ?? 'Customer',
            'item_name'   => $this->paymentDescription($payment),
            'item_number' => (string) $payment->id,
            'ipn_url'     => route('payment.webhook', ['gateway' => 'coinpayments']),
            'success_url' => $callbackUrl . '?status=success',
            'cancel_url'  => $callbackUrl . '?status=cancelled',
            'custom'      => json_encode(['payment_id' => $payment->id]),
        ];

        $postData = http_build_query($fields, '', '&');
        $hmac     = hash_hmac('sha512', $postData, $privateKey);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::API_BASE,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'HMAC: ' . $hmac,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("CoinPayments cURL error: {$error}");
        }

        $json = json_decode($body, true);

        if (($json['error'] ?? '') === 'ok' && isset($json['result']['checkout_url'])) {
            return ['redirect_url' => $json['result']['checkout_url']];
        }

        if (isset($json['result']['status_url'])) {
            return ['redirect_url' => $json['result']['status_url']];
        }

        $errorMsg = $json['error'] ?? 'Failed to create CoinPayments transaction.';
        throw new \RuntimeException("CoinPayments: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting CoinPayments IPN confirmation.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $ipnSecret = $this->credential('ipn_secret');
        if (! $ipnSecret) {
            return true;
        }

        $hmac = $headers['hmac'] ?? $headers['HMAC'] ?? '';
        if (! $hmac) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $ipnSecret);
        return hash_equals($expected, $hmac);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status     = (int) ($payload['status'] ?? -1);
        $statusText = $payload['status_text'] ?? '';
        $txnId      = $payload['txn_id'] ?? '';
        $custom     = json_decode($payload['custom'] ?? '{}', true);

        // CoinPayments IPN status: >= 100 means complete, 0 = waiting, < 0 = error/cancelled
        if ($status >= 100) {
            return PaymentResult::success(
                transactionId: $txnId,
                message: 'CoinPayments crypto payment confirmed.',
                metadata: [
                    'coinpayments_txn_id' => $txnId,
                    'status_text'         => $statusText,
                    'amount'              => $payload['amount1'] ?? null,
                    'currency'            => $payload['currency1'] ?? null,
                    'received_amount'     => $payload['amount2'] ?? null,
                    'received_currency'   => $payload['currency2'] ?? null,
                ],
            );
        }

        if ($status >= 0) {
            return PaymentResult::pending("CoinPayments: {$statusText}", $txnId);
        }

        return PaymentResult::failure("CoinPayments payment failed: {$statusText}");
    }
}
