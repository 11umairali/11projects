<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handles payment gateway callbacks and webhooks.
 *
 * Routes:
 *   GET|POST  /payment/callback/{gateway}  -> handle()
 *   POST      /payment/webhook/{gateway}   -> webhook()
 *
 * Security:
 *   - Callback handler verifies the payment via the gateway's verify() method
 *     before updating any local state (prevents IDOR via crafted callback params).
 *   - Webhook handler validates the gateway signature before processing.
 *   - Payment lookups are always scoped through the gateway result metadata,
 *     NOT from raw untrusted request parameters.
 */
class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $manager,
    ) {}

    /**
     * Handle the redirect callback from a payment gateway.
     *
     * The customer is sent here after completing (or cancelling) payment
     * on the gateway's hosted page.
     */
    public function handle(Request $request, string $gateway)
    {
        try {
            $driver = $this->manager->driver($gateway);
            $result = $driver->handleCallback($request->all());

            if ($result->success) {
                $this->updatePaymentFromResult($result, $gateway, 'succeeded');

                return redirect()->route('settings.billing')
                    ->with('success', $result->message);
            }

            if ($result->isPending()) {
                $this->updatePaymentFromResult($result, $gateway, 'pending');

                return redirect()->route('settings.billing')
                    ->with('info', $result->message);
            }

            return redirect()->route('settings.billing')
                ->with('error', $result->message);

        } catch (\Exception $e) {
            Log::error("Payment callback error [{$gateway}]", [
                'error'   => $e->getMessage(),
                // Never log the full payload -- it may contain card tokens or PII
                'gateway' => $gateway,
            ]);

            return redirect()->route('settings.billing')
                ->with('error', 'Payment processing error. Please contact support.');
        }
    }

    /**
     * Handle an asynchronous webhook / IPN from a payment gateway.
     *
     * Webhooks are server-to-server calls that confirm payment status
     * independently of the customer's browser redirect.
     */
    public function webhook(Request $request, string $gateway)
    {
        try {
            $driver  = $this->manager->driver($gateway);
            $rawBody = $request->getContent();
            $headers = collect($request->headers->all())
                ->mapWithKeys(fn($v, $k) => [$k => $v[0] ?? ''])
                ->all();

            // Verify webhook signature
            if (! $driver->verifyWebhookSignature($rawBody, $headers)) {
                Log::warning("Payment webhook signature verification failed [{$gateway}]", [
                    'headers' => array_keys($headers),
                ]);
                return response('Invalid signature', 403);
            }

            $payload = $request->all();
            $result  = $driver->handleWebhook($payload);

            if ($result->success) {
                $this->updatePaymentFromResult($result, $gateway, 'succeeded');
            } elseif ($result->isPending()) {
                $this->updatePaymentFromResult($result, $gateway, 'pending');
            }

            Log::info("Payment webhook processed [{$gateway}]", [
                'success'        => $result->success,
                'message'        => $result->message,
                'transaction_id' => $result->transactionId,
            ]);

            // Most gateways expect a 200 OK response
            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error("Payment webhook error [{$gateway}]", [
                'error' => $e->getMessage(),
            ]);

            // Return 200 even on error to prevent gateway retries during debugging
            return response('Error logged', 200);
        }
    }

    /**
     * Securely update a payment record from a gateway result.
     *
     * Instead of trusting a payment_id from the raw HTTP request (IDOR risk),
     * we first verify that the payment_id from the result metadata corresponds
     * to a real payment that:
     *   1. Actually exists in our database.
     *   2. Was initiated with this specific gateway (gateway_slug matches).
     *   3. Has not already reached a terminal 'succeeded' state (idempotency).
     *
     * If the metadata does not contain a payment_id, we look up the payment
     * by the gateway's transaction ID instead -- a value that only the real
     * gateway can produce.
     */
    private function updatePaymentFromResult($result, string $gatewaySlug, string $newStatus): void
    {
        $payment = $this->resolvePaymentSecurely($result, $gatewaySlug);

        if (!$payment) {
            Log::warning("Payment callback: could not resolve payment", [
                'gateway' => $gatewaySlug,
                'transaction_id' => $result->transactionId,
                'metadata' => $result->metadata,
            ]);
            return;
        }

        // Idempotency: don't downgrade a succeeded payment
        if ($payment->status === 'succeeded') {
            Log::info("Payment callback: payment already succeeded, skipping update", [
                'payment_id' => $payment->id,
                'gateway' => $gatewaySlug,
            ]);
            return;
        }

        // Don't allow pending to overwrite succeeded
        if ($newStatus === 'pending' && $payment->status === 'succeeded') {
            return;
        }

        $payment->update([
            'status'                 => $newStatus,
            'gateway_transaction_id' => $result->transactionId,
            'description'            => $result->message,
        ]);

        // Activate subscription on successful payment
        if ($newStatus === 'succeeded') {
            $this->activateSubscription($payment);
        }
    }

    /**
     * Create/activate a subscription after successful payment.
     */
    private function activateSubscription(Payment $payment): void
    {
        app(\App\Services\Payment\SubscriptionActivator::class)->activate($payment);
    }

    /**
     * Resolve the payment securely using ONLY trusted data:
     *   - metadata['payment_id'] from the gateway driver's handleCallback() -- the
     *     driver is responsible for parsing this from the gateway's signed response,
     *     NOT from raw query params.
     *   - gateway_transaction_id lookup as a fallback.
     *
     * In both cases we scope the lookup to the gateway slug to prevent cross-gateway
     * manipulation.
     */
    private function resolvePaymentSecurely($result, string $gatewaySlug): ?Payment
    {
        // 1. Try payment_id from the gateway result metadata (driver-parsed, not raw request).
        $paymentId = $result->metadata['payment_id'] ?? null;

        if ($paymentId) {
            $payment = Payment::where('id', (int) $paymentId)
                ->where('gateway_slug', $gatewaySlug)
                ->first();

            if ($payment) {
                return $payment;
            }

            Log::warning("Payment callback: metadata payment_id does not match gateway", [
                'payment_id' => $paymentId,
                'gateway_slug' => $gatewaySlug,
            ]);
        }

        // 2. Fall back to looking up by the gateway's transaction ID.
        //    Only the real gateway can produce this value.
        if ($result->transactionId) {
            return Payment::where('gateway_transaction_id', $result->transactionId)
                ->where('gateway_slug', $gatewaySlug)
                ->first();
        }

        return null;
    }
}
