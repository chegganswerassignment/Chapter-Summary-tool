<?php

namespace App\Http\Requests;

use App\Models\AiModel;
use App\Rules\SingleActiveModel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAiModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $model = $this->route('ai_model');

        return [
            'provider_id' => ['required', 'exists:ai_providers,id'],
            'display_name' => ['required', 'string', 'max:255'],
            'api_model_code' => ['required', 'string', 'max:255'],
            'context_window' => ['required', 'integer', 'min:1024', 'max:5000000'],
            'task_specialty' => ['sometimes', 'string', 'in:'.implode(',', [
                AiModel::TASK_ALL,
                AiModel::TASK_SUMMARY,
                AiModel::TASK_TRANSLATION,
                AiModel::TASK_QUIZ,
            ])],
            'is_default' => ['sometimes', 'boolean', new SingleActiveModel($model instanceof AiModel ? $model->id : null)],
        ];
    }
}
