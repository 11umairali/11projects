<?php

namespace App\Services\AI;

use App\Models\KbChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class RAGService
{
    private ?Client $pineconeClient = null;
    private ?string $pineconeHost = null;

    public function __construct()
    {
        $apiKey = config('services.pinecone.api_key');
        $host = config('services.pinecone.host');

        if ($apiKey && $host) {
            $this->pineconeHost = rtrim($host, '/');
            $this->pineconeClient = new Client([
                'base_uri' => $this->pineconeHost,
                'timeout' => 120,
                'connect_timeout' => 10,
                'headers' => [
                    'Api-Key' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);
        }
    }

    /**
     * Retrieve relevant context chunks for a query.
     *
     * Uses Pinecone vector search if configured, otherwise falls back to MySQL FULLTEXT.
     *
     * @param int    $workspaceId  The workspace to search within.
     * @param string $query        The search query text.
     * @param int    $topK         Maximum number of results to return.
     *
     * @return array Array of [content, document_title, score, chunk_id] entries.
     */
    public function retrieveContext(int $workspaceId, string $query, int $topK = 5): array
    {
        if ($this->pineconeClient) {
            // Retry Pinecone up to 2 times with exponential backoff before falling back
            $retries = 2;
            for ($attempt = 1; $attempt <= $retries; $attempt++) {
                try {
                    return $this->retrieveFromPinecone($workspaceId, $query, $topK);
                } catch (\Exception $e) {
                    if ($attempt === $retries) {
                        Log::warning('Pinecone failed after retries, falling back to MySQL', [
                            'error' => $e->getMessage(),
                            'attempts' => $attempt,
                            'workspace_id' => $workspaceId,
                        ]);
                        break;
                    }
                    usleep(500000 * $attempt); // 0.5s, 1s backoff
                }
            }
        }

        // FIX-075: If MySQL fallback also fails, return empty array instead of throwing.
        // Let the AI respond without KB context rather than breaking the entire reply flow.
        try {
            return $this->retrieveFromMySQL($workspaceId, $query, $topK);
        } catch (\Exception $e) {
            Log::error('All KB retrieval methods failed, proceeding without context', [
                'message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'query_length' => mb_strlen($query),
            ]);

            return [];
        }
    }

    /**
     * Store a chunk's embedding vector in Pinecone.
     *
     * @param int     $workspaceId  The workspace ID for namespace/filtering.
     * @param KbChunk $chunk        The chunk model.
     * @param array   $vector       The float[] embedding vector.
     */
    public function storeEmbedding(int $workspaceId, KbChunk $chunk, array $vector): void
    {
        if (!$this->pineconeClient) {
            Log::debug('Pinecone not configured, skipping vector storage', [
                'workspace_id' => $workspaceId,
                'chunk_id' => $chunk->id,
            ]);
            return;
        }

        $vectorId = "ws{$workspaceId}_chunk{$chunk->id}";

        try {
            $this->pineconeClient->post('/vectors/upsert', [
                'json' => [
                    'vectors' => [
                        [
                            'id' => $vectorId,
                            'values' => $vector,
                            'metadata' => [
                                'workspace_id' => $workspaceId,
                                'chunk_id' => $chunk->id,
                                'document_id' => $chunk->document_id,
                                'chunk_index' => $chunk->chunk_index,
                                'content_preview' => mb_substr($chunk->content, 0, 200),
                            ],
                        ],
                    ],
                    'namespace' => "workspace_{$workspaceId}",
                ],
            ]);

            // Store the vector ID on the chunk for future reference
            $chunk->update(['vector_id' => $vectorId]);

            Log::debug('Stored embedding in Pinecone', [
                'vector_id' => $vectorId,
                'workspace_id' => $workspaceId,
            ]);
        } catch (GuzzleException $e) {
            Log::error('Pinecone upsert failed', [
                'message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'chunk_id' => $chunk->id,
            ]);

            throw new \RuntimeException(
                "Failed to store embedding in Pinecone: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Store multiple chunk embeddings in Pinecone using batched upserts.
     *
     * FIX-076: Batches vectors into groups of 100 instead of upserting one at a time.
     * Each batch is sent as a single HTTP call to minimize round-trips and latency.
     *
     * @param int   $workspaceId  The workspace ID for namespace/filtering.
     * @param array $items        Array of ['chunk' => KbChunk, 'vector' => float[]] entries.
     */
    public function storeEmbeddingsBatch(int $workspaceId, array $items): void
    {
        if (!$this->pineconeClient) {
            Log::debug('Pinecone not configured, skipping batch vector storage', [
                'workspace_id' => $workspaceId,
                'count' => count($items),
            ]);
            return;
        }

        if (empty($items)) {
            return;
        }

        $batches = array_chunk($items, 100);

        foreach ($batches as $batchIndex => $batch) {
            $vectors = [];
            $chunkIds = [];

            foreach ($batch as $item) {
                /** @var KbChunk $chunk */
                $chunk = $item['chunk'];
                $vector = $item['vector'];
                $vectorId = "ws{$workspaceId}_chunk{$chunk->id}";

                $vectors[] = [
                    'id' => $vectorId,
                    'values' => $vector,
                    'metadata' => [
                        'workspace_id' => $workspaceId,
                        'chunk_id' => $chunk->id,
                        'document_id' => $chunk->document_id,
                        'chunk_index' => $chunk->chunk_index,
                        'content_preview' => mb_substr($chunk->content, 0, 200),
                    ],
                ];

                $chunkIds[$chunk->id] = $vectorId;
            }

            try {
                $this->pineconeClient->post('/vectors/upsert', [
                    'json' => [
                        'vectors' => $vectors,
                        'namespace' => "workspace_{$workspaceId}",
                    ],
                ]);

                // Update vector_id on all chunks in this batch
                foreach ($chunkIds as $chunkId => $vectorId) {
                    KbChunk::where('id', $chunkId)->update(['vector_id' => $vectorId]);
                }

                Log::debug('Stored embedding batch in Pinecone', [
                    'workspace_id' => $workspaceId,
                    'batch' => $batchIndex + 1,
                    'batch_size' => count($vectors),
                    'total_batches' => count($batches),
                ]);
            } catch (GuzzleException $e) {
                Log::error('Pinecone batch upsert failed', [
                    'message' => $e->getMessage(),
                    'workspace_id' => $workspaceId,
                    'batch' => $batchIndex + 1,
                    'batch_size' => count($vectors),
                ]);

                throw new \RuntimeException(
                    "Failed to store embedding batch in Pinecone: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Delete embeddings from Pinecone for the given chunk IDs.
     *
     * @param int   $workspaceId  The workspace ID.
     * @param array $chunkIds     Array of chunk IDs whose vectors to delete.
     */
    public function deleteEmbeddings(int $workspaceId, array $chunkIds): void
    {
        if (!$this->pineconeClient) {
            return;
        }

        $vectorIds = array_map(
            fn(int $id) => "ws{$workspaceId}_chunk{$id}",
            $chunkIds
        );

        try {
            $response = $this->pineconeClient->post('/vectors/delete', [
                'json' => [
                    'ids' => $vectorIds,
                    'namespace' => "workspace_{$workspaceId}",
                ],
            ]);

            // Only clear vector_id if Pinecone confirmed deletion (2xx response)
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                KbChunk::whereIn('id', $chunkIds)->update(['vector_id' => null]);

                Log::debug('Deleted embeddings from Pinecone', [
                    'workspace_id' => $workspaceId,
                    'count' => count($vectorIds),
                ]);
            } else {
                Log::warning('Pinecone delete returned non-success status, vector_id not cleared', [
                    'workspace_id' => $workspaceId,
                    'status_code' => $statusCode,
                    'chunk_ids' => $chunkIds,
                ]);
            }
        } catch (GuzzleException $e) {
            Log::error('Pinecone delete failed, vector_id references preserved for retry', [
                'message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'chunk_ids' => $chunkIds,
            ]);

            throw new \RuntimeException(
                "Failed to delete embeddings from Pinecone: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Retrieve context using Pinecone vector similarity search.
     */
    private function retrieveFromPinecone(int $workspaceId, string $query, int $topK): array
    {
        try {
            // Generate query embedding via AIManager (OpenAI embeddings)
            $aiManager = app(AIManager::class);
            $queryVector = $aiManager->generateEmbedding($query);

            $response = $this->pineconeClient->post('/query', [
                'json' => [
                    'vector' => $queryVector,
                    'topK' => $topK,
                    'includeMetadata' => true,
                    'namespace' => "workspace_{$workspaceId}",
                    'filter' => [
                        'workspace_id' => ['$eq' => $workspaceId],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $matches = $data['matches'] ?? [];

            if (empty($matches)) {
                return [];
            }

            // Fetch full chunk content from MySQL (Pinecone metadata only has preview)
            $chunkIds = array_map(
                fn(array $match) => $match['metadata']['chunk_id'] ?? 0,
                $matches
            );

            $chunks = KbChunk::with('document')
                ->whereIn('id', array_filter($chunkIds))
                ->get()
                ->keyBy('id');

            $results = [];
            foreach ($matches as $match) {
                $chunkId = $match['metadata']['chunk_id'] ?? 0;
                $chunk = $chunks->get($chunkId);

                if ($chunk) {
                    $results[] = [
                        'content' => $chunk->content,
                        'document_title' => $chunk->document?->title ?? 'Unknown',
                        'score' => $match['score'] ?? 0,
                        'chunk_id' => $chunkId,
                    ];
                }
            }

            return $results;
        } catch (GuzzleException $e) {
            Log::error('Pinecone query failed, falling back to MySQL', [
                'message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
            ]);

            // Fall back to MySQL FULLTEXT search
            return $this->retrieveFromMySQL($workspaceId, $query, $topK);
        } catch (\RuntimeException $e) {
            Log::error('Embedding generation failed for Pinecone query, falling back to MySQL', [
                'message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
            ]);

            return $this->retrieveFromMySQL($workspaceId, $query, $topK);
        }
    }

    /**
     * Retrieve context using MySQL FULLTEXT search fallback.
     */
    private function retrieveFromMySQL(int $workspaceId, string $query, int $topK): array
    {
        // Clean the query for BOOLEAN MODE: remove special characters, keep words
        $cleanQuery = preg_replace('/[^\p{L}\p{N}\s]/u', '', $query);
        $words = array_filter(explode(' ', $cleanQuery));

        if (empty($words)) {
            return [];
        }

        // Build boolean search string: each word prefixed with + for required match
        $booleanQuery = implode(' ', array_map(fn(string $word) => "+{$word}", $words));

        try {
            $results = DB::table('kb_chunks')
                ->join('kb_documents', 'kb_chunks.document_id', '=', 'kb_documents.id')
                ->where('kb_chunks.workspace_id', $workspaceId)
                ->where('kb_documents.status', 'ready')
                ->whereNull('kb_documents.deleted_at')
                ->whereRaw(
                    'MATCH(kb_chunks.content) AGAINST(? IN BOOLEAN MODE)',
                    [$booleanQuery]
                )
                ->selectRaw(
                    'kb_chunks.id as chunk_id, kb_chunks.content, kb_documents.title as document_title, '
                    . 'MATCH(kb_chunks.content) AGAINST(? IN BOOLEAN MODE) as relevance_score',
                    [$booleanQuery]
                )
                ->orderByDesc('relevance_score')
                ->limit($topK)
                ->get();

            return $results->map(fn($row) => [
                'content' => $row->content,
                'document_title' => $row->document_title,
                'score' => round((float) $row->relevance_score, 4),
                'chunk_id' => $row->chunk_id,
            ])->toArray();
        } catch (\Exception $e) {
            Log::error('MySQL FULLTEXT search failed', [
                'message' => $e->getMessage(),
                'workspace_id' => $workspaceId,
                'query' => $query,
            ]);

            // Last resort: simple LIKE fallback
            return $this->retrieveWithLike($workspaceId, $words, $topK);
        }
    }

    /**
     * Last-resort fallback using LIKE queries when FULLTEXT is unavailable.
     */
    private function retrieveWithLike(int $workspaceId, array $words, int $topK): array
    {
        $query = DB::table('kb_chunks')
            ->join('kb_documents', 'kb_chunks.document_id', '=', 'kb_documents.id')
            ->where('kb_chunks.workspace_id', $workspaceId)
            ->where('kb_documents.status', 'ready')
            ->whereNull('kb_documents.deleted_at');

        foreach (array_slice($words, 0, 5) as $word) {
            $query->where('kb_chunks.content', 'LIKE', "%{$word}%");
        }

        $results = $query
            ->select('kb_chunks.id as chunk_id', 'kb_chunks.content', 'kb_documents.title as document_title')
            ->limit($topK)
            ->get();

        return $results->map(fn($row) => [
            'content' => $row->content,
            'document_title' => $row->document_title,
            'score' => 0.5, // Arbitrary score for LIKE matches
            'chunk_id' => $row->chunk_id,
        ])->toArray();
    }
}
