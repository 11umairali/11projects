<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CouponController extends Controller
{
    /**
     * List all coupons with pagination.
     */
    public function index(Request $request): View
    {
        $query = DB::table('coupons');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<', now());
            }
        }

        $query->orderByDesc('created_at');

        $coupons = $query->paginate(25)->withQueryString();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.coupons.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|alpha_dash',
            'type' => 'required|string|in:percent_off,amount_off',
            'percent_off' => 'required_if:type,percent_off|nullable|numeric|min:1|max:100',
            'amount_off' => 'required_if:type,amount_off|nullable|numeric|min:0.01',
            'currency' => 'required_if:type,amount_off|nullable|string|size:3',
            'duration' => 'required|string|in:once,repeating,forever',
            'duration_in_months' => 'required_if:duration,repeating|nullable|integer|min:1|max:36',
            'max_redemptions' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $code = $request->input('code') ?: strtoupper(Str::random(8));

        DB::table('coupons')->insert([
            'code' => $code,
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'percent_off' => $request->input('percent_off'),
            'amount_off' => $request->input('amount_off'),
            'currency' => $request->input('currency', 'usd'),
            'duration' => $request->input('duration'),
            'duration_in_months' => $request->input('duration_in_months'),
            'max_redemptions' => $request->input('max_redemptions'),
            'times_redeemed' => 0,
            'stripe_coupon_id' => null,
            'is_active' => true,
            'expires_at' => $request->input('expires_at'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Auth::user();
        DB::table('audit_logs')->insert([
            'auditable_type' => 'coupon',
            'auditable_id' => DB::getPdo()->lastInsertId(),
            'event' => 'coupon_created',
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'new_values' => json_encode(['code' => $code, 'name' => $request->input('name')]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.coupons.index')
            ->with('success', "Coupon '{$code}' created.");
    }

    public function show(int $id): View
    {
        $coupon = DB::table('coupons')->where('id', $id)->firstOrFail();

        return view('admin.coupons.show', compact('coupon'));
    }

    public function edit(int $id): View
    {
        $coupon = DB::table('coupons')->where('id', $id)->firstOrFail();

        return view('admin.coupons.edit', compact('coupon'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $coupon = DB::table('coupons')->where('id', $id)->first();

        if (!$coupon) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'max_redemptions' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::table('coupons')->where('id', $id)->update([
            'name' => $request->input('name'),
            'is_active' => $request->boolean('is_active', true),
            'max_redemptions' => $request->input('max_redemptions'),
            'expires_at' => $request->input('expires_at'),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.coupons.index')
            ->with('success', "Coupon '{$coupon->code}' updated.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $coupon = DB::table('coupons')->where('id', $id)->first();

        if (!$coupon) {
            abort(404);
        }

        DB::table('coupons')->where('id', $id)->delete();

        $user = Auth::user();
        DB::table('audit_logs')->insert([
            'auditable_type' => 'coupon',
            'auditable_id' => $id,
            'event' => 'coupon_deleted',
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'old_values' => json_encode(['code' => $coupon->code, 'name' => $coupon->name]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.coupons.index')
            ->with('success', "Coupon '{$coupon->code}' deleted.");
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $handle = fopen($request->file('file')->getPathname(), 'r');
        $header = array_map('trim', fgetcsv($handle));
        $imported = 0;

        while ($row = fgetcsv($handle)) {
            if (count($row) < count($header)) continue;
            $data = array_combine($header, $row);

            $code = $data['Code'] ?? $data['code'] ?? null;
            $name = $data['Name'] ?? $data['name'] ?? null;
            if (!$code || !$name) continue;

            $exists = DB::table('coupons')->where('code', $code)->exists();
            if ($exists) continue;

            $type = $data['Type'] ?? $data['type'] ?? 'percent_off';
            if (strtolower($type) === 'percentage') $type = 'percent_off';
            if (strtolower($type) === 'fixed') $type = 'amount_off';
            if (!in_array($type, ['percent_off', 'amount_off'])) $type = 'percent_off';

            $duration = strtolower($data['Duration'] ?? $data['duration'] ?? 'once');
            if (!in_array($duration, ['once', 'repeating', 'forever'])) $duration = 'once';

            DB::table('coupons')->insert([
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'percent_off' => $type === 'percent_off' ? ($data['Percent Off'] ?? $data['percent_off'] ?? 0) : null,
                'amount_off' => $type === 'amount_off' ? ($data['Amount Off'] ?? $data['amount_off'] ?? 0) : null,
                'currency' => $data['Currency'] ?? $data['currency'] ?? 'usd',
                'duration' => $duration,
                'duration_in_months' => $duration === 'repeating' ? ($data['Duration In Months'] ?? $data['duration_in_months'] ?? null) : null,
                'max_redemptions' => $data['Max Redemptions'] ?? $data['max_redemptions'] ?? null,
                'times_redeemed' => 0,
                'stripe_coupon_id' => null,
                'is_active' => true,
                'expires_at' => $data['Expires At'] ?? $data['expires_at'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $imported++;
        }

        fclose($handle);

        return back()->with('success', "$imported coupons imported.");
    }

    public function export(Request $request): StreamedResponse
    {
        $headers = ['Code', 'Name', 'Type', 'Percent Off', 'Amount Off', 'Currency', 'Duration', 'Duration In Months', 'Max Redemptions', 'Expires At'];

        if ($request->has('template')) {
            return response()->streamDownload(function () use ($headers) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headers);
                fclose($handle);
            }, 'coupons-template.csv', ['Content-Type' => 'text/csv']);
        }

        $query = DB::table('coupons');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<', now());
            }
        }

        $coupons = $query->orderByDesc('created_at')->get();

        $filename = 'coupons-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($coupons) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Code', 'Name', 'Type', 'Discount', 'Duration', 'Times Redeemed', 'Max Redemptions', 'Active', 'Expires At', 'Created At']);
            foreach ($coupons as $coupon) {
                $discount = $coupon->type === 'percent_off'
                    ? $coupon->percent_off . '%'
                    : '$' . number_format($coupon->amount_off, 2);
                fputcsv($handle, [
                    $coupon->id,
                    $coupon->code,
                    $coupon->name,
                    $coupon->type === 'percent_off' ? 'Percentage' : 'Fixed',
                    $discount,
                    ucfirst($coupon->duration),
                    $coupon->times_redeemed ?? 0,
                    $coupon->max_redemptions ?? 'Unlimited',
                    $coupon->is_active ? 'Yes' : 'No',
                    $coupon->expires_at ?? 'Never',
                    $coupon->created_at,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $count = DB::table('coupons')->whereIn('id', $request->ids)->delete();

        return back()->with('success', "{$count} coupons deleted.");
    }
}
