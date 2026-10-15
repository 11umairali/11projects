<?php

namespace App\Jobs;

use App\Models\KbDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScrapeWebsiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Exponential backoff: 60s, 5min between retries.
     */
    public array $backoff = [60, 300];

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * The queue this job should be dispatched to.
     */

    /**
     * Maximum content length to process (5MB).
     */
    private const MAX_CONTENT_LENGTH = 5 * 1024 * 1024;

    public function __construct(
        private readonly string $url,
        private readonly int $workspaceId,
    ) {}

    public function handle(): void
    {
        $url = $this->url;
        $workspaceId = $this->workspaceId;

        Log::info('ScrapeWebsiteJob: starting', [
            'url' => $url,
            'workspace_id' => $workspaceId,
        ]);

        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException("Invalid URL: {$url}");
            }

            // SSRF protection: block internal/private network URLs.
            // This is the last line of defense -- the Livewire component
            // should also validate before dispatching this job.
            if (!$this->isUrlSafe($url)) {
                throw new \RuntimeException('URL targets a private or reserved network and cannot be scraped.');
            }

            // Fetch page HTML
            $html = $this->fetchPage($url);

            if (empty($html)) {
                throw new \RuntimeException('Empty response from URL.');
            }

            // Extract main content
            $title = $this->extractTitle($html);
            $content = $this->extractContent($html);

            if (empty(trim($content))) {
                throw new \RuntimeException('No extractable text content found on the page.');
            }

            // Parse domain for metadata
            $parsedUrl = parse_url($url);
            $domain = $parsedUrl['host'] ?? $url;

            // Create KbDocument with type='website' (idempotent on retry)
            $document = KbDocument::firstOrCreate(
                ['workspace_id' => $workspaceId, 'source_url' => $url],
                [
                    'type' => 'website',
                    'title' => $title ?: $domain,
                    'content' => $content,
                    'status' => 'extracting', // Will be updated by ProcessKBDocumentJob
                ]
            );

            // Dispatch ProcessKBDocumentJob to chunk and embed the content
            ProcessKBDocumentJob::dispatch($document);

            Log::info('ScrapeWebsiteJob: completed, dispatched processing', [
                'document_id' => $document->id,
                'url' => $url,
                'title' => $title,
                'content_length' => mb_strlen($content),
            ]);
        } catch (\Exception $e) {
            Log::error('ScrapeWebsiteJob failed', [
                'url' => $url,
                'workspace_id' => $workspaceId,
                'error' => $e->getMessage(),
            ]);

            // Create a failed document record so the user can see the error
            KbDocument::create([
                'workspace_id' => $workspaceId,
                'type' => 'website',
                'title' => "Failed: {$url}",
                'source_url' => $url,
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 255),
            ]);

            throw $e;
        }
        $this->onQueue('processing');
    }

    /**
     * Fetch page HTML using Guzzle.
     */
    private function fetchPage(string $url): string
    {
        $client = new Client([
            'timeout' => 120,
            'connect_timeout' => 10,
            'allow_redirects' => [
                'max' => 5,
                'strict' => false,
                'referer' => true,
                'protocols' => ['http', 'https'],
            ],
            'headers' => [
                'User-Agent' => config('app.name', 'MailTrixy') . '-Bot/1.0 (Knowledge Base Scraper)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
            'verify' => true,
        ]);

        try {
            $response = $client->get($url);
            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                throw new \RuntimeException("HTTP {$statusCode} response from {$url}");
            }

            // Check content type is HTML
            $contentType = $response->getHeaderLine('Content-Type');
            if ($contentType && !Str::contains($contentType, ['text/html', 'application/xhtml'])) {
                throw new \RuntimeException("Non-HTML content type: {$contentType}");
            }

            // Check content length
            $body = $response->getBody()->getContents();
            if (strlen($body) > self::MAX_CONTENT_LENGTH) {
                throw new \RuntimeException('Page content exceeds 5MB limit.');
            }

            return $body;
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Failed to fetch URL: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Extract the page title from HTML.
     */
    private function extractTitle(string $html): string
    {
        // Try <title> tag first
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            // Clean up common suffixes like " | Company Name"
            $title = preg_replace('/\s*[|\-]\s*$/', '', $title);
            return mb_substr($title, 0, 255);
        }

        // Try og:title meta tag
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/', $html, $matches)) {
            return mb_substr(html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8'), 0, 255);
        }

        // Try first h1
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return mb_substr(trim(strip_tags($matches[1])), 0, 255);
        }

        return '';
    }

    /**
     * Extract main content from HTML, stripping navigation, headers, footers, scripts, etc.
     */
    private function extractContent(string $html): string
    {
        // Suppress libxml errors for malformed HTML
        $previousUseErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        // Load HTML with UTF-8 encoding hint
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        // Remove unwanted elements
        $tagsToRemove = ['script', 'style', 'nav', 'footer', 'header', 'aside', 'noscript', 'iframe', 'svg', 'form'];

        foreach ($tagsToRemove as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            // Collect first since removing modifies the live NodeList
            $toRemove = [];
            for ($i = 0; $i < $elements->length; $i++) {
                $toRemove[] = $elements->item($i);
            }
            foreach ($toRemove as $element) {
                $element->parentNode?->removeChild($element);
            }
        }

        // Remove elements by common non-content class/id patterns
        $xpath = new \DOMXPath($dom);
        $nonContentSelectors = [
            '//*[contains(@class, "nav")]',
            '//*[contains(@class, "menu")]',
            '//*[contains(@class, "sidebar")]',
            '//*[contains(@class, "footer")]',
            '//*[contains(@class, "header")]',
            '//*[contains(@class, "cookie")]',
            '//*[contains(@class, "popup")]',
            '//*[contains(@class, "modal")]',
            '//*[contains(@class, "advertisement")]',
            '//*[contains(@class, "ad-")]',
            '//*[contains(@id, "nav")]',
            '//*[contains(@id, "menu")]',
            '//*[contains(@id, "sidebar")]',
            '//*[contains(@id, "footer")]',
            '//*[contains(@id, "header")]',
            '//*[contains(@id, "cookie")]',
        ];

        foreach ($nonContentSelectors as $selector) {
            try {
                $nodes = $xpath->query($selector);
                if ($nodes) {
                    $toRemove = [];
                    foreach ($nodes as $node) {
                        $toRemove[] = $node;
                    }
                    foreach ($toRemove as $node) {
                        $node->parentNode?->removeChild($node);
                    }
                }
            } catch (\Exception $e) {
                // Skip invalid selectors
                continue;
            }
        }

        // Try to find the main content area
        $contentNode = null;

        // Look for <main>, <article>, or common content containers
        $contentSelectors = [
            '//main',
            '//article',
            '//*[@id="content"]',
            '//*[@id="main-content"]',
            '//*[@class="content"]',
            '//*[@role="main"]',
        ];

        foreach ($contentSelectors as $selector) {
            try {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $contentNode = $nodes->item(0);
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // If no content container found, use body
        if (!$contentNode) {
            $bodies = $dom->getElementsByTagName('body');
            if ($bodies->length > 0) {
                $contentNode = $bodies->item(0);
            }
        }

        if (!$contentNode) {
            return strip_tags($html);
        }

        // Extract text with basic formatting preservation
        $text = $this->extractNodeText($contentNode);

        // Final cleanup
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = preg_replace('/[^\S\n]+/', ' ', $text);

        return trim($text);
    }

    /**
     * Recursively extract text from a DOM node, preserving paragraph and heading structure.
     */
    private function extractNodeText(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $text = '';
        $tagName = strtolower($node->nodeName);

        // Block-level elements get newlines
        $blockTags = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'tr', 'blockquote', 'pre', 'section'];

        if (in_array($tagName, $blockTags)) {
            $text .= "\n";
        }

        // Heading prefix
        if (preg_match('/^h([1-6])$/', $tagName)) {
            $text .= "\n";
        }

        foreach ($node->childNodes as $child) {
            $text .= $this->extractNodeText($child);
        }

        if (in_array($tagName, $blockTags)) {
            $text .= "\n";
        }

        // Line break elements
        if ($tagName === 'br') {
            $text .= "\n";
        }

        return $text;
    }

    /**
     * Validate that a URL does not target internal/private networks (SSRF protection).
     */
    private function isUrlSafe(string $url): bool
    {
        $parsed = parse_url($url);

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return false;
        }

        $hostLower = strtolower($host);

        $blockedHosts = [
            'localhost', '0.0.0.0',
            'metadata.google.internal', 'metadata.google',
            '169.254.169.254', 'metadata.azure.internal',
        ];
        if (in_array($hostLower, $blockedHosts)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        // Resolve hostname and verify resolved IPs are not private
        $resolvedIps = gethostbynamel($hostLower);
        if ($resolvedIps) {
            foreach ($resolvedIps as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('ScrapeWebsiteJob permanently failed', [
            'url' => $this->url,
            'workspace_id' => $this->workspaceId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
