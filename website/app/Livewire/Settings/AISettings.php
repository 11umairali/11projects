<?php

namespace App\Livewire\Settings;

use App\Models\AiChannelConfig;
use App\Models\AiConfig;
use App\Traits\AuthorizesWorkspaceActions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

/**
 * Livewire backing the /settings/ai screen.
 *
 * The page is split into TWO sections:
 *   1. Global Provider/Billing card (provider, model, API key, cost cap)
 *      → backed by ai_configs (one row per workspace).
 *   2. Per-Channel cards (one each for email, whatsapp, sms, live_chat,
 *      telegram) — each with its OWN enable toggle, prompt, send_mode,
 *      confidence, escalation, reliability filters.
 *      → backed by ai_channel_configs (one row per workspace+channel).
 *
 * AiConfigResolver merges these two layers at runtime, so the rest of
 * the codebase calls one method and gets back a flat behavior object.
 */
class AISettings extends Component
{
    use AuthorizesWorkspaceActions;

    // ── Provider / Billing (global, ai_configs) ──
    public string $provider = 'openai';
    public string $model = 'gpt-4o';
    public bool $useOwnKey = false;
    public string $apiKey = '';
    public ?string $monthlyCostLimit = '10.00';

    // ── Per-channel state ──
    /** @var array<string, array<string,mixed>> */
    public array $channels = [];

    /** Channel currently expanded in the UI (null = none). */
    public ?string $expanded = 'email';

    // Test
    public bool $testing = false;
    public ?string $testResult = null;

    public function mount(): void
    {
        $workspaceId = auth()->user()->active_workspace_id;

        // Provider/billing layer
        $config = AiConfig::where('workspace_id', $workspaceId)->first();
        $adminProvider = \App\Models\SystemSetting::get('ai_default_provider', '') ?: null;
        $adminModel = \App\Models\SystemSetting::get('ai_default_model', '') ?: null;

        // Plan-gate: workspaces WITHOUT the `ai_own_key` feature are locked
        // to the admin-configured platform default provider/model. They use
        // the admin's API key, so they can't pick a different provider —
        // the admin's key only works with the provider it was issued for.
        $workspace = \App\Models\Workspace::find($workspaceId);
        $canOwnKey = $workspace
            ? app(\App\Services\PlanLimitService::class)->hasFeature($workspace, 'ai_own_key')
            : false;

        if ($config) {
            // If they don't have own-key access, force-snap to admin defaults
            // regardless of what's stored on their AiConfig row (handles the
            // case where they were on Pro and downgraded to Free).
            if (!$canOwnKey && $adminProvider) {
                $this->provider = $adminProvider;
                $this->model = $adminModel ?: 'gpt-4o';
            } else {
                $this->provider = $config->provider ?: ($adminProvider ?: 'openai');
                $this->model = $config->model ?: ($adminModel ?: 'gpt-4o');
            }
            $this->useOwnKey = (bool) $config->use_own_key;
            $this->apiKey = $config->use_own_key && $config->getRawOriginal('api_key') ? '********' : '';
            $this->monthlyCostLimit = $config->monthly_cost_limit !== null
                ? number_format((float) $config->monthly_cost_limit, 2, '.', '')
                : '10.00';
        } else {
            if ($adminProvider) $this->provider = $adminProvider;
            if ($adminModel) $this->model = $adminModel;
        }

        // Per-channel layer — one entry per channel (existing rows or seeded defaults)
        $existing = AiChannelConfig::where('workspace_id', $workspaceId)
            ->get()
            ->keyBy('channel');

        foreach (AiChannelConfig::CHANNELS as $ch) {
            $row = $existing->get($ch);
            if ($row) {
                $this->channels[$ch] = $this->rowToState($row);
            } else {
                // Seed sensible defaults — email gets strict filters, others lenient.
                $this->channels[$ch] = $this->defaultChannelState($ch);
            }
        }
    }

