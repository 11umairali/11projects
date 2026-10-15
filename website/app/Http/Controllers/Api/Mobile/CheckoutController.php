<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Services\Payment\PaymentGatewayManager;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * In-app plan purchase. Mirrors the website's CheckoutController (same validation, coupon rules,
 * duplicate-payment guard and gateway drivers) but returns JSON so the app can show the checkout natively.
 *
 *   GET  checkout/gateways
 *   POST checkout/coupon      {code, plan_id}
 *   POST checkout             {plan_id, gateway, billing_cycle, currency?, coupon_code?}
 *   POST checkout/{id}/confirm            (bank transfer: "I have made the transfer")
 *   GET  payments                         (payment history)
 */
class CheckoutController extends Controller
{
    use AuthorizesApiActions;

    public function __construct(private readonly PaymentGatewayManager $gateways) {}

    public function gateways(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = PaymentGateway::active()->orderBy('sort_order')->get()->map(fn ($g) => [
            'slug' => $g->slug, 'name' => $g->name, 'currencies' => $g->supported_currencies ?? [], 'manual' => in_array($g->slug, ['bank_transfer', 'offline'], true),
        ]);
        return response()->json(['data' => $rows]);
    }

    public function coupon(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $d = $request->validate(['code' => 'required|string|max:50', 'plan_id' => 'required|integer']);
        $c = Coupon::where('code', strtoupper($d['code']))->first();
        if (!$c || !$c->isValid()) return response()->json(['valid' => false, 'message' => 'Invalid or expired coupon code.']);
        if (!$c->isApplicableToPlan($d['plan_id'])) return response()->json(['valid' => false, 'message' => 'This coupon is not applicable to the selected plan.']);
        return response()->json(['valid' => true, 'type' => $c->type, 'value' => (float) $c->value, 'name' => $c->name,
            'message' => $c->type === 'percent' ? "{$c->value}% off" : '$' . number_format($c->value, 2) . ' off']);
    }

