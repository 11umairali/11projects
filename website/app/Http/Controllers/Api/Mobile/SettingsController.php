<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Exceptions\PlanLimitReachedException;
use App\Http\Controllers\Controller;
use App\Jobs\RetryWebhookJob;
use App\Models\AiConfig;
use App\Models\AutoReplyRule;
use App\Models\ChannelIntegration;
use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\EmailSignature;
use App\Models\WebhookLog;
use App\Models\Workspace;
use App\Services\AI\AIManager;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesApiActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Workspace settings resources: auto-reply rules, email accounts & signatures,
 * channels, AI configuration, webhook logs, integrations.
 * Mirrors the Livewire\Settings\* components; secrets are never returned.
 */
class SettingsController extends Controller
{
    use AuthorizesApiActions;

    private function wid(Request $r): int { return (int) $r->user()->active_workspace_id; }

    // ═════════════════════════ Auto-reply rules ═════════════════════════

    private function ruleRow(AutoReplyRule $r): array
    {
        return [
            'id' => $r->id, 'name' => $r->name, 'keywords' => $r->keywords ?? [], 'match_type' => $r->match_type,
            'reply_body' => $r->reply_body, 'reply_subject' => $r->reply_subject, 'is_active' => (bool) $r->is_active,
            'channel' => $r->channel, 'first_message_only' => (bool) $r->first_message_only,
            'priority' => (int) $r->priority, 'usage_count' => (int) $r->usage_count,
        ];
    }

