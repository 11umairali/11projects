<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', 'unique:permissions,name', 'regex:/^[a-z0-9\-]+\.[a-z0-9\-]+$/'],
            'guard_name' => ['nullable', 'string', 'in:web,api'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Permission name must follow the format: module.action (e.g., users.create)',
        ];
    }
}
