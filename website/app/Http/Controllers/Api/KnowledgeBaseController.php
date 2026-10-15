<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KbDocumentResource;
use App\Models\KbDocument;
use App\Services\AI\AIManager;
use App\Services\AI\RAGService;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class KnowledgeBaseController extends Controller
{
    use AuthorizesApiActions;
    /**
     * List KB documents with filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $query = KbDocument::where('workspace_id', $workspaceId);

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by category
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Search by title or content
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('question', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['title', 'type', 'status', 'usage_count', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        return KbDocumentResource::collection($query->paginate($perPage));
    }

    /**
     * Show a single KB document.
     */
    public function show(Request $request, int $id): KbDocumentResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $document = KbDocument::where('workspace_id', $workspaceId)->find($id);

        if (!$document) {
            return response()->json(['message' => 'Knowledge base document not found.'], 404);
        }

        return new KbDocumentResource($document);
    }

    /**
     * Create a new KB document.
     */
    public function store(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        // Enforce plan limit on KB documents
        if ($deny = $this->denyUnlessPlanAllows($request, 'kb_documents')) return $deny;

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:document,qa,url,file',
            'title' => 'required|string|max:500',
            'content' => 'nullable|string',
            'question' => 'nullable|string|max:1000',
            'answer' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'is_priority' => 'nullable|boolean',
            'source_url' => 'nullable|url|max:2048',
            'file' => 'nullable|file|mimes:pdf,txt,md,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'workspace_id' => $workspaceId,
            'type' => $request->input('type'),
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'question' => $request->input('question'),
            'answer' => $request->input('answer'),
            'category' => $request->input('category'),
            'is_priority' => $request->boolean('is_priority', false),
            'source_url' => $request->input('source_url'),
            // kb_documents.status enum: uploading/extracting/chunking/embedding/ready/failed/paused.
            // 'extracting' marks the row as "work in progress" until the
            // ProcessKBDocumentJob walks through the remaining stages.
            'status' => 'extracting',
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // Security: Scan uploaded file before processing
            $scanner = app(\App\Services\FileSecurityService::class);
            $scanResult = $scanner->scan($file);
            if (!$scanResult->passed) {
                return response()->json(['message' => $scanResult->message], 422);
            }

            $path = $file->store("kb/{$workspaceId}", 'local');
            $data['file_path'] = $path;
            $data['file_type'] = $file->getClientMimeType();
            $data['file_size'] = $file->getSize();
        }

        $document = KbDocument::create($data);

        // Queue the document for chunking and embedding
        // In a production system this would dispatch a job:
        // ProcessKbDocumentJob::dispatch($document);
        // For now, mark simple text documents as ready immediately
        if (in_array($document->type, ['document', 'qa']) && $document->content) {
            $document->update(['status' => 'ready']);
        }

        return (new KbDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a KB document.
     */
    public function update(Request $request, int $id): KbDocumentResource|JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $document = KbDocument::where('workspace_id', $workspaceId)->find($id);

        if (!$document) {
            return response()->json(['message' => 'Knowledge base document not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:500',
            'content' => 'nullable|string',
            'question' => 'nullable|string|max:1000',
            'answer' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'is_priority' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $document->update($validator->validated());

        return new KbDocumentResource($document);
    }

    /**
     * Delete a KB document (soft delete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        $document = KbDocument::where('workspace_id', $workspaceId)->find($id);

        if (!$document) {
            return response()->json(['message' => 'Knowledge base document not found.'], 404);
        }

        // Also delete associated chunks
        $document->kbChunks()->delete();
        $document->delete();

        return response()->json(null, 204);
    }

    /**
     * Scrape a URL and create a KB document from its content.
     */
    public function scrape(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'create')) return $deny;

        $workspaceId = $request->user()->active_workspace_id;

        // Enforce plan limit on KB documents (scrape creates a document)
        if ($deny = $this->denyUnlessPlanAllows($request, 'kb_documents')) return $deny;

        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'title' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $url = $request->input('url');

        // SSRF protection: block private/internal IP ranges
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');

        if (!in_array($scheme, ['http', 'https'])) {
            return response()->json(['message' => 'Only HTTP and HTTPS URLs are allowed.'], 422);
        }

        // Resolve hostname to IP and check against blocked ranges
        $ips = gethostbynamel($host);
        if ($ips === false) {
            return response()->json(['message' => 'Could not resolve hostname.'], 422);
        }

        $blockedCidrs = [
            '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
            '169.254.0.0/16', '0.0.0.0/8', '100.64.0.0/10',
            '198.18.0.0/15', '240.0.0.0/4',
        ];

        foreach ($ips as $ip) {
            // Block IPv6 addresses entirely (could be used to bypass IPv4 CIDR checks)
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // Allow only if it's a publicly routable IPv6 address (block loopback, link-local, ULA)
                if ($ip === '::1' || str_starts_with($ip, 'fe80:') || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
                    return response()->json(['message' => 'URL points to a private/internal network address.'], 422);
                }
                continue; // Allow public IPv6
            }

            // Check IPv4 against blocked CIDR ranges
            foreach ($blockedCidrs as $cidr) {
                [$subnet, $mask] = explode('/', $cidr);
                if ((ip2long($ip) & (~((1 << (32 - (int)$mask)) - 1))) === ip2long($subnet)) {
                    return response()->json(['message' => 'URL points to a private/internal network address.'], 422);
                }
            }
        }

        try {
            $response = Http::timeout(120)
                ->maxRedirects(3)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'MailTrixy') . '-KB-Scraper/1.0',
                ])
                ->withOptions(['stream' => true])
                ->get($url);

            // Check content length before reading body (prevent memory exhaustion)
            $contentLength = (int) ($response->header('Content-Length') ?? 0);
            if ($contentLength > 5 * 1024 * 1024) { // 5MB max
                return response()->json(['message' => 'URL content too large (max 5MB).'], 422);
            }

            if (!$response->successful()) {
                return response()->json([
                    'message' => "Failed to fetch URL: HTTP {$response->status()}",
                ], 422);
            }

            $html = $response->body();

            // Strip HTML tags, scripts, and styles to extract text
            $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
            $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $text);
            $text = strip_tags($text);
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            if (empty($text)) {
                return response()->json([
                    'message' => 'Could not extract text content from the URL.',
                ], 422);
            }

            // Truncate to a reasonable size
            $text = mb_substr($text, 0, 50000);

            // Extract title from HTML if not provided
            $title = $request->input('title');
            if (!$title) {
                preg_match('/<title>(.*?)<\/title>/is', $html, $titleMatch);
                $title = $titleMatch[1] ?? parse_url($url, PHP_URL_HOST);
                $title = html_entity_decode(trim($title));
            }

            $document = KbDocument::create([
                'workspace_id' => $workspaceId,
                'type' => 'url',
                'title' => mb_substr($title, 0, 500),
                'content' => $text,
                'source_url' => $url,
                'category' => $request->input('category'),
                'status' => 'ready',
            ]);

            return (new KbDocumentResource($document))
                ->response()
                ->setStatusCode(201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to scrape URL: ' . $e->getMessage(),
            ], 422);
        }
    }
}
