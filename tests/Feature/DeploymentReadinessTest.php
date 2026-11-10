<?php

namespace Tests\Feature;

use App\Events\ProcessingResultReady;
use App\Models\AiModel;
use App\Models\Document;
use App\Models\ProcessingResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_result_ready_broadcast_carries_no_content(): void
    {
        $document = Document::create([
            'user_id' => User::factory()->create()->id,
            'original_name' => 'big.pdf',
            'file_url' => 'documents/big.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1,
            'status' => Document::STATUS_PROCESSING,
        ]);

        $result = ProcessingResult::create([
            'document_id' => $document->id,
            'result_type' => AiModel::TASK_SUMMARY,
            'status' => ProcessingResult::STATUS_COMPLETED,
            'content_json' => ['text' => str_repeat('x', 50_000)],
            'error_message' => str_repeat('e', 5_000),
        ]);

        $payload = (new ProcessingResultReady($result))->broadcastWith();

        $this->assertSame(['result_id', 'document_id', 'result_type', 'status'], array_keys($payload));
        $this->assertSame($result->id, $payload['result_id']);
        $this->assertSame(AiModel::TASK_SUMMARY, $payload['result_type']);
        $this->assertSame(ProcessingResult::STATUS_COMPLETED, $payload['status']);
        $this->assertLessThan(1_000, strlen(json_encode($payload)), 'Must stay far below Reverb max_message_size.');
    }

    public function test_only_admins_can_view_horizon(): void
    {
        $this->assertFalse(Gate::forUser(null)->allows('viewHorizon'));
        $this->assertFalse(Gate::forUser(User::factory()->create(['role' => 'student']))->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser(User::factory()->create(['role' => 'admin']))->allows('viewHorizon'));

        $this->get('/horizon')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'student']))->get('/horizon')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/horizon')->assertOk();
    }

    public function test_the_redis_retry_after_outlasts_the_longest_job_timeout(): void
    {
        $this->assertGreaterThan(180, config('queue.connections.redis.retry_after'));
        $this->assertGreaterThan(180, config('horizon.defaults.supervisor-1.timeout'));
        $this->assertLessThan(
            config('queue.connections.redis.retry_after'),
            config('horizon.defaults.supervisor-1.timeout'),
        );
    }

    public function test_production_seeding_creates_no_demo_accounts(): void
    {
        $this->app['env'] = 'production';
        config(['app.seed_admin.email' => null, 'app.seed_admin.password' => null]);

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);

        config(['app.seed_admin.email' => 'owner@example.org', 'app.seed_admin.password' => 'a-long-random-secret']);
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertTrue(User::firstWhere('email', 'owner@example.org')->isAdmin());
    }
}
