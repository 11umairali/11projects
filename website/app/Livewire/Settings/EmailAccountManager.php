<?php

namespace App\Livewire\Settings;

use App\Exceptions\PlanLimitReachedException;
use App\Models\EmailAccount;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Rule;
use Livewire\Component;

class EmailAccountManager extends Component
{
    use AuthorizesWorkspaceActions;

    public bool $showAddForm = false;
    public bool $showProviderPicker = false;
    public string $selectedProvider = '';
    public ?int $reconnectAccountId = null;

    #[Rule('required|email|max:255')]
    public string $accountEmail = '';

    #[Rule('nullable|string|max:255')]
    public string $displayName = '';

    #[Rule('required|string|max:255')]
    public string $imapHost = '';

    #[Rule('required|integer|min:1|max:65535')]
    public int $imapPort = 993;

    #[Rule('required|string|max:255')]
    public string $imapUsername = '';

    #[Rule('nullable|string|max:255')]
    public string $imapPassword = '';

    #[Rule('required|in:ssl,tls,none')]
    public string $imapEncryption = 'ssl';

    #[Rule('nullable|string|max:255')]
    public string $smtpHost = '';

    #[Rule('nullable|integer|min:1|max:65535')]
    public int $smtpPort = 587;

    #[Rule('nullable|string|max:255')]
    public string $smtpUsername = '';

    #[Rule('nullable|string|max:255')]
    public string $smtpPassword = '';

    #[Rule('nullable|in:ssl,tls,starttls,none')]
    public string $smtpEncryption = 'tls';

    public string $testStatus = '';
    public bool $testLoading = false;
    public ?int $editingAccountId = null;

    public function openAddForm(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->reset([
            'accountEmail', 'displayName', 'imapHost', 'imapUsername',
            'imapPassword', 'smtpHost', 'smtpUsername', 'smtpPassword',
            'testStatus', 'selectedProvider',
        ]);
        $this->imapPort = 993;
        $this->imapEncryption = 'ssl';
        $this->smtpPort = 587;
        $this->smtpEncryption = 'tls';
        $this->showAddForm = false;
        $this->showProviderPicker = true;
    }

    public function selectProvider(string $provider): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->selectedProvider = $provider;
        $presets = config('email_providers.presets');

        if (isset($presets[$provider])) {
            $preset = $presets[$provider];
            $this->imapHost = $preset['imap_host'];
            $this->imapPort = $preset['imap_port'];
            $this->imapEncryption = $preset['imap_encryption'];
            $this->smtpHost = $preset['smtp_host'];
            $this->smtpPort = $preset['smtp_port'];
            $this->smtpEncryption = $preset['smtp_encryption'];
        }

