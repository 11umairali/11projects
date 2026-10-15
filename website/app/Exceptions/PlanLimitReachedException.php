<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class PlanLimitReachedException extends HttpException
{
    public readonly string $featureKey;
    public readonly int $currentUsage;
    public readonly int $limit;

    public function __construct(string $featureKey, int $currentUsage, int $limit)
    {
        $this->featureKey = $featureKey;
        $this->currentUsage = $currentUsage;
        $this->limit = $limit;

        $featureName = str_replace('_', ' ', $featureKey);

        if ($limit === 0 && $currentUsage === 0) {
            // Boolean feature that is disabled
            $message = "The {$featureName} feature is not available on your current plan.";
        } else {
            $message = "You've reached your {$featureName} limit ({$currentUsage}/{$limit}). Upgrade your plan for more.";
        }

        parent::__construct(403, $message);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $this->getMessage(),
                'code' => 'PLAN_LIMIT_REACHED',
                'feature' => $this->featureKey,
                'usage' => [
                    'used' => $this->currentUsage,
                    'limit' => $this->limit,
                ],
                'upgrade_url' => config('app.url') . '/settings/billing',
            ], 403);
        }

        return redirect()
            ->back()
            ->with('error', $this->getMessage());
    }
}
