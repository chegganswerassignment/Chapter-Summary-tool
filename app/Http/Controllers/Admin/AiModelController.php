<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiModelRequest;
use App\Http\Requests\UpdateAiModelRequest;
use App\Models\AiModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    public function store(StoreAiModelRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['task_specialty'] ??= AiModel::TASK_ALL;
        $data['is_default'] ??= false;

        AiModel::create($data);

        return back()->with('success', 'Model created.');
    }

    public function update(UpdateAiModelRequest $request, AiModel $ai_model): RedirectResponse
    {
        $ai_model->update($request->validated());

        return back()->with('success', 'Model updated.');
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'model_id' => ['required', 'integer', 'exists:ai_models,id'],
        ]);

        $model = AiModel::with('provider')->findOrFail($data['model_id']);

        if (! $model->provider->is_active) {
            return back()->with('error', "Provider [{$model->provider->name}] is inactive. Activate it before selecting its model.");
        }

        $model->activate();

        return back()->with('success', "Active model set to {$model->display_name}.");
    }

    public function destroy(AiModel $ai_model): RedirectResponse
    {
        if ($ai_model->isActiveForAllTasks()) {
            return back()->with('error', 'This is the active model. Select a different active model before deleting it.');
        }

        $ai_model->delete();

        return back()->with('success', 'Model deleted.');
    }
}
