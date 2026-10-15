<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Blockonomics payment gateway driver (Bitcoin).
 *
 * Generates a unique BTC address per payment and monitors
 * for incoming transactions via callback.
 *
 * @see https://www.blockonomics.co/views/api.html
 */
class BlockonomicsDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://www.blockonomics.co/api';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'required' => true,
             'hint' => 'Get your API key from Blockonomics Dashboard > Stores > API Key.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiKey = $this->credential('api_key');

        // Generate a new BTC address for this payment
        $response = $this->httpRequest('POST', self::API_BASE . '/new_address', [], [
            'Authorization: Bearer ' . $apiKey,
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['address'])) {
            $btcAddress = $json['address'];

            // Get the current BTC price
            $priceResponse = $this->httpRequest('GET', self::API_BASE . '/price?currency=' . strtoupper($payment->currency ?? 'USD'));
            $priceJson     = $priceResponse['json'] ?? [];
            $btcPrice      = $priceJson['price'] ?? 0;

            $btcAmount = $btcPrice > 0 ? number_format($payment->amount / $btcPrice, 8, '.', '') : '0.00000000';
            $fiatAmount = number_format((float) $payment->amount, 2);
            $currency   = strtoupper($payment->currency ?? 'USD');

            $escapedAddress = htmlspecialchars($btcAddress, ENT_QUOTES, 'UTF-8');
            $escapedCallbackUrl = htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8');

            $html = <<<HTML
            <div style="max-width:500px;margin:0 auto;padding:24px;border:1px solid #e0e0e0;border-radius:8px;text-align:center;">
                <h3>Bitcoin Payment</h3>
                <p>Amount: <strong>{$currency} {$fiatAmount}</strong> (~{$btcAmount} BTC)</p>
                <p>Send Bitcoin to this address:</p>
                <code style="display:block;padding:12px;background:#f5f5f5;border-radius:4px;word-break:break-all;margin:16px 0;font-size:14px;">{$escapedAddress}</code>
                <p style="color:#666;font-size:0.9em;">Payment will be detected automatically. Please allow a few minutes for blockchain confirmation.</p>
                <a href="{$escapedCallbackUrl}?status=pending&address={$escapedAddress}" class="btn btn-primary" style="display:inline-block;padding:10px 24px;margin-top:16px;text-decoration:none;background:#4F46E5;color:white;border-radius:6px;">I Have Sent Payment</a>
            </div>
            HTML;

            return ['html' => $html];
        }

        $errorMsg = $json['message'] ?? 'Failed to generate Bitcoin address.';
        throw new \RuntimeException("Blockonomics: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting Bitcoin payment confirmation.');
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status  = (int) ($payload['status'] ?? -1);
        $address = $payload['addr'] ?? '';
        $value   = $payload['value'] ?? 0;
        $txid    = $payload['txid'] ?? '';

        // Blockonomics status: 0 = unconfirmed, 1 = partially confirmed, 2 = confirmed
        if ($status >= 2) {
            $btcAmount = $value / 100000000; // Convert satoshis to BTC
            return PaymentResult::success(
                transactionId: $txid ?: $address,
                message: 'Bitcoin payment confirmed.',
                metadata: [
                    'btc_address' => $address,
                    'btc_amount'  => $btcAmount,
                    'txid'        => $txid,
                    'confirmations' => $status,
                ],
            );
        }

        if ($status >= 0) {
            return PaymentResult::pending('Bitcoin payment detected, awaiting confirmations.', $txid ?: $address);
        }

        return PaymentResult::failure('Blockonomics: no valid payment detected.');
    }
}
