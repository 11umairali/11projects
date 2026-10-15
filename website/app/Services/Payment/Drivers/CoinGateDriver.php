<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * CoinGate payment gateway driver (Crypto).
 *
 * @see https://developer.coingate.com/reference/create-order
 */
class CoinGateDriver extends AbstractGatewayDriver
{
    private const API_BASE     = 'https://api.coingate.com/api/v2';
    private const SANDBOX_BASE = 'https://api-sandbox.coingate.com/api/v2';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiToken = $this->credential('api_token');
        $baseUrl  = $this->isSandbox() ? self::SANDBOX_BASE : self::API_BASE;

        $payload = [
            'order_id'         => (string) $payment->id,
            'price_amount'     => number_format((float) $payment->amount, 2, '.', ''),
            'price_currency'   => strtoupper($payment->currency ?? 'USD'),
            'receive_currency' => strtoupper($payment->currency ?? 'USD'),
            'title'            => $this->paymentDescription($payment),
            'description'      => config('app.name') . " Payment #{$payment->id}",
            'callback_url'     => route('payment.webhook', ['gateway' => 'coingate']),
            'success_url'      => $callbackUrl . '?status=success',
            'cancel_url'       => $callbackUrl . '?status=cancelled',
            'token'            => $apiToken,
        ];

        $response = $this->httpRequest('POST', $baseUrl . '/orders', $payload, [
            'Authorization: Token ' . $apiToken,
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['payment_url'])) {
            return ['redirect_url' => $json['payment_url']];
        }

        $errorMsg = $json['message'] ?? $json['reason'] ?? 'Failed to create CoinGate order.';
        throw new \RuntimeException("CoinGate: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $status = $payload['status'] ?? '';
        if ($status === 'cancelled') {
            return PaymentResult::failure('Payment was cancelled.');
        }
        return PaymentResult::pending('Awaiting CoinGate payment confirmation.');
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $status  = $payload['status'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $cgId    = $payload['id'] ?? '';

        if ($status === 'paid') {
            return PaymentResult::success(
                transactionId: (string) ($cgId ?: $orderId),
                message: 'CoinGate crypto payment confirmed.',
                metadata: [
                    'coingate_id'       => $cgId,
                    'order_id'          => $orderId,
                    'pay_amount'        => $payload['pay_amount'] ?? null,
                    'pay_currency'      => $payload['pay_currency'] ?? null,
                    'receive_amount'    => $payload['receive_amount'] ?? null,
                    'receive_currency'  => $payload['receive_currency'] ?? null,
                ],
            );
        }

        if ($status === 'pending' || $status === 'confirming') {
            return PaymentResult::pending("CoinGate: {$status}", (string) $cgId);
        }

        if (in_array($status, ['expired', 'invalid', 'canceled'])) {
            return PaymentResult::failure("CoinGate payment {$status}.");
        }

        return PaymentResult::failure("Unhandled CoinGate status: {$status}");
    }
}