    /**
     * Map an existing AiChannelConfig row to the shape consumed by the blade.
     */
    private function rowToState(AiChannelConfig $row): array
    {
        $filters = $row->skip_filters ?? AiChannelConfig::defaultSkipFilters($row->channel);
        return [
            'enabled' => (bool) $row->enabled,
            'personality_preset' => $row->personality_preset ?: 'friendly',
            'custom_prompt' => $row->custom_prompt ?? '',
            'additional_instructions' => $row->additional_instructions ?? '',
            'send_mode' => $row->send_mode ?: 'approval',
            'confidence_threshold' => (int) ($row->confidence_threshold ?? 75),
            'max_reply_length' => $row->max_reply_length ?: 'medium',
            'first_message_only' => (bool) $row->first_message_only,
            'max_replies_per_conversation' => (int) ($row->max_replies_per_conversation ?? 3),
            'skip_own_threads' => (bool) $row->skip_own_threads,
            'reply_delay' => $row->reply_delay ?: 'none',
            'escalation_enabled' => (bool) $row->escalation_enabled,
            'escalate_below_confidence' => (int) ($row->escalate_below_confidence ?? 50),
            'escalation_assignee_id' => $row->escalation_assignee_id ? (int) $row->escalation_assignee_id : null,
            'escalation_tag' => $row->escalation_tag ?: 'needs_human',
            // Filter flags (flattened for cleaner blade binding)
            'filter_skip_noreply' => (bool) ($filters['skip_noreply'] ?? false),
            'filter_skip_autoreply' => (bool) ($filters['skip_autoreply'] ?? false),
            'filter_skip_bounces' => (bool) ($filters['skip_bounces'] ?? false),
            'filter_skip_promotional' => (bool) ($filters['skip_promotional'] ?? false),
            'filter_require_valid_from' => (bool) ($filters['require_valid_from'] ?? false),
            'filter_custom_blocklist' => is_array($filters['custom_blocklist'] ?? null)
                ? implode("\n", $filters['custom_blocklist'])
                : '',
        ];
    }

    /**
     * Default state for a channel that has no row yet. Email is strict
     * out of the box; chat-like channels are permissive.
     */
    private function defaultChannelState(string $channel): array
    {
        $isEmail = $channel === 'email';
        return [
            'enabled' => false, // user must opt in explicitly
            'personality_preset' => 'friendly',
            'custom_prompt' => '',
            'additional_instructions' => '',
            'send_mode' => 'approval',
            'confidence_threshold' => 75,
            'max_reply_length' => 'medium',
            'first_message_only' => false,
            'max_replies_per_conversation' => 3,
            'skip_own_threads' => true,
            'reply_delay' => $isEmail ? '30s' : 'none',
            'escalation_enabled' => false,
            'escalate_below_confidence' => 50,
            'escalation_assignee_id' => null,
            'escalation_tag' => 'needs_human',
            'filter_skip_noreply' => $isEmail,
            'filter_skip_autoreply' => $isEmail,
            'filter_skip_bounces' => $isEmail,
            'filter_skip_promotional' => false,
            'filter_require_valid_from' => $isEmail,
            'filter_custom_blocklist' => '',
        ];
    }

    public function toggleExpanded(string $channel): void
    {
        $this->expanded = $this->expanded === $channel ? null : $channel;
    }

    /**
     * Snap $model to the first AVAILABLE_MODELS entry of the new provider
     * whenever the user switches providers — the previous model ID is
     * almost certainly invalid for the new vendor (e.g. "gpt-4o" is
     * meaningless to Anthropic). Sourcing from the provider class keeps
     * the snap-to default in sync with the dropdown options.
     */
    public function updatedProvider(string $value): void
    {
        $providerClasses = [
            'openai'    => \App\Services\AI\Providers\OpenAIProvider::class,
            'anthropic' => \App\Services\AI\Providers\AnthropicProvider::class,
            'gemini'    => \App\Services\AI\Providers\GeminiProvider::class,
            'mistral'   => \App\Services\AI\Providers\MistralProvider::class,
        ];
        $cls = $providerClasses[$value] ?? $providerClasses['openai'];
        $first = array_key_first($cls::AVAILABLE_MODELS) ?: 'gpt-4o';
        $this->model = $first;
    }

