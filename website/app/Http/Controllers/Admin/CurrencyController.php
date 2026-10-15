<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    /**
     * List all currencies with search and active filter.
     */
    public function index(Request $request): View
    {
        $query = Currency::query()->orderBy('sort_order')->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $currencies = $query->paginate(25)->withQueryString();

        $totalCount = Currency::count();
        $activeCount = Currency::where('is_active', true)->count();

        return view('admin.currencies.index', compact('currencies', 'totalCount', 'activeCount'));
    }

    /**
     * Store a new currency.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'code'               => ['required', 'string', 'max:5', 'unique:currencies,code'],
            'symbol'             => ['required', 'string', 'max:10'],
            'symbol_position'    => ['required', 'in:before,after'],
            'decimal_separator'  => ['required', 'string', 'max:5'],
            'thousand_separator' => ['required', 'string', 'max:5'],
            'decimal_digits'     => ['required', 'integer', 'min:0', 'max:4'],
            'exchange_rate'      => ['required', 'numeric', 'min:0'],
            'is_active'          => ['boolean'],
            'is_default'         => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');

        // If setting as default, unset other defaults first
        if ($validated['is_default']) {
            Currency::where('is_default', true)->update(['is_default' => false]);
            $validated['is_active'] = true; // Default currency must be active
        }

        Currency::create($validated);

        return back()->with('success', "Currency \"{$validated['name']}\" has been created.");
    }

    /**
     * Update an existing currency.
     */
    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'code'               => ['required', 'string', 'max:5', Rule::unique('currencies', 'code')->ignore($currency->id)],
            'symbol'             => ['required', 'string', 'max:10'],
            'symbol_position'    => ['required', 'in:before,after'],
            'decimal_separator'  => ['required', 'string', 'max:5'],
            'thousand_separator' => ['required', 'string', 'max:5'],
            'decimal_digits'     => ['required', 'integer', 'min:0', 'max:4'],
            'exchange_rate'      => ['required', 'numeric', 'min:0'],
            'is_active'          => ['boolean'],
            'is_default'         => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');

        // If setting as default, unset other defaults first
        if ($validated['is_default']) {
            Currency::where('is_default', true)->where('id', '!=', $currency->id)->update(['is_default' => false]);
            $validated['is_active'] = true; // Default currency must be active
        }

        $currency->update($validated);

        return back()->with('success', "Currency \"{$currency->name}\" has been updated.");
    }

    /**
     * Delete a currency.
     */
    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_default) {
            return back()->with('error', 'Cannot delete the default currency. Set another currency as default first.');
        }

        $name = $currency->name;
        $currency->delete();

        return back()->with('success', "Currency \"{$name}\" has been deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Currency $currency): RedirectResponse
    {
        if ($currency->is_default && $currency->is_active) {
            return back()->with('error', 'Cannot deactivate the default currency.');
        }

        $currency->update(['is_active' => !$currency->is_active]);

        $status = $currency->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Currency \"{$currency->name}\" has been {$status}.");
    }

    /**
     * Set a currency as default.
     */
    public function setDefault(Currency $currency): RedirectResponse
    {
        Currency::where('is_default', true)->update(['is_default' => false]);

        $currency->update([
            'is_default' => true,
            'is_active'  => true,
        ]);

        return back()->with('success', "Currency \"{$currency->name}\" is now the default currency.");
    }
}
