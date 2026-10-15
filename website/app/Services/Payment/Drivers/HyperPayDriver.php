<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * HyperPay (ACI Worldwide) payment gateway driver (Middle East / Africa).
 * @see https://wordpresshyperpay.docs.oppwa.com/
 */
class HyperPayDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://eu-test.oppwa.com';
    private const PROD_BASE    = 'https://eu-prod.oppwa.com';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'access_token', 'label' => 'Access Token', 'type' => 'password', 'required' => true],
            ['key' => 'entity_id',    'label' => 'Entity ID',    'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $accessToken = $this->credential('access_token');
        $entityId = $this->credential('entity_id');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $payload = [
            'entityId' => $entityId, 'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => strtoupper($payment->currency ?? 'SAR'), 'paymentType' => 'DB',
            'merchantTransactionId' => (string) $payment->id,
        ];
        $response = $this->httpFormPost($baseUrl . '/v1/checkouts', $payload, ['Authorization: Bearer ' . $accessToken]);
        $json = $response['json'] ?? [];
        $checkoutId = $json['id'] ?? null;
        if (! $checkoutId) { throw new \RuntimeException("HyperPay: " . ($json['result']['description'] ?? 'Failed to create checkout.')); }
        $eScriptUrl = htmlspecialchars($baseUrl . "/v1/paymentWidgets.js?checkoutId={$checkoutId}", ENT_QUOTES, 'UTF-8');
        $eCallbackUrl = htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8');
        $html = "<script src=\"{$eScriptUrl}\"></script><form action=\"{$eCallbackUrl}\" class=\"paymentWidgets\" data-brands=\"VISA MASTER AMEX MADA\"></form>";
        return ['html' => $html];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $resourcePath = $payload['resourcePath'] ?? $payload['id'] ?? null;
        if (! $resourcePath) { return PaymentResult::failure('Missing HyperPay resource path.'); }
        $accessToken = $this->credential('access_token');
        $entityId = $this->credential('entity_id');
        $baseUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        $statusUrl = $baseUrl . $resourcePath . '?entityId=' . $entityId;
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $statusUrl, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("HyperPay cURL error: {$error}"); }
        $json = json_decode($body, true);
        $resultCode = $json['result']['code'] ?? '';
        if (preg_match('/^(000\.000\.|000\.100\.1)/', $resultCode)) {
            return PaymentResult::success(transactionId: $json['id'] ?? '', message: 'Payment successful.',
                metadata: ['hyperpay_id' => $json['id'] ?? null, 'hyperpay_result_code' => $resultCode, 'payment_brand' => $json['paymentBrand'] ?? null]);
        }
        if (preg_match('/^000\.[36]/', $resultCode)) { return PaymentResult::pending('HyperPay payment pending review. Code: ' . $resultCode, $json['id'] ?? ''); }
        return PaymentResult::failure("HyperPay: " . ($json['result']['description'] ?? "Result code: {$resultCode}"));
    }
}