    /**
     * Persist BOTH layers in one save: provider/billing → ai_configs,
     * and each channel's behavior → ai_channel_configs.
     */
    public function save(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate([
            'provider' => 'required|string|in:openai,anthropic,gemini,mistral,custom',
            'model' => 'required|string|max:100',
            'useOwnKey' => 'boolean',
            'apiKey' => 'nullable|string|max:500',
            'monthlyCostLimit' => 'nullable|numeric|min:0|max:99999.99',
        ]);

        $workspaceId = auth()->user()->active_workspace_id;
        $workspace = \App\Models\Workspace::find($workspaceId);
        $plan = app(\App\Services\PlanLimitService::class);

        // Plan gates — server-side enforcement. The UI also locks these
        // controls but a determined user could submit a crafted Livewire
        // payload, so the back-end is the source of truth.
        $hasOwnKey = $plan->hasFeature($workspace, 'ai_own_key');
        $hasPerChannel = $plan->hasFeature($workspace, 'ai_per_channel');
        $hasEscalation = $plan->hasFeature($workspace, 'ai_auto_escalation');

        // If user toggled "use own key" but their plan doesn't allow it,
        // force it off + clear the key so platform credentials are used.
        if ($this->useOwnKey && !$hasOwnKey) {
            $this->useOwnKey = false;
            $this->apiKey = '';
            session()->flash('error', 'Your plan does not include "Use my own API key". Upgrade to Pro to unlock it.');
            return;
        }

        // Without own-key access, the workspace is locked to the admin-
        // configured platform default provider/model — they pay the LLM
        // bill, so they pick the model. Override anything the user submitted
        // to prevent the admin's API key being used against a model it
        // wasn't intended for (which would either fail or rack up bills
        // on a more expensive model than the admin chose).
        if (!$hasOwnKey) {
            $adminProvider = \App\Models\SystemSetting::get('ai_default_provider', '') ?: null;
            $adminModel = \App\Models\SystemSetting::get('ai_default_model', '') ?: null;
            if ($adminProvider) {
                $this->provider = $adminProvider;
            }
            if ($adminModel) {
                $this->model = $adminModel;
            }
        }

        // 1. Provider/billing row
        $globalData = [
            'workspace_id' => $workspaceId,
            'provider' => $this->provider,
            'model' => $this->model,
            'use_own_key' => $this->useOwnKey,
            'monthly_cost_limit' => $this->monthlyCostLimit !== null && $this->monthlyCostLimit !== ''
                ? (float) $this->monthlyCostLimit
                : 0,
        ];
        if ($this->useOwnKey && $this->apiKey && $this->apiKey !== '********') {
            $globalData['api_key'] = $this->apiKey;
        }
        if (! $this->useOwnKey) {
            $globalData['api_key'] = null;
        }

        AiConfig::updateOrCreate(['workspace_id' => $workspaceId], $globalData);

        // 2. Per-channel rows
        foreach (AiChannelConfig::CHANNELS as $ch) {
            $state = $this->channels[$ch] ?? $this->defaultChannelState($ch);

            $blocklist = array_values(array_filter(array_map(
                fn ($l) => trim($l),
                preg_split('/[\r\n,]+/', (string) ($state['filter_custom_blocklist'] ?? '')) ?: []
            )));

            $skipFilters = [
                'skip_noreply' => (bool) ($state['filter_skip_noreply'] ?? false),
                'skip_autoreply' => (bool) ($state['filter_skip_autoreply'] ?? false),
                'skip_bounces' => (bool) ($state['filter_skip_bounces'] ?? false),
                'skip_promotional' => (bool) ($state['filter_skip_promotional'] ?? false),
                'skip_outbound' => true,
                'require_valid_from' => (bool) ($state['filter_require_valid_from'] ?? false),
                'custom_blocklist' => $blocklist,
            ];

            // Per-channel customization gate: only the email channel is
            // editable for plans without `ai_per_channel`. Other channels
            // stay at safe defaults (disabled) and any submitted overrides
            // are dropped on the floor.
            if (!$hasPerChannel && $ch !== 'email') {
                $state = $this->defaultChannelState($ch);
            }

            // Escalation gate: silently force-disable when the plan
            // doesn't include it.
            $escalationEnabled = $hasEscalation && (bool) ($state['escalation_enabled'] ?? false);

            AiChannelConfig::updateOrCreate(
                ['workspace_id' => $workspaceId, 'channel' => $ch],
                [
                    'enabled' => (bool) ($state['enabled'] ?? false),
                    'personality_preset' => $state['personality_preset'] ?? 'friendly',
                    'custom_prompt' => trim($state['custom_prompt'] ?? '') ?: null,
                    'additional_instructions' => trim($state['additional_instructions'] ?? '') ?: null,
                    'send_mode' => $state['send_mode'] ?? 'approval',
                    'confidence_threshold' => max(0, min(100, (int) ($state['confidence_threshold'] ?? 75))),
                    'max_reply_length' => $state['max_reply_length'] ?? 'medium',
                    'skip_filters' => $skipFilters,
                    'first_message_only' => (bool) ($state['first_message_only'] ?? false),
                    'max_replies_per_conversation' => max(1, min(99, (int) ($state['max_replies_per_conversation'] ?? 3))),
                    'skip_own_threads' => (bool) ($state['skip_own_threads'] ?? true),
                    'reply_delay' => $state['reply_delay'] ?? 'none',
                    'escalation_enabled' => $escalationEnabled,
                    'escalate_below_confidence' => max(0, min(100, (int) ($state['escalate_below_confidence'] ?? 50))),
                    'escalation_assignee_id' => $escalationEnabled && !empty($state['escalation_assignee_id']) ? (int) $state['escalation_assignee_id'] : null,
                    'escalation_tag' => trim($state['escalation_tag'] ?? '') ?: 'needs_human',
                ]
            );
        }

        session()->flash('success', 'AI configuration saved successfully.');
    }

