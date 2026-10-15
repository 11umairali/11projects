<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Tag;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Contact groups (lists), trash, duplicate detection + merge, bulk actions.
 * Mirrors Livewire\Contacts\ContactListManager / ContactTrash / ContactMerge / ContactList.
 */
class ContactExtrasController extends Controller
{
    use AuthorizesApiActions;

    private function wid(Request $r): int { return (int) $r->user()->active_workspace_id; }

    private function c(Contact $c): array
    {
        return [
            'id' => $c->id, 'full_name' => $c->full_name, 'first_name' => $c->first_name, 'last_name' => $c->last_name,
            'email' => $c->email, 'phone' => $c->phone, 'company' => $c->company, 'lead_score' => $c->lead_score,
            'status' => $c->status, 'deleted_at' => $c->deleted_at?->toIso8601String(),
        ];
    }

    // ── Groups ──────────────────────────────────────────────────────────

    public function groups(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = ContactList::where('workspace_id', $this->wid($request))->orderBy('name')->get()
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'description' => $g->description, 'contacts_count' => (int) $g->contacts_count]);
        return response()->json(['data' => $rows]);
    }

    public function createGroup(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:500']);
        $g = ContactList::create(['workspace_id' => $this->wid($request), 'contacts_count' => 0] + $d);
        return response()->json(['data' => ['id' => $g->id, 'name' => $g->name, 'description' => $g->description, 'contacts_count' => 0]], 201);
    }

    public function updateGroup(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:500']);
        $g = ContactList::where('workspace_id', $this->wid($request))->findOrFail($id);
        $g->update($d);
        return response()->json(['data' => ['id' => $g->id, 'name' => $g->name, 'description' => $g->description, 'contacts_count' => (int) $g->contacts_count]]);
    }

    public function deleteGroup(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $g = ContactList::where('workspace_id', $this->wid($request))->findOrFail($id);
        $g->contacts()->detach();
        $g->delete();
        return response()->json(['message' => 'Group deleted.']);
    }

    public function groupMembers(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $g = ContactList::where('workspace_id', $this->wid($request))->findOrFail($id);
        return response()->json(['data' => $g->contacts()->orderBy('first_name')->limit(500)->get()->map(fn ($c) => $this->c($c))]);
    }

    public function addToGroup(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate(['contact_ids' => 'required|array|min:1|max:500', 'contact_ids.*' => 'integer']);
        $wid = $this->wid($request);
        $g = ContactList::where('workspace_id', $wid)->findOrFail($id);

        $valid = Contact::where('workspace_id', $wid)->whereIn('id', $d['contact_ids'])->pluck('id')->all();
        $existing = $g->contacts()->pluck('contacts.id')->all();
        $new = array_diff($valid, $existing);
        if ($new) {
            $pivot = [];
            foreach ($new as $cid) $pivot[$cid] = ['added_at' => now()];
            $g->contacts()->attach($pivot);
            $g->refreshContactsCount();
        }
        return response()->json(['message' => count($new) . ' contact(s) added.', 'added' => count($new)]);
    }

    public function removeFromGroup(Request $request, int $id, int $contactId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $g = ContactList::where('workspace_id', $this->wid($request))->findOrFail($id);
        $g->contacts()->detach($contactId);
        $g->refreshContactsCount();
        return response()->json(['message' => 'Removed from group.']);
    }

    // ── Trash ───────────────────────────────────────────────────────────

    public function trash(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $q = Contact::onlyTrashed()->where('workspace_id', $this->wid($request));
        if ($s = $request->input('search')) {
            $q->where(fn ($w) => $w->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        $p = $q->orderByDesc('deleted_at')->paginate(min((int) $request->input('per_page', 30), 100));
        return response()->json(['data' => $p->getCollection()->map(fn ($c) => $this->c($c))->values(),
            'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        Contact::onlyTrashed()->where('workspace_id', $this->wid($request))->findOrFail($id)->restore();
        return response()->json(['message' => 'Contact restored.']);
    }

    public function forceDelete(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'dangerous')) return $deny;
        Contact::onlyTrashed()->where('workspace_id', $this->wid($request))->findOrFail($id)->forceDelete();
        return response()->json(['message' => 'Contact permanently deleted.']);
    }

    public function restoreAll(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $q = Contact::onlyTrashed()->where('workspace_id', $this->wid($request));
        $n = $q->count();
        $q->restore();
        return response()->json(['message' => "{$n} contact(s) restored."]);
    }

    public function emptyTrash(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'dangerous')) return $deny;
        $q = Contact::onlyTrashed()->where('workspace_id', $this->wid($request));
        $n = $q->count();
        $q->forceDelete();
        return response()->json(['message' => "{$n} contact(s) permanently deleted."]);
    }

    // ── Duplicates & merge ──────────────────────────────────────────────

    public function duplicates(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $wid = $this->wid($request);
        $emails = Contact::where('workspace_id', $wid)->whereNotNull('email')->where('email', '!=', '')
            ->selectRaw('LOWER(email) as normalized_email, COUNT(*) as cnt')
            ->groupByRaw('LOWER(email)')->having('cnt', '>', 1)->limit(100)->pluck('normalized_email');

        $groups = [];
        foreach ($emails as $email) {
            $contacts = Contact::where('workspace_id', $wid)->whereRaw('LOWER(email) = ?', [$email])
                ->orderByDesc('lead_score')->orderByDesc('updated_at')->limit(20)->get();
            if ($contacts->count() < 2) continue;
            $groups[] = ['match_type' => 'email', 'match_value' => $email, 'contacts' => $contacts->map(fn ($c) => $this->c($c))->values()];
        }
        return response()->json(['data' => $groups]);
    }

    public function merge(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate(['primary_id' => 'required|integer', 'secondary_ids' => 'required|array|min:1|max:20', 'secondary_ids.*' => 'integer']);
        $wid = $this->wid($request);
        $secondaryIds = array_values(array_diff($d['secondary_ids'], [$d['primary_id']]));
        if (!$secondaryIds) return response()->json(['message' => 'No secondary contacts to merge.'], 422);

        $primary = Contact::where('workspace_id', $wid)->with('tags')->find($d['primary_id']);
        if (!$primary) return response()->json(['message' => 'Primary contact not found.'], 404);
        $secondaries = Contact::where('workspace_id', $wid)->whereIn('id', $secondaryIds)->with('tags')->get();
        if ($secondaries->isEmpty()) return response()->json(['message' => 'Secondary contacts not found.'], 404);
        $secondaryIds = $secondaries->pluck('id')->all();

        DB::transaction(function () use ($primary, $secondaries, $secondaryIds) {
            DB::table('conversations')->whereIn('contact_id', $secondaryIds)->update(['contact_id' => $primary->id]);
            DB::table('deals')->whereIn('contact_id', $secondaryIds)->update(['contact_id' => $primary->id]);

            $existing = $primary->tags->pluck('id')->all();
            $all = $existing;
            foreach ($secondaries as $s) $all = array_merge($all, $s->tags->pluck('id')->all());
            $all = array_values(array_unique($all));
            $primary->tags()->sync($all);
            foreach (array_diff($all, $existing) as $tagId) {
                $tag = Tag::find($tagId);
                if ($tag) { try { event(new \App\Events\TagAdded($primary, $tag)); } catch (\Throwable $e) {} }
            }

            foreach (['phone', 'company', 'job_title', 'city', 'country', 'timezone', 'avatar_path', 'last_contacted_at', 'last_seen_at'] as $f) {
                if (empty($primary->{$f})) {
                    foreach ($secondaries as $s) {
                        if (!empty($s->{$f})) { $primary->{$f} = $s->{$f}; break; }
                    }
                }
            }
            $custom = $primary->custom_fields ?? [];
            foreach ($secondaries as $s) {
                foreach (($s->custom_fields ?? []) as $k => $v) {
                    if (!isset($custom[$k]) || $custom[$k] === null || $custom[$k] === '') $custom[$k] = $v;
                }
            }
            $primary->custom_fields = $custom;
            $primary->lead_score = max($primary->lead_score ?? 0, ...$secondaries->pluck('lead_score')->map(fn ($s) => $s ?? 0)->all());
            $primary->save();

            Contact::whereIn('id', $secondaryIds)->delete();   // soft-delete → recoverable from Trash
        });

        $u = $request->user();
        DB::table('audit_logs')->insert([
            'auditable_type' => 'App\\Models\\Contact', 'auditable_id' => $primary->id, 'event' => 'contacts_merged',
            'actor_type' => 'user', 'actor_id' => $u->id, 'actor_name' => $u->name,
            'old_values' => json_encode(['secondary_contact_ids' => $secondaryIds]),
            'new_values' => json_encode(['primary_contact_id' => $primary->id, 'merged_count' => count($secondaryIds)]),
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['message' => count($secondaryIds) . ' duplicate contact(s) merged.', 'data' => $this->c($primary->fresh())]);
    }

    // ── Bulk actions ────────────────────────────────────────────────────

    /** POST contacts-bulk {ids[], action: delete|add_tag|remove_tag|add_to_group, tag_id?, group_id?} */
    public function bulk(Request $request): JsonResponse
    {
        $d = $request->validate([
            'ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer',
            'action' => 'required|in:delete,add_tag,remove_tag,add_to_group',
            'tag_id' => 'nullable|integer', 'group_id' => 'nullable|integer',
        ]);
        $wid = $this->wid($request);
        $level = $d['action'] === 'delete' ? 'manage' : 'interact';
        if ($deny = $this->denyUnlessRole($request, $level)) return $deny;

        $contacts = Contact::where('workspace_id', $wid)->whereIn('id', $d['ids'])->get();
        switch ($d['action']) {
            case 'delete':
                Contact::where('workspace_id', $wid)->whereIn('id', $contacts->pluck('id'))->delete();
                break;
            case 'add_tag':
            case 'remove_tag':
                $tag = Tag::where('workspace_id', $wid)->find($d['tag_id'] ?? 0);
                if (!$tag) return response()->json(['message' => 'Tag not found.'], 404);
                foreach ($contacts as $c) {
                    $d['action'] === 'add_tag' ? $c->tags()->syncWithoutDetaching([$tag->id]) : $c->tags()->detach($tag->id);
                }
                break;
            case 'add_to_group':
                $g = ContactList::where('workspace_id', $wid)->find($d['group_id'] ?? 0);
                if (!$g) return response()->json(['message' => 'Group not found.'], 404);
                $new = array_diff($contacts->pluck('id')->all(), $g->contacts()->pluck('contacts.id')->all());
                $pivot = [];
                foreach ($new as $cid) $pivot[$cid] = ['added_at' => now()];
                if ($pivot) { $g->contacts()->attach($pivot); $g->refreshContactsCount(); }
                break;
        }
        return response()->json(['message' => 'Done.', 'affected' => $contacts->count()]);
    }

    /** GET contacts/{id}/timeline — recent conversations, deals and activity for a contact. */
    public function timeline(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $wid = $this->wid($request);
        $c = Contact::where('workspace_id', $wid)->findOrFail($id);

        $convs = DB::table('conversations')->where('workspace_id', $wid)->where('contact_id', $c->id)->whereNull('deleted_at')
            ->orderByDesc('last_message_at')->limit(30)->get(['id', 'channel', 'subject', 'status', 'last_message_at'])
            ->map(fn ($r) => ['type' => 'conversation', 'id' => $r->id, 'title' => $r->subject ?: '(no subject)', 'channel' => $r->channel, 'status' => $r->status, 'at' => $r->last_message_at]);
        $deals = DB::table('deals')->where('workspace_id', $wid)->where('contact_id', $c->id)->whereNull('deleted_at')
            ->orderByDesc('updated_at')->limit(30)->get(['id', 'title', 'value', 'status', 'updated_at'])
            ->map(fn ($r) => ['type' => 'deal', 'id' => $r->id, 'title' => $r->title, 'value' => (float) $r->value, 'status' => $r->status, 'at' => $r->updated_at]);

        $items = $convs->concat($deals)->sortByDesc('at')->values();
        return response()->json(['data' => $items]);
    }
}
