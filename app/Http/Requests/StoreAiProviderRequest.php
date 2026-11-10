<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:ai_providers,slug'],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['boolean'],
        ];
    }
}