    /** Test the OpenAI/Anthropic key (unchanged from previous version). */
    public function testApiKey(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) return;

        $throttleKey = 'ai-key-test:' . auth()->id();
        if (Cache::has($throttleKey)) {
            $this->testResult = 'error:Please wait 10 seconds between tests.';
            return;
        }
        Cache::put($throttleKey, true, 10);

        $this->testing = true;
        $this->testResult = null;

        $key = $this->apiKey;
        if (! $key || $key === '********') {
            $config = AiConfig::where('workspace_id', auth()->user()->active_workspace_id)->first();
            if ($config && $config->use_own_key) $key = $config->api_key;
        }
        if (! $key || $key === '********') {
            $this->testResult = 'error:No API key provided.';
            $this->testing = false;
            return;
        }

        try {
            $success = match ($this->provider) {
                'openai' => $this->testOpenAI($key),
                'anthropic' => $this->testAnthropic($key),
                default => strlen($key) >= 10,
            };
            $this->testResult = $success ? 'success:API key is valid!' : 'error:API key validation failed.';
        } catch (\Exception $e) {
            $this->testResult = 'error:' . $e->getMessage();
        }
        $this->testing = false;
    }

    private function testOpenAI(string $key): bool
    {
        return Http::withToken($key)->timeout(120)->connectTimeout(10)
            ->get('https://api.openai.com/v1/models')->successful();
    }

    private function testAnthropic(string $key): bool
    {
        return Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->timeout(120)->connectTimeout(10)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 10,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])->successful();
    }

    public function render()
    {
        $workspaceId = auth()->user()->active_workspace_id;
        $workspace = \App\Models\Workspace::find($workspaceId);

        $assigneeOptions = collect();
        if ($workspace) {
            $assigneeOptions = $workspace->members()
                ->wherePivot('status', 'active')
                ->get(['users.id', 'users.name', 'users.email']);
        }

        // Channel metadata for the UI: icon class, label, blurb.
        $channelMeta = [
            'email' => ['label' => 'Email', 'blurb' => 'AI replies to incoming emails. Strict filters skip noreply, bounces, and auto-replies.'],
            'whatsapp' => ['label' => 'WhatsApp', 'blurb' => 'AI replies to inbound WhatsApp messages via the Cloud API.'],
            'sms' => ['label' => 'SMS', 'blurb' => 'AI replies to inbound SMS via Twilio. Concise responses recommended.'],
            'live_chat' => ['label' => 'Live Chat', 'blurb' => 'AI handles website chat widget conversations 24/7.'],
            'telegram' => ['label' => 'Telegram', 'blurb' => 'AI replies to Telegram bot conversations.'],
        ];

        // Plan-feature gates. The view uses these to lock premium controls
        // behind upgrade prompts. Backend save() also re-checks these.
        $plan = app(\App\Services\PlanLimitService::class);
        $planFeatures = [
            'ai_own_key' => $workspace ? $plan->hasFeature($workspace, 'ai_own_key') : false,
            'ai_per_channel' => $workspace ? $plan->hasFeature($workspace, 'ai_per_channel') : false,
            'ai_auto_escalation' => $workspace ? $plan->hasFeature($workspace, 'ai_auto_escalation') : false,
        ];

        return view('livewire.settings.ai-settings', [
            'assigneeOptions' => $assigneeOptions,
            'channelMeta' => $channelMeta,
            'planFeatures' => $planFeatures,
        ]);
    }
}
