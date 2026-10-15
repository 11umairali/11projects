<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Paytm payment gateway driver.
 *
 * @see https://business.paytm.com/docs/api/initiate-transaction-api/
 */
class PaytmDriver extends AbstractGatewayDriver
{
    private const STAGING_BASE = 'https://securegw-stage.paytm.in';
    private const PROD_BASE    = 'https://securegw.paytm.in';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id',  'label' => 'Merchant ID',  'type' => 'text',     'required' => true],
            ['key' => 'merchant_key', 'label' => 'Merchant Key', 'type' => 'password', 'required' => true],
            ['key' => 'website',      'label' => 'Website',      'type' => 'text',     'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantId  = $this->credential('merchant_id');
        $merchantKey = $this->credential('merchant_key');
        $website     = $this->credential('website');
        $orderId = 'PAYTM_' . $payment->id . '_' . time();

        $body = [
            'body' => [
                'requestType' => 'Payment', 'mid' => $merchantId, 'websiteName' => $website,
                'orderId' => $orderId, 'callbackUrl' => $callbackUrl,
                'txnAmount' => ['value' => number_format((float) $payment->amount, 2, '.', ''), 'currency' => strtoupper($payment->currency ?? 'INR')],
                'userInfo' => ['custId' => (string) ($payment->workspace_id ?? 'GUEST')],
            ],
        ];

        $bodyJson = json_encode($body['body']);
        $checksum = $this->generateChecksum($bodyJson, $merchantKey);
        $payload = ['body' => $body['body'], 'head' => ['signature' => $checksum]];

        $url      = $this->baseUrl() . "/theia/api/v1/initiateTransaction?mid={$merchantId}&orderId={$orderId}";
        $response = $this->httpRequest('POST', $url, $payload);
        $json     = $response['json'] ?? [];

        $resultCode = $json['body']['resultInfo']['resultCode'] ?? '';
        $txnToken   = $json['body']['txnToken'] ?? null;

        if ($resultCode === 'S' && $txnToken) {
            $redirectUrl = $this->baseUrl() . "/theia/api/v1/showPaymentPage?mid={$merchantId}&orderId={$orderId}&txnToken={$txnToken}";
            return ['redirect_url' => $redirectUrl];
        }

        $errorMsg = $json['body']['resultInfo']['resultMsg'] ?? 'Failed to initiate Paytm transaction.';
        throw new \RuntimeException("Paytm: {$errorMsg}");
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $orderId = $payload['ORDERID'] ?? null;
        $bankTxnId = $payload['BANKTXNID'] ?? null;
        $status = $payload['STATUS'] ?? '';
        $checksumStr = $payload['CHECKSUMHASH'] ?? '';

        if (! $orderId) { return PaymentResult::failure('Missing Paytm order ID.'); }

        $merchantKey = $this->credential('merchant_key');
        $paramsToVerify = $payload;
        unset($paramsToVerify['CHECKSUMHASH']);

        if (! $checksumStr || ! $this->verifyChecksum($paramsToVerify, $merchantKey, $checksumStr)) {
            return PaymentResult::failure('Invalid or missing Paytm checksum.');
        }

        $result = $this->queryTransactionStatus($orderId);
        if ($result !== null) { return $result; }

        if ($status === 'TXN_SUCCESS') {
            return PaymentResult::success(transactionId: $bankTxnId ?? $orderId, message: 'Payment successful.',
                metadata: ['paytm_order_id' => $orderId, 'bank_txn_id' => $bankTxnId, 'txn_id' => $payload['TXNID'] ?? null]);
        }

        $respMsg = $payload['RESPMSG'] ?? "Transaction status: {$status}";
        return PaymentResult::failure("Paytm: {$respMsg}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $orderId = $payment->gateway_transaction_id ?? null;
        if (! $orderId) { return PaymentResult::failure('No transaction ID available.'); }
        $result = $this->queryTransactionStatus($orderId);
        return $result ?? PaymentResult::failure('Unable to verify Paytm transaction.');
    }

    private function queryTransactionStatus(string $orderId): ?PaymentResult
    {
        $merchantId = $this->credential('merchant_id');
        $merchantKey = $this->credential('merchant_key');
        $body = ['mid' => $merchantId, 'orderId' => $orderId];
        $bodyJson = json_encode($body);
        $checksum = $this->generateChecksum($bodyJson, $merchantKey);
        $payload = ['body' => $body, 'head' => ['signature' => $checksum]];

        $url = $this->baseUrl() . "/v3/order/status";
        $response = $this->httpRequest('POST', $url, $payload);
        $json = $response['json'] ?? [];
        $resultCode = $json['body']['resultInfo']['resultCode'] ?? '';
        $resultStatus = $json['body']['resultInfo']['resultStatus'] ?? '';

        if ($resultCode === '01' || $resultStatus === 'TXN_SUCCESS') {
            return PaymentResult::success(transactionId: $json['body']['txnId'] ?? $orderId, message: 'Payment verified.',
                metadata: ['paytm_order_id' => $orderId, 'bank_txn_id' => $json['body']['bankTxnId'] ?? null]);
        }
        if ($resultStatus === 'PENDING') { return PaymentResult::pending('Paytm transaction is pending.', $orderId); }
        return null;
    }

    private function baseUrl(): string { return $this->isSandbox() ? self::STAGING_BASE : self::PROD_BASE; }

    private function generateChecksum(string $body, string $key): string
    {
        $salt = substr(bin2hex(random_bytes(4)), 0, 4);
        $hash = hash('sha256', $body . '|' . $salt);
        $hashString = $hash . $salt;
        $iv = '@@@@&&&&####$$$$';
        $encrypted = openssl_encrypt($hashString, 'AES-128-CBC', $key, 0, $iv);
        if ($encrypted === false) { throw new \RuntimeException('Paytm: Failed to generate checksum.'); }
        return $encrypted;
    }

    private function verifyChecksum(array $params, string $key, string $checksum): bool
    {
        $iv = '@@@@&&&&####$$$$';
        $decrypted = openssl_decrypt($checksum, 'AES-128-CBC', $key, 0, $iv);
        if ($decrypted === false || strlen($decrypted) < 68) { return false; }
        $providedHash = substr($decrypted, 0, 64);
        $salt = substr($decrypted, 64);
        ksort($params);
        $str = implode('|', $params);
        $expectedHash = hash('sha256', $str . '|' . $salt);
        return hash_equals($expectedHash, $providedHash);
    }
}
