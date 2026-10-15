<?php

namespace App\Jobs;

use App\Models\KbChunk;
use App\Models\KbDocument;
use App\Services\AI\AIManager;
use App\Services\AI\RAGService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessKBDocumentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Exponential backoff intervals in seconds: 1min, 5min, 15min.
     *
     * @var array<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * The maximum number of seconds the job can run.
     * Large documents need time for chunking + embedding.
     */
    public int $timeout = 600;

    /**
     * The queue this job should be dispatched to.
     */

    /**
     * Target chunk size in characters (roughly 500-1000 tokens).
     * 1 token ~ 4 chars for English text.
     */
    private const CHUNK_SIZE_CHARS = 2000;

    /**
     * Overlap between chunks as a fraction of chunk size.
     */
    private const CHUNK_OVERLAP = 0.10;

    public function __construct(
        private readonly KbDocument $document,
    ) {}

    /**
     * The unique ID for this job (prevents duplicate processing for the same document).
     */
    public function uniqueId(): string
    {
        return 'kb-document-' . $this->document->id;
        $this->onQueue('processing');
    }

    public function handle(AIManager $aiManager, RAGService $ragService): void
    {
        $document = $this->document->fresh();

        if (!$document || $document->status === 'ready') {
            return;
        }

        try {
            // Step 1: Extract text
            $document->update(['status' => 'extracting']);
            $text = $this->extractText($document);

            if (empty(trim($text))) {
                $document->update([
                    'status' => 'failed',
                    'error_message' => 'No text content could be extracted from the document.',
                ]);
                return;
            }

            // Store extracted content on the document
            $document->update(['content' => $text]);

            // Step 2: Clean text
            $cleanText = $this->cleanText($text);

            // Step 3: Chunk text
            $document->update(['status' => 'chunking']);
            $chunks = $this->chunkText($cleanText);

            if (empty($chunks)) {
                $document->update([
                    'status' => 'failed',
                    'error_message' => 'Document produced no valid text chunks after processing.',
                ]);
                return;
            }

            // Delete any existing chunks for this document (re-processing)
            $existingChunkIds = $document->kbChunks()->pluck('id')->toArray();
            if (!empty($existingChunkIds)) {
                $ragService->deleteEmbeddings($document->workspace_id, $existingChunkIds);
                $document->kbChunks()->delete();
            }

            // Step 4 & 5: Generate embeddings and store
            $document->update(['status' => 'embedding']);
            $storedCount = 0;

            foreach ($chunks as $index => $chunkText) {
                $chunk = KbChunk::create([
                    'document_id' => $document->id,
                    'workspace_id' => $document->workspace_id,
                    'content' => $chunkText,
                    'chunk_index' => $index,
                ]);

                // Generate embedding vector
                try {
                    $vector = $aiManager->generateEmbedding($chunkText);
                    $ragService->storeEmbedding($document->workspace_id, $chunk, $vector);
                } catch (\Exception $e) {
                    Log::warning('Failed to generate/store embedding for chunk', [
                        'document_id' => $document->id,
                        'chunk_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue processing remaining chunks even if one fails
                }

                $storedCount++;
            }

            // Step 6: Mark as ready
            $document->update([
                'status' => 'ready',
                'chunks_count' => $storedCount,
                'error_message' => null,
            ]);

            Log::info('KB document processed successfully', [
                'document_id' => $document->id,
                'workspace_id' => $document->workspace_id,
                'chunks' => $storedCount,
                'type' => $document->type,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessKBDocumentJob failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $document->update([
                'status' => 'failed',
                'error_message' => mb_substr("Processing failed: {$e->getMessage()}", 0, 255),
            ]);

            throw $e;
        }
    }

    /**
     * Extract text content from the document based on its type and file format.
     */
    private function extractText(KbDocument $document): string
    {
        // Q&A type: combine question + answer
        if ($document->type === 'qa') {
            $parts = [];
            if ($document->question) {
                $parts[] = "Question: {$document->question}";
            }
            if ($document->answer) {
                $parts[] = "Answer: {$document->answer}";
            }
            return implode("\n\n", $parts);
        }

        // Website type: content should already be extracted by ScrapeWebsiteJob
        if ($document->type === 'website') {
            return $document->content ?? '';
        }

        // Document type: extract from file
        if ($document->content) {
            return $document->content;
        }

        if (!$document->file_path) {
            return '';
        }

        return $this->extractFromFile($document);
    }

    /**
     * Extract text from uploaded files based on file type.
     */
    private function extractFromFile(KbDocument $document): string
    {
        $filePath = $document->file_path;
        $fileType = strtolower($document->file_type ?? '');

        // Check if file exists in storage
        if (!Storage::exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $fileContents = Storage::get($filePath);

        return match ($fileType) {
            'txt', 'md', 'csv' => $fileContents,
            'pdf' => $this->extractFromPdf($fileContents),
            'docx' => $this->extractFromDocx($filePath),
            default => $fileContents, // Attempt raw text for unknown types
        };
    }

    /**
     * Extract text from PDF using smalot/pdfparser if available.
     */
    private function extractFromPdf(string $pdfContents): string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            Log::warning('smalot/pdfparser not installed, cannot extract PDF text. Install with: composer require smalot/pdfparser');
            throw new \RuntimeException(
                'PDF parsing requires the smalot/pdfparser package. Install it with: composer require smalot/pdfparser'
            );
        }

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseContent($pdfContents);

            return $pdf->getText();
        } catch (\Exception $e) {
            throw new \RuntimeException("PDF extraction failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Extract text from DOCX using ZipArchive to read document.xml.
     */
    private function extractFromDocx(string $storagePath): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($tempPath, Storage::get($storagePath));

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tempPath) !== true) {
                throw new \RuntimeException('Could not open DOCX file.');
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if (!$xml) {
                throw new \RuntimeException('DOCX file has no document.xml.');
            }

            // Strip XML tags to extract text
            $text = strip_tags($xml);
            // Clean up excessive whitespace
            $text = preg_replace('/\s+/', ' ', $text);

            return trim($text);
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Clean extracted text: normalize whitespace, remove control characters.
     */
    private function cleanText(string $text): string
    {
        // Remove null bytes and other control characters (keep newlines and tabs)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Collapse multiple blank lines into two
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Collapse multiple spaces (but not newlines) into one
        $text = preg_replace('/[^\S\n]+/', ' ', $text);

        return trim($text);
    }

    /**
     * Split text into overlapping chunks of approximately CHUNK_SIZE_CHARS characters.
     *
     * Splits on paragraph boundaries when possible, falling back to sentence
     * boundaries, then word boundaries.
     *
     * @return string[]
     */
    private function chunkText(string $text): array
    {
        $chunkSize = self::CHUNK_SIZE_CHARS;
        $overlapSize = (int) ($chunkSize * self::CHUNK_OVERLAP);

        $textLength = mb_strlen($text);

        // Short text: return as single chunk
        if ($textLength <= $chunkSize) {
            return [trim($text)];
        }

        // Split into paragraphs first
        $paragraphs = preg_split('/\n\n+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) {
                continue;
            }

            // If adding this paragraph exceeds the chunk size
            if (mb_strlen($currentChunk) + mb_strlen($paragraph) + 2 > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);

                    // Create overlap: take the last portion of the current chunk
                    $currentChunk = mb_substr($currentChunk, -$overlapSize);
                }

                // If a single paragraph is larger than chunk size, split by sentences
                if (mb_strlen($paragraph) > $chunkSize) {
                    $sentenceChunks = $this->splitLargeParagraph($paragraph, $chunkSize, $overlapSize);
                    foreach ($sentenceChunks as $sc) {
                        $chunks[] = trim($sc);
                    }
                    $currentChunk = mb_substr(end($sentenceChunks) ?: '', -$overlapSize);
                    continue;
                }
            }

            $currentChunk .= ($currentChunk ? "\n\n" : '') . $paragraph;
        }

        // Don't forget the last chunk
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        // Filter out very small chunks (less than 50 chars)
        return array_values(array_filter($chunks, fn(string $c) => mb_strlen($c) >= 50));
    }

    /**
     * Split a large paragraph into sentence-based chunks.
     *
     * @return string[]
     */
    private function splitLargeParagraph(string $paragraph, int $chunkSize, int $overlapSize): array
    {
        // Split on sentence boundaries
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current) + mb_strlen($sentence) + 1 > $chunkSize) {
                if (!empty($current)) {
                    $chunks[] = $current;
                    $current = mb_substr($current, -$overlapSize);
                }
            }

            $current .= ($current ? ' ' : '') . $sentence;
        }

        if (!empty(trim($current))) {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('ProcessKBDocumentJob permanently failed', [
            'document_id' => $this->document->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->document->update([
            'status' => 'failed',
            'error_message' => mb_substr('Job permanently failed: ' . ($exception?->getMessage() ?? 'Unknown error'), 0, 255),
        ]);
    }
}
