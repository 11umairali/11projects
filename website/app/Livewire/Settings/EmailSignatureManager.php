<?php

namespace App\Livewire\Settings;

use App\Models\EmailAccount;
use App\Models\EmailSignature;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Attributes\Rule;
use Livewire\Component;

/**
 * Per-account email signature manager.
 *
 * Backed by the `email_signatures` table. The actual auto-append on
 * outgoing mail is already wired into EmailSendService::getSignature()
 * for SMTP / Gmail / Outlook send paths — when an admin saves a default
 * signature here, it lands on every outbound message immediately.
 *
 * UX shape:
 *   • Top-of-page account picker (only shown if 2+ accounts connected)
 *   • List of signatures for the selected account, with default badge
 *     and per-toggle controls for "append on new" / "append on replies"
 *   • Add / Edit form opens inline with a rich-text editor
 *   • One default per account (selecting a new default unsets the old)
 */
class EmailSignatureManager extends Component
{
    use AuthorizesWorkspaceActions;

    /** Email account whose signatures we're showing. Null until first account loads. */
    public ?int $selectedAccountId = null;

    /** Form state. */
    public bool $showForm = false;
    public ?int $editingId = null;

    #[Rule('required|string|max:100')]
    public string $signatureName = '';

    #[Rule('required|string|max:50000')]
    public string $contentHtml = '';

    public bool $isDefault = true;
    public bool $appendToNew = true;
    public bool $appendToReplies = true;

    public function mount(): void
    {
        // Pre-select the user's default account if they have one.
        $first = $this->workspaceAccounts()->first();
        if ($first) {
            $this->selectedAccountId = $first->id;
        }
    }

    /**
     * All email accounts in the active workspace — drives the picker
     * and the per-account scoping. Computed each render so newly-added
     * accounts show up without a refresh.
     */
    public function getWorkspaceAccountsProperty()
    {
        return $this->workspaceAccounts();
    }

    /** All signatures for the selected account, default first. */
    public function getSignaturesProperty()
    {
        if (!$this->selectedAccountId) {
            return collect();
        }

        // Verify the account belongs to the user's workspace (IDOR guard).
        $account = $this->workspaceAccounts()
            ->where('id', $this->selectedAccountId)
            ->first();

        if (!$account) {
            return collect();
        }

        return EmailSignature::where('email_account_id', $account->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function selectAccount(int $accountId): void
    {
        $this->selectedAccountId = $accountId;
        $this->resetForm();
    }

    public function newSignature(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            return;
        }
        if (!$this->selectedAccountId) {
            session()->flash('error', __('Connect an email account first.'));
            return;
        }
        $this->resetForm();
        $this->showForm = true;
    }

    public function editSignature(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $sig = $this->findOwnedSignature($id);
        if (!$sig) return;

        $this->editingId = $sig->id;
        $this->signatureName = $sig->name;
        $this->contentHtml = $sig->content_html;
        $this->isDefault = (bool) $sig->is_default;
        $this->appendToNew = (bool) $sig->append_to_new;
        $this->appendToReplies = (bool) $sig->append_to_replies;
        $this->showForm = true;
    }

    public function save(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            return;
        }
        if (!$this->selectedAccountId) {
            session()->flash('error', __('No email account selected.'));
            return;
        }

        $this->validate();

        // Verify account ownership (IDOR guard) before any write.
        $account = $this->workspaceAccounts()
            ->where('id', $this->selectedAccountId)
            ->first();
        if (!$account) {
            session()->flash('error', __('Email account not found.'));
            return;
        }

        // If marking this signature default, unset any existing default
        // for the same account first — only one default per account.
        if ($this->isDefault) {
            EmailSignature::where('email_account_id', $account->id)
                ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
                ->update(['is_default' => false]);
        }

        if ($this->editingId) {
            $sig = $this->findOwnedSignature($this->editingId);
            if (!$sig) return;

            $sig->update([
                'name' => $this->signatureName,
                'content_html' => $this->contentHtml,
                'is_default' => $this->isDefault,
                'append_to_new' => $this->appendToNew,
                'append_to_replies' => $this->appendToReplies,
            ]);

            session()->flash('success', __('Signature updated.'));
        } else {
            EmailSignature::create([
                'email_account_id' => $account->id,
                'name' => $this->signatureName,
                'content_html' => $this->contentHtml,
                'is_default' => $this->isDefault,
                'append_to_new' => $this->appendToNew,
                'append_to_replies' => $this->appendToReplies,
            ]);

            session()->flash('success', __('Signature created.'));
        }

        $this->resetForm();
    }

    public function setDefault(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $sig = $this->findOwnedSignature($id);
        if (!$sig) return;

        EmailSignature::where('email_account_id', $sig->email_account_id)
            ->where('id', '!=', $sig->id)
            ->update(['is_default' => false]);

        $sig->update(['is_default' => true]);

        session()->flash('success', __('Default signature updated.'));
    }

    public function toggleNew(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $sig = $this->findOwnedSignature($id);
        if (!$sig) return;

        $sig->update(['append_to_new' => !$sig->append_to_new]);
    }

    public function toggleReplies(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $sig = $this->findOwnedSignature($id);
        if (!$sig) return;

        $sig->update(['append_to_replies' => !$sig->append_to_replies]);
    }

    public function deleteSignature(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) {
            return;
        }

        $sig = $this->findOwnedSignature($id);
        if (!$sig) return;

        $sig->delete();
        session()->flash('success', __('Signature deleted.'));

        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.settings.email-signature-manager', [
            'accounts'   => $this->workspaceAccounts,
            'signatures' => $this->signatures,
        ]);
    }

    /* ──────────────── helpers ──────────────── */

    /** Email accounts visible to the current workspace. */
    protected function workspaceAccounts()
    {
        $workspaceId = auth()->user()->active_workspace_id;
        return EmailAccount::where('workspace_id', $workspaceId)
            ->orderByDesc('is_default')
            ->orderBy('email')
            ->get();
    }

    /**
     * Resolve a signature ONLY if it belongs to an account in the
     * current workspace. Returns null on any IDOR attempt.
     */
    protected function findOwnedSignature(int $id): ?EmailSignature
    {
        $accountIds = $this->workspaceAccounts()->pluck('id');
        return EmailSignature::whereIn('email_account_id', $accountIds)
            ->where('id', $id)
            ->first();
    }

    protected function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->signatureName = '';
        $this->contentHtml = '';
        $this->isDefault = true;
        $this->appendToNew = true;
        $this->appendToReplies = true;
        $this->resetErrorBag();
    }
}
