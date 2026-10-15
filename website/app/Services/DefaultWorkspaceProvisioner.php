<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

/**
 * Auto-creates a workspace at registration time so new users land straight
 * on /dashboard instead of being forced through the onboarding wizard's
 * "create workspace" screen.
 *
 * RegisterResponse already redirects to /onboarding/step-1 only when
 * $user->hasWorkspace() is false (i.e. active_workspace_id is null). By
 * provisioning a workspace here -- the same moment the built-in mailbox is
 * normally wired up during onboarding step 1 -- that check simply passes
 * and users skip the manual workspace-config step entirely. They can still
 * rename the workspace, add a logo, etc. later from Settings.
 *
 * Safe to call more than once: no-ops if the user already has a workspace.
 */
class DefaultWorkspaceProvisioner
{
    public function ensure(User $user): ?Workspace
    {
        if ($user->active_workspace_id) {
            return $user->activeWorkspace;
        }

        $workspace = Workspace::create([
            'name' => "{$user->name}'s Workspace",
            'onboarding_step' => 5,
            'onboarding_completed' => true,
        ]);

        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'status' => 'online',
        ]);

        $user->update(['active_workspace_id' => $workspace->id]);

        $this->assignDefaultPlan($workspace);

        return $workspace;
    }

    /**
     * Mirrors OnboardingController::storeStep1()'s plan assignment so
     * auto-provisioned workspaces get the same default/trial plan a
     * manually-created one would.
     */
    private function assignDefaultPlan(Workspace $workspace): void
    {
        try {
            $trialDays = (int) SystemSetting::get('trial_days', 14);

            $plan = \App\Support\BillingPolicy::defaultPlan();

            if ($plan) {
                if ($plan->isFree()) $trialDays = 0;
                Subscription::create([
                    'workspace_id' => $workspace->id,
                    'plan_id' => $plan->id,
                    'status' => $trialDays > 0 ? 'trialing' : 'active',
                    'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('DefaultWorkspaceProvisioner: default plan assignment failed', [
                'workspace_id' => $workspace->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
