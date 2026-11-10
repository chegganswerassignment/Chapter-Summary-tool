<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Document;
use App\Models\ProcessingResult;
use App\Services\Export\DocumentPdfRenderer;
use App\Services\Export\PdfRenderingException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentExportController extends Controller
{
    /** Order sections appear in when exporting everything. */
    private const SECTION_ORDER = [AiModel::TASK_SUMMARY, AiModel::TASK_TRANSLATION, AiModel::TASK_QUIZ];

    private const EXPORTABLE_TYPES = [AiModel::TASK_SUMMARY, AiModel::TASK_TRANSLATION, 'all'];

    public function __invoke(Request $request, Document $document, DocumentPdfRenderer $renderer): StreamedResponse|RedirectResponse
    {
        abort_unless(
            $document->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );

        $type = (string) $request->query('type', 'all');

        if (! in_array($type, self::EXPORTABLE_TYPES, true)) {
            return $this->fail($document, 'Unknown export type. Use summary, translation or all.');
        }

        $wanted = $type === 'all' ? self::SECTION_ORDER : [$type];

        $completed = $document->results()
            ->where('status', ProcessingResult::STATUS_COMPLETED)
            ->whereIn('result_type', $wanted)
            ->get()
            ->keyBy('result_type');

        $sections = collect($wanted)
            ->map(fn (string $resultType) => $completed->has($resultType) ? $this->buildSection($completed[$resultType]) : null)
            ->filter()
            ->values();

        if ($sections->isEmpty()) {
            return $this->fail($document, $type === 'all'
                ? 'Nothing is ready to export yet. Wait until at least one result has completed.'
                : "The {$type} is not ready yet, so it cannot be exported.");
        }

        $rootRtl = $sections->first()['rtl'];

        $html = view('pdf.document-export', [
            'document' => $document,
            'sections' => $sections,
            'rtl' => $rootRtl,
            'lang' => $rootRtl ? 'ar' : 'en',
            'css' => $renderer->appCss(),
            'fontCss' => $renderer->fontCss(),
            'exportedAt' => now(),
            'appName' => config('app.name'),
        ])->render();

        try {
            $pdf = $renderer->render($html);
        } catch (PdfRenderingException $e) {
            Log::error('PDF export failed', ['document_id' => $document->id, 'type' => $type, 'error' => $e->getMessage()]);

            return $this->fail($document, 'The PDF could not be generated right now because the PDF engine failed to start or timed out. Please try again, or contact an administrator.');
        }

        return response()->streamDownload(
            function () use ($pdf) {
                echo $pdf;
            },
            $this->filename($document, $type),
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'],
        );
    }

    private function fail(Document $document, string $message): RedirectResponse
    {
        return redirect()->route('documents.show', $document)->with('error', $message);
    }

    /**
     * @return array{type: string, kind: string, rtl: bool, html?: string, questions?: array<int, array<string, mixed>>}|null
     */
    private function buildSection(ProcessingResult $result): ?array
    {
        $content = $result->content_json ?? [];

        if ($result->result_type === AiModel::TASK_QUIZ) {
            $questions = array_values(array_filter($content['questions'] ?? [], 'is_array'));

            if ($questions === []) {
                return null;
            }

            $sample = collect($questions)->pluck('question')->implode(' ');

            return ['type' => $result->result_type, 'kind' => 'quiz', 'rtl' => $this->isRtl($sample), 'questions' => $questions];
        }

        $text = trim((string) ($content['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        return [
            'type' => $result->result_type,
            'kind' => 'text',
            'rtl' => $this->isRtl($text),
            'html' => $this->markdownToHtml($text, $result->result_type),
        ];
    }

    /**
     * Raw HTML is stripped and unsafe links are dropped: the text is model output derived from
     * an uploaded document, and it ends up inside a real browser.
     */
    private function markdownToHtml(string $text, string $type): string
    {
        $options = ['html_input' => 'strip', 'allow_unsafe_links' => false];

        if ($type === AiModel::TASK_TRANSLATION) {
            $options['renderer'] = ['soft_break' => "<br>\n"];
        }

        // Str::markdown already enables GitHub-flavored Markdown (tables, strikethrough).
        return Str::markdown($text, $options);
    }

    private function isRtl(string $text): bool
    {
        $arabic = preg_match_all('/\p{Arabic}/u', $text);
        $latin = preg_match_all('/\p{Latin}/u', $text);

        return $arabic > 0 && $arabic >= $latin;
    }

    private function filename(Document $document, string $type): string
    {
        $base = pathinfo($document->original_name, PATHINFO_FILENAME) ?: 'document';
        $base = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/u', ' ', $base));

        return ($base !== '' ? $base : 'document')." - {$type}.pdf";
    }
}
