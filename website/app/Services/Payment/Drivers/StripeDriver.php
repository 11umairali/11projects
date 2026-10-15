<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Stripe payment gateway driver.
 *
 * Uses the Stripe REST API directly via cURL -- no SDK required.
 * Creates a Checkout Session and redirects the customer to Stripe-hosted
 * payment page.  On completion, the callback / webhook verifies the
 * session and captures the payment intent ID.
 *
 * @see https://docs.stripe.com/api/checkout/sessions
 */
class StripeDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.stripe.com/v1';

    private const MINIMUM_AMOUNTS = [
        'usd' => 50,   'eur' => 50,   'gbp' => 30,   'inr' => 5000,
        'aud' => 50,   'cad' => 50,   'sgd' => 50,   'hkd' => 400,
        'jpy' => 50,   'mxn' => 1000, 'brl' => 50,   'aed' => 200,
        'myr' => 200,  'nzd' => 50,   'thb' => 2000, 'chf' => 50,
        'dkk' => 250,  'nok' => 300,  'sek' => 300,
    ];

    public static function credentialFields(): array
    {
        return [
            ['key' => 'publishable_key', 'label' => 'Publishable Key', 'type' => 'text',     'required' => true],
            ['key' => 'secret_key',      'label' => 'Secret Key',      'type' => 'password',  'required' => true],
            ['key' => 'webhook_secret',  'label' => 'Webhook Secret',  'type' => 'password',  'required' => false,
             'hint' => 'Optional. Only needed if you set up webhooks in Stripe Dashboard.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $secretKey = $this->credential('secret_key');
        $currency  = strtolower($payment->currency ?? 'usd');
        $amount    = $this->amountInSmallestUnit($payment);
        $minimum   = self::MINIMUM_AMOUNTS[$currency] ?? 50;

        if ($amount < $minimum) {
            $humanMin = number_format($minimum / 100, 2);
            throw new \RuntimeException(
                "The payment amount is below Stripe's minimum chargeable amount for " .
                strtoupper($currency) . " (minimum: {$humanMin} " . strtoupper($currency) . ")."
            );
        }

        $sessionData = [
            'payment_method_types[]' => 'card',
            'mode'                   => 'payment',
            'success_url'            => $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'             => $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'cancelled=1',
            'client_reference_id'    => $payment->id,
            'line_items[0][price_data][currency]'     => $currency,
            'line_items[0][price_data][unit_amount]'   => $amount,
            'line_items[0][price_data][product_data][name]' => $this->paymentDescription($payment),
            'line_items[0][quantity]' => 1,
            'metadata[payment_id]'   => $payment->id,
        ];

        $response = $this->stripeRequest('POST', '/checkout/sessions', $sessionData, $secretKey);

        if (isset($response['json']['url'])) {
            return ['redirect_url' => $response['json']['url']];
        }

        $errorMsg = $response['json']['error']['message'] ?? 'Failed to create Stripe checkout session.';
        throw new \RuntimeException("Stripe: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $sessionId = $payload['session_id'] ?? null;

        if (isset($payload['cancelled'])) {
            return PaymentResult::failure('Payment was cancelled by the customer.');
        }

        if (! $sessionId) {
            return PaymentResult::failure('Missing Stripe session ID in callback.');
        }

        $secretKey = $this->credential('secret_key');
        $response  = $this->stripeRequest('GET', "/checkout/sessions/{$sessionId}", [], $secretKey);
        $session   = $response['json'] ?? [];

        if (($session['payment_status'] ?? '') === 'paid') {
            return PaymentResult::success(
                transactionId: $session['payment_intent'] ?? $sessionId,
                message: 'Payment completed successfully.',
                metadata: [
                    'payment_id'               => $session['metadata']['payment_id'] ?? $session['client_reference_id'] ?? null,
                    'stripe_session_id'        => $sessionId,
                    'stripe_payment_intent'    => $session['payment_intent'] ?? null,
                    'stripe_customer'          => $session['customer'] ?? null,
                ],
            );
        }

        return PaymentResult::failure(
            'Payment not completed. Status: ' . ($session['payment_status'] ?? 'unknown'),
        );
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookSecret = $this->credential('webhook_secret');
        if (! $webhookSecret) {
            \Illuminate\Support\Facades\Log::warning('Stripe webhook secret not configured -- skipping signature verification.');
            return true;
        }

        $sigHeader = $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? '';
        if (! $sigHeader) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $sigHeader) as $item) {
            [$key, $value] = explode('=', $item, 2) + [1 => ''];
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if (! $timestamp || ! $signature) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signedPayload, $webhookSecret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $type = $payload['type'] ?? '';
        $data = $payload['data']['object'] ?? [];

        if ($type === 'checkout.session.completed' && ($data['payment_status'] ?? '') === 'paid') {
            return PaymentResult::success(
                transactionId: $data['payment_intent'] ?? $data['id'],
                message: 'Webhook confirmed payment.',
                metadata: [
                    'payment_id'            => $data['metadata']['payment_id'] ?? $data['client_reference_id'] ?? null,
                    'stripe_event_type'     => $type,
                    'stripe_session_id'     => $data['id'] ?? null,
                    'stripe_payment_intent' => $data['payment_intent'] ?? null,
                ],
            );
        }

        if ($type === 'payment_intent.succeeded') {
            return PaymentResult::success(
                transactionId: $data['id'],
                message: 'Payment intent succeeded.',
                metadata: ['stripe_event_type' => $type],
            );
        }

        return PaymentResult::failure("Unhandled Stripe webhook event: {$type}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $transactionId = $payment->gateway_transaction_id ?? null;
        if (! $transactionId) {
            return PaymentResult::failure('No transaction ID available for verification.');
        }

        $secretKey = $this->credential('secret_key');
        $response = $this->stripeRequest('GET', "/payment_intents/{$transactionId}", [], $secretKey);
        $intent   = $response['json'] ?? [];

        if (($intent['status'] ?? '') === 'succeeded') {
            return PaymentResult::success(
                transactionId: $intent['id'],
                message: 'Payment verified successfully.',
                metadata: [
                    'amount_received' => $intent['amount_received'] ?? null,
                    'currency'        => $intent['currency'] ?? null,
                ],
            );
        }

        return PaymentResult::failure('Payment not verified. Status: ' . ($intent['status'] ?? 'unknown'));
    }

    private function stripeRequest(string $method, string $path, array $data, string $secretKey): array
    {
        $url = self::API_BASE . $path;
        $ch  = curl_init();

        $headers = [
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'GET' && $data) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        }

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("Stripe cURL error: {$error}");
        }

        return [
            'status' => $status,
            'body'   => $body,
            'json'   => json_decode($body, true),
        ];
    }
}
