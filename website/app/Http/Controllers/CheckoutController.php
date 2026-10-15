<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    /**
     * Show the checkout page with currency/gateway selection.
     */
    public function show(Request $request, int $planId): View
    {
        $workspace = auth()->user()->activeWorkspace;
        $plan = Plan::where('is_active', true)->findOrFail($planId);

        if ($plan->isFree()) {
            return redirect()->route('settings.billing')
                ->with('error', 'Free plans do not require payment.');
        }

        $billingCycle = $request->query('cycle', 'monthly');
        $price = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

        // Load all active gateways ordered by sort_order
        $gateways = PaymentGateway::active()->orderBy('sort_order')->get();

        // Build gateway-currency map for JS filtering
        $gatewayCurrencies = [];
        foreach ($gateways as $gw) {
            $gatewayCurrencies[$gw->slug] = $gw->supported_currencies ?? [];
        }

        // Default currency from user preference or system default
        $userCurrency = auth()->user()->currency_code ?? 'USD';
        $defaultCurrency = \App\Models\Currency::getDefault()?->code ?? $userCurrency;

        // Collect all unique currencies from gateways
        $availableCurrencies = collect($gatewayCurrencies)
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($availableCurrencies)) {
            $availableCurrencies = ['USD'];
        }

        // Build currency symbols from database
        $currencySymbols = \App\Models\Currency::whereIn('code', $availableCurrencies)
            ->pluck('symbol', 'code')
            ->toArray();

        // Fallback for currencies not in our table
        foreach ($availableCurrencies as $code) {
            if (!isset($currencySymbols[$code])) {
                $currencySymbols[$code] = $code;
            }
        }

        // Convert price to selected currency for display
        $currencies = \App\Models\Currency::where('is_active', true)->get()->keyBy('code');

        $couponCode = $request->query('coupon', '');

        return view('checkout.index', [
            'plan' => $plan,
            'price' => $price,
            'billingCycle' => $billingCycle,
            'gateways' => $gateways,
            'gatewayCurrencies' => $gatewayCurrencies,
            'defaultCurrency' => $defaultCurrency,
            'availableCurrencies' => $availableCurrencies,
            'currencySymbols' => $currencySymbols,
            'currencies' => $currencies,
            'workspace' => $workspace,
            'couponCode' => $couponCode,
        ]);
    }

    /**
     * Process the checkout — create payment record and initiate gateway.
     */
    public function process(Request $request, int $planId)
    {
        $request->validate([
            'gateway' => 'required|string|max:50',
            'billing_cycle' => 'required|in:monthly,yearly',
            'currency' => 'nullable|string|max:10',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $workspace = auth()->user()->activeWorkspace;
        $plan = Plan::where('is_active', true)->findOrFail($planId);

        if ($plan->isFree()) {
            return redirect()->route('settings.billing')
                ->with('error', 'Free plans do not require payment.');
        }

        $billingCycle = $request->input('billing_cycle', 'monthly');
        $price = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
        $currency = strtoupper($request->input('currency', 'USD'));
        $gatewaySlug = $request->input('gateway');

        // Validate gateway exists and is active
        $gateway = PaymentGateway::where('slug', $gatewaySlug)
            ->where('is_active', true)
            ->first();

        if (! $gateway) {
            return back()->with('error', 'Selected payment method is not available.');
        }

        // Validate currency is supported by this gateway
        $supportedCurrencies = $gateway->supported_currencies ?? [];
        if (! empty($supportedCurrencies) && ! in_array($currency, $supportedCurrencies)) {
            return back()->with('error', 'Selected currency is not supported by this payment method.');
        }

        // Coupon validation
        $coupon = null;
        $originalAmount = $price;
        $discountAmount = 0;
        $couponCode = $request->input('coupon_code');

        if ($couponCode) {
            $coupon = Coupon::where('code', strtoupper($couponCode))->first();

            if (!$coupon || !$coupon->isValid()) {
                return back()->with('error', 'Invalid or expired coupon code.');
            }

            if (!$coupon->isApplicableToPlan($plan->id)) {
                return back()->with('error', 'This coupon is not applicable to the selected plan.');
            }

            $discountAmount = $coupon->calculateDiscount($price);
            $price = $coupon->getDiscountedPrice($price);
        }

        // Prevent duplicate in-flight payments using DB-level locking to avoid race conditions.
        // lockForUpdate() ensures concurrent requests serialize on this row check.
        $payment = DB::transaction(function () use ($workspace, $price, $currency, $gatewaySlug, $plan, $billingCycle, $couponCode, $discountAmount, $originalAmount, $coupon) {
            $existingPending = Payment::where('workspace_id', $workspace->id)
                ->where('status', 'pending')
                ->where('created_at', '>=', now()->subMinutes(30))
                ->lockForUpdate()
                ->first();

            if ($existingPending) {
                return null; // signal duplicate
            }

            return Payment::create([
                'workspace_id' => $workspace->id,
                'amount' => $price,
                'currency' => $currency,
                'status' => 'pending',
                'gateway_slug' => $gatewaySlug,
                'description' => "{$plan->name} ({$billingCycle}) subscription",
                'coupon_code' => $couponCode ? strtoupper($couponCode) : null,
                'discount_amount' => $discountAmount > 0 ? $discountAmount : null,
                'original_amount' => $couponCode ? $originalAmount : null,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'user_id' => auth()->id(),
                ],
            ]);
        });

        // Increment coupon usage after successful payment creation
        if ($payment && $coupon) {
            $coupon->incrementUsage();
        }

        if (! $payment) {
            return back()->with('error', 'You already have a pending payment. Please wait for it to complete or try again later.');
        }

        // Store plan info in session for callback
        session([
            'checkout_payment_id' => $payment->id,
            'checkout_plan_id' => $plan->id,
            'checkout_billing_cycle' => $billingCycle,
        ]);

        // Resolve gateway driver and initiate
        try {
            $driver = $this->gatewayManager->driverFromModel($gateway);
            $callbackUrl = route('payment.callback', ['gateway' => $gatewaySlug]);
            $result = $driver->initiate($payment, $callbackUrl);

            if (isset($result['redirect_url'])) {
                return redirect()->away($result['redirect_url']);
            }

            if (isset($result['html'])) {
                return view('checkout.gateway-form', [
                    'html' => $result['html'],
                    'plan' => $plan,
                    'payment' => $payment,
                ]);
            }

            return back()->with('error', 'Could not initiate payment. Please try another method.');
        } catch (\Throwable $e) {
            Log::error('Checkout initiation failed', [
                'gateway' => $gatewaySlug,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);

            // Clear checkout session data so user can retry without stale state
            session()->forget(['checkout_payment_id', 'checkout_plan_id', 'checkout_billing_cycle']);

            return back()->with('error', 'Payment could not be initiated: ' . $e->getMessage());
        }
    }

    /**
     * Success page after payment completes.
     */
    public function success(Request $request)
    {
        $paymentId = session('checkout_payment_id');
        $payment = $paymentId ? Payment::find($paymentId) : null;

        // Clear checkout session
        session()->forget(['checkout_payment_id', 'checkout_plan_id', 'checkout_billing_cycle']);

        return view('checkout.success', [
            'payment' => $payment,
        ]);
    }

    /**
     * Cancel page when user aborts payment.
     */
    public function cancel()
    {
        session()->forget(['checkout_payment_id', 'checkout_plan_id', 'checkout_billing_cycle']);

        return redirect()->route('settings.billing')
            ->with('error', 'Payment was canceled. No charges were made.');
    }

    /**
     * Validate a coupon code via AJAX.
     */
    public function validateCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string', 'plan_id' => 'required|integer']);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired coupon code.']);
        }

        if (!$coupon->isApplicableToPlan($request->plan_id)) {
            return response()->json(['valid' => false, 'message' => 'This coupon is not applicable to the selected plan.']);
        }

        return response()->json([
            'valid' => true,
            'type' => $coupon->type,
            'value' => $coupon->value,
            'name' => $coupon->name,
            'message' => $coupon->type === 'percent' ? "{$coupon->value}% off" : "$" . number_format($coupon->value, 2) . " off",
        ]);
    }
}
