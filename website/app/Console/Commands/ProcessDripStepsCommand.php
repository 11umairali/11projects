<?php

namespace App\Console\Commands;

use App\Models\DripEnrollment;
use App\Services\Campaign\DripService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDripStepsCommand extends Command
{
    protected $signature = 'mailtrixy:process-drips';

    protected $description = 'Process all due drip sequence steps for active enrollments';

    public function handle(DripService $dripService): int
    {
        $dueEnrollments = DripEnrollment::where('status', 'active')
            ->where('next_step_at', '<=', now())
            ->with(['sequence.campaign.emailAccount', 'contact'])
            ->limit(100)
            ->get();

        if ($dueEnrollments->isEmpty()) {
            Log::debug('[process-drips] No due enrollments');
            $this->info('No due drip enrollments found.');
            return self::SUCCESS;
        }

        Log::info("[process-drips] Processing {$dueEnrollments->count()} due enrollment(s)");
        $this->info("Processing {$dueEnrollments->count()} due drip enrollments...");

        $processed = 0;
        $failed = 0;

        foreach ($dueEnrollments as $enrollment) {
            try {
                $dripService->processStep($enrollment);
                $processed++;
                Log::info("[process-drips] Processed enrollment #{$enrollment->id} for contact #{$enrollment->contact_id}");
            } catch (\Throwable $e) {
                $failed++;
                Log::error("[process-drips] Failed enrollment #{$enrollment->id}: {$e->getMessage()}");
                $this->error("Failed enrollment #{$enrollment->id}: {$e->getMessage()}");
            }
        }

        Log::info("[process-drips] Complete: {$processed} processed, {$failed} failed");
        $this->info("Drip processing complete: {$processed} processed, {$failed} failed.");

        return self::SUCCESS;
    }
}