    private function ruleRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'keywords' => 'required|array|min:1', 'keywords.*' => 'string|max:100',
            'match_type' => 'required|in:any,all,exact',
            'reply_body' => 'required|string',
            'reply_subject' => 'nullable|string|max:255',
            'channel' => 'required|in:all,email,whatsapp,sms,chat,telegram',
            'priority' => 'required|integer|min:0|max:999',
            'is_active' => 'boolean', 'first_message_only' => 'boolean',
        ];
    }

    public function rules(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = AutoReplyRule::where('workspace_id', $this->wid($request))->orderByDesc('priority')->orderBy('name')->get()->map(fn ($r) => $this->ruleRow($r));
        return response()->json(['data' => $rows]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate($this->ruleRules());
        $d['keywords'] = array_values(array_filter(array_map('trim', $d['keywords'])));
        $r = AutoReplyRule::create($d + ['workspace_id' => $this->wid($request), 'is_active' => $d['is_active'] ?? true, 'first_message_only' => $d['first_message_only'] ?? true]);
        return response()->json(['data' => $this->ruleRow($r)], 201);
    }

    public function updateRule(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate($this->ruleRules());
        $d['keywords'] = array_values(array_filter(array_map('trim', $d['keywords'])));
        $r = AutoReplyRule::where('workspace_id', $this->wid($request))->findOrFail($id);
        $r->update($d);
        return response()->json(['data' => $this->ruleRow($r->fresh())]);
    }

    public function toggleRule(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $r = AutoReplyRule::where('workspace_id', $this->wid($request))->findOrFail($id);
        $r->update(['is_active' => !$r->is_active]);
        return response()->json(['data' => $this->ruleRow($r->fresh())]);
    }

    public function destroyRule(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        AutoReplyRule::where('workspace_id', $this->wid($request))->where('id', $id)->delete();
        return response()->json(['message' => 'Auto-reply rule deleted.']);
    }

    public function testRule(Request $request): JsonResponse
    {
        $d = $request->validate(['message' => 'required|string|max:2000']);
        $input = mb_strtolower(trim($d['message']));
        $rules = AutoReplyRule::where('workspace_id', $this->wid($request))->where('is_active', true)->orderByDesc('priority')->get();
        foreach ($rules as $rule) {
            $kw = $rule->keywords ?? [];
            $hit = match ($rule->match_type) {
                'any'   => collect($kw)->contains(fn ($k) => str_contains($input, mb_strtolower($k))),
                'all'   => collect($kw)->every(fn ($k) => str_contains($input, mb_strtolower($k))),
                'exact' => collect($kw)->contains(fn ($k) => $input === mb_strtolower($k)),
                default => false,
            };
            if ($hit) {
                return response()->json(['data' => ['matched' => true, 'rule' => $rule->name, 'priority' => $rule->priority, 'reply' => Str::limit($rule->reply_body, 300)]]);
            }
        }
        return response()->json(['data' => ['matched' => false, 'reply' => null]]);
    }

    // ═════════════════════════ Email accounts ═════════════════════════

    private function accRow(EmailAccount $a): array
    {
        return [
            'id' => $a->id, 'email' => $a->email, 'display_name' => $a->display_name, 'provider' => $a->provider,
            'status' => $a->status, 'error_message' => $a->error_message, 'is_default' => (bool) $a->is_default,
            'ai_auto_reply' => (bool) $a->ai_auto_reply, 'is_oauth' => $a->isOAuth(),
            'imap_host' => $a->imap_host, 'imap_port' => $a->imap_port, 'imap_encryption' => $a->imap_encryption,
            'smtp_host' => $a->smtp_host, 'smtp_port' => $a->smtp_port, 'smtp_encryption' => $a->smtp_encryption,
        ];
    }

    public function accounts(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = EmailAccount::where('workspace_id', $this->wid($request))->orderByDesc('is_default')->orderBy('id')->get()->map(fn ($a) => $this->accRow($a));
        return response()->json(['data' => $rows]);
    }

    private function accRules(bool $creating): array
    {
        return [
            'email' => 'required|email|max:255', 'display_name' => 'nullable|string|max:255',
            'imap_host' => 'required|string|max:255', 'imap_port' => 'required|integer|min:1|max:65535',
            'imap_username' => 'required|string|max:255', 'imap_password' => ($creating ? 'required' : 'nullable') . '|string|max:255',
            'imap_encryption' => 'required|in:ssl,tls,none',
            'smtp_host' => 'nullable|string|max:255', 'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255', 'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:ssl,tls,starttls,none',
        ];
    }

    public function testAccount(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate(['imap_host' => 'required|string', 'imap_port' => 'required|integer', 'imap_username' => 'required|string', 'imap_password' => 'required|string', 'imap_encryption' => 'nullable|in:ssl,tls,none']);
        try {
            $client = (new \Webklex\PHPIMAP\ClientManager())->make([
                'host' => $d['imap_host'], 'port' => (int) $d['imap_port'], 'encryption' => $d['imap_encryption'] ?? 'ssl',
                'validate_cert' => false, 'timeout' => 60, 'username' => $d['imap_username'], 'password' => $d['imap_password'], 'protocol' => 'imap',
            ]);
            $client->connect();
            $client->disconnect();
            return response()->json(['message' => 'Connection successful.', 'ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Connection failed: ' . $e->getMessage(), 'ok' => false], 422);
        }
    }

    public function storeAccount(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate($this->accRules(true));
        $wid = $this->wid($request);
        try {
            app(PlanLimitService::class)->assertCanCreate(Workspace::findOrFail($wid), 'email_accounts');
        } catch (PlanLimitReachedException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'PLAN_LIMIT'], 403);
        }
        $first = !EmailAccount::where('workspace_id', $wid)->exists();
        $a = EmailAccount::create($d + [
            'workspace_id' => $wid, 'user_id' => $request->user()->id, 'provider' => 'imap',
            'status' => 'connected', 'is_default' => $first, 'ai_auto_reply' => false,
        ]);
        return response()->json(['data' => $this->accRow($a)], 201);
    }

    public function updateAccount(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate($this->accRules(false));
        $a = EmailAccount::where('workspace_id', $this->wid($request))->findOrFail($id);
        foreach (['imap_password', 'smtp_password'] as $p) {
            if (empty($d[$p])) unset($d[$p]);       // keep existing secret when left blank
        }
        $a->fill($d + ['status' => 'connected']);
        $a->save();
        return response()->json(['data' => $this->accRow($a->fresh())]);
    }

    public function accountAction(Request $request, int $id, string $action): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $wid = $this->wid($request);
        $a = EmailAccount::where('workspace_id', $wid)->findOrFail($id);
        switch ($action) {
            case 'disconnect': $a->update(['status' => 'disconnected']); break;
            case 'reconnect':  $a->update(['status' => 'connected', 'error_message' => null]); break;
            case 'default':
                EmailAccount::where('workspace_id', $wid)->update(['is_default' => false]);
                $a->update(['is_default' => true]);
                break;
            case 'toggle-ai': $a->update(['ai_auto_reply' => !$a->ai_auto_reply]); break;
            default: return response()->json(['message' => 'Unknown action.'], 404);
        }
        return response()->json(['data' => $this->accRow($a->fresh())]);
    }

    public function destroyAccount(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $a = EmailAccount::where('workspace_id', $this->wid($request))->findOrFail($id);
        DB::transaction(function () use ($a) {
            Conversation::where('email_account_id', $a->id)->get()->each(function ($c) {
                $c->messages()->delete();
                $c->forceDelete();
            });
            $a->delete();
        });
        return response()->json(['message' => 'Email account removed.']);
    }

    // ── Signatures ──

    private function sigRow(EmailSignature $s): array
    {
        return ['id' => $s->id, 'email_account_id' => $s->email_account_id, 'name' => $s->name, 'content_html' => $s->content_html,
            'is_default' => (bool) $s->is_default, 'append_to_new' => (bool) $s->append_to_new, 'append_to_replies' => (bool) $s->append_to_replies];
    }

    private function ownedAccount(Request $r, int $id): EmailAccount
    {
        return EmailAccount::where('workspace_id', $this->wid($r))->findOrFail($id);
    }

    public function signatures(Request $request, int $accountId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $this->ownedAccount($request, $accountId);
        return response()->json(['data' => EmailSignature::where('email_account_id', $accountId)->orderByDesc('is_default')->orderBy('name')->get()->map(fn ($s) => $this->sigRow($s))]);
    }

    public function storeSignature(Request $request, int $accountId): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $this->ownedAccount($request, $accountId);
        $d = $request->validate(['name' => 'required|string|max:100', 'content_html' => 'required|string|max:50000',
            'is_default' => 'boolean', 'append_to_new' => 'boolean', 'append_to_replies' => 'boolean']);
        $d['content_html'] = \App\Helpers\HtmlSanitizer::sanitize($d['content_html']);
        if (!empty($d['is_default'])) EmailSignature::where('email_account_id', $accountId)->update(['is_default' => false]);
        $s = EmailSignature::create($d + ['email_account_id' => $accountId]);
        return response()->json(['data' => $this->sigRow($s)], 201);
    }

    public function updateSignature(Request $request, int $accountId, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $this->ownedAccount($request, $accountId);
        $d = $request->validate(['name' => 'required|string|max:100', 'content_html' => 'required|string|max:50000',
            'is_default' => 'boolean', 'append_to_new' => 'boolean', 'append_to_replies' => 'boolean']);
        $d['content_html'] = \App\Helpers\HtmlSanitizer::sanitize($d['content_html']);
        $s = EmailSignature::where('email_account_id', $accountId)->findOrFail($id);
        if (!empty($d['is_default'])) EmailSignature::where('email_account_id', $accountId)->where('id', '!=', $id)->update(['is_default' => false]);
        $s->update($d);
        return response()->json(['data' => $this->sigRow($s->fresh())]);
    }

    public function destroySignature(Request $request, int $accountId, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'interact')) return $deny;
        $this->ownedAccount($request, $accountId);
        EmailSignature::where('email_account_id', $accountId)->where('id', $id)->delete();
        return response()->json(['message' => 'Signature deleted.']);
    }

    // ═════════════════════════ Channels ═════════════════════════

    private const CHANNEL_FEATURE = ['whatsapp' => 'whatsapp', 'sms' => 'sms', 'telegram' => 'telegram', 'slack' => 'slack', 'chat' => 'live_chat'];

    private const CHANNEL_FIELDS = [
        'whatsapp' => ['phone_number_id', 'access_token', 'verify_token', 'app_secret'],
        'sms'      => ['sid', 'auth_token', 'phone_number'],
        'telegram' => ['bot_token'],
        'slack'    => ['client_id', 'client_secret', 'signing_secret'],
        'chat'     => ['widget_color', 'welcome_message', 'company_name', 'position', 'offline_message'],
    ];

    public function channels(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $wid = $this->wid($request);
        $rows = ChannelIntegration::where('workspace_id', $wid)->whereIn('channel', array_keys(self::CHANNEL_FIELDS))->get()->keyBy('channel');
        $widget = DB::table('chat_widgets')->where('workspace_id', $wid)->first();
        $ws = Workspace::find($wid);
        $svc = app(PlanLimitService::class);

        $out = [];
        foreach (self::CHANNEL_FIELDS as $ch => $fields) {
            $r = $rows->get($ch);
            $creds = $r?->credentials ?? [];
            $item = [
                'channel' => $ch, 'status' => $r?->status ?? 'inactive', 'error_message' => $r?->error_message,
                'available' => isset(self::CHANNEL_FEATURE[$ch]) ? $svc->hasFeature($ws, self::CHANNEL_FEATURE[$ch]) : true,
                'ai_auto_reply' => (bool) ($r?->ai_auto_reply ?? false),
                'account_name' => $r?->account_name,
                // Which secrets are set (never the values).
                'configured' => collect($fields)->mapWithKeys(fn ($f) => [$f => !empty($creds[$f])])->all(),
            ];
            if ($ch === 'chat') {
                $item['config'] = ['widget_color' => $creds['widget_color'] ?? '#5F33E1', 'welcome_message' => $creds['welcome_message'] ?? '',
                    'company_name' => $creds['company_name'] ?? '', 'position' => $creds['position'] ?? 'bottom-right', 'offline_message' => $creds['offline_message'] ?? ''];
                $item['public_id'] = $widget->public_id ?? null;
                $item['embed_snippet'] = $widget ? '<script src="' . url('/widget/loader.js') . '" data-widget="' . $widget->public_id . '" async></script>' : null;
            }
            if ($ch === 'sms' || $ch === 'whatsapp') $item['phone_number'] = $r?->phone_number ?? null;
            $out[] = $item;
        }
        $out[] = ['channel' => 'webhook_urls', 'urls' => [
            'telegram' => url('/api/webhooks/telegram'), 'whatsapp' => url('/api/webhooks/whatsapp'),
            'twilio' => url('/api/webhooks/twilio/incoming'), 'slack' => url('/api/webhooks/slack/events'),
        ]];
        return response()->json(['data' => $out]);
    }

    public function saveChannel(Request $request, string $channel): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        if (!isset(self::CHANNEL_FIELDS[$channel])) return response()->json(['message' => 'Unknown channel.'], 404);
        $wid = $this->wid($request);

        if (isset(self::CHANNEL_FEATURE[$channel]) && !app(PlanLimitService::class)->hasFeature(Workspace::find($wid), self::CHANNEL_FEATURE[$channel])) {
            return response()->json(['message' => ucfirst($channel) . ' is not available on your current plan. Please upgrade.', 'code' => 'PLAN_FEATURE'], 403);
        }

        $rules = match ($channel) {
            'whatsapp' => ['phone_number_id' => 'required|string|max:255', 'access_token' => 'required|string|max:1000', 'verify_token' => 'required|string|max:255', 'app_secret' => 'required|string|max:255'],
            'sms'      => ['sid' => 'required|string|max:255', 'auth_token' => 'required|string|max:255', 'phone_number' => 'required|string|max:30'],
            'telegram' => ['bot_token' => 'required|string|max:255'],
            'slack'    => ['client_id' => 'required|string|max:255', 'client_secret' => 'required|string|max:255', 'signing_secret' => 'required|string|max:255'],
            'chat'     => ['widget_color' => 'required|string|max:20', 'welcome_message' => 'required|string|max:500', 'company_name' => 'nullable|string|max:100',
                           'position' => 'required|in:bottom-right,bottom-left', 'offline_message' => 'nullable|string|max:500', 'ai_auto_reply' => 'boolean'],
        };
        $d = $request->validate($rules);

        $existing = ChannelIntegration::where('workspace_id', $wid)->where('channel', $channel)->first();
        $creds = collect($d)->only(self::CHANNEL_FIELDS[$channel])->all();
        $chatAi = $channel === 'chat' ? (bool) ($d['ai_auto_reply'] ?? false) : false;

        $top = [
            'credentials' => $creds, 'config' => $channel === 'chat' ? ['ai_auto_reply' => $chatAi] : [],
            'status' => collect($creds)->filter(fn ($v) => !empty($v))->isNotEmpty() ? 'active' : 'inactive',
            'ai_auto_reply' => $chatAi,
        ];
        if ($channel === 'sms') { $top['phone_number'] = $d['phone_number']; $top['account_name'] = 'Twilio'; }
        if ($channel === 'whatsapp') { $top['phone_number'] = $d['phone_number_id']; }

        ChannelIntegration::updateOrCreate(['workspace_id' => $wid, 'channel' => $channel], $top);

        if ($channel === 'chat') {
            $wd = ['primary_color' => $d['widget_color'], 'welcome_message' => $d['welcome_message'], 'company_name' => $d['company_name'] ?? null,
                'position' => $d['position'], 'offline_message' => $d['offline_message'] ?? null, 'ai_auto_reply' => $chatAi, 'updated_at' => now()];
            if (!DB::table('chat_widgets')->where('workspace_id', $wid)->exists()) {
                DB::table('chat_widgets')->insert($wd + ['workspace_id' => $wid, 'public_id' => Str::random(32), 'created_at' => now()]);
            } else {
                DB::table('chat_widgets')->where('workspace_id', $wid)->update($wd);
            }
        }
        if ($channel === 'telegram' && str_starts_with(config('app.url'), 'https')) {
            try { (new \App\Services\Channels\TelegramService($d['bot_token']))->setWebhook(url('/api/webhooks/telegram')); } catch (\Throwable $e) { report($e); }
        }
        return response()->json(['message' => ucfirst($channel) . ' channel settings saved.']);
    }

    public function disconnectChannel(Request $request, string $channel): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        ChannelIntegration::where('workspace_id', $this->wid($request))->where('channel', $channel)->update(['status' => 'inactive']);
        return response()->json(['message' => ucfirst($channel) . ' channel disconnected.']);
    }

    // ═════════════════════════ AI configuration ═════════════════════════

    private const AI_FIELDS = ['provider', 'model', 'temperature', 'max_reply_length', 'personality_preset', 'custom_prompt', 'additional_instructions',
        'confidence_threshold', 'send_mode', 'auto_reply_enabled', 'use_html_formatting', 'use_bullet_points', 'reply_language', 'include_greeting',
        'include_signoff', 'signoff_text', 'include_sender_name', 'business_hours_only', 'first_message_only', 'skip_own_threads',
        'escalation_enabled', 'escalate_below_confidence', 'escalation_assignee_id', 'escalation_tag'];

    public function ai(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $c = AiConfig::where('workspace_id', $this->wid($request))->first();
        $defaults = ['provider' => 'openai', 'model' => 'gpt-4o', 'temperature' => 0.3, 'max_reply_length' => 'medium', 'personality_preset' => 'friendly',
            'custom_prompt' => '', 'additional_instructions' => '', 'confidence_threshold' => 75, 'send_mode' => 'approval', 'auto_reply_enabled' => false,
            'use_html_formatting' => true, 'use_bullet_points' => true, 'reply_language' => 'auto', 'include_greeting' => 'ai_decides', 'include_signoff' => 'always',
            'signoff_text' => 'Best regards,', 'include_sender_name' => true, 'business_hours_only' => false, 'first_message_only' => false, 'skip_own_threads' => false,
            'escalation_enabled' => false, 'escalate_below_confidence' => 50, 'escalation_assignee_id' => null, 'escalation_tag' => 'needs_human'];
        $data = $c ? collect(self::AI_FIELDS)->mapWithKeys(fn ($f) => [$f => $c->{$f} ?? $defaults[$f]])->all() : $defaults;
        return response()->json(['data' => $data]);
    }

    public function updateAi(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate([
            'provider' => 'required|in:openai,anthropic,gemini,mistral', 'model' => 'required|string|max:100',
            'temperature' => 'required|numeric|min:0|max:2', 'max_reply_length' => 'required|in:short,medium,long',
            'personality_preset' => 'required|in:professional,friendly,casual,sales,support,custom',
            'custom_prompt' => 'nullable|string|max:5000', 'additional_instructions' => 'nullable|string|max:5000',
            'confidence_threshold' => 'required|integer|min:0|max:100', 'send_mode' => 'required|in:autonomous,approval,suggestions',
            'auto_reply_enabled' => 'boolean', 'use_html_formatting' => 'boolean', 'use_bullet_points' => 'boolean',
            'reply_language' => 'nullable|string|max:100', 'include_greeting' => 'required|in:always,never,ai_decides',
            'include_signoff' => 'required|in:always,never,ai_decides', 'signoff_text' => 'nullable|string|max:200',
            'include_sender_name' => 'boolean', 'business_hours_only' => 'boolean', 'first_message_only' => 'boolean', 'skip_own_threads' => 'boolean',
            'escalation_enabled' => 'boolean', 'escalate_below_confidence' => 'required|integer|min:0|max:100',
            'escalation_assignee_id' => 'nullable|integer', 'escalation_tag' => 'required|string|max:64',
        ]);
        foreach (['custom_prompt', 'additional_instructions', 'signoff_text'] as $f) {
            $d[$f] = ($d[$f] ?? '') !== '' ? $d[$f] : null;
        }
        $d['escalation_tag'] = trim($d['escalation_tag']) ?: 'needs_human';
        AiConfig::updateOrCreate(['workspace_id' => $this->wid($request)], $d);
        return response()->json(['message' => 'AI configuration saved.']);
    }

    public function testAi(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $d = $request->validate(['message' => 'required|string|max:5000']);
        $key = 'ai-test:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['message' => 'Rate limit reached. Wait ' . RateLimiter::availableIn($key) . ' seconds.'], 429);
        }
        RateLimiter::hit($key, 60);
        try {
            $r = app(AIManager::class)->generateReply($request->user()->activeWorkspace, $d['message'], ['agent_name' => $request->user()->name]);
            return response()->json(['data' => ['content' => $r->content, 'confidence' => $r->confidence, 'model' => $r->model, 'cost' => $r->cost]]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 422);
        }
    }

    // ═════════════════════════ Webhook logs ═════════════════════════

    private function logRow(WebhookLog $l, bool $full = false): array
    {
        $row = ['id' => $l->id, 'direction' => $l->direction, 'url' => $l->url, 'method' => $l->method, 'status' => $l->status,
            'response_status' => $l->response_status, 'attempts' => $l->attempts, 'duration_ms' => $l->duration_ms,
            'error_message' => $l->error_message, 'created_at' => $l->created_at?->toIso8601String()];
        if ($full) $row += ['payload' => $l->payload, 'response_body' => $l->response_body];
        return $row;
    }

    public function webhookLogs(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $q = WebhookLog::where('workspace_id', $this->wid($request));
        if ($s = $request->input('status')) $q->where('status', $s);
        if ($t = $request->input('search')) $q->where('url', 'like', "%{$t}%");
        $p = $q->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 25), 100));
        return response()->json(['data' => $p->getCollection()->map(fn ($l) => $this->logRow($l))->values(),
            'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()]]);
    }

    public function webhookLog(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        return response()->json(['data' => $this->logRow(WebhookLog::where('workspace_id', $this->wid($request))->findOrFail($id), true)]);
    }

    public function retryWebhook(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        $log = WebhookLog::where('workspace_id', $this->wid($request))->findOrFail($id);
        if ($log->status === 'success') return response()->json(['message' => 'This webhook already succeeded.'], 422);
        if (empty($log->url)) return response()->json(['message' => 'Cannot retry — webhook URL is missing.'], 422);
        $log->update(['status' => 'pending', 'attempts' => 0, 'next_retry_at' => now(), 'error_message' => null]);
        RetryWebhookJob::dispatch($log);
        return response()->json(['message' => 'Webhook retry queued.']);
    }

    // ═════════════════════════ Integrations ═════════════════════════

    private const INTEGRATIONS = ['slack' => 'Slack', 'stripe' => 'Stripe', 'zapier' => 'Zapier', 'salesforce' => 'Salesforce', 'hubspot' => 'HubSpot', 'google_calendar' => 'Google Calendar'];

    public function integrations(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'view')) return $deny;
        $rows = ChannelIntegration::where('workspace_id', $this->wid($request))->whereIn('channel', array_keys(self::INTEGRATIONS))->get()->keyBy('channel');
        $out = [];
        foreach (self::INTEGRATIONS as $key => $name) {
            $r = $rows->get($key);
            $cfg = $r?->config ?? [];
            $out[] = ['key' => $key, 'name' => $name, 'status' => $r?->status ?? 'not_connected', 'account_name' => $r?->account_name,
                'error_message' => $r?->error_message, 'last_sync_at' => $cfg['last_sync_at'] ?? null, 'connected_at' => $r?->updated_at?->toIso8601String()];
        }
        return response()->json(['data' => $out]);
    }

    public function disconnectIntegration(Request $request, string $service): JsonResponse
    {
        if ($deny = $this->denyUnlessRole($request, 'manage')) return $deny;
        if (!isset(self::INTEGRATIONS[$service])) return response()->json(['message' => 'Unknown integration.'], 404);
        $i = ChannelIntegration::where('workspace_id', $this->wid($request))->where('channel', $service)->first();
        if ($i) {
            $i->update(['credentials' => null, 'status' => 'inactive', 'error_message' => null, 'account_name' => null, 'refresh_token' => null,
                'token_expires_at' => null, 'zapier_webhook_token' => null, 'zapier_api_token' => null]);
            if ($service === 'stripe') DB::table('system_settings')->where('key', 'stripe_secret_key')->delete();
        }
        return response()->json(['message' => self::INTEGRATIONS[$service] . ' disconnected.']);
    }
}
