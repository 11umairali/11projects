<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Log;

/**
 * Base class for all payment gateway drivers.
 *
 * Each concrete driver extends this class and implements the three core
 * methods: credentialFields(), initiate(), and handleCallback().
 *
 * HTTP calls are made via cURL so that drivers have zero Composer
 * package dependencies.
 */
abstract class AbstractGatewayDriver
{
    protected PaymentGateway $gateway;

    public function __construct(PaymentGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    // ---------------------------------------------------------------
    //  Abstract contract every driver must fulfil
    // ---------------------------------------------------------------

    /**
     * Declare the credential fields the admin must fill in.
     *
     * Return format:
     *   [
     *       ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
     *       ['key' => 'mode',       'label' => 'Mode',       'type' => 'select',   'required' => true, 'options' => ['sandbox','live']],
     *   ]
     *
     * Supported types: text, password, select, textarea
     */
    abstract public static function credentialFields(): array;

    /**
     * Start a payment session.
     *
     * @return array  Must contain EITHER:
     *   - ['redirect_url' => 'https://...']   -- redirect the user
     *   - ['html' => '<form ...>']            -- render inline checkout form
     */
    abstract public function initiate(Payment $payment, string $callbackUrl): array;

    /**
     * Process the return / redirect callback from the gateway.
     */
    abstract public function handleCallback(array $payload): PaymentResult;

    // ---------------------------------------------------------------
    //  Optional hooks -- override when the gateway supports them
    // ---------------------------------------------------------------

    /**
     * Process an asynchronous webhook / IPN notification.
     * Default implementation delegates to handleCallback().
     */
    public function handleWebhook(array $payload): PaymentResult
    {
        return $this->handleCallback($payload);
    }

    /**
     * Verify the webhook signature from the gateway.
     *
     * Override in each driver that supports webhook signature verification.
     * Return true if the signature is valid, false otherwise.
     * Drivers that don't support webhooks can leave the default (returns true).
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        return true;
    }

    /**
     * Actively verify a payment status with the gateway API.
     */
    public function verify(Payment $payment): PaymentResult
    {
        return PaymentResult::failure('Verification not supported by this gateway.');
    }

    // ---------------------------------------------------------------
    //  Shared helpers available to every driver
    // ---------------------------------------------------------------

    /**
     * Retrieve a single decrypted credential value.
     */
    protected function credential(string $key, $default = null)
    {
        return $this->gateway->getCredential($key, $default);
    }

    /**
     * Return all decrypted credentials.
     */
    protected function credentials(): array
    {
        return $this->gateway->getDecryptedCredentials();
    }

    /**
     * Whether the gateway is running in sandbox / test mode.
     */
    protected function isSandbox(): bool
    {
        return $this->gateway->isSandbox();
    }

    /**
     * Format the payment amount as an integer in the smallest currency unit
     * (e.g. cents for USD, paisa for INR).
     */
    protected function amountInSmallestUnit(Payment $payment): int
    {
        return (int) round($payment->amount * 100);
    }

    /**
     * Build a short description for the payment.
     */
    protected function paymentDescription(Payment $payment): string
    {
        return "Payment #{$payment->id}";
    }

    // ---------------------------------------------------------------
    //  cURL helper -- every driver uses this for HTTP calls
    // ---------------------------------------------------------------

    /**
     * Execute an HTTP request via cURL.
     *
     * @param  string  $method   GET | POST | PUT | DELETE
     * @param  string  $url
     * @param  array   $data     Body payload (sent as JSON for POST/PUT)
     * @param  array   $headers  Additional headers  ['Authorization: Bearer xxx']
     * @param  int     $timeout  Seconds
     * @return array   ['status' => int, 'body' => string, 'json' => array|null]
     *
     * @throws \RuntimeException on cURL failure
     */
    protected function httpRequest(
        string $method,
        string $url,
        array $data = [],
        array $headers = [],
        int $timeout = 30,
    ): array {
        $ch = curl_init();

        try {
            $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
            $mergedHeaders  = array_merge($defaultHeaders, $headers);

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => $mergedHeaders,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            switch (strtoupper($method)) {
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data ? json_encode($data) : '{}');
                    break;
                case 'PUT':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data ? json_encode($data) : '{}');
                    break;
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    break;
                case 'GET':
                default:
                    if ($data) {
                        $url .= '?' . http_build_query($data);
                        curl_setopt($ch, CURLOPT_URL, $url);
                    }
                    break;
            }

            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
        } finally {
            curl_close($ch);
        }

        if ($body === false) {
            Log::error("Payment gateway cURL error", [
                'gateway' => $this->gateway->slug,
                'url'     => $url,
                'error'   => $error,
            ]);
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        return [
            'status' => $status,
            'body'   => $body,
            'json'   => json_decode($body, true),
        ];
    }

    /**
     * POST a form-encoded request (some gateways expect this).
     */
    protected function httpFormPost(string $url, array $data, array $headers = [], int $timeout = 30): array
    {
        $ch = curl_init();

        try {
            $defaultHeaders = ['Accept: application/json'];
            $mergedHeaders  = array_merge($defaultHeaders, $headers);

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER     => $mergedHeaders,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
        } finally {
            curl_close($ch);
        }

        if ($body === false) {
            Log::error("Payment gateway cURL form-post error", [
                'gateway' => $this->gateway->slug,
                'url'     => $url,
                'error'   => $error,
            ]);
            throw new \RuntimeException("HTTP form-post failed: {$error}");
        }

        return [
            'status' => $status,
            'body'   => $body,
            'json'   => json_decode($body, true),
        ];
    }
}
