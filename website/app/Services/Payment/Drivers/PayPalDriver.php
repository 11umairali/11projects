<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * PayPal payment gateway driver.
 *
 * Uses the PayPal REST API v2 (Orders API) via cURL.
 * Creates an order, redirects to PayPal for approval, then captures on callback.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/
 */
class PayPalDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://api-m.sandbox.paypal.com';
    private const LIVE_BASE    = 'https://api-m.paypal.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'required' => true],
            ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password', 'required' => true],
            ['key' => 'webhook_id',    'label' => 'Webhook ID',    'type' => 'text',     'required' => false,
             'hint' => 'Found in PayPal Developer Dashboard under Webhooks. Used for signature verification.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $accessToken = $this->getAccessToken();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) $payment->id,
                    'description'  => $this->paymentDescription($payment),
                    'amount' => [
                        'currency_code' => strtoupper($payment->currency ?? 'USD'),
                        'value'         => number_format((float) $payment->amount, 2, '.', ''),
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'return_url'                 => $callbackUrl . '?success=1',
                        'cancel_url'                 => $callbackUrl . '?cancelled=1',
                        'brand_name'                 => config('app.name'),
                        'user_action'                => 'PAY_NOW',
                        'payment_method_preference'  => 'IMMEDIATE_PAYMENT_REQUIRED',
                    ],
                ],
            ],
        ];

        $response = $this->httpRequest('POST', $this->baseUrl() . '/v2/checkout/orders', $payload, [
            'Authorization: Bearer ' . $accessToken,
        ]);

        $json = $response['json'] ?? [];

        if (isset($json['id'])) {
            $approveUrl = collect($json['links'] ?? [])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            if ($approveUrl) {
                return ['redirect_url' => $approveUrl];
            }
        }

        $errorMsg = $json['message'] ?? 'Failed to create PayPal order.';
        throw new \RuntimeException("PayPal: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        if (isset($payload['cancelled'])) {
            return PaymentResult::failure('Payment was cancelled by the customer.');
        }

        $paypalOrderId = $payload['token'] ?? $payload['order_id'] ?? null;
        if (! $paypalOrderId) {
            return PaymentResult::failure('Missing PayPal order ID in callback.');
        }

        $accessToken = $this->getAccessToken();
        $response    = $this->httpRequest(
            'POST',
            $this->baseUrl() . "/v2/checkout/orders/{$paypalOrderId}/capture",
            [],
            ['Authorization: Bearer ' . $accessToken],
        );

        $json   = $response['json'] ?? [];
        $status = $json['status'] ?? '';

        if ($status === 'COMPLETED') {
            $capture       = $json['purchase_units'][0]['payments']['captures'][0] ?? [];
            $transactionId = $capture['id'] ?? $paypalOrderId;

            return PaymentResult::success(
                transactionId: $transactionId,
                message: 'Payment captured successfully.',
                metadata: [
                    'paypal_order_id'   => $paypalOrderId,
                    'paypal_capture_id' => $transactionId,
                    'payer_email'       => $json['payer']['email_address'] ?? null,
                ],
            );
        }

        return PaymentResult::failure("PayPal order not completed. Status: {$status}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookId = $this->credential('webhook_id');
        if (! $webhookId) {
            // If no webhook_id configured, fall back to verifying the event
            // by fetching it from PayPal API (less efficient but secure)
            return $this->verifyWebhookViaApi($rawBody, $headers);
        }

        // PayPal webhook signature verification via API
        // @see https://developer.paypal.com/api/rest/webhooks/#link-verifywebhooksignature
        $transmissionId   = $headers['paypal-transmission-id'] ?? $headers['PAYPAL-TRANSMISSION-ID'] ?? '';
        $transmissionTime = $headers['paypal-transmission-time'] ?? $headers['PAYPAL-TRANSMISSION-TIME'] ?? '';
        $transmissionSig  = $headers['paypal-transmission-sig'] ?? $headers['PAYPAL-TRANSMISSION-SIG'] ?? '';
        $certUrl          = $headers['paypal-cert-url'] ?? $headers['PAYPAL-CERT-URL'] ?? '';
        $authAlgo         = $headers['paypal-auth-algo'] ?? $headers['PAYPAL-AUTH-ALGO'] ?? 'SHA256withRSA';

        if (! $transmissionId || ! $transmissionTime || ! $transmissionSig || ! $certUrl) {
            \Illuminate\Support\Facades\Log::warning('PayPal webhook missing signature headers');
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();

            $verifyPayload = [
                'auth_algo'         => $authAlgo,
                'cert_url'          => $certUrl,
                'transmission_id'   => $transmissionId,
                'transmission_sig'  => $transmissionSig,
                'transmission_time' => $transmissionTime,
                'webhook_id'        => $webhookId,
                'webhook_event'     => json_decode($rawBody, true),
            ];

            $response = $this->httpRequest(
                'POST',
                $this->baseUrl() . '/v1/notifications/verify-webhook-signature',
                $verifyPayload,
                ['Authorization: Bearer ' . $accessToken],
            );

            $json = $response['json'] ?? [];
            $verificationStatus = $json['verification_status'] ?? '';

            return $verificationStatus === 'SUCCESS';
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayPal webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Fallback: verify a webhook event by fetching it from PayPal API.
     * If the event exists and matches, it's legitimate.
     */
    private function verifyWebhookViaApi(string $rawBody, array $headers): bool
    {
        $payload = json_decode($rawBody, true);
        $eventId = $payload['id'] ?? '';

        if (! $eventId) {
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();
            $response = $this->httpRequest(
                'GET',
                $this->baseUrl() . "/v1/notifications/webhooks-events/{$eventId}",
                [],
                ['Authorization: Bearer ' . $accessToken],
            );

            $json = $response['json'] ?? [];

            // If PayPal returns the event, it's genuine
            return isset($json['id']) && $json['id'] === $eventId;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PayPal webhook event verification failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $eventType = $payload['event_type'] ?? '';
        $resource  = $payload['resource'] ?? [];

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $captureId = $resource['id'] ?? '';

            if ($captureId) {
                $accessToken = $this->getAccessToken();
                $response = $this->httpRequest(
                    'GET',
                    $this->baseUrl() . "/v2/payments/captures/{$captureId}",
                    [],
                    ['Authorization: Bearer ' . $accessToken],
                );

                $json   = $response['json'] ?? [];
                $status = $json['status'] ?? '';

                if ($status === 'COMPLETED') {
                    return PaymentResult::success(
                        transactionId: $captureId,
                        message: 'Webhook confirmed and verified payment capture.',
                        metadata: [
                            'paypal_event_type' => $eventType,
                            'amount'            => $json['amount']['value'] ?? null,
                            'currency'          => $json['amount']['currency_code'] ?? null,
                        ],
                    );
                }

                return PaymentResult::failure("PayPal capture status: {$status}");
            }
        }

        return PaymentResult::failure("Unhandled PayPal webhook event: {$eventType}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $transactionId = $payment->gateway_transaction_id ?? null;
        if (! $transactionId) {
            return PaymentResult::failure('No transaction ID available for verification.');
        }

        $accessToken = $this->getAccessToken();
        $response = $this->httpRequest(
            'GET',
            $this->baseUrl() . "/v2/payments/captures/{$transactionId}",
            [],
            ['Authorization: Bearer ' . $accessToken],
        );

        $json   = $response['json'] ?? [];
        $status = $json['status'] ?? '';

        if ($status === 'COMPLETED') {
            return PaymentResult::success(
                transactionId: $transactionId,
                message: 'Payment verified successfully.',
            );
        }

        return PaymentResult::failure("Payment not verified. Status: {$status}");
    }

    private function baseUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_BASE : self::LIVE_BASE;
    }

    private function getAccessToken(): string
    {
        $clientId     = $this->credential('client_id');
        $clientSecret = $this->credential('client_secret');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl() . '/v1/oauth2/token',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERPWD        => $clientId . ':' . $clientSecret,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body  = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("PayPal token request failed: {$error}");
        }

        $json = json_decode($body, true);

        if (isset($json['access_token'])) {
            return $json['access_token'];
        }

        throw new \RuntimeException('PayPal: Unable to obtain access token.');
    }
}
