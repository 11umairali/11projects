<?php
namespace App\Livewire\TempMail;

use App\Exceptions\PlanLimitReachedException;
use App\Models\Message;
use App\Models\TempMailAddress;
use App\Services\TempMailService;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;

class TempMailPage extends Component
{
    use AuthorizesWorkspaceActions;

    public ?int $activeAddressId = null;
    public ?int $selectedMessageId = null;
    public ?int $selectedDomainId = null;
    public string $addressLabel = '';
    public bool $showHistory = false;
    public bool $showGenerator = false;

    public function mount(): void
    {
        // Auto-select first active address
        $first = TempMailAddress::forWorkspace(auth()->user()->active_workspace_id)
            ->active()
            ->latest()
            ->first();

        if ($first) {
            $this->activeAddressId = $first->id;
        }
    }

    public function generateAddress(): void
    {
        if (!$this->authorizeWorkspaceAction('create')) return;

        try {
            // "Change" semantics: only ONE active temp address per
            // workspace at a time. Wipe every live address first so
            // the new one replaces it — no lingering pill bar, no
            // accidental inbox leaks into an old address the user
            // already moved past.
            $service = app(TempMailService::class);
            $existing = TempMailAddress::forWorkspace(auth()->user()->active_workspace_id)
                ->active()
                ->get();
            foreach ($existing as $old) {
                try {
                    $service->deleteAddress($old);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('TempMailPage.generate: cleanup of old address failed', [
                        'address_id' => $old->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $address = $service->generateAddress(
                auth()->user()->activeWorkspace,
                auth()->user(),
                $this->selectedDomainId ?: null,
                $this->addressLabel ?: null,
            );

            $this->activeAddressId = $address->id;
            $this->selectedMessageId = null;
            $this->addressLabel = '';
            $this->selectedDomainId = null;
            $this->showGenerator = false;

            session()->flash('success', "Temp email created: {$address->full_address}");
        } catch (PlanLimitReachedException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to generate address. ' . $e->getMessage());
        }
    }

    public function deleteAddress(int $id): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $address = TempMailAddress::where('workspace_id', auth()->user()->active_workspace_id)
            ->findOrFail($id);

        app(TempMailService::class)->deleteAddress($address);

        if ($this->activeAddressId === $id) {
            $this->activeAddressId = null;
            $this->selectedMessageId = null;
        }

        session()->flash('success', 'Temp email deleted.');
    }

    public function selectAddress(int $id): void
    {
        $this->activeAddressId = $id;
        $this->selectedMessageId = null;
    }

    public function selectMessage(int $id): void
    {
        $this->selectedMessageId = $id;
    }

    /**
     * Manual "Refresh" button — runs the IMAP sync SYNCHRONOUSLY (not the
     * fire-and-forget afterResponse the 3s poll uses) so new messages show
     * up in the same round-trip and the user gets immediate feedback. The
     * wire:loading spinner on the button is driven by `wire:target="syncNow"`.
     */
    public function syncNow(): void
    {
        if (! $this->authorizeWorkspaceAction('interact')) return;

        try {
            $domains = \App\Models\TempMailDomain::where('status', 'active')->get();

            $service = app(TempMailService::class);
            $total = 0;
            foreach ($domains as $domain) {
                try {
                    $total += $service->syncDomain($domain);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('TempMailPage.syncNow: domain sync failed', [
                        'domain' => $domain->domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($total > 0) {
                session()->flash('success', "Pulled {$total} new message(s).");
            } else {
                session()->flash('success', 'Inbox is up to date.');
            }
        } catch (\Throwable $e) {
            session()->flash('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    public function refreshInbox(): void
    {
        // Called by wire:poll every few seconds. IMAP sync is kicked into
        // `dispatchAfterResponse` so the Livewire poll returns in <50 ms with
        // whatever's already in the DB. The sync runs after the response is
        // flushed and the next poll (3 s later) reads its result.
        //
        // Domains live at the platform level — there is no workspace_id column
        // on temp_mail_domains, so we filter by status='active' only. Logging
        // is verbose on purpose so you can trace a single poll end-to-end in
        // storage/logs/laravel.log.
        $wsId = auth()->user()->active_workspace_id;
        $reqId = \Illuminate\Support\Str::uuid()->toString();

        \Illuminate\Support\Facades\Log::info('TempMail.poll: received', [
            'req' => $reqId,
            'workspace_id' => $wsId,
            'user_id' => auth()->id(),
        ]);

        if (! $wsId) {
            \Illuminate\Support\Facades\Log::warning('TempMail.poll: no active workspace, skipping', ['req' => $reqId]);
            return;
        }

        // Throttle so multiple tabs / overlapping polls don't hammer IMAP —
        // one sync per workspace every 3 s matches the poll cadence.
        $throttleKey = "tempmail_sync_throttle:{$wsId}";
        if (\Illuminate\Support\Facades\Cache::has($throttleKey)) {
            \Illuminate\Support\Facades\Log::info('TempMail.poll: throttled, another sync ran recently', ['req' => $reqId]);
            return;
        }
        \Illuminate\Support\Facades\Cache::put($throttleKey, true, 3);

        dispatch(function () use ($wsId, $reqId) {
            $started = microtime(true);
            try {
                $domains = \App\Models\TempMailDomain::where('status', 'active')->get();

                \Illuminate\Support\Facades\Log::info('TempMail.sync: starting deferred sync', [
                    'req' => $reqId,
                    'workspace_id' => $wsId,
                    'domain_count' => $domains->count(),
                    'domains' => $domains->pluck('domain')->all(),
                ]);

                if ($domains->isEmpty()) {
                    \Illuminate\Support\Facades\Log::warning('TempMail.sync: no active domains — admin must add one at /admin/temp-mail', ['req' => $reqId]);
                    return;
                }

                $service = app(\App\Services\TempMailService::class);
                foreach ($domains as $domain) {
                    $dStart = microtime(true);
                    \Illuminate\Support\Facades\Log::info('TempMail.sync: domain BEGIN', [
                        'req' => $reqId,
                        'domain' => $domain->domain,
                        'imap_host' => $domain->imap_host,
                        'imap_port' => $domain->imap_port,
                        'imap_user' => $domain->imap_username,
                    ]);
                    try {
                        $newCount = $service->syncDomain($domain);
                        \Illuminate\Support\Facades\Log::info('TempMail.sync: domain END', [
                            'req' => $reqId,
                            'domain' => $domain->domain,
                            'new_messages' => $newCount,
                            'elapsed_ms' => (int) ((microtime(true) - $dStart) * 1000),
                        ]);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('TempMail.sync: domain FAILED', [
                            'req' => $reqId,
                            'domain' => $domain->domain,
                            'error' => $e->getMessage(),
                            'class' => get_class($e),
                            'file' => $e->getFile() . ':' . $e->getLine(),
                        ]);
                    }
                }

                \Illuminate\Support\Facades\Log::info('TempMail.sync: deferred sync finished', [
                    'req' => $reqId,
                    'total_elapsed_ms' => (int) ((microtime(true) - $started) * 1000),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('TempMail.sync: deferred sync exception', [
                    'req' => $reqId,
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        })->afterResponse();
    }

    public function getActiveAddressesProperty(): \Illuminate\Support\Collection
    {
        $wsId = auth()->user()->active_workspace_id;

        if ($this->showHistory) {
            return TempMailAddress::withTrashed()
                ->forWorkspace($wsId)
                ->latest()
                ->limit(50)
                ->get();
        }

        return TempMailAddress::forWorkspace($wsId)
            ->active()
            ->latest()
            ->get();
    }

    public function getSelectedAddressProperty(): ?TempMailAddress
    {
        if (!$this->activeAddressId) return null;
        return TempMailAddress::withTrashed()->find($this->activeAddressId);
    }

    public function getMessagesProperty(): \Illuminate\Support\Collection
    {
        $address = $this->selectedAddress;
        if (!$address || !$address->conversation_id) return collect();

        return Message::where('conversation_id', $address->conversation_id)
            ->latest()
            ->limit(100)
            ->get();
    }

    public function getSelectedMessageDetailProperty(): ?Message
    {
        if (!$this->selectedMessageId) return null;
        return Message::with('attachments')->find($this->selectedMessageId);
    }

    public function getAvailableDomainsProperty(): \Illuminate\Support\Collection
    {
        return app(TempMailService::class)->getAvailableDomains();
    }

    public function getPlanUsageProperty(): array
    {
        $wsId = auth()->user()->active_workspace_id;
        $activeCount = TempMailAddress::where('workspace_id', $wsId)->active()->count();
        $limit = app(\App\Services\PlanLimitService::class)
            ->getFeatureLimit(auth()->user()->activeWorkspace, 'temp_mail_addresses');

        return [
            'used' => $activeCount,
            'limit' => $limit,
            'unlimited' => $limit === null,
        ];
    }

    public function render()
    {
        return view('livewire.temp-mail.temp-mail-page');
    }
}
