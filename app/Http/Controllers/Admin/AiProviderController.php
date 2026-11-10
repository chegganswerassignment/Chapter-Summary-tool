<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiProviderRequest;
use App\Http\Requests\UpdateAiProviderRequest;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AiProviderController extends Controller
{
    public function index(): Response
    {
        $providers = AiProvider::query()
            ->with(['models' => fn ($query) => $query->orderBy('display_name')])
            ->orderBy('name')
            ->get()
            ->map(fn (AiProvider $provider) => [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'base_url' => $provider->base_url,
                'is_active' => $provider->is_active,
                'has_api_key' => (bool) $provider->api_key,
                'models' => $provider->models->map(fn ($model) => [
                    'id' => $model->id,
                    'display_name' => $model->display_name,
                    'api_model_code' => $model->api_model_code,
                    'context_window' => $model->context_window,
                    'is_active' => $model->isActiveForAllTasks(),
                ]),
            ]);

        return Inertia::render('Admin/AiRouting', [
            'providers' => $providers,
            'activeModelId' => AiModel::activeForAllTasks()?->id,
        ]);
    }

    public function store(StoreAiProviderRequest $request): RedirectResponse
    {
        AiProvider::create($request->validated());

        return back()->with('success', 'Provider created.');
    }

    public function update(UpdateAiProviderRequest $request, AiProvider $ai_provider): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $ai_provider->update($data);

        return back()->with('success', 'Provider updated.');
    }

    public function destroy(AiProvider $ai_provider): RedirectResponse
    {
        $ai_provider->delete();

        return back()->with('success', 'Provider deleted.');
    }
}
