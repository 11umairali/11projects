<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Services\Email\DeliverabilityChecker;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Website features that had no mobile API yet:
 *  – Email deliverability check (SPF / DKIM / DMARC / MX)      (Livewire\Settings\DeliverabilityCheck)
 *  – Dashboard "actionable insights"                           (Livewire\Dashboard\ActionableInsights)
 * Both reuse the website's own code, so results are identical.
 */
class ToolsController extends Controller
{
    use AuthorizesApiActions;

    /** The domain of the workspace's default connected mailbox – pre-fills the check form. */
    public function deliverabilityDefault(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $account = EmailAccount::where('workspace_id', $request->user()->active_workspace_id)
            ->where('status', 'connected')->orderByDesc('is_default')->first();
        $domain = '';
        if ($account && $account->email && str_contains($account->email, '@')) {
            $domain = explode('@', $account->email)[1];
        }
        return response()->json(['domain' => $domain]);
    }

    public function deliverabilityCheck(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/'],
        ], [
            'domain.regex' => 'Enter a valid domain such as example.com (no http:// and no path).',
        ]);

        $workspaceId = $request->user()->active_workspace_id;
        $key = "deliverability_rate:{$workspaceId}";
        $count = (int) Cache::get($key, 0);
        if ($count >= 5) {
            return response()->json(['message' => 'Limit reached: 5 checks per hour. Please try again later.'], 429);
        }
        Cache::put($key, $count + 1, 3600);

        try {
            $checker = new DeliverabilityChecker();
            $checker->clearCache($data['domain'], $workspaceId);
            return response()->json($checker->checkDomain($data['domain'], $workspaceId));
        } catch (\Throwable $e) {
            \Log::warning('Deliverability check failed: ' . $e->getMessage());
            return response()->json(['message' => 'The check could not be completed. Please try again.'], 500);
        }
    }

    /** Mail-server details (IMAP / SMTP / POP3) so the customer can use the mailbox in another app. Never contains a password. */
    public function mailSettings(Request $request): JsonResponse
    {
        return response()->json(['data' => \App\Support\MailServerInfo::for($request->user())]);
    }

    /** Same list the website dashboard shows (max 4, most important first, dismissed ones removed). */
    public function insights(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        try {
            auth()->setUser($request->user()); // the website component reads auth()->user()
            $component = new \App\Livewire\Dashboard\ActionableInsights();
            $method = new \ReflectionMethod($component, 'generateInsights');
            $method->setAccessible(true);
            return response()->json(['data' => array_values($method->invoke($component))]);
        } catch (\Throwable $e) {
            \Log::warning('Insights failed: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    public function dismissInsight(Request $request, string $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        Cache::put("insight_dismissed:{$request->user()->id}:" . preg_replace('/[^A-Za-z0-9_\-]/', '', $id), true, now()->addDays(7));
        return response()->json(['ok' => true]);
    }
}
