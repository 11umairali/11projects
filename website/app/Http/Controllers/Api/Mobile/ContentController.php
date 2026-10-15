<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\HelpArticle;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

/** Activity feed, help centre, email templates. */
class ContentController extends Controller
{
    use AuthorizesApiActions;

    public function activity(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $wid = $request->user()->active_workspace_id;

        $q = Activity::query()->where('properties->workspace_id', $wid)->with(['causer', 'subject'])->latest();
        if ($t = $request->input('type')) {
            $parts = explode('.', $t, 2);
            $q->where('log_name', $parts[0]);
            if (isset($parts[1])) $q->where('event', $parts[1]);
        }
        if ($s = $request->input('search')) $q->where('description', 'like', "%{$s}%");

        $p = $q->paginate(min((int) $request->input('per_page', 25), 100));
        $rows = $p->getCollection()->map(fn ($a) => [
            'id'          => $a->id,
            'log_name'    => $a->log_name,
            'event'       => $a->event,
            'description' => $a->description,
            'causer'      => $a->causer?->name ?? 'System',
            'subject'     => $a->subject_type ? class_basename($a->subject_type) : null,
            'created_at'  => $a->created_at?->toIso8601String(),
        ])->values();

        return response()->json(['data' => $rows, 'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function helpArticles(Request $request): JsonResponse
    {
        $q = HelpArticle::published()->orderBy('sort_order');
        if ($c = $request->input('category')) $q->where('category', $c);
        if ($s = $request->input('search')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('excerpt', 'like', "%{$s}%")->orWhere('content', 'like', "%{$s}%"));
        }
        $rows = $q->get(['id', 'slug', 'title', 'excerpt', 'category', 'icon'])->values();
        return response()->json(['data' => $rows, 'categories' => HelpArticle::published()->distinct()->orderBy('category')->pluck('category')]);
    }

    public function helpArticle(Request $request, int $id): JsonResponse
    {
        $a = HelpArticle::published()->findOrFail($id);
        return response()->json(['data' => $a->only(['id', 'slug', 'title', 'content', 'excerpt', 'category', 'icon', 'helpful_count', 'not_helpful_count'])]);
    }

    public function helpVote(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['helpful' => 'required|boolean']);
        $key = ($d['helpful'] ? 'helpful_' : 'not_helpful_') . $request->user()->id . '_' . $id;
        if (!Cache::has($key)) {
            Cache::put($key, true, 60);
            HelpArticle::published()->where('id', $id)->increment($d['helpful'] ? 'helpful_count' : 'not_helpful_count');
        }
        return response()->json(['message' => 'Thanks for your feedback.']);
    }

    public function templates(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = EmailTemplate::where('workspace_id', $request->user()->active_workspace_id)->orderByDesc('id')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'category' => $t->category, 'is_default' => (bool) $t->is_default,
                'usage_count' => (int) $t->usage_count, 'thumbnail_url' => $t->thumbnail_path ? asset('storage/' . $t->thumbnail_path) : null,
                'blocks' => $t->blocks]);
        return response()->json(['data' => $rows]);
    }

    public function deleteTemplate(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        EmailTemplate::where('workspace_id', $request->user()->active_workspace_id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Template deleted.']);
    }

    /** GET temp-mail-domains: the stock /temp-mail/domains filters a global table by workspace_id and 500s. */
    public function tempMailDomains(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = \App\Models\TempMailDomain::where('status', 'active')
            ->get(['id', 'uuid', 'domain', 'display_name', 'default_lifetime_hours']);
        return response()->json(['data' => $rows]);
    }
}
