<?php

namespace App\Livewire\KnowledgeBase;

use App\Exceptions\PlanLimitReachedException;
use App\Jobs\ProcessKBDocumentJob;
use App\Jobs\ScrapeWebsiteJob;
use App\Models\KbDocument;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class DocumentManager extends Component
{
    use AuthorizesWorkspaceActions;
    use WithFileUploads;

    public string $activeTab = 'documents';

    // Document upload
    public $uploadFile = null;

    // Website scraping
    public string $scrapeUrl = '';

    // Q&A form
    public string $qaQuestion = '';
    public string $qaAnswer = '';

    // Inline edit Q&A
    public ?int $editingQaId = null;
    public string $editQuestion = '';
    public string $editAnswer = '';

    /**
     * Maximum allowed response size for website scraping (5 MB).
     */
    protected const MAX_SCRAPE_RESPONSE_BYTES = 5 * 1024 * 1024;

    public function mount(): void
    {
        if (! $this->authorizeWorkspaceAction('view')) {
            return;
        }
    }

    public function updatedUploadFile(): void
    {
        if ($this->uploadFile) {
            $this->uploadDocument();
        }
    }

    public function uploadDocument(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        // Enforce plan limit for knowledge base documents
        try {
            $workspace = Workspace::findOrFail(auth()->user()->active_workspace_id);
            app(PlanLimitService::class)->assertCanCreate($workspace, 'kb_documents');
        } catch (PlanLimitReachedException $e) {
            $this->uploadFile = null;
            session()->flash('error', 'You have reached your plan limit for knowledge base documents. Please upgrade.');
            return;
        }

        $this->validate([
            'uploadFile' => 'required|file|mimes:pdf,docx,txt,csv,xlsx,md|mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/markdown,text/x-markdown|max:25600',
        ]);

        // Check storage limit before processing file
        $storageLimitMb = $workspace->subscription?->plan?->featureLimit('storage_mb');
        if ($storageLimitMb) {
            $currentUsageMb = KbDocument::where('workspace_id', $workspace->id)
                ->sum('file_size') / (1024 * 1024);
            $fileSizeMb = $this->uploadFile->getSize() / (1024 * 1024);
            if (($currentUsageMb + $fileSizeMb) > $storageLimitMb) {
                $this->uploadFile = null;
                $this->addError('uploadFile', 'Storage limit reached (' . $storageLimitMb . 'MB). Please upgrade your plan.');
                return;
            }
        }

        $workspaceId = auth()->user()->active_workspace_id;
        $file = $this->uploadFile;
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();

        // MN-010: Check for duplicate content via SHA-256 hash
        $contentHash = hash_file('sha256', $file->getRealPath());
        $existingDoc = KbDocument::where('workspace_id', $workspaceId)
            ->where('content_hash', $contentHash)
            ->first();

        if ($existingDoc) {
            $this->uploadFile = null;
            session()->flash('error', "Duplicate content detected. This file matches the existing document \"{$existingDoc->title}\".");
            return;
        }

        // Store file
        $path = $file->store("kb/{$workspaceId}", 'local');

        // Create DB record
        $document = KbDocument::create([
            'workspace_id' => $workspaceId,
            'type' => 'document',
            'title' => $originalName,
            'content_hash' => $contentHash,
            'file_path' => $path,
            'file_type' => $extension,
            'file_size' => $size,
            'status' => 'extracting',
        ]);

        // Dispatch processing job
        // Runs in the same PHP process right after the HTTP response is sent
// — no queue worker required, no minute-tick delay, no "stuck" status.
ProcessKBDocumentJob::dispatchAfterResponse($document);

        $this->uploadFile = null;
        session()->flash('success', "Document \"{$originalName}\" uploaded and queued for processing.");
    }

    public function deleteDocument(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $document = KbDocument::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($id);

        $title = $document->title;

        // Delete file from storage if exists
        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        // Delete associated chunks
        $document->kbChunks()->delete();
        $document->delete();

        session()->flash('success', "Document \"{$title}\" deleted.");
    }

    public function reprocessDocument(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        // Atomic status transition: only update if NOT already processing.
        // This prevents race conditions from duplicate click / concurrent requests.
        $updated = KbDocument::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $id)
            ->where('status', '!=', 'extracting')
            ->update(['status' => 'extracting', 'error_message' => null]);

        if ($updated === 0) {
            session()->flash('error', 'This document is already being processed. Please wait.');
            return;
        }

        $document = KbDocument::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($id);

        // Runs in the same PHP process right after the HTTP response is sent
// — no queue worker required, no minute-tick delay, no "stuck" status.
ProcessKBDocumentJob::dispatchAfterResponse($document);

        session()->flash('success', 'Document queued for reprocessing.');
    }

    public function scrapeWebsite(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        // Enforce plan limit for knowledge base documents
        try {
            $workspace = Workspace::findOrFail(auth()->user()->active_workspace_id);
            app(PlanLimitService::class)->assertCanCreate($workspace, 'kb_documents');
        } catch (PlanLimitReachedException $e) {
            session()->flash('error', 'You have reached your plan limit for knowledge base documents. Please upgrade.');
            return;
        }

        $this->validate([
            'scrapeUrl' => 'required|url|max:500',
        ]);

        // SSRF protection: block internal/private network URLs
        if (! $this->isUrlSafe($this->scrapeUrl)) {
            $this->addError('scrapeUrl', 'This URL is not allowed. Only public HTTP/HTTPS URLs are permitted.');
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        // Check for duplicate URL
        $exists = KbDocument::where('workspace_id', $workspaceId)
            ->where('source_url', $this->scrapeUrl)
            ->where('type', 'website')
            ->exists();

        if ($exists) {
            $this->addError('scrapeUrl', 'This URL has already been scraped.');
            return;
        }

        // FIX-105: Pre-check content size with HEAD request before dispatching job
        try {
            $headResponse = \Illuminate\Support\Facades\Http::timeout(120)->head($this->scrapeUrl);
            $contentLength = (int) ($headResponse->header('Content-Length') ?? 0);
            if ($contentLength > self::MAX_SCRAPE_RESPONSE_BYTES) {
                $maxMb = round(self::MAX_SCRAPE_RESPONSE_BYTES / 1024 / 1024, 1);
                $this->addError('scrapeUrl', "Page is too large ({$maxMb}MB limit). Try a more specific URL.");
                return;
            }
        } catch (\Exception $e) {
            // HEAD failed — proceed with job dispatch, job will enforce the limit
        }

        ScrapeWebsiteJob::dispatch($this->scrapeUrl, $workspaceId, self::MAX_SCRAPE_RESPONSE_BYTES);

        $this->scrapeUrl = '';
        session()->flash('success', 'Website scraping job dispatched. This may take a few minutes.');
    }

    /**
     * Validate that a URL does not target internal/private networks (SSRF protection).
     *
     * Blocks:
     *   - Private IP ranges (10.x, 172.16-31.x, 192.168.x, 127.x, 169.254.x)
     *   - Localhost aliases
     *   - Cloud metadata endpoints (AWS, GCP, Azure)
     *   - Non-HTTP(S) schemes
     */
    private function isUrlSafe(string $url): bool
    {
        $parsed = parse_url($url);

        // Must be http or https
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'])) {
            return false;
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return false;
        }

        $hostLower = strtolower($host);

        // Block known dangerous hostnames
        $blockedHosts = [
            'localhost',
            '0.0.0.0',
            'metadata.google.internal',
            'metadata.google',
            '169.254.169.254',          // AWS/GCP metadata endpoint
            'metadata.azure.internal',  // Azure metadata
        ];
        if (in_array($hostLower, $blockedHosts)) {
            return false;
        }

        // If the host is an IP address, block private/reserved ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // filter_var returns false if the IP IS private/reserved when these
            // flags are used -- so a return of false means it's private.
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }

            // Explicit blocks for edge cases the filter may not catch
            if ($host === '127.0.0.1' || $host === '0.0.0.0'
                || str_starts_with($host, '169.254.')
                || str_starts_with($host, '10.')
                || str_starts_with($host, '172.16.') || str_starts_with($host, '172.17.')
                || str_starts_with($host, '172.18.') || str_starts_with($host, '172.19.')
                || str_starts_with($host, '172.20.') || str_starts_with($host, '172.21.')
                || str_starts_with($host, '172.22.') || str_starts_with($host, '172.23.')
                || str_starts_with($host, '172.24.') || str_starts_with($host, '172.25.')
                || str_starts_with($host, '172.26.') || str_starts_with($host, '172.27.')
                || str_starts_with($host, '172.28.') || str_starts_with($host, '172.29.')
                || str_starts_with($host, '172.30.') || str_starts_with($host, '172.31.')
                || str_starts_with($host, '192.168.')
            ) {
                return false;
            }
        }

        // Resolve hostname to IP and check that too (prevents DNS rebinding
        // against obvious private targets like "internal.corp" -> 10.0.0.1)
        $resolvedIps = gethostbynamel($hostLower);
        if ($resolvedIps) {
            foreach ($resolvedIps as $ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function addQAPair(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        // Enforce plan limit for knowledge base documents
        try {
            $workspace = Workspace::findOrFail(auth()->user()->active_workspace_id);
            app(PlanLimitService::class)->assertCanCreate($workspace, 'kb_documents');
        } catch (PlanLimitReachedException $e) {
            session()->flash('error', 'You have reached your plan limit for knowledge base documents. Please upgrade.');
            return;
        }

        $this->validate([
            'qaQuestion' => 'required|string|max:1000',
            'qaAnswer' => 'required|string|max:5000',
        ]);

        $workspaceId = auth()->user()->active_workspace_id;

        $document = KbDocument::create([
            'workspace_id' => $workspaceId,
            'type' => 'qa',
            'title' => mb_substr($this->qaQuestion, 0, 255),
            'question' => $this->qaQuestion,
            'answer' => $this->qaAnswer,
            'status' => 'extracting',
        ]);

        // Process Q&A for chunking/embedding
        // Runs in the same PHP process right after the HTTP response is sent
// — no queue worker required, no minute-tick delay, no "stuck" status.
ProcessKBDocumentJob::dispatchAfterResponse($document);

        $this->qaQuestion = '';
        $this->qaAnswer = '';
        session()->flash('success', 'Q&A pair added.');
    }

    public function startEditQA(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $qa = KbDocument::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('type', 'qa')
            ->findOrFail($id);

        $this->editingQaId = $id;
        $this->editQuestion = $qa->question ?? '';
        $this->editAnswer = $qa->answer ?? '';
    }

    public function saveEditQA(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate([
            'editQuestion' => 'required|string|max:1000',
            'editAnswer' => 'required|string|max:5000',
        ]);

        $qa = KbDocument::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('type', 'qa')
            ->findOrFail($this->editingQaId);

        $qa->update([
            'title' => mb_substr($this->editQuestion, 0, 255),
            'question' => $this->editQuestion,
            'answer' => $this->editAnswer,
            'status' => 'extracting',
        ]);

        // Re-process for updated embeddings (after-response dispatch so the
        // UI returns instantly instead of waiting on the queue worker).
        $qa->kbChunks()->delete();
        ProcessKBDocumentJob::dispatchAfterResponse($qa);

        $this->cancelEditQA();
        session()->flash('success', 'Q&A pair updated.');
    }

    public function cancelEditQA(): void
    {
        $this->editingQaId = null;
        $this->editQuestion = '';
        $this->editAnswer = '';
    }

    public function deleteQAPair(int $id): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $qa = KbDocument::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('type', 'qa')
            ->where('id', $id)
            ->first();

        if (! $qa) {
            session()->flash('error', 'Q&A pair not found.');
            return;
        }

        // Clean up chunks before deleting
        $qa->kbChunks()->delete();
        $qa->delete();

        session()->flash('success', 'Q&A pair deleted.');
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $documents = KbDocument::where('workspace_id', $workspaceId)
            ->where('type', 'document')
            ->orderByDesc('created_at')
            ->get();

        $websites = KbDocument::where('workspace_id', $workspaceId)
            ->where('type', 'website')
            ->orderByDesc('created_at')
            ->get();

        $qaPairs = KbDocument::where('workspace_id', $workspaceId)
            ->where('type', 'qa')
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'documents' => $documents->count(),
            'websites' => $websites->count(),
            'qa_pairs' => $qaPairs->count(),
            'total_chunks' => KbDocument::where('workspace_id', $workspaceId)->sum('chunks_count'),
        ];

        return view('livewire.knowledge-base.document-manager', [
            'documents' => $documents,
            'websites' => $websites,
            'qaPairs' => $qaPairs,
            'stats' => $stats,
        ]);
    }
}
