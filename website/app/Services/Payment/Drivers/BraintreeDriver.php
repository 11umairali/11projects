<?php

namespace App\Services\Payment\Drivers;

use App\Models\Payment;
use App\Services\Payment\AbstractGatewayDriver;
use App\Services\Payment\PaymentResult;

/**
 * Braintree payment gateway driver.
 *
 * @see https://developer.paypal.com/braintree/docs/start/overview
 */
class BraintreeDriver extends AbstractGatewayDriver
{
    private const SANDBOX_BASE = 'https://api.sandbox.braintreegateway.com:443/merchants';
    private const PROD_BASE    = 'https://api.braintreegateway.com:443/merchants';

    public static function credentialFields(): array
    {
        return [
            ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text',     'required' => true],
            ['key' => 'public_key',  'label' => 'Public Key',  'type' => 'text',     'required' => true],
            ['key' => 'private_key', 'label' => 'Private Key', 'type' => 'password', 'required' => true],
        ];
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchantId = $this->credential('merchant_id');
        $publicKey  = $this->credential('public_key');
        $privateKey = $this->credential('private_key');

        $tokenXml = '<client-token><version>2</version></client-token>';
        $response = $this->braintreeRequest('POST', "/{$merchantId}/client_token", $tokenXml, $publicKey, $privateKey);

        $clientToken = '';
        if (preg_match('/<value>(.*?)<\/value>/s', $response['body'], $matches)) { $clientToken = trim($matches[1]); }
        if (! $clientToken) { throw new \RuntimeException('Braintree: Unable to generate client token.'); }

        $amount = number_format((float) $payment->amount, 2, '.', '');
        $paymentId = $payment->id;

        $jsClientToken = json_encode($clientToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsCallbackUrl = json_encode($callbackUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsPaymentId   = json_encode((string) $paymentId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsAmount      = json_encode($amount, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        $jsCsrfToken   = json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html = <<<HTML
        <div id="braintree-dropin-container"></div>
        <button id="braintree-submit-btn" class="btn btn-primary" disabled>Pay Now</button>
        <script src="https://js.braintreegateway.com/web/dropin/1.40.2/js/dropin.min.js"></script>
        <script>
            braintree.dropin.create({
                authorization: {$jsClientToken}, container: '#braintree-dropin-container',
                card: { cardholderName: { required: true } }
            }, function(err, instance) {
                if (err) { console.error(err); return; }
                var btn = document.getElementById('braintree-submit-btn');
                btn.disabled = false;
                btn.addEventListener('click', function() {
                    btn.disabled = true;
                    instance.requestPaymentMethod(function(requestErr, payload) {
                        if (requestErr) { btn.disabled = false; return; }
                        var form = document.createElement('form');
                        form.method = 'POST'; form.action = {$jsCallbackUrl};
                        var fields = { payment_method_nonce: payload.nonce, payment_id: {$jsPaymentId}, amount: {$jsAmount}, _token: {$jsCsrfToken} };
                        for (var key in fields) { var input = document.createElement('input'); input.type = 'hidden'; input.name = key; input.value = fields[key]; form.appendChild(input); }
                        document.body.appendChild(form); form.submit();
                    });
                });
            });
        </script>
        HTML;

        return ['html' => $html];
    }

    public function handleCallback(array $payload): PaymentResult
    {
        $nonce = $payload['payment_method_nonce'] ?? null;
        if (! $nonce) { return PaymentResult::failure('Missing Braintree payment nonce.'); }

        $paymentId = $payload['payment_id'] ?? null;
        $localPayment = $paymentId ? Payment::find($paymentId) : null;
        if (! $localPayment) { return PaymentResult::failure('Braintree: could not find associated payment.'); }

        $amount = number_format((float) $localPayment->amount, 2, '.', '');
        $merchantId = $this->credential('merchant_id');
        $publicKey  = $this->credential('public_key');
        $privateKey = $this->credential('private_key');

        $escapedAmount = htmlspecialchars($amount, ENT_XML1, 'UTF-8');
        $escapedNonce  = htmlspecialchars($nonce, ENT_XML1, 'UTF-8');

        $transactionXml = "<transaction><type>sale</type><amount>{$escapedAmount}</amount><payment-method-nonce>{$escapedNonce}</payment-method-nonce><options><submit-for-settlement>true</submit-for-settlement></options></transaction>";

        $response = $this->braintreeRequest('POST', "/{$merchantId}/transactions", $transactionXml, $publicKey, $privateKey);
        $body = $response['body'];

        $transId = ''; $status = '';
        if (preg_match('/<id>(.*?)<\/id>/', $body, $m)) { $transId = $m[1]; }
        if (preg_match('/<status>(.*?)<\/status>/', $body, $m)) { $status = $m[1]; }

        if (in_array($status, ['authorized', 'submitted_for_settlement', 'settled', 'settling'])) {
            return PaymentResult::success(transactionId: $transId, message: 'Payment processed.', metadata: ['braintree_status' => $status]);
        }

        $errorMsg = '';
        if (preg_match('/<message>(.*?)<\/message>/', $body, $m)) { $errorMsg = $m[1]; }
        return PaymentResult::failure("Braintree transaction failed. Status: {$status}. {$errorMsg}");
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $privateKey = $this->credential('private_key');
        if (! $privateKey) { return true; }
        parse_str($rawBody, $params);
        $btSignature = $params['bt_signature'] ?? '';
        $btPayload   = $params['bt_payload'] ?? '';
        if (! $btSignature || ! $btPayload) { return false; }
        $parts = explode('|', $btSignature, 2);
        if (count($parts) !== 2) { return false; }
        $receivedSignature = $parts[1];
        $publicKey = $this->credential('public_key');
        $hmacKey = hash('sha256', hash('sha256', $privateKey, true) . $publicKey);
        $expectedSignature = hash_hmac('sha1', $btPayload, $hmacKey);
        return hash_equals($expectedSignature, $receivedSignature);
    }

    public function handleWebhook(array $payload): PaymentResult
    {
        $btPayload = $payload['bt_payload'] ?? '';
        if (! $btPayload) { return PaymentResult::failure('Missing Braintree webhook payload.'); }
        $xml = base64_decode($btPayload, true);
        if ($xml === false) { return PaymentResult::failure('Invalid Braintree webhook encoding.'); }
        $kind = ''; $transId = '';
        if (preg_match('/<kind>(.*?)<\/kind>/s', $xml, $m)) { $kind = trim($m[1]); }
        if (preg_match('/<id>(.*?)<\/id>/', $xml, $m)) { $transId = trim($m[1]); }
        if ($kind === 'transaction_settled') { return PaymentResult::success(transactionId: $transId, message: 'Braintree: transaction settled.'); }
        if ($kind === 'transaction_settlement_declined') { return PaymentResult::failure("Braintree: settlement declined. Transaction: {$transId}"); }
        return PaymentResult::failure("Braintree webhook received. Kind: {$kind}");
    }

    public function verify(Payment $payment): PaymentResult
    {
        $transId = $payment->gateway_transaction_id ?? null;
        if (! $transId) { return PaymentResult::failure('No transaction ID available.'); }
        $merchantId = $this->credential('merchant_id');
        $publicKey  = $this->credential('public_key');
        $privateKey = $this->credential('private_key');
        $response = $this->braintreeRequest('GET', "/{$merchantId}/transactions/{$transId}", '', $publicKey, $privateKey);
        $body = $response['body']; $status = '';
        if (preg_match('/<status>(.*?)<\/status>/', $body, $m)) { $status = $m[1]; }
        if (in_array($status, ['authorized', 'submitted_for_settlement', 'settled', 'settling'])) { return PaymentResult::success(transactionId: $transId, message: "Payment verified. Status: {$status}."); }
        return PaymentResult::failure("Payment not verified. Status: {$status}");
    }

    private function braintreeRequest(string $method, string $path, string $xmlBody, string $publicKey, string $privateKey): array
    {
        $url = $this->baseUrl() . $path;
        $ch = curl_init();
        $headers = ['Content-Type: application/xml', 'Accept: application/xml', 'X-ApiVersion: 6', 'User-Agent: Braintree PHP'];
        curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_HTTPHEADER => $headers, CURLOPT_USERPWD => $publicKey . ':' . $privateKey, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        if ($method === 'POST' && $xmlBody) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlBody); }
        $body = curl_exec($ch); $error = curl_error($ch); curl_close($ch);
        if ($body === false) { throw new \RuntimeException("Braintree cURL error: {$error}"); }
        return ['body' => $body];
    }

    private function baseUrl(): string { return $this->isSandbox() ? self::SANDBOX_BASE : self::PROD_BASE; }
}
