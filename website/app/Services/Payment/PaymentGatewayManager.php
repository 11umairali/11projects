<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;

/**
 * Main entry-point for the payment subsystem.
 *
 * Resolves the correct gateway driver based on its slug, so calling
 * code never needs to know which concrete class backs a gateway.
 *
 * Usage:
 *   $manager = app(PaymentGatewayManager::class);
 *   $driver  = $manager->driver('stripe');
 *   $result  = $driver->initiate($payment, $callbackUrl);
 */
class PaymentGatewayManager
{
    /**
     * Cache of resolved driver instances, keyed by slug.
     *
     * PERF-002: Drivers are instantiated lazily on first access and reused
     * within the same request lifecycle.  This avoids constructing all 40
     * driver objects upfront when only 1-2 are needed per request.
     *
     * @var array<string, AbstractGatewayDriver>
     */
    private array $resolvedDrivers = [];

    /**
     * Slug -> Driver class mapping.
     *
     * Every gateway that the platform supports must be registered here.
     * Total: 40 gateways (30 traditional + 10 crypto)
     */
    private const DRIVER_MAP = [
        // --- Top 10: full production implementations ---
        'stripe'        => Drivers\StripeDriver::class,
        'paypal'        => Drivers\PayPalDriver::class,
        'razorpay'      => Drivers\RazorpayDriver::class,
        'mollie'        => Drivers\MollieDriver::class,
        'paystack'      => Drivers\PaystackDriver::class,
        'flutterwave'   => Drivers\FlutterwaveDriver::class,
        'paytm'         => Drivers\PaytmDriver::class,
        'square'        => Drivers\SquareDriver::class,
        'braintree'     => Drivers\BraintreeDriver::class,
        'twocheckout'   => Drivers\TwoCheckoutDriver::class,

        // --- Additional 18 online gateways ---
        'coinbase'      => Drivers\CoinbaseDriver::class,
        'mercadopago'   => Drivers\MercadoPagoDriver::class,
        'iyzico'        => Drivers\IyzicoDriver::class,
        'paddle'        => Drivers\PaddleDriver::class,
        'authorize_net' => Drivers\AuthorizeNetDriver::class,
        'sslcommerz'    => Drivers\SslCommerzDriver::class,
        'instamojo'     => Drivers\InstamojoDriver::class,
        'phonepe'       => Drivers\PhonePeDriver::class,
        'cashfree'      => Drivers\CashfreeDriver::class,
        'payu'          => Drivers\PayUDriver::class,
        'midtrans'      => Drivers\MidtransDriver::class,
        'xendit'        => Drivers\XenditDriver::class,
        'tap'           => Drivers\TapDriver::class,
        'hyperpay'      => Drivers\HyperPayDriver::class,
        'paytr'         => Drivers\PayTRDriver::class,
        'fondy'         => Drivers\FondyDriver::class,
        'skrill'        => Drivers\SkrillDriver::class,
        'cinetpay'      => Drivers\CinetPayDriver::class,

        // --- Manual / offline ---
        'bank_transfer' => Drivers\BankTransferDriver::class,
        'offline'       => Drivers\OfflineDriver::class,

        // --- Crypto gateways (10) ---
        'coinbase_commerce' => Drivers\CoinbaseCommerceDriver::class,
        'coinpayments'      => Drivers\CoinPaymentsDriver::class,
        'nowpayments'       => Drivers\NowPaymentsDriver::class,
        'bitpay'            => Drivers\BitPayDriver::class,
        'cryptomus'         => Drivers\CryptomusDriver::class,
        'plisio'            => Drivers\PlisioDriver::class,
        'coingate'          => Drivers\CoinGateDriver::class,
        'blockonomics'      => Drivers\BlockonomicsDriver::class,
        'triple_a'          => Drivers\TripleADriver::class,
        'oxapay'            => Drivers\OxaPayDriver::class,
    ];

    /**
     * Resolve a driver instance for the given gateway slug -- lazy loaded.
     *
     * The gateway must exist in the database AND be active.
     * Once resolved, the driver instance is cached for the lifetime of
     * this manager (typically a single request when bound as a singleton).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public function driver(string $slug): AbstractGatewayDriver
    {
        if (isset($this->resolvedDrivers[$slug])) {
            return $this->resolvedDrivers[$slug];
        }

        $gateway     = PaymentGateway::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $driverClass = $this->resolveDriverClass($slug);

        $this->resolvedDrivers[$slug] = new $driverClass($gateway);

        return $this->resolvedDrivers[$slug];
    }

    /**
     * Resolve a driver instance from an already-loaded PaymentGateway model -- lazy loaded.
     *
     * Useful when you already have the model and do not want a second query.
     * The instance is cached by slug for reuse within this request.
     */
    public function driverFromModel(PaymentGateway $gateway): AbstractGatewayDriver
    {
        if (isset($this->resolvedDrivers[$gateway->slug])) {
            return $this->resolvedDrivers[$gateway->slug];
        }

        $driverClass = $this->resolveDriverClass($gateway->slug);

        $this->resolvedDrivers[$gateway->slug] = new $driverClass($gateway);

        return $this->resolvedDrivers[$gateway->slug];
    }

    /**
     * Flush all cached driver instances.
     *
     * Useful in long-running processes (queue workers) or testing
     * where gateway configuration may change between calls.
     */
    public function flush(): void
    {
        $this->resolvedDrivers = [];
    }

    /**
     * Return all active gateways, ordered by their admin-defined sort order.
     */
    public function activeGateways(): Collection
    {
        return PaymentGateway::where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * Return the credential field definitions for a given gateway slug.
     *
     * This is used by the admin settings UI to render the form dynamically.
     */
    public function credentialFieldsFor(string $slug): array
    {
        $driverClass = $this->resolveDriverClass($slug);

        return $driverClass::credentialFields();
    }

    /**
     * Return the full list of supported gateway slugs.
     */
    public function supportedSlugs(): array
    {
        return array_keys(self::DRIVER_MAP);
    }

    /**
     * Check whether a slug is supported by the platform.
     */
    public function isSupported(string $slug): bool
    {
        return isset(self::DRIVER_MAP[$slug]);
    }

    // ---------------------------------------------------------------

    private function resolveDriverClass(string $slug): string
    {
        if (! isset(self::DRIVER_MAP[$slug])) {
            throw new \InvalidArgumentException("Unknown payment gateway driver: {$slug}");
        }

        return self::DRIVER_MAP[$slug];
    }
}
