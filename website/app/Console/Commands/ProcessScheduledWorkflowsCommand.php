<?php

namespace App\Console\Commands;

use App\Jobs\ExecuteWorkflowJob;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fires workflows with trigger subtype='scheduled' whose configured schedule
 * matches the current time. Runs every minute from the scheduler.
 *
 * Trigger config shape:
 *   {
 *     "schedule": "daily" | "weekly" | "monthly" | "every_30min" | "cron",
 *     "cron": "0 9 * * *",              // only when schedule=cron
 *     "weekday": 1,                      // 0=Sun..6=Sat, only when schedule=weekly
 *     "hour": 9, "minute": 0,           // HH:MM to fire (daily/weekly/monthly)
 *     "day_of_month": 1                 // only when schedule=monthly
 *   }
 *
 * We never broadcast to every contact — scheduled workflows have no
 * per-contact semantics, they just fire once per tick and actions operate
 * on whatever the workflow graph says (segment queries, etc.).
 */
class ProcessScheduledWorkflowsCommand extends Command
{
    protected $signature = 'workflows:process-scheduled';

    protected $description = 'Fire scheduled workflows whose schedule matches the current minute';

    public function handle(): int
    {
        $now = now();

        $workflows = Workflow::where('status', 'active')
            ->whereHas('workflowNodes', function ($q) {
                $q->where('type', 'trigger')->where('subtype', 'scheduled');
            })
            ->with(['workflowNodes' => function ($q) {
                $q->where('type', 'trigger')->where('subtype', 'scheduled');
            }])
            ->get();

        if ($workflows->isEmpty()) {
            Log::debug('[workflows:process-scheduled] No scheduled workflows');
            return self::SUCCESS;
        }

        $fired = 0;
        foreach ($workflows as $workflow) {
            try {
                $config = $workflow->workflowNodes->first()?->config ?? [];
                if (!$this->shouldFireNow($config, $now)) {
                    continue;
                }

                if ($this->alreadyFiredThisWindow($workflow->id, $config, $now)) {
                    continue;
                }

                ExecuteWorkflowJob::dispatch(
                    workflowId: $workflow->id,
                    contactId: null,
                    triggerData: [
                        'scheduled_at' => $now->toIso8601String(),
                        'schedule' => $config['schedule'] ?? 'unknown',
                    ],
                )->onQueue('workflows');

                $fired++;
                Log::info('[workflows:process-scheduled] Fired', [
                    'workflow_id' => $workflow->id,
                    'schedule' => $config['schedule'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning("[workflows:process-scheduled] Failed for {$workflow->id}: {$e->getMessage()}");
            }
        }

        if ($fired > 0) {
            $this->info("Fired {$fired} scheduled workflow(s)");
        }

        return self::SUCCESS;
    }

    /**
     * Return true if the schedule config matches the current time.
     * Supports simple presets (every_30min/daily/weekly/monthly) and raw cron.
     */
    private function shouldFireNow(array $config, Carbon $now): bool
    {
        $schedule = $config['schedule'] ?? null;
        if (!$schedule) return false;

        $hour = (int) ($config['hour'] ?? 9);
        $minute = (int) ($config['minute'] ?? 0);

        return match ($schedule) {
            // Every 30 minutes on :00 and :30
            'every_30min' => in_array($now->minute, [0, 30], true),
            // Every hour on :00
            'hourly' => $now->minute === 0,
            // Once a day at configured HH:MM
            'daily' => $now->hour === $hour && $now->minute === $minute,
            // Once a week at configured weekday + HH:MM (0=Sun..6=Sat, matches PHP/Carbon)
            'weekly' => $now->dayOfWeek === (int) ($config['weekday'] ?? 1)
                && $now->hour === $hour && $now->minute === $minute,
            // Once a month on configured day + HH:MM
            'monthly' => $now->day === (int) ($config['day_of_month'] ?? 1)
                && $now->hour === $hour && $now->minute === $minute,
            // Raw cron expression
            'cron' => $this->cronMatches($config['cron'] ?? '', $now),
            default => false,
        };
    }

    /**
     * Dupe-guard: if the same workflow already fired within the current
     * trigger window, skip. Protects against the scheduler firing twice in a
     * minute (overlap), and against manually-edited schedule configs that
     * match multiple minutes.
     */
    private function alreadyFiredThisWindow(int $workflowId, array $config, Carbon $now): bool
    {
        // For sub-daily schedules, dedupe by the minute we're currently in.
        // For daily+ schedules, dedupe by the current date.
        $windowStart = match ($config['schedule'] ?? '') {
            'every_30min', 'hourly' => $now->copy()->startOfMinute(),
            default => $now->copy()->startOfDay(),
        };

        return WorkflowExecution::where('workflow_id', $workflowId)
            ->where('started_at', '>=', $windowStart)
            ->exists();
    }

    /**
     * Minimal cron-expression matcher (5 fields: minute hour day month weekday).
     * Supports "*", comma lists, and step values like "* / N" (no space, slash).
     * No ranges, no named months.
     */
    private function cronMatches(string $cron, Carbon $now): bool
    {
        $cron = trim($cron);
        if ($cron === '') return false;

        $parts = preg_split('/\s+/', $cron);
        if (count($parts) !== 5) return false;

        [$min, $hr, $dom, $mon, $dow] = $parts;

        return $this->cronFieldMatches($min, $now->minute, 0, 59)
            && $this->cronFieldMatches($hr, $now->hour, 0, 23)
            && $this->cronFieldMatches($dom, $now->day, 1, 31)
            && $this->cronFieldMatches($mon, $now->month, 1, 12)
            && $this->cronFieldMatches($dow, $now->dayOfWeek, 0, 6);
    }

    private function cronFieldMatches(string $field, int $current, int $min, int $max): bool
    {
        if ($field === '*') return true;

        // Step: "*/5"
        if (str_starts_with($field, '*/')) {
            $step = (int) substr($field, 2);
            return $step > 0 && ($current % $step) === 0;
        }

        // Comma list: "1,15,30"
        if (str_contains($field, ',')) {
            return in_array((string) $current, array_map('trim', explode(',', $field)), true);
        }

        return (string) $current === $field;
    }
}
