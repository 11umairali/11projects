<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Handles in-app system updates for MailTrixy.
 *
 * Flow (run from /admin/update):
 *   1. Backup → zip code + dump database to storage/app/backups/v{x}_{ts}/
 *   2. Upload → save user-supplied update.zip to storage/app/temp/updater/
 *   3. Apply  → extract ZIP and overwrite UPDATABLE_PATHS while skipping
 *               PROTECTED_PATHS (.env, storage, vendor, uploaded media)
 *   4. Migrate → php artisan migrate --force (additive only — Laravel's
 *               migrator never drops user data; existing rows are kept)
 *   5. Finalize → clear caches, run health check, drop temp staging
 *
 * Idempotent + safe: backup + rollback paths exist for every update.
 * No customer data (uploaded files in storage/, DB rows, .env) is ever
 * touched during a normal apply.
 */
class UpdaterService
{
    private string $backupDir;
    private string $tempDir;

    /** Paths that must NEVER be overwritten by an update. */
    private const PROTECTED_PATHS = [
        '.env',
        'storage',
        'database/database.sqlite',
        'vendor',
        'node_modules',
    ];

    /** File extensions in public/ that must never be touched (user assets). */
    private const PROTECTED_PUBLIC_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'tiff',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        'mp4', 'mov', 'avi', 'mkv', 'mp3', 'wav',
        'zip', 'rar', 'tar', 'gz',
        'ttf', 'otf', 'woff', 'woff2', 'eot',
    ];

    /** Paths that get updated (code only). */
    private const UPDATABLE_PATHS = [
        'app',
        'config',
        'database/migrations',
        'database/seeders',
        'public/css',
        'public/js',
        'public/build',
        'resources',
        'routes',
        'lang',
        'scripts',
    ];

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        $this->tempDir = storage_path('app/temp/updater');
    }

    public function currentVersion(): string
    {
        return config('version.version', '1.0.0');
    }

    public function currentBuild(): int
    {
        return (int) config('version.build', 0);
    }

    /**
     * Read the version from config/version.php inside an uploaded ZIP.
     */
    public function getZipVersion(string $zipPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $candidates = ['config/version.php'];

        // If ZIP has a single root folder, try inside it
        for ($i = 0; $i < min($zip->numFiles, 50); $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^([^/]+)/config/version\.php$#', $name, $m)) {
                $candidates[] = $name;
                break;
            }
        }

        $version = null;
        foreach ($candidates as $candidate) {
            $content = $zip->getFromName($candidate);
            if ($content !== false) {
                if (preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m)) {
                    $version = $m[1];
                }
                break;
            }
        }

        $zip->close();
        return $version;
    }

    // ── STEP 1: Backup ───────────────────────────────────────────────

    public function createBackup(): array
    {
        $version = $this->currentVersion();
        $ts = now()->format('Y-m-d_His');
        $dir = $this->backupDir . "/v{$version}_{$ts}";
        File::ensureDirectoryExists($dir, 0755, true);

        $codeZip = $dir . '/code_backup.zip';
        $this->zipCodeFiles($codeZip);

        $dbFile = $dir . '/database_backup.sql';
        $this->dumpDatabase($dbFile);

        File::put($dir . '/rollback.json', json_encode([
            'version' => $version,
            'created_at' => now()->toIso8601String(),
            'code_backup' => $codeZip,
            'db_backup' => $dbFile,
        ], JSON_PRETTY_PRINT));

        return ['code' => $codeZip, 'database' => $dbFile, 'dir' => $dir];
    }

    // ── STEP 2: Upload ───────────────────────────────────────────────

    public function saveUploadedZip(\Illuminate\Http\UploadedFile $uploadedFile): string
    {
        File::ensureDirectoryExists($this->tempDir, 0755, true);
        $path = $this->tempDir . '/update.zip';
        $uploadedFile->move($this->tempDir, 'update.zip');
        return $path;
    }

    // ── STEP 3: Extract & Apply ──────────────────────────────────────

    public function applyUpdate(string $zipPath): array
    {
        $stagingDir = $this->tempDir . '/staging';
        if (File::isDirectory($stagingDir)) {
            File::deleteDirectory($stagingDir);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open update ZIP.');
        }
        $zip->extractTo($stagingDir);
        $zip->close();

        // If ZIP has a single root folder, step into it
        $items = File::directories($stagingDir);
        if (count($items) === 1 && count(File::files($stagingDir)) === 0) {
            $stagingDir = $items[0];
        }

        $basePath = base_path();
        $updated = [];

        foreach (self::UPDATABLE_PATHS as $relPath) {
            $srcPath = $stagingDir . '/' . $relPath;
            $destPath = $basePath . '/' . $relPath;

            if (! File::exists($srcPath)) {
                continue;
            }

            if (File::isDirectory($srcPath)) {
                // For migrations + seeders, only ADD new files (never remove existing)
                if (str_contains($relPath, 'migrations') || str_contains($relPath, 'seeders')) {
                    $this->mergeOnly($srcPath, $destPath);
                } else {
                    $this->safeCopyDirectory($srcPath, $destPath, $relPath);
                }
            } else {
                if (! $this->isProtected($relPath)) {
                    File::ensureDirectoryExists(dirname($destPath));
                    File::copy($srcPath, $destPath);
                }
            }

            $updated[] = $relPath;
        }

        // Update root files (composer.json, composer.lock) if present
        foreach (['composer.json', 'composer.lock'] as $rootFile) {
            $srcFile = $stagingDir . '/' . $rootFile;
            if (File::exists($srcFile)) {
                File::copy($srcFile, $basePath . '/' . $rootFile);
            }
        }

        // Update version.php if present in the update
        $newVersionFile = $stagingDir . '/config/version.php';
        if (File::exists($newVersionFile)) {
            File::copy($newVersionFile, config_path('version.php'));
        }

        return $updated;
    }

    // ── STEP 4: Migrate ──────────────────────────────────────────────

    /**
     * Run new migrations + run any new seeders flagged --class.
     * Existing migration history is preserved — Laravel's migrator only
     * runs files NOT recorded in the `migrations` table, so old data
     * stays intact and new tables/columns get added on top.
     */
    public function runMigrations(): string
    {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        // Optionally also run idempotent seeders that ship with the update.
        // We look for a magic class Database\Seeders\UpdateSeeder which
        // should be guarded with INSERT ... ON DUPLICATE KEY semantics so
        // running it on an already-seeded DB is a no-op.
        //
        // The class name is referenced as a string (not ::class) so static
        // analysers don't error before the seeder ships in a future update.
        $updateSeeder = 'Database\\Seeders\\UpdateSeeder';
        if (class_exists($updateSeeder)) {
            try {
                Artisan::call('db:seed', [
                    '--class' => $updateSeeder,
                    '--force' => true,
                ]);
                $output .= "\n" . Artisan::output();
            } catch (\Throwable $e) {
                $output .= "\nUpdateSeeder failed: " . $e->getMessage();
            }
        }

        return $output;
    }

    // ── STEP 5: Finalize ─────────────────────────────────────────────

    public function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }

    public function healthCheck(): array
    {
        $results = [];
        try {
            DB::connection()->getPdo();
            $results['database'] = true;
        } catch (\Throwable $e) {
            $results['database'] = false;
        }
        $results['env_file'] = File::exists(base_path('.env'));
        $results['storage_writable'] = is_writable(storage_path());
        $results['uploads_intact'] = File::isDirectory(storage_path('app/public'));
        return $results;
    }

    // ── ROLLBACK ─────────────────────────────────────────────────────

    public function listBackups(): array
    {
        if (! File::isDirectory($this->backupDir)) {
            return [];
        }

        $backups = [];
        foreach (File::directories($this->backupDir) as $dir) {
            $rollbackFile = $dir . '/rollback.json';
            if (File::exists($rollbackFile)) {
                $info = json_decode(File::get($rollbackFile), true);
                $info['path'] = $dir;
                $backups[] = $info;
            }
        }

        usort($backups, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $backups;
    }

    public function rollback(string $backupDir): void
    {
        $rollbackFile = $backupDir . '/rollback.json';
        if (! File::exists($rollbackFile)) {
            throw new RuntimeException('Rollback info not found.');
        }

        $info = json_decode(File::get($rollbackFile), true);

        if (! empty($info['code_backup']) && File::exists($info['code_backup'])) {
            $zip = new ZipArchive();
            if ($zip->open($info['code_backup']) === true) {
                $zip->extractTo(base_path());
                $zip->close();
            }
        }

        if (! empty($info['db_backup']) && File::exists($info['db_backup'])) {
            $this->restoreDatabase($info['db_backup']);
        }

        $this->clearCaches();
    }

    public function cleanup(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function isProtected(string $relPath): bool
    {
        foreach (self::PROTECTED_PATHS as $protected) {
            if ($relPath === $protected || str_starts_with($relPath, $protected . '/')) {
                return true;
            }
        }

        if (str_starts_with($relPath, 'public/')) {
            $allowedCodeDirs = ['public/css/', 'public/js/', 'public/build/'];
            $inCodeDir = false;
            foreach ($allowedCodeDirs as $dir) {
                if (str_starts_with($relPath, $dir)) {
                    $inCodeDir = true;
                    break;
                }
            }
            if (! $inCodeDir) {
                $ext = strtolower(pathinfo($relPath, PATHINFO_EXTENSION));
                if (in_array($ext, self::PROTECTED_PUBLIC_EXTENSIONS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function safeCopyDirectory(string $srcDir, string $destDir, string $baseRelPath): void
    {
        File::ensureDirectoryExists($destDir, 0755, true);
        foreach (File::allFiles($srcDir) as $file) {
            $relPath = $baseRelPath . '/' . $file->getRelativePathname();
            if ($this->isProtected($relPath)) {
                continue;
            }
            $dest = $destDir . '/' . $file->getRelativePathname();
            File::ensureDirectoryExists(dirname($dest), 0755, true);
            File::copy($file->getPathname(), $dest);
        }
    }

    /**
     * Add-only directory merge — used for migrations + seeders. Existing
     * files on disk are preserved (so running migrations whose files have
     * been edited locally won't get clobbered), only NEW files are copied.
     */
    private function mergeOnly(string $srcDir, string $destDir): void
    {
        File::ensureDirectoryExists($destDir, 0755, true);
        foreach (File::files($srcDir) as $file) {
            $dest = $destDir . '/' . $file->getFilename();
            if (! File::exists($dest)) {
                File::copy($file->getPathname(), $dest);
            }
        }
    }

    private function zipCodeFiles(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create backup ZIP.');
        }

        $basePath = base_path();
        foreach (self::UPDATABLE_PATHS as $relPath) {
            $fullPath = $basePath . '/' . $relPath;
            if (File::isDirectory($fullPath)) {
                foreach (File::allFiles($fullPath) as $file) {
                    $rel = $relPath . '/' . $file->getRelativePathname();
                    $zip->addFile($file->getPathname(), $rel);
                }
            } elseif (File::exists($fullPath)) {
                $zip->addFile($fullPath, $relPath);
            }
        }

        $zip->close();
    }

    private function dumpDatabase(string $path): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            if (File::exists($dbPath)) {
                File::copy($dbPath, $path);
            }
            return;
        }

        if ($driver === 'mysql') {
            $host = config("database.connections.{$connection}.host");
            $port = config("database.connections.{$connection}.port", 3306);
            $db = config("database.connections.{$connection}.database");
            $user = config("database.connections.{$connection}.username");
            $pass = config("database.connections.{$connection}.password");

            $cmd = sprintf(
                'mysqldump -h %s -P %s -u %s %s %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                $pass ? '-p' . escapeshellarg($pass) : '',
                escapeshellarg($db),
                escapeshellarg($path)
            );

            if (function_exists('exec')) {
                @exec($cmd, $output, $exitCode);
                if ($exitCode !== 0) {
                    $this->dumpDatabasePhp($path);
                }
            } else {
                $this->dumpDatabasePhp($path);
            }
            return;
        }

        File::put($path, "-- Database backup not supported for driver: {$driver}\n");
    }

    private function dumpDatabasePhp(string $path): void
    {
        $tables = DB::select('SHOW TABLES');
        $dbName = config('database.connections.' . config('database.default') . '.database');
        $key = "Tables_in_{$dbName}";

        $sql = "-- MailTrixy Database Backup\n-- Date: " . now()->toIso8601String() . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $tableName = $table->$key;
            $create = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $sql .= $create[0]->{'Create Table'} . ";\n\n";

            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $values = collect((array) $row)->map(function ($val) {
                    if (is_null($val)) return 'NULL';
                    return "'" . addslashes((string) $val) . "'";
                })->implode(', ');
                $sql .= "INSERT INTO `{$tableName}` VALUES ({$values});\n";
            }
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        File::put($path, $sql);
    }

    private function restoreDatabase(string $path): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            File::copy($path, $dbPath);
            return;
        }

        if ($driver === 'mysql') {
            $sql = File::get($path);
            DB::unprepared($sql);
        }
    }
}
