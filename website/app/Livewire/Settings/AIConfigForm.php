<?php

namespace App\Livewire\Settings;

use App\Models\AiConfig;
use App\Services\AI\AIManager;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\MistralProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Rule;
use Livewire\Component;

class AIConfigForm extends Component
{
    use AuthorizesWorkspaceActions;

    #[Rule('required|in:openai,anthropic,gemini,mistral')]
    public string $provider = 'openai';

    #[Rule('required|string|max:100')]
    public string $model = 'gpt-4o';

    #[Rule('numeric|min:0|max:2')]
    public float $temperature = 0.3;

    #[Rule('required|in:short,medium,long')]
    public string $max_reply_length = 'medium';

    #[Rule('required|in:professional,friendly,casual,sales,support,custom')]
    public string $personality_preset = 'friendly';

    #[Rule('nullable|string|max:5000')]
    public string $custom_prompt = '';

    #[Rule('nullable|string|max:5000')]
    public string $additional_instructions = '';

    #[Rule('required|integer|min:0|max:100')]
    public int $confidence_threshold = 75;

    #[Rule('required|in:autonomous,approval,suggestions')]
    public string $send_mode = 'approval';

    #[Rule('boolean')]
    public bool $auto_reply_enabled = false;

    #[Rule('boolean')]
    public bool $use_html_formatting = true;

    #[Rule('boolean')]
    public bool $use_bullet_points = true;

    #[Rule('nullable|string|max:100')]
    public string $reply_language = 'auto';

    #[Rule('required|in:always,never,ai_decides')]
    public string $include_greeting = 'ai_decides';

    #[Rule('required|in:always,never,ai_decides')]
    public string $include_signoff = 'always';

    #[Rule('nullable|string|max:200')]
    public string $signoff_text = 'Best regards,';

    #[Rule('boolean')]
    public bool $include_sender_name = true;

    #[Rule('boolean')]
    public bool $business_hours_only = false;

    #[Rule('boolean')]
    public bool $first_message_only = false;

    #[Rule('boolean')]
    public bool $skip_own_threads = false;

    // ── AI Auto-Escalation ──
    // When enabled, conversations where AI confidence falls below the
    // threshold are auto-assigned to a human, tagged, and a notification
    // is sent to the assignee. Reply stays as a draft for review.
    #[Rule('boolean')]
    public bool $escalation_enabled = false;

    #[Rule('required|integer|min:0|max:100')]
    public int $escalate_below_confidence = 50;

    /** Specific user id to assign to; null = round-robin among workspace agents. */
    #[Rule('nullable|integer')]
    public ?int $escalation_assignee_id = null;

    #[Rule('required|string|max:64')]
    public string $escalation_tag = 'needs_human';

    // Test AI
    public string $testMessage = '';
    public string $testResponse = '';
    public bool $testLoading = false;

    public function mount(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $config = AiConfig::where('workspace_id', $workspaceId)->first();

        if ($config) {
            $this->provider = $config->provider ?? 'openai';
            $this->model = $config->model ?? 'gpt-4o';
            $this->temperature = (float) ($config->temperature ?? 0.3);
            $this->max_reply_length = $config->max_reply_length ?? 'medium';
            $this->personality_preset = $config->personality_preset ?? 'friendly';
            $this->custom_prompt = $config->custom_prompt ?? '';
            $this->additional_instructions = $config->additional_instructions ?? '';
            $this->confidence_threshold = (int) ($config->confidence_threshold ?? 75);
            $this->send_mode = $config->send_mode ?? 'approval';
            $this->auto_reply_enabled = (bool) $config->auto_reply_enabled;
            $this->use_html_formatting = (bool) ($config->use_html_formatting ?? true);
            $this->use_bullet_points = (bool) ($config->use_bullet_points ?? true);
            $this->reply_language = $config->reply_language ?? 'auto';
            $this->include_greeting = $config->include_greeting ?? 'ai_decides';
            $this->include_signoff = $config->include_signoff ?? 'always';
            $this->signoff_text = $config->signoff_text ?? 'Best regards,';
            $this->include_sender_name = (bool) ($config->include_sender_name ?? true);
            $this->business_hours_only = (bool) $config->business_hours_only;
            $this->first_message_only = (bool) $config->first_message_only;
            $this->skip_own_threads = (bool) $config->skip_own_threads;
            $this->escalation_enabled = (bool) ($config->escalation_enabled ?? false);
            $this->escalate_below_confidence = (int) ($config->escalate_below_confidence ?? 50);
            $this->escalation_assignee_id = $config->escalation_assignee_id ? (int) $config->escalation_assignee_id : null;
            $this->escalation_tag = (string) ($config->escalation_tag ?? 'needs_human');
        }
    }

