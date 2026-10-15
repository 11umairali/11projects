<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Authorize.Net payment gateway driver.
 * @see https://developer.authorize.net/api/reference/
 */
class AuthorizeNetDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://apitest.authorize.net/xml/v1/request.api';
    private const PROD_BASE    = 'https://api.authorize.net/xml/v1/request.api';
    private const SANDBOX_HOSTED = 'https://test.authorize.net/payment/payment';
    private const PROD_HOSTED    = 'https://accept.authorize.net/payment/payment';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'api_login_id',   'label' => 'API Login ID',   'type' => 'text',     'required' => true],
            ['key' => 'transaction_key', 'label' => 'Transaction Key', 'type' => 'password', 'required' => true],
            ['key' => 'signature_key',   'label' => 'Signature Key',   'type' => 'password', 'required' => false],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $apiLoginId = $this->credential('api_login_id');
        $transactionKey = $this->credential('transaction_key');
        $apiUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;

        $payload = ['getHostedPaymentPageRequest' => [
            'merchantAuthentication' => ['name' => $apiLoginId, 'transactionKey' => $transactionKey],
            'transactionRequest' => ['transactionType' => 'authCaptureTransaction', 'amount' => number_format((float) $payment->amount, 2, '.', ''), 'order' => ['invoiceNumber' => (string) $payment->id, 'description' => $this->paymentDescription($payment)]],
            'hostedPaymentSettings' => ['setting' => [
                ['settingName' => 'hostedPaymentReturnOptions', 'settingValue' => json_encode(['showReceipt' => false, 'url' => $callbackUrl, 'urlText' => 'Return', 'cancelUrl' => $callbackUrl . '?cancelled=1'])],
                ['settingName' => 'hostedPaymentButtonOptions', 'settingValue' => json_encode(['text' => 'Pay Now'])],
            ]],
        ]];

        $response = $this->authorizeNetRequest($apiUrl, $payload);
        $json = $response['json'] ?? [];
        $token = $json['token'] ?? null;

        if ($token) {
            $hostedUrl = $this->isSandbox() ? self::SANDBOX_HOSTED : self::PROD_HOSTED;
            $eHostedUrl = htmlspecialchars($hostedUrl, ENT_QUOTES, 'UTF-8');
            $eToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
            $html = "<form id=\"authnet-form\" method=\"POST\" action=\"{$eHostedUrl}\"><input type=\"hidden\" name=\"token\" value=\"{$eToken}\" /></form><script>document.getElementById('authnet-form').submit();</script>";
            return ['html' => $html];
        }
        throw new \RuntimeException("Authorize.Net: " . ($json['messages']['message'][0]['text'] ?? 'Failed to create payment page.'));
    }

    public function handleCallback(array $payload): PaymentResult
    {
        if (isset($payload['cancelled'])) { return PaymentResult::failure('Payment was cancelled.'); }
        $transId = $payload['transId'] ?? $payload['x_trans_id'] ?? null;
        $response = $payload['x_response_code'] ?? $payload['responseCode'] ?? null;
        if ($transId && (string) $response === '1') {
            $verifyResult = $this->getTransactionDetails($transId);
            if ($verifyResult !== null) { return $verifyResult; }
            return PaymentResult::success(transactionId: $transId, message: 'Payment completed.');
        }
        if ($transId) { return PaymentResult::pending('Authorize.Net payment received, verifying...', $transId); }
        return PaymentResult::failure('Authorize.Net payment not completed.');
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $signatureKey = $this->credential('signature_key');
        if (! $signatureKey) { return true; }
        $signature = $headers['x-anet-signature'] ?? $headers['X-ANET-Signature'] ?? '';
        if (! $signature) { return false; }
        $signature = str_ireplace('sha512=', '', $signature);
        $expected = strtoupper(hash_hmac('sha512', $rawBody, hex2bin($signatureKey)));
        return hash_equals($expected, strtoupper($signature));
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $eventType = $payload['eventType'] ?? '';
        $webhookId = $payload['payload']['id'] ?? '';
        if (in_array($eventType, ['net.authorize.payment.authcapture.created', 'net.authorize.payment.capture.created'])) {
            $result = $this->getTransactionDetails($webhookId);
            if ($result !== null) { return $result; }
            return PaymentResult::success(transactionId: $webhookId, message: "Authorize.Net webhook: {$eventType}");
        }
        return PaymentResult::failure("Unhandled Authorize.Net webhook event: {$eventType}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $transactionId = $payment->gateway_transaction_id ?? null;
        if (! $transactionId) { return PaymentResult::failure('No transaction ID available.'); }
        $result = $this->getTransactionDetails($transactionId);
        return $result ?? PaymentResult::failure('Unable to verify transaction.');
    }

    private function getTransactionDetails(string $transactionId): ?PaymentResult
    {
        $apiLoginId = $this->credential('api_login_id');
        $transactionKey = $this->credential('transaction_key');
        $apiUrl = $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE;
        try {
            $response = $this->authorizeNetRequest($apiUrl, ['getTransactionDetailsRequest' => ['merchantAuthentication' => ['name' => $apiLoginId, 'transactionKey' => $transactionKey], 'transId' => $transactionId]]);
            $json = $response['json'] ?? [];
            if (($json['messages']['resultCode'] ?? '') !== 'Ok') { return null; }
            $txn = $json['transaction'] ?? [];
            $status = $txn['transactionStatus'] ?? '';
            if (in_array($status, ['settledSuccessfully', 'capturedPendingSettlement', 'authorizedPendingCapture'])) { return PaymentResult::success(transactionId: $transactionId, message: "Payment verified. Status: {$status}."); }
            if (in_array($status, ['voided', 'declined', 'expired'])) { return PaymentResult::failure("Authorize.Net transaction {$status}."); }
            if ($status) { return PaymentResult::pending("Authorize.Net transaction status: {$status}", $transactionId); }
        } catch (\Exception $e) { return null; }
        return null;
    }

    private function authorizeNetRequest(string $url, array $data): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("Authorize.Net cURL error: {$error}"); }
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
        return ['body' => $body, 'json' => json_decode($body, true)];
    }
}
