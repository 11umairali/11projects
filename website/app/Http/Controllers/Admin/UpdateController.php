<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UpdaterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Admin → System Update screen.
 *
 * Drives a 5-step wizard:
 *   1. Backup files + DB     → POST /admin/update/backup
 *   2. Upload update ZIP     → POST /admin/update/upload
 *   3. Apply (extract + copy)→ POST /admin/update/apply
 *   4. Run new migrations    → POST /admin/update/migrate
 *   5. Clear caches + verify → POST /admin/update/finalize
 *
 *   Rollback (anytime)       → POST /admin/update/rollback
 *
 * Only Super Admins (Spatie role OR is_admin + admin_role=super_admin)
 * can access. Each step returns JSON; the blade is a single-page Alpine
 * wizard that drives them sequentially.
 */
class UpdateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                $user = $request->user();
                if (!$user) {
                    abort(403);
                }

                $isSuper = (method_exists($user, 'hasRole') && $user->hasRole('Super Admin'))
                    || ($user->is_admin && ($user->admin_role ?? '') === 'super_admin');

                if (!$isSuper) {
                    abort(403, 'Only Super Admins can access the system updater.');
                }
                return $next($request);
            }),
        ];
    }

    public function __construct(private UpdaterService $updater)
    {
    }

    public function index()
    {
        $currentVersion = $this->updater->currentVersion();
        $currentBuild = $this->updater->currentBuild();
        $backups = $this->updater->listBackups();

        return view('admin.update.index', compact('currentVersion', 'currentBuild', 'backups'));
    }

    public function backup(): JsonResponse
    {
        try {
            $result = $this->updater->createBackup();
            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully.',
                'backup' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ]);

        try {
            $path = $this->updater->saveUploadedZip($request->file('file'));

            $zipVersion = $this->updater->getZipVersion($path);
            if (!$zipVersion) {
                $this->updater->cleanup();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid update package. No version info found in ZIP (config/version.php missing).',
                ], 422);
            }

            $currentVersion = $this->updater->currentVersion();
            if (version_compare($zipVersion, $currentVersion, '<=')) {
                $this->updater->cleanup();
                return response()->json([
                    'success' => false,
                    'message' => "ZIP contains v{$zipVersion} but you already have v{$currentVersion}. Upload a newer version.",
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "Update ZIP uploaded — v{$zipVersion} ready to install.",
                'zip_version' => $zipVersion,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function apply(Request $request): JsonResponse
    {
        $zipPath = storage_path('app/temp/updater/update.zip');

        if (!file_exists($zipPath)) {
            return response()->json([
                'success' => false,
                'message' => 'No update ZIP found. Please upload first.',
            ], 400);
        }

        try {
            $updated = $this->updater->applyUpdate($zipPath);
            return response()->json([
                'success' => true,
                'message' => 'Files updated successfully.',
                'updated' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Apply failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function migrate(): JsonResponse
    {
        try {
            $output = $this->updater->runMigrations();
            return response()->json([
                'success' => true,
                'message' => 'Migrations completed.',
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function finalize(): JsonResponse
    {
        try {
            $this->updater->clearCaches();
            $health = $this->updater->healthCheck();
            $this->updater->cleanup();

            $allGood = !in_array(false, $health, true);

            return response()->json([
                'success' => $allGood,
                'message' => $allGood ? 'Update completed successfully!' : 'Update done but health check has warnings.',
                'health' => $health,
                'new_version' => config('version.version'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Finalize failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function rollback(Request $request): JsonResponse
    {
        $request->validate([
            'backup_dir' => ['required', 'string'],
        ]);

        $dir = $request->input('backup_dir');

        // Security: ensure the path is within our backups directory
        $backupBase = realpath(storage_path('app/backups'));
        $targetDir = realpath($dir);
        if (!$targetDir || !str_starts_with($targetDir, $backupBase)) {
            return response()->json(['success' => false, 'message' => 'Invalid backup path.'], 403);
        }

        try {
            $this->updater->rollback($targetDir);
            return response()->json([
                'success' => true,
                'message' => 'Rollback completed. Version restored.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
