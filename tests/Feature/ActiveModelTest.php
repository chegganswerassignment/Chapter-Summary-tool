<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use App\Services\Ai\AiProviderFactory;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActiveModelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function provider(string $slug = 'anthropic', bool $active = true): AiProvider
    {
        return AiProvider::create(['name' => ucfirst($slug), 'slug' => $slug, 'is_active' => $active]);
    }

    private function model(AiProvider $provider, string $code, string $task = AiModel::TASK_ALL, bool $default = false): AiModel
    {
        return AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => $code,
            'api_model_code' => $code,
            'context_window' => 200000,
            'task_specialty' => $task,
            'is_default' => $default,
        ]);
    }

    public function test_activating_a_model_makes_it_the_only_default_for_every_task(): void
    {
        $provider = $this->provider();
        $old = $this->model($provider, 'old-model', AiModel::TASK_ALL, true);
        $legacy = $this->model($provider, 'legacy-quiz', AiModel::TASK_QUIZ, true);
        $new = $this->model($provider, 'new-model', AiModel::TASK_SUMMARY, false);

        $this->actingAs($this->admin())
            ->put(route('admin.ai-models.activate'), ['model_id' => $new->id])
            ->assertSessionHas('success');

        $this->assertSame(1, AiModel::where('is_default', true)->count());
        $this->assertFalse($old->fresh()->is_default);
        $this->assertFalse($legacy->fresh()->is_default);
        $this->assertTrue($new->fresh()->isActiveForAllTasks());

        $factory = new AiProviderFactory;
        foreach ([AiModel::TASK_SUMMARY, AiModel::TASK_TRANSLATION, AiModel::TASK_QUIZ] as $task) {
            $this->assertSame($new->id, $factory->resolveModelFor($task)->id);
        }
    }

    public function test_a_model_of_an_inactive_provider_cannot_be_activated(): void
    {
        $active = $this->model($this->provider('anthropic'), 'current', AiModel::TASK_ALL, true);
        $blocked = $this->model($this->provider('google', false), 'blocked');

        $this->actingAs($this->admin())
            ->put(route('admin.ai-models.activate'), ['model_id' => $blocked->id])
            ->assertSessionHas('error');

        $this->assertTrue($active->fresh()->isActiveForAllTasks());
        $this->assertFalse($blocked->fresh()->is_default);
    }

    public function test_students_cannot_activate_models(): void
    {
        $model = $this->model($this->provider(), 'm');

        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->put(route('admin.ai-models.activate'), ['model_id' => $model->id])
            ->assertForbidden();
    }

    public function test_creating_a_second_default_all_model_is_rejected_by_validation(): void
    {
        $provider = $this->provider();
        $this->model($provider, 'current', AiModel::TASK_ALL, true);

        $this->actingAs($this->admin())
            ->post(route('admin.ai-models.store'), [
                'provider_id' => $provider->id,
                'display_name' => 'Second',
                'api_model_code' => 'second',
                'context_window' => 8192,
                'task_specialty' => AiModel::TASK_ALL,
                'is_default' => true,
            ])
            ->assertSessionHasErrors('is_default');

        $this->assertDatabaseMissing('ai_models', ['api_model_code' => 'second']);
    }

    public function test_updating_a_model_into_a_second_default_all_is_rejected(): void
    {
        $provider = $this->provider();
        $this->model($provider, 'current', AiModel::TASK_ALL, true);
        $other = $this->model($provider, 'other');

        $this->actingAs($this->admin())
            ->put(route('admin.ai-models.update', $other), [
                'provider_id' => $provider->id,
                'display_name' => 'other',
                'api_model_code' => 'other',
                'context_window' => 8192,
                'is_default' => true,
            ])
            ->assertSessionHasErrors('is_default');
    }

    public function test_the_model_layer_refuses_a_second_default_all_row(): void
    {
        $provider = $this->provider();
        $this->model($provider, 'current', AiModel::TASK_ALL, true);

        $this->expectException(DomainException::class);

        $this->model($provider, 'second', AiModel::TASK_ALL, true);
    }

    public function test_the_active_model_cannot_be_deleted_but_others_can(): void
    {
        $provider = $this->provider();
        $active = $this->model($provider, 'active', AiModel::TASK_ALL, true);
        $spare = $this->model($provider, 'spare');
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('admin.ai-models.destroy', $active))->assertSessionHas('error');
        $this->assertDatabaseHas('ai_models', ['id' => $active->id]);

        $this->actingAs($admin)->delete(route('admin.ai-models.destroy', $spare))->assertSessionHas('success');
        $this->assertDatabaseMissing('ai_models', ['id' => $spare->id]);
    }

    public function test_the_admin_page_receives_the_active_model_id(): void
    {
        $provider = $this->provider();
        $active = $this->model($provider, 'active', AiModel::TASK_ALL, true);

        $this->actingAs($this->admin())
            ->get(route('admin.ai-routing'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AiRouting')
                ->where('activeModelId', $active->id)
                ->where('providers.0.models.0.is_active', true));
    }
}
