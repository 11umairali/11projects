<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\StripeService;
use App\Services\Payment\SubscriptionActivator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
    ) {}

    /**
     * List all payments with filtering and pagination.
     */
    public function index(Request $request): View
    {
        $query = Payment::with(['workspace', 'subscription.plan']);

        // Search by workspace name or stripe payment ID
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('stripe_payment_id', 'like', "%{$search}%")
                    ->orWhere('stripe_invoice_id', 'like', "%{$search}%")
                    ->orWhereHas('workspace', fn ($w) => $w->where('name', 'like', "%{$search}%"));
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by date range
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Sort
        $query->orderByDesc('created_at');

        $payments = $query->paginate(25)->withQueryString();

        // Summary stats
        $stats = Payment::selectRaw("
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN refund_amount > 0 THEN refund_amount ELSE 0 END) as total_refunded
            ")
            ->first();

        return view('admin.payments.index', compact('payments', 'stats'));
    }

    /**
     * Issue a refund for a payment via Stripe.
     *
     * SEC-005: Only super_admin or admins with payment module access can refund.
     * Rate-limited to 10 refunds per hour per admin to prevent
     * accidental mass-refund operations or compromised admin accounts
     * from draining revenue.
     */
    public function refund(Request $request, int $id): RedirectResponse
    {
        $admin = auth()->user();

        // SEC-005: Authorization — only super_admin or admin with payments permission can refund
        if ($admin->admin_role !== 'super_admin') {
            if (!RoleController::roleHasAccess($admin->admin_role ?? 'support', 'payments')) {
                Log::warning('Unauthorized refund attempt', [
                    'admin_id' => $admin->id,
                    'admin_role' => $admin->admin_role,
                    'payment_id' => $id,
                    'ip' => $request->ip(),
                ]);

                DB::table('audit_logs')->insert([
                    'auditable_type' => 'App\\Models\\Payment',
                    'auditable_id' => $id,
                    'event' => 'refund_unauthorized',
                    'actor_type' => 'admin',
                    'actor_id' => $admin->id,
                    'actor_name' => $admin->name,
                    'new_values' => json_encode([
                        'reason' => 'insufficient_permissions',
                        'admin_role' => $admin->admin_role,
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return redirect()->back()
                    ->with('error', 'You do not have permission to issue refunds.');
            }
        }

        // Rate limit: max 10 refunds per hour per admin
        $throttleKey = 'admin-refund:' . $admin->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 60)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            Log::warning('Admin refund rate limit hit', [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'ip' => $request->ip(),
            ]);

            return redirect()->back()
                ->with('error', "Refund rate limit reached (10 per hour). Please wait approximately {$minutes} minute(s) before issuing another refund. This limit exists to protect against accidental mass refunds.");
        }

        $payment = Payment::with('workspace')->findOrFail($id);

        // FIX-004: Admin auth is valid but ensure audit trail includes workspace context
        if ($payment->status !== 'succeeded' && $payment->status !== 'partially_refunded') {
            return redirect()->back()->with('error', 'Only succeeded or partially refunded payments can be refunded.');
        }

        if (!$payment->stripe_payment_id) {
            return redirect()->back()->with('error', 'No Stripe payment ID — cannot process refund.');
        }

        // Calculate maximum refundable amount
        $alreadyRefunded = $payment->refund_amount ?? 0;
        $maxRefundable = $payment->amount - $alreadyRefunded;

        if ($maxRefundable <= 0) {
            return redirect()->back()->with('error', 'This payment has already been fully refunded.');
        }

        // Validate refund amount (default to remaining balance for full refund)
        $requestedAmount = $request->input('refund_amount')
            ? (float) $request->input('refund_amount')
            : $maxRefundable;

        if ($requestedAmount <= 0 || $requestedAmount > $maxRefundable) {
            return redirect()->back()->with('error', "Refund amount must be between \$0.01 and \${$maxRefundable}.");
        }

        try {
            $stripeKey = config('cashier.secret') ?: config('services.stripe.secret');
            if (empty($stripeKey)) { throw new \RuntimeException('Stripe is not configured.'); }
            $stripe = new \Stripe\StripeClient($stripeKey);

            $refundParams = [
                'payment_intent' => $payment->stripe_payment_id,
                'amount' => (int) round($requestedAmount * 100), // Stripe uses cents
            ];

            $refund = $stripe->refunds->create($refundParams);

            // Count this against the rate limit only on success
            RateLimiter::hit($throttleKey, 3600);

            $newRefundTotal = $alreadyRefunded + $requestedAmount;
            $isFullyRefunded = $newRefundTotal >= $payment->amount;

            $payment->update([
                'refund_amount' => $newRefundTotal,
                'refunded_at' => now(),
                'status' => $isFullyRefunded ? 'refunded' : 'partially_refunded',
            ]);

            DB::table('audit_logs')->insert([
                'auditable_type' => 'App\\Models\\Payment',
                'auditable_id' => $payment->id,
                'event' => 'refunded',
                'actor_type' => 'admin',
                'actor_id' => $admin->id,
                'actor_name' => $admin->name,
                'old_values' => json_encode([
                    'status' => $payment->getOriginal('status'),
                    'refund_amount' => $alreadyRefunded,
                ]),
                'new_values' => json_encode([
                    'status' => $isFullyRefunded ? 'refunded' : 'partially_refunded',
                    'refund_amount' => $newRefundTotal,
                    'stripe_refund_id' => $refund->id,
                    'workspace_id' => $payment->workspace_id,
                    'workspace_name' => $payment->workspace?->name,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->back()->with('success', "Refund of \${$requestedAmount} processed successfully. Stripe refund ID: {$refund->id}");

        } catch (\Stripe\Exception\CardException $e) {
            Log::error('Stripe refund failed — card error', [
                'payment_id' => $payment->id,
                'stripe_code' => $e->getStripeCode(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Refund failed: The card issuer declined the refund. The customer may need to contact their bank. (Stripe code: ' . $e->getStripeCode() . ')');

        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error('Stripe refund failed — invalid request', [
                'payment_id' => $payment->id,
                'stripe_code' => $e->getStripeCode(),
                'error' => $e->getMessage(),
            ]);

            $userMessage = match ($e->getStripeCode()) {
                'charge_already_refunded' => 'This payment has already been fully refunded in Stripe. The local record may be out of sync.',
                'charge_expired_for_refund' => 'This payment is too old to refund via Stripe (payments older than 180 days cannot be refunded). Please process a manual credit.',
                default => 'Stripe rejected the refund request: ' . $e->getMessage(),
            };

            return redirect()->back()->with('error', $userMessage);

        } catch (\Stripe\Exception\AuthenticationException $e) {
            Log::critical('Stripe authentication failed during refund — API key may be invalid', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Stripe API authentication failed. Please verify the Stripe secret key in your configuration.');

        } catch (\Stripe\Exception\RateLimitException $e) {
            Log::warning('Stripe rate limit during refund', [
                'payment_id' => $payment->id,
            ]);

            return redirect()->back()->with('error', 'Stripe is temporarily rate-limiting our requests. Please wait a moment and try again.');

        } catch (\Stripe\Exception\ApiConnectionException $e) {
            Log::error('Stripe connection failed during refund', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Could not connect to Stripe. Please check your server\'s internet connection and try again.');

        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe refund failed — general API error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Stripe refund failed: ' . $e->getMessage());
        }
    }


    // ─────────────────────────────────────────────────────────────
    //  Manual (bank transfer / offline) payment review
    // ─────────────────────────────────────────────────────────────

    /** Gateways whose payments are confirmed by an admin, not by a webhook. */
    private const MANUAL_GATEWAYS = ['bank_transfer', 'offline'];

    /**
     * Same permission rule as refunds: super admins, or admins whose role
     * has access to the payments module.
     */
    private function canReviewPayments(): bool
    {
        $admin = auth()->user();

        return $admin->admin_role === 'super_admin'
            || RoleController::roleHasAccess($admin->admin_role ?? 'support', 'payments');
    }

    private function auditPaymentAction(Request $request, Payment $payment, string $event, array $extra = []): void
    {
        $admin = auth()->user();

        DB::table('audit_logs')->insert([
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'event' => $event,
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
            'actor_name' => $admin->name,
            'new_values' => json_encode($extra + ['workspace_id' => $payment->workspace_id, 'amount' => $payment->amount]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Approve a pending bank-transfer payment: mark it succeeded and
     * activate the plan the customer asked for.
     */
    public function approve(Request $request, int $id): RedirectResponse
    {
        if (! $this->canReviewPayments()) {
            return back()->with('error', 'You do not have permission to review payments.');
        }

        $result = DB::transaction(function () use ($id) {
            // Lock the row so a double-click can't activate twice.
            $payment = Payment::lockForUpdate()->findOrFail($id);

            if ($payment->status !== 'pending') {
                return ['error', 'Only pending payments can be approved.'];
            }
            if (! in_array($payment->gateway_slug, self::MANUAL_GATEWAYS, true)) {
                return ['error', 'Only bank transfer / offline payments can be approved manually.'];
            }

            $payment->update([
                'status' => 'succeeded',
                'description' => 'Bank transfer confirmed by admin',
                'failure_reason' => null,
            ]);

            return ['ok', $payment];
        });

        if ($result[0] === 'error') {
            return back()->with('error', $result[1]);
        }

        /** @var Payment $payment */
        $payment = $result[1];
        $activated = app(SubscriptionActivator::class)->activate($payment);

        $this->auditPaymentAction($request, $payment, 'payment_approved', ['subscription_activated' => $activated]);

        return back()->with(
            'success',
            $activated
                ? 'Payment approved and the plan has been activated.'
                : 'Payment approved, but no plan could be activated (missing plan on this payment). Please check it manually.'
        );
    }

    /**
     * Reject a pending bank-transfer payment (transfer never arrived, wrong
     * amount, etc.). The customer's plan is left unchanged.
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        if (! $this->canReviewPayments()) {
            return back()->with('error', 'You do not have permission to review payments.');
        }

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $payment = Payment::findOrFail($id);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be rejected.');
        }
        if (! in_array($payment->gateway_slug, self::MANUAL_GATEWAYS, true)) {
            return back()->with('error', 'Only bank transfer / offline payments can be rejected manually.');
        }

        $payment->update([
            'status' => 'failed',
            'failure_reason' => $data['reason'] ?? 'Rejected by admin',
        ]);

        $this->auditPaymentAction($request, $payment, 'payment_rejected', ['reason' => $data['reason'] ?? null]);

        return back()->with('success', 'Payment rejected.');
    }

    /**
     * Delete a pending or failed payment record. Succeeded / refunded
     * payments are financial records and can never be deleted here.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        if (! $this->canReviewPayments()) {
            return back()->with('error', 'You do not have permission to delete payments.');
        }

        $payment = Payment::findOrFail($id);

        if (! in_array($payment->status, ['pending', 'failed'], true)) {
            return back()->with('error', 'Only pending or failed payments can be deleted.');
        }

        $this->auditPaymentAction($request, $payment, 'payment_deleted', ['status' => $payment->status]);
        $payment->delete();

        return back()->with('success', 'Payment deleted.');
    }

    /**
     * Import payment records from a CSV file (read-only historical records).
     *
     * This creates local payment records only — it does NOT charge anyone
     * or interact with Stripe. Useful for importing legacy payment history
     * from a previous billing system.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getPathname(), 'r');
        $header = array_map('trim', fgetcsv($handle));
        $imported = 0;

        while ($row = fgetcsv($handle)) {
            if (count($row) < count($header)) continue;
            $data = array_combine($header, $row);

            $amount = $data['Amount'] ?? $data['amount'] ?? null;
            $stripePaymentId = $data['Stripe Payment ID'] ?? $data['stripe_payment_id'] ?? null;
            if ($amount === null) continue;

            // Skip duplicates by stripe_payment_id if present
            if ($stripePaymentId) {
                $exists = Payment::where('stripe_payment_id', $stripePaymentId)->exists();
                if ($exists) continue;
            }

            $status = strtolower($data['Status'] ?? $data['status'] ?? 'succeeded');
            if (!in_array($status, ['succeeded', 'failed', 'pending', 'refunded', 'partially_refunded'])) {
                $status = 'succeeded';
            }

            Payment::create([
                'workspace_id' => $data['Workspace ID'] ?? $data['workspace_id'] ?? null,
                'subscription_id' => $data['Subscription ID'] ?? $data['subscription_id'] ?? null,
                'stripe_payment_id' => $stripePaymentId,
                'stripe_invoice_id' => $data['Stripe Invoice ID'] ?? $data['stripe_invoice_id'] ?? null,
                'amount' => (float) $amount,
                'currency' => $data['Currency'] ?? $data['currency'] ?? 'usd',
                'status' => $status,
                'description' => $data['Description'] ?? $data['description'] ?? 'Imported record',
            ]);
            $imported++;
        }

        fclose($handle);

        return back()->with('success', "$imported payment records imported.");
    }

    /**
     * Export payments as a CSV download.
     * Supports ?template=1 to return an empty CSV with headers only.
     */
    public function export(Request $request): StreamedResponse
    {
        $headers = ['Stripe Payment ID', 'Stripe Invoice ID', 'Workspace ID', 'Subscription ID', 'Amount', 'Currency', 'Status', 'Description'];

        if ($request->has('template')) {
            return response()->streamDownload(function () use ($headers) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headers);
                fclose($handle);
            }, 'payments-template.csv', ['Content-Type' => 'text/csv']);
        }

        $query = Payment::with(['workspace', 'subscription.plan']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('stripe_payment_id', 'like', "%{$search}%")
                    ->orWhere('stripe_invoice_id', 'like', "%{$search}%")
                    ->orWhereHas('workspace', fn ($w) => $w->where('name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $payments = $query->orderByDesc('created_at')->get();

        $filename = 'payments-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Stripe Payment ID', 'Workspace', 'Plan', 'Amount', 'Status', 'Refund Amount', 'Date']);
            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->id,
                    $payment->stripe_payment_id ?? '',
                    $payment->workspace?->name ?? 'N/A',
                    $payment->subscription?->plan?->name ?? 'N/A',
                    number_format($payment->amount, 2),
                    ucfirst($payment->status),
                    $payment->refund_amount ? number_format($payment->refund_amount, 2) : '0.00',
                    $payment->created_at->format('Y-m-d H:i'),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
