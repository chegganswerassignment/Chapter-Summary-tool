<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $google = $this->upsertProvider('google', [
                'name' => 'Google AI',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'api_key' => config('services.google_ai.key'),
            ]);

            $openai = $this->upsertProvider('openai', [
                'name' => 'OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => config('services.openai.key'),
            ]);

            $anthropic = $this->upsertProvider('anthropic', [
                'name' => 'Anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'api_key' => config('services.anthropic.key'),
            ]);

            // One active model serves every task (summary, translation, quiz).
            // Clear all defaults first so no legacy per-task default survives.
            AiModel::query()->where('is_default', true)->update(['is_default' => false]);

            // Reference rows only: never default, kept so they can be selected later.
            $this->upsertReferenceModel($google, 'gemini-3.8-flash', 'Gemini 3.8 Flash', 1048576);
            $this->upsertReferenceModel($openai, 'gpt-6-luna', 'GPT-6 Luna', 1050000);

            AiModel::updateOrCreate(
                ['provider_id' => $anthropic->id, 'api_model_code' => 'claude-haiku-4-5'],
                [
                    'display_name' => 'Claude Haiku 4.5',
                    'context_window' => 200000,
                    'task_specialty' => AiModel::TASK_ALL,
                    'is_default' => true,
                ]
            );
        });
    }

    private function upsertReferenceModel(AiProvider $provider, string $code, string $name, int $contextWindow): void
    {
        $model = AiModel::updateOrCreate(
            ['provider_id' => $provider->id, 'api_model_code' => $code],
            [
                'display_name' => $name,
                'context_window' => $contextWindow,
                'task_specialty' => AiModel::TASK_ALL,
                'is_default' => false,
            ]
        );

        // Collapse legacy per-task duplicates of the same provider/model code.
        AiModel::query()
            ->where('provider_id', $provider->id)
            ->where('api_model_code', $code)
            ->where('id', '!=', $model->id)
            ->delete();
    }

    /**
     * Never overwrite a stored API key with null (e.g. when config is cached
     * or the env var is missing on this server), and never re-enable a
     * provider an admin has switched off: is_active is only set on creation.
     */
    private function upsertProvider(string $slug, array $attributes): AiProvider
    {
        if (blank($attributes['api_key'] ?? null)) {
            unset($attributes['api_key']);
        }

        $provider = AiProvider::firstOrNew(['slug' => $slug]);

        if (! $provider->exists) {
            $provider->is_active = true;
        }

        $provider->fill($attributes)->save();

        return $provider;
    }
}
