<?php

namespace App\Jobs;

use App\Events\DocumentStatusUpdated;
use App\Models\AiModel;
use App\Models\Document;
use App\Models\ProcessingResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly string $documentId) {}

    public function handle(): void
    {
        $document = Document::findOrFail($this->documentId);

        try {
            $absolutePath = Storage::disk('local')->path($document->file_url);

            if (! file_exists($absolutePath)) {
                throw new \RuntimeException("Document file does not exist on disk at [{$absolutePath}].");
            }

            // Local text extraction is bypassed in favor of native multimodal processing.
            $document->update([
                'status' => Document::STATUS_QUEUED,
            ]);

            broadcast(new DocumentStatusUpdated($document));

            $this->queueProcessingTasks($document);
        } catch (Throwable $e) {
            Log::error('Document preparation failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            $document->update([
                'status' => Document::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            broadcast(new DocumentStatusUpdated($document));
        }
    }

    private function queueProcessingTasks(Document $document): void
    {
        $document->update(['status' => Document::STATUS_PROCESSING]);
        broadcast(new DocumentStatusUpdated($document));

        foreach ([AiModel::TASK_SUMMARY, AiModel::TASK_TRANSLATION, AiModel::TASK_QUIZ] as $taskType) {
            $result = ProcessingResult::create([
                'document_id' => $document->id,
                'result_type' => $taskType,
                'status' => ProcessingResult::STATUS_PENDING,
            ]);

            ProcessDocumentAiJob::dispatch($result->id);
        }
    }
}