    public function start(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate([
            'plan_id' => 'required|integer', 'gateway' => 'required|string|max:50', 'billing_cycle' => 'required|in:monthly,yearly',
            'currency' => 'nullable|string|max:10', 'coupon_code' => 'nullable|string|max:50',
        ]);

        $workspace = $request->user()->activeWorkspace;
        $plan = Plan::where('is_active', true)->find($d['plan_id']);
        if (!$plan) return response()->json(['message' => 'Plan not found.'], 404);
        if ($plan->isFree()) return response()->json(['message' => 'Free plans do not require payment.'], 422);

        $gateway = PaymentGateway::where('slug', $d['gateway'])->where('is_active', true)->first();
        if (!$gateway) return response()->json(['message' => 'Selected payment method is not available.'], 422);

        $currency = strtoupper($d['currency'] ?? 'USD');
        $supported = $gateway->supported_currencies ?? [];
        if ($supported && !in_array($currency, $supported)) return response()->json(['message' => 'Selected currency is not supported by this payment method.'], 422);

        $price = $d['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
        $original = $price; $discount = 0; $coupon = null;
        if (!empty($d['coupon_code'])) {
            $coupon = Coupon::where('code', strtoupper($d['coupon_code']))->first();
            if (!$coupon || !$coupon->isValid()) return response()->json(['message' => 'Invalid or expired coupon code.'], 422);
            if (!$coupon->isApplicableToPlan($plan->id)) return response()->json(['message' => 'This coupon is not applicable to the selected plan.'], 422);
            $discount = $coupon->calculateDiscount($price);
            $price = $coupon->getDiscountedPrice($price);
        }

        $payment = DB::transaction(function () use ($workspace, $price, $currency, $gateway, $plan, $d, $discount, $original, $coupon, $request) {
            $pending = Payment::where('workspace_id', $workspace->id)->where('status', 'pending')->where('created_at', '>=', now()->subMinutes(30))->lockForUpdate()->first();
            if ($pending) return null;
            return Payment::create([
                'workspace_id' => $workspace->id, 'amount' => $price, 'currency' => $currency, 'status' => 'pending', 'gateway_slug' => $gateway->slug,
                'description' => "{$plan->name} ({$d['billing_cycle']}) subscription",
                'coupon_code' => $coupon ? strtoupper($d['coupon_code']) : null,
                'discount_amount' => $discount > 0 ? $discount : null, 'original_amount' => $coupon ? $original : null,
                'metadata' => ['plan_id' => $plan->id, 'billing_cycle' => $d['billing_cycle'], 'user_id' => $request->user()->id],
            ]);
        });
        if (!$payment) return response()->json(['message' => 'You already have a pending payment. Wait for it to complete or try again in 30 minutes.'], 422);
        if ($coupon) $coupon->incrementUsage();

        try {
            $driver = $this->gateways->driverFromModel($gateway);
            $result = $driver->initiate($payment, route('payment.callback', ['gateway' => $gateway->slug]));

            if (isset($result['redirect_url'])) {
                return response()->json(['type' => 'redirect', 'payment_id' => $payment->id, 'url' => $result['redirect_url'], 'amount' => (float) $price, 'currency' => $currency]);
            }

            if (in_array($gateway->slug, ['bank_transfer', 'offline'], true)) {
                $c = $gateway->getDecryptedCredentials();
                $labels = ['bank_name' => 'Bank name', 'account_name' => 'Account name', 'account_number' => 'Account number', 'routing_number' => 'Routing number', 'swift_code' => 'SWIFT code', 'iban' => 'IBAN'];
                $details = [];
                foreach ($labels as $k => $label) if (!empty($c[$k])) $details[] = ['label' => $label, 'value' => (string) $c[$k]];
                $details[] = ['label' => 'Payment reference', 'value' => (string) $payment->id];
                return response()->json(['type' => 'manual', 'payment_id' => $payment->id, 'amount' => (float) $price, 'currency' => $currency,
                    'details' => $details, 'instructions' => $c['instructions'] ?? null]);
            }

            // Gateways that render their own form: fall back to the hosted checkout page in an in-app tab.
            return response()->json(['type' => 'web', 'payment_id' => $payment->id, 'path' => '/checkout/' . $plan->id, 'amount' => (float) $price, 'currency' => $currency]);
        } catch (\Throwable $e) {
            Log::error('Mobile checkout initiation failed', ['gateway' => $gateway->slug, 'payment_id' => $payment->id, 'error' => $e->getMessage()]);
            $payment->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
            return response()->json(['message' => 'Payment could not be started: ' . \Illuminate\Support\Str::limit($e->getMessage(), 160)], 422);
        }
    }

    /** Customer says the bank transfer has been made. Payment stays pending until an admin verifies it (same as the website). */
    public function confirm(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $payment = Payment::where('workspace_id', $request->user()->active_workspace_id)->find($id);
        if (!$payment) return response()->json(['message' => 'Payment not found.'], 404);
        if (!in_array($payment->gateway_slug, ['bank_transfer', 'offline'], true)) return response()->json(['message' => 'This payment does not need confirmation.'], 422);

        // Optional transfer reference typed by the customer – stored so the admin can match it to the bank statement.
        if ($ref = trim((string) $request->input('reference', ''))) {
            $payment->update(['metadata' => array_merge((array) $payment->metadata, ['customer_reference' => mb_substr($ref, 0, 200)])]);
        }
        $req = Request::create('/payment/callback/' . $payment->gateway_slug, 'POST', ['payment_id' => (string) $payment->id, 'method' => $payment->gateway_slug]);
        app(\App\Http\Controllers\PaymentCallbackController::class)->handle($req, $payment->gateway_slug);
        return response()->json(['message' => 'Thanks! Your subscription will be activated once we confirm the payment.', 'status' => $payment->fresh()->status]);
    }

    /** Polled by the app after the in-app payment page closes, to show success / pending / failed. */
    public function status(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $p = Payment::where('workspace_id', $request->user()->active_workspace_id)->find($id);
        if (!$p) return response()->json(['message' => 'Payment not found.'], 404);
        return response()->json(['id' => $p->id, 'status' => $p->status, 'gateway' => $p->gateway_slug, 'amount' => (float) $p->amount, 'currency' => $p->currency]);
    }

    public function payments(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = Payment::where('workspace_id', $request->user()->active_workspace_id)->latest()->limit(50)->get()->map(fn ($p) => [
            'id' => $p->id, 'description' => $p->description, 'amount' => (float) $p->amount, 'currency' => $p->currency, 'status' => $p->status,
            'gateway' => $p->gateway_slug, 'created_at' => $p->created_at?->toIso8601String(),
        ]);
        return response()->json(['data' => $rows]);
    }
}
