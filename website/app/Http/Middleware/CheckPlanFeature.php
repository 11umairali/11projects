<?php

namespace App\Http\Middleware;

use App\Exceptions\PlanLimitReachedException;
use App\Services\PlanLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   Route::get('/...')->middleware('plan.feature:ai_auto_reply');
     *   Route::get('/...')->middleware('plan.feature:campaigns,emails_per_month');
     *
     * @param string $featureKeys Comma-separated feature keys to check.
     */
    public function handle(Request $request, Closure $next, string ...$featureKeys): Response
    {
        $workspace = $request->attributes->get('workspace');

        // Fallback: try to get workspace from authenticated user
        if (!$workspace && $request->user()) {
            $workspace = $request->user()->activeWorkspace;
        }

        if (!$workspace) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'No active workspace found.',
                    'code' => 'NO_WORKSPACE',
                ], 403);
            }
            return redirect()->back()->with('error', 'No active workspace found.');
        }

        foreach ($featureKeys as $featureKey) {
            try {
                $this->planLimitService->assertFeatureEnabled($workspace, $featureKey);
            } catch (PlanLimitReachedException $e) {
                if ($request->expectsJson()) {
                    $usage = $this->planLimitService->getFeatureUsage($workspace, $featureKey);

                    return response()->json([
                        'error' => $e->getMessage(),
                        'code' => 'PLAN_FEATURE_RESTRICTED',
                        'feature' => $featureKey,
                        'usage' => $usage,
                        'upgrade_url' => config('app.url') . '/settings/billing',
                    ], 403);
                }

                return redirect()
                    ->back()
                    ->with('error', $e->getMessage());
            }
        }

        return $next($request);
    }
}
