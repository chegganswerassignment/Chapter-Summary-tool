<?php

namespace Tests\Feature;

use App\Events\DocumentStatusUpdated;
use App\Events\ProcessingResultReady;
use App\Jobs\ProcessDocumentAiJob;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Document;
use App\Models\ProcessingResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProcessDocumentAiJobRetryTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private ProcessingResult $result;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('documents/test.pdf', '%PDF-1.4 test');
        Event::fake([ProcessingResultReady::class, DocumentStatusUpdated::class]);

        $provider = AiProvider::create([
            'name' => 'Anthropic',
            'slug' => 'anthropic',
            'api_key' => 'test-key',
            'is_active' => true,
        ]);

        AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => 'Claude Haiku 4.5',
            'api_model_code' => 'claude-haiku-4-5',
            'context_window' => 200000,
            'task_specialty' => AiModel::TASK_ALL,
            'is_default' => true,
        ]);

        $document = Document::create([
            'user_id' => User::factory()->create()->id,
            'original_name' => 'test.pdf',
            'file_url' => 'documents/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 13,
            'status' => Document::STATUS_PROCESSING,
        ]);

        $this->result = ProcessingResult::create([
            'document_id' => $document->id,
            'result_type' => AiModel::TASK_SUMMARY,
            'status' => ProcessingResult::STATUS_PENDING,
        ]);
    }

    private function runJob(int $attempt): ProcessDocumentAiJob
    {
        $job = new ProcessDocumentAiJob($this->result->id);
        $job->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        app()->call([$job, 'handle']);

        return $job;
    }

    private function fakeError(int $status, string $message = 'boom'): void
    {
        Http::fake([self::ENDPOINT => Http::response(['type' => 'error', 'error' => ['message' => $message]], $status)]);
    }

    public function test_a_503_on_the_first_attempt_is_released_with_backoff_instead_of_failing(): void
    {
        $this->fakeError(503, 'high demand, try again later');

        $job = $this->runJob(attempt: 1);

        $job->assertReleased(delay: 15);
        $job->assertNotFailed();

        $result = $this->result->fresh();
        $this->assertSame(ProcessingResult::STATUS_PENDING, $result->status);
        $this->assertNull($result->error_message);
        $this->assertSame(Document::STATUS_PROCESSING, $result->document->status);

        Event::assertNotDispatched(ProcessingResultReady::class);
        Event::assertNotDispatched(DocumentStatusUpdated::class);
    }

    public function test_a_503_on_the_final_attempt_marks_the_result_failed(): void
    {
        $this->fakeError(503, 'high demand, try again later');

        $job = $this->runJob(attempt: 2);

        $job->assertNotReleased();

        $result = $this->result->fresh();
        $this->assertSame(ProcessingResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('503', $result->error_message);
        $this->assertStringContainsString('high demand', $result->error_message);

        Event::assertDispatched(ProcessingResultReady::class, fn ($e) => $e->result->status === ProcessingResult::STATUS_FAILED);
    }

    public function test_a_401_fails_immediately_without_retrying(): void
    {
        $this->fakeError(401, 'invalid x-api-key');

        $job = $this->runJob(attempt: 1);

        $job->assertNotReleased();

        $result = $this->result->fresh();
        $this->assertSame(ProcessingResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('401', $result->error_message);

        Http::assertSentCount(1);
        Event::assertDispatched(ProcessingResultReady::class);
    }

    /** @return array<string, array{int}> */
    public static function retryableStatuses(): array
    {
        return ['timeout' => [408], 'rate limit' => [429], 'server error' => [500], 'bad gateway' => [502], 'overloaded' => [529]];
    }

    /** @return array<string, array{int}> */
    public static function permanentStatuses(): array
    {
        return ['bad request' => [400], 'forbidden' => [403], 'not found' => [404], 'unprocessable' => [422]];
    }

    #[DataProvider('retryableStatuses')]
    public function test_transient_statuses_are_released(int $status): void
    {
        $this->fakeError($status);

        $this->runJob(attempt: 1)->assertReleased(delay: 15);

        $this->assertSame(ProcessingResult::STATUS_PENDING, $this->result->fresh()->status);
    }

    #[DataProvider('permanentStatuses')]
    public function test_permanent_statuses_fail_immediately(int $status): void
    {
        $this->fakeError($status);

        $this->runJob(attempt: 1)->assertNotReleased();

        $this->assertSame(ProcessingResult::STATUS_FAILED, $this->result->fresh()->status);
    }

    public function test_a_connection_failure_is_released(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->runJob(attempt: 1)->assertReleased(delay: 15);

        $this->assertSame(ProcessingResult::STATUS_PENDING, $this->result->fresh()->status);
    }

    public function test_a_missing_default_model_fails_immediately(): void
    {
        AiModel::query()->update(['is_default' => false]);
        Http::fake();

        $this->runJob(attempt: 1)->assertNotReleased();

        $result = $this->result->fresh();
        $this->assertSame(ProcessingResult::STATUS_FAILED, $result->status);
        $this->assertStringContainsString('No default AI model', $result->error_message);
        Http::assertNothingSent();
    }

    public function test_the_retry_succeeds_once_the_provider_recovers(): void
    {
        Http::fake([
            self::ENDPOINT => Http::sequence()
                ->push(['type' => 'error', 'error' => ['message' => 'overloaded']], 503)
                ->push([
                    'content' => [['type' => 'text', 'text' => 'The summary.']],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ], 200),
        ]);

        $this->runJob(attempt: 1)->assertReleased(delay: 15);
        $this->assertSame(ProcessingResult::STATUS_PENDING, $this->result->fresh()->status);

        $this->runJob(attempt: 2)->assertNotReleased();

        $result = $this->result->fresh();
        $this->assertSame(ProcessingResult::STATUS_COMPLETED, $result->status);
        $this->assertSame(['text' => 'The summary.'], $result->content_json);
        Http::assertSentCount(2);
    }
}
