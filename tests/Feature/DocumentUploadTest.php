<?php

namespace Tests\Feature;

use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * UploadedFile::fake()->create() writes junk ASCII content, which finfo
     * detects as text/plain regardless of the declared mime type — so the
     * "mimes" validation rule needs a file with a real PDF magic header.
     */
    private function fakePdfUpload(string $name = 'chapter.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    public function test_authenticated_user_can_upload_a_pdf_chapter(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();
        $file = $this->fakePdfUpload();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'user_id' => $user->id,
            'original_name' => 'chapter.pdf',
            'status' => Document::STATUS_UPLOADED,
        ]);

        $document = Document::first();
        Storage::disk('local')->assertExists($document->file_url);

        Queue::assertPushed(ExtractDocumentTextJob::class, fn ($job) => $job->documentId === $document->id);
    }

    public function test_upload_rejects_unsupported_file_types(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('chapter.exe', 100, 'application/octet-stream');

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_guest_cannot_access_documents(): void
    {
        $this->get(route('documents.index'))->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_another_users_document(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $document = Document::create([
            'user_id' => $owner->id,
            'original_name' => 'secret.pdf',
            'file_url' => 'documents/secret.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1000,
            'status' => Document::STATUS_COMPLETED,
        ]);

        $this->actingAs($intruder)
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }
}
