<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $documents = $request->user()
            ->documents()
            ->latest()
            ->get()
            ->map(fn (Document $document) => $this->transformSummary($document));

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        $document = $request->user()->documents()->create([
            'original_name' => $file->getClientOriginalName(),
            'file_url' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => Document::STATUS_UPLOADED,
        ]);

        ExtractDocumentTextJob::dispatch($document->id);

        return redirect()->route('documents.show', $document)
            ->with('success', 'Your chapter was uploaded and is now being processed.');
    }

    public function show(Request $request, Document $document): Response
    {
        $this->authorizeAccess($request, $document);

        $document->load('results.model.provider');

        return Inertia::render('Documents/Show', [
            'document' => $this->transformDetail($document),
        ]);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->authorizeAccess($request, $document);

        Storage::disk('local')->delete($document->file_url);
        $document->delete();

        return redirect()->route('documents.index')->with('success', 'Document deleted.');
    }

    private function countWords(string $text): int
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $tokens ? count($tokens) : 0;
    }

    private function authorizeAccess(Request $request, Document $document): void
    {
        abort_unless(
            $document->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );
    }

    private function transformSummary(Document $document): array
    {
        return [
            'id' => $document->id,
            'original_name' => $document->original_name,
            'status' => $document->status,
            'file_size' => $document->file_size,
            'created_at' => $document->created_at->toIso8601String(),
        ];
    }

    private function transformDetail(Document $document): array
    {
        return [
            ...$this->transformSummary($document),
            'failure_reason' => $document->failure_reason,
            'original_word_count' => $document->extracted_text
                ? $this->countWords($document->extracted_text)
                : null,
            'results' => $document->results->map(fn ($result) => [
                'id' => $result->id,
                'result_type' => $result->result_type,
                'status' => $result->status,
                'content' => $result->content_json,
                'error_message' => $result->error_message,
                'model' => $result->model ? [
                    'display_name' => $result->model->display_name,
                    'provider' => $result->model->provider->name,
                ] : null,
            ]),
        ];
    }
}
