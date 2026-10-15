<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Razorpay payment gateway driver.
 *
 * Uses the Razorpay Orders API via cURL. Creates an order server-side,
 * returns an HTML form that opens the Razorpay Checkout modal on the
 * client, then verifies the payment signature on callback.
 *
 * @see https://razorpay.com/docs/api/orders/
 */
class RazorpayDriver extends AbstractGatewayDriver
{
    private const API_BASE = 'https://api.razorpay.com/v1';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'key_id',         'label' => 'Key ID',         'type' => 'text',     'required' => true],
            ['key' => 'key_secret',     'label' => 'Key Secret',     'type' => 'password', 'required' => true],
            ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false,
             'hint' => 'Optional. Only needed if you set up webhooks in Razorpay Dashboard.'],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $keyId     = $this->credential('key_id');
        $keySecret = $this->credential('key_secret');

        $payload = [
            'amount'   => $this->amountInSmallestUnit($payment),
            'currency' => strtoupper($payment->currency ?? 'INR'),
            'receipt'  => (string) $payment->id,
            'notes'    => [
                'payment_id' => $payment->id,
            ],
        ];

        $response = $this->razorpayRequest('POST', '/orders', $payload, $keyId, $keySecret);
        $json     = $response['json'] ?? [];

        if (! isset($json['id'])) {
            $errorMsg = $json['error']['description'] ?? 'Failed to create Razorpay order.';
            throw new \RuntimeException("Razorpay: {$errorMsg}");
        }

        $razorpayOrderId = $json['id'];
        $appName         = config('app.name');

        $jsKeyId       = json_encode($keyId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsAmount      = json_encode($this->amountInSmallestUnit($payment));
        $jsCurrency    = json_encode($payload['currency'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsAppName     = json_encode($appName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsDescription = json_encode("Payment #{$payment->id}", JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsOrderId     = json_encode($razorpayOrderId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsCallbackUrl = json_encode($callbackUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsCsrfToken   = json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html = <<<HTML
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            var options = {
                key: {$jsKeyId},
                amount: {$jsAmount},
                currency: {$jsCurrency},
                name: {$jsAppName},
                description: {$jsDescription},
                order_id: {$jsOrderId},
                handler: function(response) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = {$jsCallbackUrl};
                    var fields = {
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature,
                        _token: {$jsCsrfToken}
                    };
                    for (var key in fields) {
                        var input = document.createElement('input');
                        input.type = 'hidden'; input.name = key; input.value = fields[key];
                        form.appendChild(input);
                    }
                    document.body.appendChild(form);
                    form.submit();
                },
                modal: {
                    ondismiss: function() {
                        window.location.href = {$jsCallbackUrl} + '?cancelled=1';
                    }
                }
            };
            var rzp = new Razorpay(options);
            rzp.open();
        </script>
        HTML;

        return ['html' => $html];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        if (isset($payload['cancelled'])) {
            return PaymentResult::failure('Payment was cancelled by the customer.');
        }

        $paymentId = $payload['razorpay_payment_id'] ?? null;
        $orderId   = $payload['razorpay_order_id'] ?? null;
        $signature = $payload['razorpay_signature'] ?? null;

        if (! $paymentId || ! $orderId || ! $signature) {
            return PaymentResult::failure('Missing Razorpay payment parameters.');
        }

        $keySecret         = $this->credential('key_secret');
        $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);

        if (! hash_equals($expectedSignature, $signature)) {
            return PaymentResult::failure('Invalid Razorpay payment signature.');
        }

        return PaymentResult::success(
            transactionId: $paymentId,
            message: 'Payment verified successfully.',
            metadata: [
                'razorpay_payment_id' => $paymentId,
                'razorpay_order_id'   => $orderId,
            ],
        );
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $webhookSecret = $this->credential('webhook_secret');
        if (! $webhookSecret) {
            return true;
        }

        $signature = $headers['x-razorpay-signature'] ?? $headers['X-Razorpay-Signature'] ?? '';
        if (! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $webhookSecret);
        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $event  = $payload['event'] ?? '';
        $entity = $payload['payload']['payment']['entity'] ?? [];

        if ($event === 'payment.captured') {
            return PaymentResult::success(
                transactionId: $entity['id'] ?? '',
                message: 'Webhook confirmed payment capture.',
                metadata: [
                    'razorpay_event'    => $event,
                    'razorpay_order_id' => $entity['order_id'] ?? null,
                    'method'            => $entity['method'] ?? null,
                ],
            );
        }

        if ($event === 'payment.failed') {
            $reason = $entity['error_description'] ?? 'Payment failed';
            return PaymentResult::failure("Razorpay payment failed: {$reason}");
        }

        return PaymentResult::failure("Unhandled Razorpay webhook event: {$event}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $paymentId = $payment->gateway_transaction_id ?? null;
        if (! $paymentId) {
            return PaymentResult::failure('No transaction ID available for verification.');
        }

        $keyId     = $this->credential('key_id');
        $keySecret = $this->credential('key_secret');

        $response = $this->razorpayRequest('GET', "/payments/{$paymentId}", [], $keyId, $keySecret);
        $json     = $response['json'] ?? [];
        $status   = $json['status'] ?? '';

        if ($status === 'captured') {
            return PaymentResult::success(
                transactionId: $paymentId,
                message: 'Payment verified -- captured.',
                metadata: ['amount' => $json['amount'] ?? null, 'currency' => $json['currency'] ?? null],
            );
        }

        return PaymentResult::failure("Payment not verified. Status: {$status}");
    }

    private function razorpayRequest(string $method, string $path, array $data, string $keyId, string $keySecret): array
    {
        $url = self::API_BASE . $path;
        $ch  = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("Razorpay cURL error: {$error}");
        }

        return ['status' => $status, 'body' => $body, 'json' => json_decode($body, true)];
    }
}
