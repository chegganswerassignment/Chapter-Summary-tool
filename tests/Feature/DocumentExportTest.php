<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\Document;
use App\Models\ProcessingResult;
use App\Models\User;
use App\Services\Export\DocumentPdfRenderer;
use App\Services\Export\PdfRenderingException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_PDF = '%PDF-1.4 fake';

    private User $owner;

    private Document $document;

    private ?string $renderedHtml = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $this->document = Document::create([
            'user_id' => $this->owner->id,
            'original_name' => 'Chapter 5.pdf',
            'file_url' => 'documents/chapter-5.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'status' => Document::STATUS_COMPLETED,
        ]);
    }

    private function makeResult(string $type, array $content, string $status = ProcessingResult::STATUS_COMPLETED): ProcessingResult
    {
        return ProcessingResult::create([
            'document_id' => $this->document->id,
            'result_type' => $type,
            'status' => $status,
            'content_json' => $content,
        ]);
    }

    private function summary(): ProcessingResult
    {
        return $this->makeResult(AiModel::TASK_SUMMARY, ['text' => "## المحور الرئيسي\n\nهذه **خلاصة** معرفية.\n\n- نقطة أولى\n- نقطة ثانية\n\n<script>alert(1)</script>"]);
    }

    private function translation(): ProcessingResult
    {
        return $this->makeResult(AiModel::TASK_TRANSLATION, ['text' => "السطر الأول\nالسطر الثاني"]);
    }

    private function quiz(): ProcessingResult
    {
        return $this->makeResult(AiModel::TASK_QUIZ, ['questions' => [[
            'question' => 'أين تحدث عملية التحلل السكري؟',
            'options' => ['النواة', 'العصارة الخلوية', 'الميتوكوندريا', 'جهاز جولجي'],
            'correct_index' => 1,
            'explanation' => 'تحدث في العصارة الخلوية.',
        ]]]);
    }

    /** Replace headless Chrome with an in-memory stub that records the HTML it was asked to print. */
    private function fakeRenderer(): MockInterface
    {
        return $this->partialMock(DocumentPdfRenderer::class, function (MockInterface $mock) {
            $mock->shouldReceive('render')->andReturnUsing(function (string $html) {
                $this->renderedHtml = $html;

                return self::FAKE_PDF;
            });
        });
    }

    private function export(?string $type = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)
            ->get(route('documents.export', array_filter(['document' => $this->document, 'type' => $type])));
    }

    public function test_a_single_type_exports_as_a_pdf_download(): void
    {
        $this->fakeRenderer();
        $this->summary();

        $response = $this->export('summary');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Chapter 5 - summary.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame(self::FAKE_PDF, $response->streamedContent());
    }

    public function test_all_combines_every_completed_result_with_a_heading_per_type(): void
    {
        $this->fakeRenderer();
        $this->summary();
        $this->translation();
        $this->quiz();

        $response = $this->export('all');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('Chapter 5 - all.pdf', $response->headers->get('Content-Disposition'));

        foreach (['الخلاصة المعرفية', 'التعريب الأكاديمي', 'التقييم الذاتي'] as $heading) {
            $this->assertStringContainsString($heading, $this->renderedHtml);
        }
        $this->assertStringContainsString('العصارة الخلوية', $this->renderedHtml);
    }

    public function test_type_defaults_to_all(): void
    {
        $this->fakeRenderer();
        $this->summary();
        $this->translation();

        $this->export()->assertOk();

        $this->assertStringContainsString('الخلاصة المعرفية', $this->renderedHtml);
        $this->assertStringContainsString('التعريب الأكاديمي', $this->renderedHtml);
    }

    public function test_all_skips_results_that_are_not_completed(): void
    {
        $this->fakeRenderer();
        $this->summary();
        $this->makeResult(AiModel::TASK_TRANSLATION, [], ProcessingResult::STATUS_PROCESSING);

        $this->export('all')->assertOk();

        $this->assertStringContainsString('الخلاصة المعرفية', $this->renderedHtml);
        $this->assertStringNotContainsString('التعريب الأكاديمي', $this->renderedHtml);
    }

    public function test_arabic_content_renders_rtl_and_markdown_is_converted_and_sanitised(): void
    {
        $this->fakeRenderer();
        $this->summary();

        $this->export('summary')->assertOk();

        $this->assertStringContainsString('<html lang="ar" dir="rtl">', $this->renderedHtml);
        $this->assertStringContainsString('<h2>المحور الرئيسي</h2>', $this->renderedHtml);
        $this->assertStringContainsString('<strong>خلاصة</strong>', $this->renderedHtml);
        $this->assertStringContainsString('<li>نقطة أولى</li>', $this->renderedHtml);
        $this->assertStringNotContainsString('<script>', $this->renderedHtml);
    }

    public function test_english_content_renders_ltr(): void
    {
        $this->fakeRenderer();
        $this->makeResult(AiModel::TASK_SUMMARY, ['text' => "# Overview\n\nPlain **English** summary."]);

        $this->export('summary')->assertOk();

        $this->assertStringContainsString('<html lang="en" dir="ltr">', $this->renderedHtml);
        $this->assertStringContainsString('Knowledge Summary', $this->renderedHtml);
    }

    public function test_the_filename_strips_the_extension_and_survives_arabic_names(): void
    {
        $this->fakeRenderer();
        $this->summary();
        $this->document->update(['original_name' => 'مادة: علم الأحياء؟.docx']);

        $response = $this->export('summary');

        $response->assertOk();
        $this->assertStringContainsString('- summary.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('.docx', $response->headers->get('Content-Disposition'));
    }

    public function test_a_non_owner_gets_403(): void
    {
        $this->summary();

        $this->export('summary', User::factory()->create())->assertForbidden();
    }

    public function test_an_admin_can_export_any_document(): void
    {
        $this->fakeRenderer();
        $this->summary();

        $this->export('summary', User::factory()->create(['role' => 'admin']))->assertOk();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('documents.export', $this->document))->assertRedirect(route('login'));
    }

    public function test_exporting_a_type_that_is_not_completed_returns_a_clear_error_without_launching_chrome(): void
    {
        $this->partialMock(DocumentPdfRenderer::class, fn (MockInterface $m) => $m->shouldNotReceive('render'));
        $this->makeResult(AiModel::TASK_TRANSLATION, [], ProcessingResult::STATUS_PROCESSING);

        $this->export('translation')
            ->assertRedirect(route('documents.show', $this->document))
            ->assertSessionHas('error', 'The translation is not ready yet, so it cannot be exported.');
    }

    public function test_exporting_a_type_that_does_not_exist_yet_returns_a_clear_error(): void
    {
        $this->partialMock(DocumentPdfRenderer::class, fn (MockInterface $m) => $m->shouldNotReceive('render'));

        $this->export('summary')
            ->assertRedirect(route('documents.show', $this->document))
            ->assertSessionHas('error', 'The summary is not ready yet, so it cannot be exported.');
    }

    public function test_exporting_everything_with_nothing_completed_returns_a_clear_error(): void
    {
        $this->partialMock(DocumentPdfRenderer::class, fn (MockInterface $m) => $m->shouldNotReceive('render'));

        $this->export('all')->assertSessionHas('error');
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $this->partialMock(DocumentPdfRenderer::class, fn (MockInterface $m) => $m->shouldNotReceive('render'));
        $this->summary();

        $this->export('quiz')->assertSessionHas('error');
        $this->export('../../etc/passwd')->assertSessionHas('error');
    }

    public function test_a_chrome_failure_becomes_a_clear_error_not_a_500(): void
    {
        $this->partialMock(DocumentPdfRenderer::class, function (MockInterface $mock) {
            $mock->shouldReceive('render')->andThrow(new PdfRenderingException('Chrome did not start'));
        });
        $this->summary();

        $this->export('summary')
            ->assertRedirect(route('documents.show', $this->document))
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'PDF engine'));
    }

    public function test_real_headless_chrome_produces_a_valid_arabic_pdf(): void
    {
        $this->summary();
        $this->translation();

        $response = $this->export('all');

        if ($response->isRedirect()) {
            $this->markTestSkipped('Headless Chrome is unavailable here: '.session('error'));
        }

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }
}