    public function save(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate();

        $workspaceId = auth()->user()->active_workspace_id;

        AiConfig::updateOrCreate(
            ['workspace_id' => $workspaceId],
            [
                'provider' => $this->provider,
                'model' => $this->model,
                'temperature' => $this->temperature,
                'max_reply_length' => $this->max_reply_length,
                'personality_preset' => $this->personality_preset,
                'custom_prompt' => $this->custom_prompt ?: null,
                'additional_instructions' => $this->additional_instructions ?: null,
                'confidence_threshold' => $this->confidence_threshold,
                'send_mode' => $this->send_mode,
                'auto_reply_enabled' => $this->auto_reply_enabled,
                'use_html_formatting' => $this->use_html_formatting,
                'use_bullet_points' => $this->use_bullet_points,
                'reply_language' => $this->reply_language,
                'include_greeting' => $this->include_greeting,
                'include_signoff' => $this->include_signoff,
                'signoff_text' => $this->signoff_text ?: null,
                'include_sender_name' => $this->include_sender_name,
                'business_hours_only' => $this->business_hours_only,
                'first_message_only' => $this->first_message_only,
                'skip_own_threads' => $this->skip_own_threads,
                'escalation_enabled' => $this->escalation_enabled,
                'escalate_below_confidence' => $this->escalate_below_confidence,
                'escalation_assignee_id' => $this->escalation_assignee_id ?: null,
                'escalation_tag' => trim($this->escalation_tag) ?: 'needs_human',
            ]
        );

        session()->flash('success', 'AI configuration saved successfully.');
    }

    public function testAi(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        if (! trim($this->testMessage)) {
            return;
        }

        // FIX-080: Rate limit via Laravel RateLimiter — 3 tests per minute per user
        $rateLimitKey = 'ai-test:' . auth()->id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 60)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->testResponse = "Rate limit reached. Please wait {$seconds} seconds before testing again.";
            return;
        }
        RateLimiter::hit($rateLimitKey, 60);

        $this->testLoading = true;
        $this->testResponse = '';

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        try {
            $workspace = auth()->user()->activeWorkspace;
            $aiManager = app(AIManager::class);

            $response = $aiManager->generateReply($workspace, $this->testMessage, [
                'agent_name' => auth()->user()->name,
            ]);

            $this->testResponse = $response->content
                . "\n\n---\nConfidence: {$response->confidence}% | Model: {$response->model} | Cost: \${$response->cost}";
        } catch (\Exception $e) {
            $this->testResponse = 'Error: ' . $e->getMessage();
        } finally {
            set_time_limit((int) $previousTimeout ?: 0);
            $this->testLoading = false;
        }
    }

    public function getModelsForProvider(): array
    {
        $allModels = AIManager::getAvailableModels();
        $providerModels = $allModels[$this->provider] ?? [];

        $result = [];
        foreach ($providerModels as $modelId => $info) {
            $result[$modelId] = $info['name'] . ' — ' . $info['description'];
        }

        return $result;
    }

    public function updatedProvider(): void
    {
        $models = $this->getModelsForProvider();
        $this->model = array_key_first($models) ?? 'gpt-4o';
    }

    public function render()
    {
        // Workspace members available as escalation assignees (active members only)
        $workspaceId = auth()->user()->active_workspace_id;
        $workspace = \App\Models\Workspace::find($workspaceId);

        $assigneeOptions = collect();
        if ($workspace) {
            $assigneeOptions = $workspace->members()
                ->wherePivot('status', 'active')
                ->get(['users.id', 'users.name', 'users.email']);
        }

        return view('livewire.settings.ai-config-form', [
            'models' => $this->getModelsForProvider(),
            'assigneeOptions' => $assigneeOptions,
        ]);
    }
}
