<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Workspace list / switch / create / settings / privacy / contact settings / delete.
 * Mirrors Livewire\WorkspaceSwitcher, WorkspaceSettings, DataPrivacySettings, ContactSettings.
 */
class WorkspacesController extends Controller
{
    use AuthorizesApiActions;

    private const RESERVED_SLUGS = [
        'admin', 'api', 'auth', 'login', 'register', 'dashboard', 'settings',
        'app', 'www', 'mail', 'ftp', 'smtp', 'imap', 'help', 'support',
        'billing', 'onboarding', 'invite', 'webhook', 'webhooks',
    ];

    private function ws(Request $r): ?Workspace
    {
        return $r->user()->active_workspace_id ? Workspace::find($r->user()->active_workspace_id) : null;
    }

    private function row(Workspace $w, ?string $role = null): array
    {
        return [
            'id'        => $w->id,
            'name'      => $w->name,
            'slug'      => $w->slug,
            'industry'  => $w->industry,
            'timezone'  => $w->timezone,
            'logo_url'  => $w->logo_path ? asset('storage/' . $w->logo_path) : null,
            'role'      => $role,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = $user->workspaces()->withPivot('role')->get()
            ->map(fn ($w) => $this->row($w, $w->pivot->role) + ['active' => $w->id === $user->active_workspace_id]);

        return response()->json(['data' => $rows]);
    }

    public function switch(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->workspaces()->where('workspaces.id', $id)->exists()) {
            return response()->json(['message' => 'You do not have access to that workspace.'], 403);
        }
        Cache::forget("workspace:{$user->id}:{$user->active_workspace_id}");
        $user->update(['active_workspace_id' => $id]);
        Cache::forget("workspace:{$user->id}:{$id}");

        return response()->json(['message' => 'Workspace switched.', 'data' => $this->row(Workspace::find($id), $user->workspaceRole($id))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'industry' => 'nullable|string|max:50',
        ]);
        $user = $request->user();
        $workspace = Workspace::create([
            'name'                 => $data['name'],
            'industry'             => $data['industry'] ?? null,
            'onboarding_step'      => 2,
            'onboarding_completed' => false,
        ]);
        $workspace->members()->attach($user->id, ['role' => 'owner', 'status' => 'online']);
        Cache::forget("workspace:{$user->id}:{$user->active_workspace_id}");
        $user->update(['active_workspace_id' => $workspace->id]);

        return response()->json(['data' => $this->row($workspace, 'owner')], 201);
    }

    // ── Workspace settings ──────────────────────────────────────────────

    public function show(Request $request): JsonResponse
    {
        $w = $this->ws($request);
        if (!$w) return response()->json(['message' => 'No active workspace.'], 404);
        return response()->json(['data' => $this->row($w, $request->user()->workspaceRole($w->id))]);
    }

    public function update(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $w = $this->ws($request);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'slug'     => ['required', 'string', 'max:100', 'alpha_dash', function ($attr, $value, $fail) use ($w) {
                if (in_array(strtolower($value), self::RESERVED_SLUGS)) {
                    return $fail('This slug is reserved and cannot be used.');
                }
                if (Workspace::where('slug', $value)->where('id', '!=', $w->id)->exists()) {
                    return $fail('This slug is already taken.');
                }
            }],
            'industry' => 'nullable|string|max:100',
            'timezone' => 'required|string|max:50',
            'logo'     => 'nullable|image|max:2048|mimes:jpg,jpeg,png,svg',
        ]);

        $fill = collect($data)->only(['name', 'slug', 'industry', 'timezone'])->all();
        if ($request->hasFile('logo')) {
            if ($w->logo_path && Storage::disk('public')->exists($w->logo_path)) {
                Storage::disk('public')->delete($w->logo_path);
            }
            $fill['logo_path'] = $request->file('logo')->store('workspace-logos', 'public');
        }
        $w->update($fill);

        return response()->json(['data' => $this->row($w->fresh(), $request->user()->workspaceRole($w->id)), 'message' => 'Workspace settings saved.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'dangerous')) return $deny;
        $w = $this->ws($request);
        $request->validate(['confirmation' => 'required|string', 'password' => 'required|string']);

        if ($request->input('confirmation') !== $w->name) {
            return response()->json(['message' => 'Workspace name does not match.', 'errors' => ['confirmation' => ['Workspace name does not match.']]], 422);
        }
        if (!Hash::check($request->input('password'), $request->user()->password)) {
            return response()->json(['message' => 'Incorrect password.', 'errors' => ['password' => ['Incorrect password.']]], 422);
        }
        if ($w->logo_path && Storage::disk('public')->exists($w->logo_path)) {
            Storage::disk('public')->delete($w->logo_path);
        }
        $memberIds = $w->members()->pluck('users.id');
        User::whereIn('id', $memberIds)->where('active_workspace_id', $w->id)->update(['active_workspace_id' => null]);
        $w->delete();

        $user = $request->user();
        $next = $user->workspaces()->first();
        if ($next) $user->update(['active_workspace_id' => $next->id]);

        return response()->json(['message' => 'Workspace deleted.', 'next_workspace_id' => $next?->id]);
    }

    // ── Privacy & contact settings (stored in workspace.settings JSON) ──

    public function privacy(Request $request): JsonResponse
    {
        $s = $this->ws($request)?->settings ?? [];
        return response()->json(['data' => [
            'conversation_retention' => (string) ($s['conversation_retention'] ?? '90'),
            'contact_retention'      => (string) ($s['contact_retention'] ?? '365'),
            'ai_training_consent'    => (bool) ($s['ai_training_consent'] ?? true),
            'analytics_consent'      => (bool) ($s['analytics_consent'] ?? true),
            'third_party_sharing'    => (bool) ($s['third_party_sharing'] ?? false),
        ]]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate([
            'conversation_retention' => 'required|string|max:10',
            'contact_retention'      => 'required|string|max:10',
            'ai_training_consent'    => 'required|boolean',
            'analytics_consent'      => 'required|boolean',
            'third_party_sharing'    => 'required|boolean',
        ]);
        $w = $this->ws($request);
        $w->update(['settings' => array_merge($w->settings ?? [], $d)]);

        return response()->json(['message' => 'Privacy settings saved.']);
    }

    public function contactSettings(Request $request): JsonResponse
    {
        $s = $this->ws($request)?->settings ?? [];
        return response()->json(['data' => [
            'contact_auto_create' => (bool) ($s['contact_auto_create'] ?? true),
            'contact_auto_tag'    => (bool) ($s['contact_auto_tag'] ?? true),
            'contact_auto_merge'  => (bool) ($s['contact_auto_merge'] ?? false),
        ]]);
    }

    public function updateContactSettings(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate([
            'contact_auto_create' => 'required|boolean',
            'contact_auto_tag'    => 'required|boolean',
            'contact_auto_merge'  => 'required|boolean',
        ]);
        $w = $this->ws($request);
        $w->update(['settings' => array_merge($w->settings ?? [], $d)]);

        return response()->json(['message' => 'Contact settings saved.']);
    }
}
