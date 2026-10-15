<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SystemController extends Controller
{
    /**
     * System health dashboard.
     */
    public function index(Request $request): View
    {
        // ------------------------------------------------------------------
        // Queue health
        // ------------------------------------------------------------------

        $pendingJobs = 0;
        $failedJobs = 0;

        try {
            $pendingJobs = DB::table('jobs')->count();
        } catch (\Exception $e) {
            // Jobs table might not exist
        }

        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Failed jobs table might not exist
        }

        // Queue depth by queue name
        $queueDepths = [];
        try {
            $queueDepths = DB::table('jobs')
                ->selectRaw('queue, COUNT(*) as count')
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray();
        } catch (\Exception $e) {
            // Ignore
        }

        // Recent failed jobs
        $recentFailedJobs = [];
        try {
            $recentFailedJobs = DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(20)
                ->get(['id', 'queue', 'payload', 'exception', 'failed_at'])
                ->map(function ($job) {
                    // Extract job class name from payload
                    $payload = json_decode($job->payload, true);
                    $job->job_class = $payload['displayName'] ?? 'Unknown';
                    // SEC-007: Sanitize exception output — may contain connection strings or credentials
                    $job->exception_summary = $this->sanitizeLogContent(mb_substr($job->exception, 0, 300));
                    return $job;
                });
        } catch (\Exception $e) {
            // Ignore
        }

        // ------------------------------------------------------------------
        // Database health
        // ------------------------------------------------------------------

        $dbSize = null;
        try {
            $dbName = config('database.connections.' . config('database.default') . '.database');
            $result = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables
                WHERE table_schema = ?
            ", [$dbName]);
            $dbSize = $result[0]->size_mb ?? null;
        } catch (\Exception $e) {
            // Not MySQL or no permission
        }

        // Table row counts for key tables
        $tableCounts = [];
        $keyTables = [
            'users', 'workspaces', 'contacts', 'conversations', 'messages',
            'campaigns', 'workflows', 'subscriptions', 'payments',
            'kb_documents', 'ai_usage_logs',
        ];
        foreach ($keyTables as $table) {
            try {
                $tableCounts[$table] = DB::table($table)->count();
            } catch (\Exception $e) {
                $tableCounts[$table] = 'N/A';
            }
        }

        // ------------------------------------------------------------------
        // Disk usage
        // ------------------------------------------------------------------

        $storagePath = storage_path();
        $diskFree = @disk_free_space($storagePath);
        $diskTotal = @disk_total_space($storagePath);
        $diskUsage = [
            'free_gb' => $diskFree !== false ? round($diskFree / 1073741824, 2) : null,
            'total_gb' => $diskTotal !== false ? round($diskTotal / 1073741824, 2) : null,
            'used_percent' => ($diskFree !== false && $diskTotal !== false && $diskTotal > 0)
                ? round((1 - $diskFree / $diskTotal) * 100, 1)
                : null,
        ];

        // Storage directory sizes
        $storageSizes = [];
        $storageDirs = ['app', 'logs', 'framework/cache', 'framework/sessions'];
        foreach ($storageDirs as $dir) {
            $fullPath = storage_path($dir);
            if (is_dir($fullPath)) {
                $storageSizes[$dir] = $this->getDirectorySizeMb($fullPath);
            }
        }

        // ------------------------------------------------------------------
        // Recent errors from log
        // ------------------------------------------------------------------

        $recentErrors = [];
        $logFile = storage_path('logs/laravel.log');
        if (file_exists($logFile)) {
            $logContent = $this->tailFile($logFile, 5000);

            // SEC-007: Sanitize log content before display to prevent secret leakage
            $logContent = $this->sanitizeLogContent($logContent);

            // Extract ERROR entries
            preg_match_all(
                '/\[\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*\]\s*(local|production)\.ERROR:.*/',
                $logContent,
                $matches
            );
            $recentErrors = array_slice(array_reverse($matches[0] ?? []), 0, 20);
            $recentErrors = array_map(fn ($e) => mb_substr($e, 0, 500), $recentErrors);
        }

        // ------------------------------------------------------------------
        // Application info
        // ------------------------------------------------------------------

        $appInfo = [
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
        ];

        return view('admin.system', compact(
            'pendingJobs',
            'failedJobs',
            'queueDepths',
            'recentFailedJobs',
            'dbSize',
            'tableCounts',
            'diskUsage',
            'storageSizes',
            'recentErrors',
            'appInfo',
        ));
    }

    /**
     * Calculate directory size in MB.
     */
    private function getDirectorySizeMb(string $path): float
    {
        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Exception $e) {
            // Permission error or similar
        }

        return round($size / 1048576, 2); // Convert to MB
    }

    /**
     * SEC-007: Sanitize log content to redact secrets, tokens, passwords, and
     * database connection strings before displaying in the admin panel.
     */
    private function sanitizeLogContent(string $content): string
    {
        $patterns = [
            // Environment variable secrets (API_KEY=..., DB_PASSWORD=..., etc.)
            '/([A-Za-z0-9_]+_KEY|[A-Za-z0-9_]+_SECRET|[A-Za-z0-9_]+_PASSWORD|[A-Za-z0-9_]+_TOKEN)=([^\s]+)/i'
                => '$1=[REDACTED]',
            // Bearer tokens in log output
            '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i'
                => 'Bearer [REDACTED]',
            // Quoted password assignments (password="...", password: "...")
            '/password["\']\s*[=:]\s*["\'][^"\']+["\']/i'
                => 'password=[REDACTED]',
            // Database connection URLs (mysql://user:pass@host/db)
            '/(?:mysql|pgsql|sqlite|redis|mongodb):\/\/[^\s]+/i'
                => '[DATABASE_URL_REDACTED]',
            // Stripe secret keys
            '/sk_(live|test)_[A-Za-z0-9]+/i'
                => 'sk_$1_[REDACTED]',
            // Generic long hex/base64 strings that look like API keys (32+ chars)
            '/(?<=["\':=\s])[A-Fa-f0-9]{40,}(?=["\'\s,;])/i'
                => '[POSSIBLE_SECRET_REDACTED]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Read the last N bytes from a file (tail).
     */
    private function tailFile(string $path, int $bytes = 5000): string
    {
        if (!file_exists($path)) {
            return '';
        }

        $fileSize = filesize($path);
        if ($fileSize <= $bytes) {
            return file_get_contents($path);
        }

        $handle = fopen($path, 'r');
        fseek($handle, -$bytes, SEEK_END);
        $content = fread($handle, $bytes);
        fclose($handle);

        return $content;
    }
}
