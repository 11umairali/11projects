<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * 2Checkout (Verifone) payment gateway driver.
 *
 * @see https://verifone.cloud/docs/2checkout/API-Integration/
 */
class TwoCheckoutDriver extends AbstractGatewayDriver
{
    private const API_BASE     = 'https://api.2checkout.com/rest/6.0';
    private const CHECKOUT_URL = 'https://secure.2checkout.com/checkout/buy';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_code',   'label' => 'Merchant Code',    'type' => 'text',     'required' => true],
            ['key' => 'secret_key',      'label' => 'Secret Key',       'type' => 'password', 'required' => true],
            ['key' => 'buy_link_secret', 'label' => 'Buy Link Secret',  'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantCode  = $this->credential('merchant_code');
        $buyLinkSecret = $this->credential('buy_link_secret');
        $currency = strtoupper($payment->currency ?? 'USD');
        $amount   = number_format((float) $payment->amount, 2, '.', '');
        $paymentRef = (string) $payment->id;
        $now = gmdate('Y-m-d H:i:s');

        $signParams = [strlen($merchantCode) . $merchantCode, strlen($now) . $now, strlen($amount) . $amount, strlen($currency) . $currency, strlen($paymentRef) . $paymentRef];
        $signString = implode('', $signParams);
        $signature  = hash_hmac('sha256', $signString, $buyLinkSecret);

        $params = [
            'merchant' => $merchantCode, 'dynamic' => 1, 'order-ext-ref' => $paymentRef,
            'item-ext-ref' => 'PAYMENT_' . $paymentRef, 'prod' => $this->paymentDescription($payment),
            'price' => $amount, 'qty' => 1, 'type' => 'PRODUCT', 'currency' => $currency,
            'return-url' => $callbackUrl, 'return-type' => 'redirect',
            'expiration' => gmdate('Y-m-d H:i:s', time() + 3600), 'order-date' => $now, 'signature' => $signature,
        ];

        return ['redirect_url' => self::CHECKOUT_URL . '?' . http_build_query($params)];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $refNo = $payload['refno'] ?? $payload['REFNO'] ?? null;
        if (! $refNo) { return PaymentResult::failure('Missing 2Checkout reference number.'); }

        $secretKey    = $this->credential('secret_key');
        $merchantCode = $this->credential('merchant_code');
        $response = $this->twoCheckoutRequest('GET', "/orders/{$refNo}/", [], $merchantCode, $secretKey);
        $json     = $response['json'] ?? [];
        $status   = $json['Status'] ?? $json['status'] ?? '';

        if (in_array($status, ['COMPLETE', 'AUTHRECEIVED'])) {
            return PaymentResult::success(transactionId: $refNo, message: 'Payment completed.',
                metadata: ['twocheckout_ref' => $refNo, 'twocheckout_status' => $status]);
        }
        if ($status === 'PENDING') { return PaymentResult::pending('2Checkout payment is pending.', $refNo); }
        return PaymentResult::failure("2Checkout order not completed. Status: {$status}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secretKey = $this->credential('secret_key');
        if (! $secretKey) { return true; }
        parse_str($rawBody, $params);
        $receivedHash = $params['HASH'] ?? '';
        if (! $receivedHash) { return true; }
        $ipnParams = $params; unset($ipnParams['HASH']);
        $hashString = '';
        foreach ($ipnParams as $value) { $val = (string) $value; $hashString .= strlen($val) . $val; }
        $expected = hash_hmac('md5', $hashString, $secretKey);
        return hash_equals($expected, $receivedHash);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $ipnType = $payload['ORDERSTATUS'] ?? '';
        $refNo   = $payload['REFNO'] ?? '';
        if (in_array($ipnType, ['COMPLETE', 'AUTHRECEIVED'])) {
            return PaymentResult::success(transactionId: $refNo, message: '2Checkout IPN confirmed payment.');
        }
        return PaymentResult::failure("Unhandled 2Checkout IPN status: {$ipnType}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $refNo = $payment->gateway_transaction_id ?? null;
        if (! $refNo) { return PaymentResult::failure('No transaction ID available.'); }
        return $this->handleCallback(['refno' => $refNo]);
    }

    private function twoCheckoutRequest(string $method, string $path, array $data, string $merchantCode, string $secretKey): array
    {
        $url  = self::API_BASE . $path;
        $now  = gmdate('Y-m-d H:i:s');
        $hash = hash_hmac('md5', strlen($merchantCode) . $merchantCode . strlen($now) . $now, $secretKey);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'X-Avangate-Authentication: code="' . $merchantCode . '" date="' . $now . '" hash="' . $hash . '"'];
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("2Checkout cURL error: {$error}"); }
        return ['body' => $body, 'json' => json_decode($body, true)];
    }
}
