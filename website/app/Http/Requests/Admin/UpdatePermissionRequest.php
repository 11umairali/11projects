<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permId = $this->route('permission')?->id ?? $this->route('permission');

        return [
            'name' => ['required', 'string', 'max:80', "unique:permissions,name,{$permId}", 'regex:/^[a-z0-9\-]+\.[a-z0-9\-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Permission name must follow the format: module.action (e.g., users.create)',
        ];
    }
}