        $this->showProviderPicker = false;
        $this->showAddForm = true;
    }

    public function closeAddForm(): void
    {
        $this->showAddForm = false;
        $this->showProviderPicker = false;
        $this->selectedProvider = '';
        $this->editingAccountId = null;
        $this->resetValidation();
    }

    public function backToProviderPicker(): void
    {
        $this->showAddForm = false;
        $this->showProviderPicker = true;
        $this->selectedProvider = '';
        $this->reconnectAccountId = null;
        $this->resetValidation();
    }

    /**
     * Initiate OAuth flow for a new Gmail or Outlook account.
     * Creates a placeholder EmailAccount so the OAuth callback knows
     * exactly which record to populate (avoids overwriting existing accounts).
     */
    public function connectOAuth(string $provider): string
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return '';
        }

        if (! in_array($provider, ['gmail', 'outlook'])) {
            return '';
        }

        // Enforce plan limit on email accounts
        try {
            $workspace = Workspace::findOrFail(auth()->user()->active_workspace_id);
            app(PlanLimitService::class)->assertCanCreate($workspace, 'email_accounts');
        } catch (PlanLimitReachedException $e) {
            session()->flash('error', $e->getMessage());
            return '';
        }

        // Check if OAuth credentials are configured
        if ($provider === 'gmail' && ! config('services.google.client_id')) {
            session()->flash('error', 'Google OAuth not configured yet. Please use "Gmail (IMAP/SMTP)" option below instead — just enter your email and App Password.');
            return '';
        }

        if ($provider === 'outlook' && ! config('services.microsoft.client_id')) {
            session()->flash('error', 'Microsoft OAuth not configured yet. Please use "Outlook / Hotmail (IMAP/SMTP)" option below instead.');
            return '';
        }

        try {
            $workspaceId = auth()->user()->active_workspace_id;

            // Create a placeholder account for the OAuth callback to populate
            $account = EmailAccount::create([
                'workspace_id' => $workspaceId,
                'user_id' => auth()->id(),
                'email' => auth()->user()->email, // Placeholder, updated after OAuth
                'display_name' => auth()->user()->name,
                'provider' => $provider,
                'status' => 'disconnected',
                'is_default' => ! EmailAccount::where('workspace_id', $workspaceId)
                    ->where('is_default', true)
                    ->exists(),
            ]);

            $route = $provider === 'gmail' ? 'email-oauth.google' : 'email-oauth.microsoft';

            return route($route, ['account_id' => $account->id]);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to connect email: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Get the OAuth URL for reconnecting an existing account.
     */
    public function getReconnectOAuthUrl(int $accountId): string
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return '';
        }

        $account = EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($accountId);

        if (! $account->isOAuth()) {
            return '';
        }

        $route = $account->provider === 'gmail' ? 'email-oauth.google' : 'email-oauth.microsoft';

        return route($route, ['account_id' => $account->id]);
    }

    public function testConnection(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $this->validate([
            'imapHost' => 'required|string',
            'imapPort' => 'required|integer',
            'imapUsername' => 'required|string',
            'imapPassword' => 'required|string',
        ]);

        $this->testLoading = true;
        $this->testStatus = '';

        try {
            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make([
                'host' => $this->imapHost,
                'port' => (int) $this->imapPort,
                'encryption' => $this->imapEncryption ?: 'ssl',
                'validate_cert' => false,
                'timeout' => 120,
                'username' => $this->imapUsername,
                'password' => $this->imapPassword,
                'protocol' => 'imap',
            ]);
            $client->connect();
            $client->disconnect();
            $this->testStatus = 'success';
        } catch (\Exception $e) {
            $this->testStatus = 'error: ' . $e->getMessage();
        }

        $this->testLoading = false;
    }

    public function editAccount(int $accountId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $account = EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($accountId);

        $this->editingAccountId = $account->id;
        $this->accountEmail = $account->email;
        $this->displayName = $account->display_name ?? '';
        $this->imapHost = $account->imap_host ?? '';
        $this->imapPort = $account->imap_port ?? 993;
        $this->imapUsername = $account->imap_username ?? '';
        $this->imapPassword = '';
        $this->imapEncryption = $account->imap_encryption ?? 'ssl';
        $this->smtpHost = $account->smtp_host ?? '';
        $this->smtpPort = $account->smtp_port ?? 587;
        $this->smtpUsername = $account->smtp_username ?? '';
        $this->smtpPassword = '';
        $this->smtpEncryption = $account->smtp_encryption ?? 'tls';
        $this->testStatus = '';
        $this->showAddForm = true;
    }

    public function saveAccount(): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        // Password is required for new accounts but optional when editing
        if (! $this->editingAccountId && empty($this->imapPassword)) {
            $this->validate(['imapPassword' => 'required|string|max:255']);
        }

        $workspaceId = auth()->user()->active_workspace_id;

        // Enforce plan limit when creating a new email account (not when editing)
        if (! $this->editingAccountId) {
            try {
                $workspace = Workspace::findOrFail($workspaceId);
                app(PlanLimitService::class)->assertCanCreate($workspace, 'email_accounts');
            } catch (PlanLimitReachedException $e) {
                session()->flash('error', $e->getMessage());
                return;
            }
        }

        $data = [
            'email' => $this->accountEmail,
            'display_name' => $this->displayName ?: null,
            'provider' => 'imap',
            'imap_host' => $this->imapHost,
            'imap_port' => $this->imapPort,
            'imap_username' => $this->imapUsername,
            'imap_encryption' => $this->imapEncryption,
            'smtp_host' => $this->smtpHost ?: null,
            'smtp_port' => $this->smtpPort,
            'smtp_username' => $this->smtpUsername ?: null,
            'smtp_encryption' => $this->smtpEncryption,
            'status' => 'connected',
        ];

        // Only update passwords if provided (not blank)
        $passwordChanged = false;
        if ($this->imapPassword) {
            $data['imap_password'] = $this->imapPassword;
            $passwordChanged = true;
        }
        if ($this->smtpPassword) {
            $data['smtp_password'] = $this->smtpPassword;
            $passwordChanged = true;
        }

        if ($this->editingAccountId) {
            // Update existing — use model instance so encrypted casts work
            $account = EmailAccount::where('workspace_id', $workspaceId)
                ->where('id', $this->editingAccountId)
                ->firstOrFail();
            $account->fill($data);
            $account->save();

            // If password changed, re-verify the connection in background
            if ($passwordChanged) {
                $account->update(['status' => 'connected', 'error_message' => null]);
            }

            $this->editingAccountId = null;
            session()->flash('success', 'Email account updated successfully.');
        } else {
            // Create new
            $isFirst = ! EmailAccount::where('workspace_id', $workspaceId)->exists();
            EmailAccount::create(array_merge($data, [
                'workspace_id' => $workspaceId,
                'user_id' => auth()->id(),
                'imap_password' => $this->imapPassword,
                'smtp_password' => $this->smtpPassword ?: null,
                'is_default' => $isFirst,
                'ai_auto_reply' => false,
            ]));
            session()->flash('success', 'Email account connected successfully.');
        }

        $this->closeAddForm();
    }

    public function disconnect(int $accountId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $accountId)
            ->update(['status' => 'disconnected']);

        session()->flash('success', 'Email account disconnected.');
    }

    public function reconnect(int $accountId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->where('id', $accountId)
            ->update(['status' => 'connected', 'error_message' => null]);

        session()->flash('success', 'Email account reconnected.');
    }

    public function deleteAccount(int $accountId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $account = EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($accountId);

        $email = $account->email;
        $convCount = 0;

        // Cascade-delete conversations and their messages along with the
        // account. The DB-level FK was migrated to cascadeOnDelete in a
        // later migration, but older installs may still have nullOnDelete
        // leaving orphaned conversations with email_account_id = NULL.
        // Doing it explicitly in PHP guarantees the result regardless of
        // which FK rule is currently applied to the database.
        \Illuminate\Support\Facades\DB::transaction(function () use ($account, &$convCount) {
            $conversationIds = \App\Models\Conversation::where('email_account_id', $account->id)
                ->pluck('id');

            $convCount = $conversationIds->count();

            if ($convCount > 0) {
                // Children of the conversations first
                \App\Models\Message::whereIn('conversation_id', $conversationIds)->delete();
                if (\Illuminate\Support\Facades\Schema::hasTable('conversation_tag')) {
                    \Illuminate\Support\Facades\DB::table('conversation_tag')
                        ->whereIn('conversation_id', $conversationIds)->delete();
                }
                // Then the conversations themselves
                \App\Models\Conversation::whereIn('id', $conversationIds)->delete();
            }

            // Email signatures attached to this account
            if (\Illuminate\Support\Facades\Schema::hasTable('email_signatures')) {
                \Illuminate\Support\Facades\DB::table('email_signatures')
                    ->where('email_account_id', $account->id)->delete();
            }

            $account->delete();
        });

        $msg = $convCount > 0
            ? "Email account \"{$email}\" and {$convCount} conversation(s) deleted."
            : "Email account \"{$email}\" deleted.";

        session()->flash('success', $msg);
    }

    public function toggleAiReply(int $accountId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $account = EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($accountId);

        $newState = ! $account->ai_auto_reply;
        $account->update(['ai_auto_reply' => $newState]);

        $status = $newState ? 'enabled' : 'disabled';
        session()->flash('success', "AI auto-reply {$status} for {$account->email}.");
    }

    public function makeDefault(int $accountId): void
    {
        if (! $this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $workspaceId = auth()->user()->active_workspace_id;

        EmailAccount::where('workspace_id', $workspaceId)
            ->update(['is_default' => false]);

        EmailAccount::where('workspace_id', $workspaceId)
            ->where('id', $accountId)
            ->update(['is_default' => true]);

        session()->flash('success', 'Default email account updated.');
    }

    public function render()
    {
        $accounts = EmailAccount::where('workspace_id', auth()->user()->active_workspace_id)
            ->orderByDesc('is_default')
            ->orderBy('email')
            ->get();

        return view('livewire.settings.email-account-manager', [
            'accounts' => $accounts,
            'providerPresets' => config('email_providers.presets', []),
        ]);
    }
}
