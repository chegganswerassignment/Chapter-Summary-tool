<?php

namespace App\Jobs;

use App\Events\DocumentStatusUpdated;
use App\Events\ProcessingResultReady;
use App\Models\AiModel;
use App\Models\Document;
use App\Models\ProcessingResult;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Tasks\GenerateQuiz;
use App\Services\Ai\Tasks\SummarizeText;
use App\Services\Ai\Tasks\TranslateText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public int $backoff = 15;

    public function __construct(public readonly string $resultId) {}

    public function handle(SummarizeText $summarize, TranslateText $translate, GenerateQuiz $quiz): void
    {
        $result = ProcessingResult::with('document')->findOrFail($this->resultId);
        $document = $result->document;

        $result->update(['status' => ProcessingResult::STATUS_PROCESSING]);

        $startedAt = microtime(true);

        try {
            $filePath = Storage::disk('local')->path($document->file_url);
            $mimeType = $document->mime_type;

            if (! file_exists($filePath)) {
                throw new \RuntimeException("Document file not found at [{$filePath}].");
            }

            $output = match ($result->result_type) {
                AiModel::TASK_SUMMARY => $summarize->handle($filePath, $mimeType),
                AiModel::TASK_TRANSLATION => $translate->handle($filePath, $mimeType),
                AiModel::TASK_QUIZ => $quiz->handle($filePath, $mimeType),
                default => throw new \InvalidArgumentException("Unknown result type [{$result->result_type}]"),
            };

            $content = is_array($output['content']) ? $output['content'] : ['text' => $output['content']];

            $result->update([
                'model_id' => $output['model']->id,
                'status' => ProcessingResult::STATUS_COMPLETED,
                'content_json' => $content,
                'prompt_tokens' => $output['prompt_tokens'],
                'completion_tokens' => $output['completion_tokens'],
                'processing_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $e) {
            if ($this->isTransient($e) && $this->attempts() < $this->tries) {
                Log::warning('AI processing task hit a transient error, retrying', [
                    'result_id' => $result->id,
                    'result_type' => $result->result_type,
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                    'retry_in_seconds' => $this->backoff,
                    'error' => $e->getMessage(),
                ]);

                $result->update(['status' => ProcessingResult::STATUS_PENDING]);
                $this->release($this->backoff);

                return;
            }

            Log::error('AI processing task failed', [
                'result_id' => $result->id,
                'result_type' => $result->result_type,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            $result->update([
                'status' => ProcessingResult::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'processing_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        }

        broadcast(new ProcessingResultReady($result->fresh()));

        $this->finalizeDocumentStatusIfComplete($document);
    }

    /**
     * Network failures and provider-side 5xx/429/408 responses are worth retrying;
     * everything else (401/400/403, validation, missing model, bad JSON) is not.
     */
    private function isTransient(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof AiProviderException && $e->isRetryable();
    }

    private function finalizeDocumentStatusIfComplete(Document $document): void
    {
        $results = $document->results()->get();
        $expectedTaskCount = 3;

        if ($results->count() < $expectedTaskCount) {
            return;
        }

        $stillPending = $results->whereIn('status', [
            ProcessingResult::STATUS_PENDING,
            ProcessingResult::STATUS_PROCESSING,
        ])->isNotEmpty();

        if ($stillPending) {
            return;
        }

        $allFailed = $results->every(fn (ProcessingResult $r) => $r->status === ProcessingResult::STATUS_FAILED);

        $document->update([
            'status' => $allFailed ? Document::STATUS_FAILED : Document::STATUS_COMPLETED,
        ]);

        broadcast(new DocumentStatusUpdated($document->fresh()));
    }
}
