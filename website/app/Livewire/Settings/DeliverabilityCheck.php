<?php

namespace App\Livewire\Settings;

use App\Models\EmailAccount;
use App\Services\Email\DeliverabilityChecker;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class DeliverabilityCheck extends Component
{
    use AuthorizesWorkspaceActions;

    public string $domain = '';

    public ?array $results = null;

    public bool $checking = false;

    /**
     * Auto-populate domain from the user's first connected email account.
     */
    public function mount(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        $account = EmailAccount::where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->orderByDesc('is_default')
            ->first();

        if ($account && $account->email) {
            $parts = explode('@', $account->email);
            if (count($parts) === 2) {
                $this->domain = $parts[1];
            }
        }
    }

    /**
     * Run deliverability checks for the entered domain.
     */
    public function checkDeliverability(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate([
            'domain' => [
                'required',
                'string',
                'max:253',
                'regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
            ],
        ], [
            'domain.required' => 'Please enter a domain to check.',
            'domain.regex' => 'Please enter a valid domain (e.g., example.com). Do not include http:// or paths.',
        ]);

        // Rate limit: 5 checks per hour per workspace
        $workspaceId = auth()->user()->active_workspace_id;
        $rateLimitKey = "deliverability_rate:{$workspaceId}";
        $checkCount = (int) Cache::get($rateLimitKey, 0);

        if ($checkCount >= 5) {
            $ttl = Cache::get("{$rateLimitKey}_ttl");
            $resetIn = $ttl ? now()->diffForHumans($ttl, ['parts' => 1]) : 'about an hour';
            session()->flash('error', "Rate limit reached (5 checks per hour). You can check again in {$resetIn}.");
            return;
        }

        // Increment rate counter (expires after 1 hour)
        Cache::put($rateLimitKey, $checkCount + 1, 3600);
        if ($checkCount === 0) {
            Cache::put("{$rateLimitKey}_ttl", now()->addHour(), 3600);
        }

        $this->checking = true;
        $this->results = null;

        try {
            $checker = new DeliverabilityChecker();

            // Clear any cached result if user explicitly re-checks
            $checker->clearCache($this->domain, $workspaceId);

            $this->results = $checker->checkDomain($this->domain, $workspaceId);
        } catch (\Throwable $e) {
            session()->flash('error', 'An unexpected error occurred while checking the domain. Please try again.');
        }

        $this->checking = false;
    }

    /**
     * Get a CSS color class based on score value.
     */
    public function getScoreColorClass(): string
    {
        if ($this->results === null) {
            return 'text-gray-400';
        }

        $score = $this->results['overall_score'] ?? 0;

        if ($score >= 80) {
            return 'text-green-500';
        }
        if ($score >= 50) {
            return 'text-yellow-500';
        }

        return 'text-red-500';
    }

    /**
     * Get score label for the overall result.
     */
    public function getScoreLabel(): string
    {
        if ($this->results === null) {
            return '';
        }

        $score = $this->results['overall_score'] ?? 0;

        if ($score >= 90) {
            return 'Excellent';
        }
        if ($score >= 70) {
            return 'Good';
        }
        if ($score >= 50) {
            return 'Fair';
        }
        if ($score >= 25) {
            return 'Poor';
        }

        return 'Critical';
    }

    public function render()
    {
        return view('livewire.settings.deliverability-check');
    }
}
