<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Ai\Clients\AnthropicAiClient;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicAiClientTest extends TestCase
{
    use RefreshDatabase;

    private function makeAnthropicModel(string $modelCode = 'claude-3-5-sonnet-20241022'): AiModel
    {
        $provider = AiProvider::create([
            'name' => 'Anthropic',
            'slug' => 'anthropic',
            'is_active' => true,
        ]);

        return AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => 'Claude 3.5 Sonnet',
            'api_model_code' => $modelCode,
            'context_window' => 200000,
            'task_specialty' => AiModel::TASK_SUMMARY,
            'is_default' => false,
        ]);
    }

    public function test_it_sends_chat_request_with_correct_payload_and_parses_response(): void
    {
        $model = $this->makeAnthropicModel();

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_123',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'This is the generated summary.',
                    ],
                ],
                'usage' => [
                    'input_tokens' => 150,
                    'output_tokens' => 75,
                ],
            ], 200),
        ]);

        $client = new AnthropicAiClient;
        $response = $client->chat(
            systemPrompt: 'You are an academic summarizer.',
            userPrompt: 'Please summarize this document.',
            model: $model,
            apiKey: 'test-anthropic-key'
        );

        $this->assertSame('This is the generated summary.', $response->content);
        $this->assertSame(150, $response->promptTokens);
        $this->assertSame(75, $response->completionTokens);

        Http::assertSent(function ($request) use ($model) {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'test-anthropic-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $request->hasHeader('anthropic-beta', 'pdfs-2024-09-25')
                && $request['model'] === $model->api_model_code
                && $request['system'] === 'You are an academic summarizer.';
        });
    }

    public function test_it_sends_multimodal_pdf_file(): void
    {
        $model = $this->makeAnthropicModel();

        $tempPdf = tempnam(sys_get_temp_dir(), 'test_pdf').'.pdf';
        file_put_contents($tempPdf, '%PDF-1.4 test content');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_456',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Summary of PDF.',
                    ],
                ],
                'usage' => [
                    'input_tokens' => 500,
                    'output_tokens' => 100,
                ],
            ], 200),
        ]);

        try {
            $client = new AnthropicAiClient;
            $response = $client->chat(
                systemPrompt: 'You are an academic summarizer.',
                userPrompt: 'Summarize the attached PDF.',
                model: $model,
                apiKey: 'test-key',
                filePath: $tempPdf,
                mimeType: 'application/pdf'
            );

            $this->assertSame('Summary of PDF.', $response->content);

            Http::assertSent(function ($request) {
                $userContent = $request['messages'][0]['content'];
                $hasDocument = false;
                foreach ($userContent as $block) {
                    if (($block['type'] ?? '') === 'document' && ($block['source']['media_type'] ?? '') === 'application/pdf') {
                        $hasDocument = true;
                    }
                }

                return $hasDocument;
            });
        } finally {
            if (file_exists($tempPdf)) {
                unlink($tempPdf);
            }
        }
    }

    public function test_it_throws_ai_provider_exception_on_api_error(): void
    {
        $model = $this->makeAnthropicModel();

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'type' => 'error',
                'error' => ['message' => 'Invalid API key'],
            ], 401),
        ]);

        $this->expectException(AiProviderException::class);

        $client = new AnthropicAiClient;
        $client->chat(
            systemPrompt: 'System',
            userPrompt: 'Prompt',
            model: $model,
            apiKey: 'invalid-key'
        );
    }
}
