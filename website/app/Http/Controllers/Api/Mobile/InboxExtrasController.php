<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Tag;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Full message body for the app reader, quick replies CRUD, tags CRUD. */
class InboxExtrasController extends Controller
{
    use AuthorizesApiActions;

    private function wid(Request $r): int { return (int) $r->user()->active_workspace_id; }

    /** GET inbox/messages/{id}/body → {text, html}. Text is decoded plain text; html is sanitised. */
    public function messageBody(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $m = Message::whereHas('conversation', fn ($q) => $q->where('workspace_id', $this->wid($request)))->findOrFail($id);

        // Images first (the sanitiser would drop cid: sources): cid: and http(s) sources become signed https URLs on this server.
        $raw = $m->body_html ? EmailAssetController::rewrite((string) $m->body_html, $m) : null;
        $html = $raw ? \App\Helpers\HtmlSanitizer::sanitize($raw) : null;
        $text = trim((string) $m->body_text);
        if ($text === '' && $html) {
            $t = preg_replace(['#<(script|style)[^>]*>.*?</\1>#is', '#<br\s*/?>#i', '#</(p|div|li|tr|h[1-6])>#i'], ['', "\n", "\n"], $html);
            $text = trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        $atts = $m->attachments()->get()->map(fn ($a) => ['id' => $a->id, 'filename' => $a->original_filename ?: $a->filename, 'mime' => $a->mime_type,
            'size' => (int) $a->size, 'size_human' => $a->size_for_humans, 'inline' => !empty($a->content_id)])->values();
        return response()->json(['data' => ['id' => $m->id, 'text' => $text, 'html' => $html, 'subject' => $m->subject, 'from_name' => $m->from_name, 'from_email' => $m->from_email,
            'to' => $m->to_emails ?? [], 'cc' => $m->cc_emails ?? [], 'date' => $m->created_at?->toIso8601String(), 'attachments' => $atts]]);
    }

    // ── Quick replies (canned responses) ──

    private function cr(CannedResponse $r): array
    {
        return ['id' => $r->id, 'title' => $r->title, 'shortcut' => $r->shortcut, 'content' => $r->content, 'category' => $r->category,
            'channels' => $r->channels ?? [], 'scope' => $r->scope, 'usage_count' => (int) $r->usage_count, 'mine' => (int) $r->user_id === (int) request()->user()->id];
    }

    public function quickReplies(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $uid = $request->user()->id;
        $rows = CannedResponse::where('workspace_id', $this->wid($request))
            ->where(fn ($q) => $q->where('scope', '!=', 'personal')->orWhereNull('scope')->orWhere('user_id', $uid))
            ->orderBy('title')->get()->map(fn ($r) => $this->cr($r));
        return response()->json(['data' => $rows]);
    }

    private function crRules(): array
    {
        return ['title' => 'required|string|max:255', 'shortcut' => 'nullable|string|max:50', 'content' => 'required|string|max:10000',
            'category' => 'nullable|string|max:100', 'scope' => 'nullable|in:personal,team,workspace', 'channels' => 'nullable|array'];
    }

    public function storeQuickReply(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $d = $request->validate($this->crRules());
        $r = CannedResponse::create($d + ['workspace_id' => $this->wid($request), 'user_id' => $request->user()->id, 'scope' => (($d['scope'] ?? 'team') === 'workspace') ? 'team' : ($d['scope'] ?? 'team')]);
        return response()->json(['data' => $this->cr($r)], 201);
    }

    public function updateQuickReply(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $d = $request->validate($this->crRules());
        if (($d['scope'] ?? null) === 'workspace') $d['scope'] = 'team';
        $r = CannedResponse::where('workspace_id', $this->wid($request))->findOrFail($id);
        $r->update($d);
        return response()->json(['data' => $this->cr($r->fresh())]);
    }

    public function destroyQuickReply(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        CannedResponse::where('workspace_id', $this->wid($request))->findOrFail($id)->delete();
        return response()->json(['message' => 'Quick reply deleted.']);
    }

    // ── Tags ──

    public function storeTag(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $d = $request->validate(['name' => 'required|string|max:50', 'color' => 'nullable|string|max:20']);
        $wid = $this->wid($request);
        if (Tag::where('workspace_id', $wid)->where('name', $d['name'])->exists()) {
            return response()->json(['message' => 'A tag with this name already exists.', 'errors' => ['name' => ['Already exists.']]], 422);
        }
        $t = Tag::create($d + ['workspace_id' => $wid, 'color' => $d['color'] ?? '#5F33E1']);
        return response()->json(['data' => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color]], 201);
    }

    /** POST contacts/{id}/tags {tag_id} and DELETE contacts/{id}/tags/{tagId} */
    public function attachTag(Request $request, int $contactId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $d = $request->validate(['tag_id' => 'required|integer']);
        $wid = $this->wid($request);
        $c = Contact::where('workspace_id', $wid)->findOrFail($contactId);
        $t = Tag::where('workspace_id', $wid)->findOrFail($d['tag_id']);
        $c->tags()->syncWithoutDetaching([$t->id]);
        return response()->json(['message' => 'Tag added.']);
    }

    public function detachTag(Request $request, int $contactId, int $tagId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $c = Contact::where('workspace_id', $this->wid($request))->findOrFail($contactId);
        $c->tags()->detach($tagId);
        return response()->json(['message' => 'Tag removed.']);
    }
}
