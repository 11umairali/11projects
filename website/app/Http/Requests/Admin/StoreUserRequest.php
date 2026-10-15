<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z\s.\'-]*$/'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => \App\Support\CredentialPolicy::passwordRules(),
            'is_active' => ['sometimes', 'boolean'],
            'email_verified' => ['sometimes', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
            'admin_role' => ['nullable', 'string', 'in:super_admin,admin,moderator,support'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Name must start with a letter and contain only letters, spaces, dots, hyphens, or apostrophes.',
        ];
    }
}
