<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Exceptions\AiProviderException;
use Database\Seeders\AiProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderFactoryTest extends TestCase
{
    use RefreshDatabase;

    private function createProvider(bool $isActive = true): AiProvider
    {
        return AiProvider::create([
            'name' => 'OpenAI',
            'slug' => 'openai',
            'is_active' => $isActive,
            'api_key' => 'test-key',
        ]);
    }

    public function test_it_resolves_task_specific_model(): void
    {
        $provider = $this->createProvider();

        $specificModel = AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => 'GPT-4o Summary',
            'api_model_code' => 'gpt-4o',
            'context_window' => 128000,
            'task_specialty' => AiModel::TASK_SUMMARY,
            'is_default' => true,
        ]);

        $factory = new AiProviderFactory;
        $resolved = $factory->resolveModelFor(AiModel::TASK_SUMMARY);

        $this->assertSame($specificModel->id, $resolved->id);
    }

    public function test_it_falls_back_to_all_model_when_no_specific_model_exists(): void
    {
        $provider = $this->createProvider();

        $allModel = AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => 'Universal Model',
            'api_model_code' => 'gpt-4o-all',
            'context_window' => 128000,
            'task_specialty' => AiModel::TASK_ALL,
            'is_default' => true,
        ]);

        $factory = new AiProviderFactory;

        // Testing for a task that has no specific model configured
        $resolvedSummary = $factory->resolveModelFor(AiModel::TASK_SUMMARY);
        $resolvedTranslation = $factory->resolveModelFor(AiModel::TASK_TRANSLATION);
        $resolvedQuiz = $factory->resolveModelFor(AiModel::TASK_QUIZ);

        $this->assertSame($allModel->id, $resolvedSummary->id);
        $this->assertSame($allModel->id, $resolvedTranslation->id);
        $this->assertSame($allModel->id, $resolvedQuiz->id);
    }

    public function test_task_specific_model_takes_precedence_over_all_model(): void
    {
        $provider = $this->createProvider();

        AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => 'Universal Model',
            'api_model_code' => 'gpt-4o-all',
            'context_window' => 128000,
            'task_specialty' => AiModel::TASK_ALL,
            'is_default' => true,
        ]);

        $specificModel = AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => 'Specialized Quiz Model',
            'api_model_code' => 'gpt-4o-quiz',
            'context_window' => 128000,
            'task_specialty' => AiModel::TASK_QUIZ,
            'is_default' => true,
        ]);

        $factory = new AiProviderFactory;

        $resolvedQuiz = $factory->resolveModelFor(AiModel::TASK_QUIZ);
        $this->assertSame($specificModel->id, $resolvedQuiz->id);
    }

    public function test_seeder_leaves_exactly_one_default_model_that_serves_every_task(): void
    {
        $anthropic = AiProvider::create(['name' => 'Anthropic', 'slug' => 'anthropic', 'is_active' => true]);
        $google = AiProvider::create(['name' => 'Google AI', 'slug' => 'google', 'is_active' => true]);

        // Legacy state: the Claude row is a summary-only default, and Gemini
        // has duplicate per-task defaults for summary and translation.
        AiModel::create([
            'provider_id' => $anthropic->id,
            'display_name' => 'Claude Haiku 4.5',
            'api_model_code' => 'claude-haiku-4-5',
            'context_window' => 200000,
            'task_specialty' => AiModel::TASK_SUMMARY,
            'is_default' => true,
        ]);

        foreach ([AiModel::TASK_SUMMARY, AiModel::TASK_TRANSLATION] as $task) {
            AiModel::create([
                'provider_id' => $google->id,
                'display_name' => 'Gemini 3.8 Flash',
                'api_model_code' => 'gemini-3.8-flash',
                'context_window' => 1048576,
                'task_specialty' => $task,
                'is_default' => true,
            ]);
        }

        $this->seed(AiProviderSeeder::class);

        $this->assertSame(1, AiModel::where('is_default', true)->count());
        $this->assertSame(1, AiModel::where('api_model_code', 'gemini-3.8-flash')->count());

        $active = AiModel::activeForAllTasks();
        $this->assertSame('claude-haiku-4-5', $active->api_model_code);

        $factory = new AiProviderFactory;
        $this->assertSame($active->id, $factory->resolveModelFor(AiModel::TASK_SUMMARY)->id);
        $this->assertSame($active->id, $factory->resolveModelFor(AiModel::TASK_TRANSLATION)->id);
        $this->assertSame($active->id, $factory->resolveModelFor(AiModel::TASK_QUIZ)->id);
    }

    public function test_seeder_does_not_reactivate_a_provider_an_admin_switched_off(): void
    {
        AiProvider::create(['name' => 'Google AI', 'slug' => 'google', 'is_active' => false]);

        $this->seed(AiProviderSeeder::class);

        $this->assertFalse(AiProvider::where('slug', 'google')->first()->is_active);
        $this->assertTrue(AiProvider::where('slug', 'anthropic')->first()->is_active);
    }

    public function test_it_throws_exception_when_neither_specific_nor_all_model_is_configured(): void
    {
        $this->createProvider();

        $factory = new AiProviderFactory;

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('No default AI model is configured for task [summary].');

        $factory->resolveModelFor(AiModel::TASK_SUMMARY);
    }
}
