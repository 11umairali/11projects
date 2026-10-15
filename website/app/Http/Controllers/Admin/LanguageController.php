<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LanguageController extends Controller
{
    /**
     * List all languages with search, active filter, and pagination.
     */
    public function index(Request $request): View
    {
        $query = Language::query()->orderBy('sort_order')->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('native_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $languages = $query->paginate(25)->withQueryString();

        $totalCount = Language::count();
        $activeCount = Language::where('is_active', true)->count();

        return view('admin.languages.index', compact('languages', 'totalCount', 'activeCount'));
    }

    /**
     * Store a new language.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:10', 'unique:languages,code'],
            'native_name' => ['nullable', 'string', 'max:255'],
            'direction'   => ['required', 'in:ltr,rtl'],
            'flag'        => ['nullable', 'string', 'max:10'],
            'is_active'   => ['boolean'],
            'is_default'  => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');

        // If setting as default, unset other defaults first
        if ($validated['is_default']) {
            Language::where('is_default', true)->update(['is_default' => false]);
            $validated['is_active'] = true; // Default language must be active
        }

        Language::create($validated);

        return back()->with('success', "Language \"{$validated['name']}\" has been created.");
    }

    /**
     * Update an existing language.
     */
    public function update(Request $request, Language $language): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:10', Rule::unique('languages', 'code')->ignore($language->id)],
            'native_name' => ['nullable', 'string', 'max:255'],
            'direction'   => ['required', 'in:ltr,rtl'],
            'flag'        => ['nullable', 'string', 'max:10'],
            'is_active'   => ['boolean'],
            'is_default'  => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_default'] = $request->boolean('is_default');

        // If setting as default, unset other defaults first
        if ($validated['is_default']) {
            Language::where('is_default', true)->where('id', '!=', $language->id)->update(['is_default' => false]);
            $validated['is_active'] = true; // Default language must be active
        }

        $language->update($validated);

        return back()->with('success', "Language \"{$language->name}\" has been updated.");
    }

    /**
     * Delete a language.
     */
    public function destroy(Language $language): RedirectResponse
    {
        if ($language->is_default) {
            return back()->with('error', 'Cannot delete the default language. Set another language as default first.');
        }

        $name = $language->name;
        $language->delete();

        return back()->with('success', "Language \"{$name}\" has been deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Language $language): RedirectResponse
    {
        if ($language->is_default && $language->is_active) {
            return back()->with('error', 'Cannot deactivate the default language.');
        }

        $language->update(['is_active' => !$language->is_active]);

        $status = $language->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Language \"{$language->name}\" has been {$status}.");
    }

    /**
     * Set a language as default.
     */
    public function setDefault(Language $language): RedirectResponse
    {
        Language::where('is_default', true)->update(['is_default' => false]);

        $language->update([
            'is_default' => true,
            'is_active'  => true,
        ]);

        return back()->with('success', "Language \"{$language->name}\" is now the default language.");
    }
}
