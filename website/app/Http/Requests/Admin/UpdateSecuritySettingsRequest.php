<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        // Existing toggle partials submit the strings "true" and "false".
        foreach ($this->all() as $key => $value) {
            if (in_array($value, ['true', 'false'], true)) $this->merge([$key => $value === 'true']);
        }
    }

    public function rules(): array
    {
        $groups = [
            'login' => [
                'max_login_attempts' => 'required|integer|min:1|max:20',
                'lockout_minutes' => 'required|integer|min:1|max:60',
                'captcha_enabled' => 'required|boolean',
                'captcha_site_key' => 'nullable|string|max:255',
                'captcha_secret_key' => 'nullable|string|max:255',
            ],
            'password' => [
                'password_min_length' => 'required|integer|min:6|max:72',
                'password_max_length' => 'required|integer|min:6|max:72|gte:password_min_length',
                'password_require_uppercase' => 'required|boolean',
                'password_require_lowercase' => 'required|boolean',
                'password_require_numbers' => 'required|boolean',
                'password_require_symbols' => 'required|boolean',
                'username_min_length' => 'required|integer|min:1|max:64',
                'username_max_length' => 'required|integer|min:1|max:64|gte:username_min_length',
                'username_allow_uppercase' => 'required|boolean',
                'username_allow_numbers' => 'required|boolean',
                'username_allow_dots' => 'required|boolean',
                'username_allow_dashes' => 'required|boolean',
                'username_allow_underscores' => 'required|boolean',
            ],
            'session' => ['session_lifetime_minutes' => 'required|integer|min:5|max:1440', 'single_session' => 'required|boolean'],
            'twofactor' => ['two_factor_admin_only' => 'required|boolean'],
            'blocking' => ['ip_blocking_enabled' => 'required|boolean', 'location_blocking_enabled' => 'required|boolean'],
        ];
        $group = $this->input('_settings_group', '');
        return ['_settings_group' => 'required|in:login,password,session,twofactor,blocking'] + (is_string($group) ? ($groups[$group] ?? []) : []);
    }
}
