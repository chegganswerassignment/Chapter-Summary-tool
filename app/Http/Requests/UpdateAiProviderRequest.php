<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('ai_providers', 'slug')->ignore($this->route('ai_provider'))],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['boolean'],
        ];
    }
}
