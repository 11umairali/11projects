<?php

namespace App\Livewire\Settings;

use App\Services\Friends\PhoneVerifier;
use App\Services\Friends\RegistrationPhone;
use Livewire\Component;

class PhoneDiscovery extends Component
{
    public string $country = '';
    public string $national = '';
    public string $channel = '';
    public string $code = '';
    public array $status = [];
    public ?string $message = null;
    public bool $ok = true;

    public function mount(): void
    {
        $this->refresh();
    }

    private function refresh(): void
    {
        $this->status = app(PhoneVerifier::class)->status(auth()->user());
        if ($this->channel === '' && !empty($this->status['channels'])) $this->channel = $this->status['channels'][0];
    }

    private function done(array $res): void
    {
        [$this->ok, $this->message] = $res;
        $this->refresh();
    }

    /** With verification ON this sends a code; with it OFF the number is simply saved (unverified). */
    public function saveNumber(): void
    {
        [$e164, $err] = RegistrationPhone::build($this->country, $this->national);
        if ($err || !$e164) { $this->done([false, $err ?: 'Enter your phone number.']); return; }
        $pv = app(PhoneVerifier::class);
        if ($pv->available()) {
            $this->done($pv->sendCode(auth()->user(), $e164, $this->channel ?: null));
        } else {
            $res = $pv->saveUnverified(auth()->user(), $e164);
            if ($res[0]) { $this->national = ''; }
            $this->done($res);
        }
    }

    public function verify(): void
    {
        $res = app(PhoneVerifier::class)->verify(auth()->user(), $this->code);
        if ($res[0]) { $this->code = ''; $this->national = ''; }
        $this->done($res);
    }

    public function setDiscoverable(bool $on): void
    {
        $this->done(app(PhoneVerifier::class)->setDiscoverable(auth()->user(), $on));
    }

    public function removeNumber(): void
    {
        app(PhoneVerifier::class)->remove(auth()->user());
        $this->done([true, 'Your phone number was removed.']);
    }

    public function render()
    {
        return view('livewire.settings.phone-discovery');
    }
}
