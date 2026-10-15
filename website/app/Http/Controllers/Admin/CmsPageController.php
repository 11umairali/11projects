<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CmsPageController extends Controller
{
    /**
     * List all pages with search, type filter, and pagination.
     */
    public function index(Request $request): View
    {
        $query = Page::query();

        // Search by title or slug
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($type = $request->input('type')) {
            if (in_array($type, ['landing', 'static', 'blog'])) {
                $query->ofType($type);
            }
        }

        // Filter by status
        if ($status = $request->input('status')) {
            if ($status === 'published') {
                $query->where('is_published', true);
            } elseif ($status === 'draft') {
                $query->where('is_published', false);
            }
        }

        $query->orderBy('type')->orderByDesc('updated_at');

        $pages = $query->paginate(25)->withQueryString();

        return view('admin.cms.index', compact('pages'));
    }

    /**
     * Show create page form.
     */
    public function create(): View
    {
        return view('admin.cms.create');
    }

    /**
     * Store a new page.
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'slug' => 'required|string|max:200|unique:pages,slug',
            'content' => 'nullable|string',
            'type' => 'required|in:landing,static,blog',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'meta_image' => 'nullable|string|max:500',
            'is_published' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $page = Page::create([
            'title' => $request->input('title'),
            'slug' => $request->input('slug') ?: Str::slug($request->input('title')),
            'content' => $request->input('content'),
            'type' => $request->input('type'),
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'meta_image' => $request->input('meta_image'),
            'is_published' => $request->boolean('is_published', false),
        ]);

        $user = Auth::user();
        DB::table('audit_logs')->insert([
            'auditable_type' => 'page',
            'auditable_id' => $page->id,
            'event' => 'page_created',
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'new_values' => json_encode(['title' => $page->title, 'slug' => $page->slug, 'type' => $page->type]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.cms.index')
            ->with('success', "Page '{$page->title}' created successfully.");
    }

    /**
     * Show a single page (redirects to edit).
     */
    public function show(Page $cm): View
    {
        return view('admin.cms.edit', ['page' => $cm]);
    }

    /**
     * Show the edit form for a page.
     */
    public function edit(Page $cm): View
    {
        return view('admin.cms.edit', ['page' => $cm]);
    }

    /**
     * Update a page.
     */
    public function update(Request $request, Page $cm): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'slug' => 'required|string|max:200|unique:pages,slug,' . $cm->id,
            'content' => 'nullable|string',
            'type' => 'required|in:landing,static,blog',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'meta_image' => 'nullable|string|max:500',
            'is_published' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $cm->update([
            'title' => $request->input('title'),
            'slug' => $request->input('slug') ?: Str::slug($request->input('title')),
            'content' => $request->input('content'),
            'type' => $request->input('type'),
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'meta_image' => $request->input('meta_image'),
            'is_published' => $request->boolean('is_published', false),
        ]);

        return redirect()->route('admin.cms.index')
            ->with('success', "Page '{$cm->title}' updated successfully.");
    }

    /**
     * Delete a page.
     */
    public function destroy(Request $request, Page $cm): RedirectResponse
    {
        $title = $cm->title;

        $user = Auth::user();
        DB::table('audit_logs')->insert([
            'auditable_type' => 'page',
            'auditable_id' => $cm->id,
            'event' => 'page_deleted',
            'actor_type' => 'admin',
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'old_values' => json_encode(['title' => $cm->title, 'slug' => $cm->slug, 'type' => $cm->type]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cm->delete();

        return redirect()->route('admin.cms.index')
            ->with('success', "Page '{$title}' deleted.");
    }

    /**
     * Bulk delete pages.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        $count = Page::whereIn('id', $request->ids)->delete();

        return back()->with('success', "{$count} page(s) deleted.");
    }

    /**
     * Export pages as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Page::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            if (in_array($type, ['landing', 'static', 'blog'])) {
                $query->ofType($type);
            }
        }

        if ($status = $request->input('status')) {
            if ($status === 'published') {
                $query->where('is_published', true);
            } elseif ($status === 'draft') {
                $query->where('is_published', false);
            }
        }

        $pages = $query->orderBy('type')->orderByDesc('updated_at')->get();

        $filename = 'pages-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($pages) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Title', 'Slug', 'Type', 'Status', 'Meta Title', 'Meta Description', 'Created At', 'Updated At']);
            foreach ($pages as $page) {
                fputcsv($handle, [
                    $page->id,
                    $page->title,
                    $page->slug,
                    ucfirst($page->type),
                    $page->is_published ? 'Published' : 'Draft',
                    $page->meta_title ?? '',
                    $page->meta_description ?? '',
                    $page->created_at,
                    $page->updated_at,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
