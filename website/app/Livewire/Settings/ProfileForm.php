<?php

namespace App\Livewire\Settings;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Rule as LivewireRule;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProfileForm extends Component
{
    use WithFileUploads;

    #[LivewireRule('required|string|max:255')]
    public string $name = '';

    public string $email = '';

    #[LivewireRule('nullable|string|max:30')]
    public string $phone = '';

    #[LivewireRule('nullable|string|max:100')]
    public string $timezone = '';

    #[LivewireRule('nullable|image|max:2048')]
    public $avatar = null;

    public ?string $avatarPreview = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';
        $this->timezone = $user->timezone ?? config('app.timezone', 'UTC');
        $this->avatarPreview = $user->avatar_path
            ? \Storage::url($user->avatar_path)
            : null;
    }

    public function updatedAvatar(): void
    {
        $this->validate(['avatar' => 'image|max:2048']);

        try {
            $this->avatarPreview = $this->avatar->temporaryUrl();
        } catch (\RuntimeException $e) {
            // S3 and some drivers don't support temporaryUrl on uploads
            $this->avatarPreview = null;
        }
    }

    public function removeAvatar(): void
    {
        $user = auth()->user();

        // Delete file from storage if it exists
        if ($user->avatar_path && \Storage::disk('public')->exists($user->avatar_path)) {
            \Storage::disk('public')->delete($user->avatar_path);
        }

        $this->avatar = null;
        $this->avatarPreview = null;

        $user->update(['avatar_path' => null]);
        // Use the toast-container event (not session flash) so the
        // success message renders immediately after the Livewire AJAX
        // call without needing a full page reload.
        $this->dispatch('toast', type: 'success', message: 'Profile photo removed.');
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore(auth()->id()),
            ],
            'phone' => 'nullable|string|max:30',
            'timezone' => 'nullable|string|max:100',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $user = auth()->user();
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'timezone' => $this->timezone ?: null,
        ];

        if ($this->avatar) {
            // Delete old avatar if it exists on disk
            if ($user->avatar_path && \Storage::disk('public')->exists($user->avatar_path)) {
                \Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $this->avatar->store('avatars', 'public');
            $this->avatarPreview = \Storage::url($data['avatar_path']);
            $this->avatar = null;
        } elseif ($this->avatarPreview === null && $user->avatar_path) {
            // Avatar was removed
            if (\Storage::disk('public')->exists($user->avatar_path)) {
                \Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = null;
        }

        $user->update($data);

        // Toast-container event so the success message pops without a
        // full-page reload (session flashes only fire on initial render).
        $this->dispatch('toast', type: 'success', message: 'Profile updated successfully.');
    }

    public function render()
    {
        $timezones = cache()->remember('php_timezones', 86400, fn () => timezone_identifiers_list());

        return view('livewire.settings.profile-form', [
            'timezones' => $timezones,
        ]);
    }
}
