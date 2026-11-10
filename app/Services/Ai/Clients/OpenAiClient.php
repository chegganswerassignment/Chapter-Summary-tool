<?php

namespace App\Services\Ai\Clients;

use App\Models\AiModel;
use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\DataTransferObjects\AiChatResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

class OpenAiClient implements AiProviderClient
{
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public function __construct(private readonly ?string $baseUrl = null) {}

    public function chat(
        string $systemPrompt,
        string $userPrompt,
        AiModel $model,
        string $apiKey,
        ?string $filePath = null,
        ?string $mimeType = null,
        bool $jsonMode = false
    ): AiChatResponse {
        $baseUrl = rtrim($this->baseUrl ?: self::DEFAULT_BASE_URL, '/');

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        if ($filePath && file_exists($filePath)) {
            $userContent = [];
            $mime = $mimeType ?: $this->detectMimeType($filePath);
            $filename = basename($filePath);
            $bytes = file_get_contents($filePath);
            $base64 = base64_encode($bytes);

            if ($this->isDocx($filePath, $mime)) {
                $extractedText = $this->readDocxText($filePath);
                if ($extractedText !== null && trim($extractedText) !== '') {
                    $userContent[] = [
                        'type' => 'text',
                        'text' => "Document Content ({$filename}):\n\n{$extractedText}",
                    ];
                } else {
                    $userContent[] = [
                        'type' => 'file',
                        'file' => [
                            'file_data' => "data:{$mime};base64,{$base64}",
                            'filename' => $filename,
                        ],
                    ];
                }
            } elseif (str_starts_with($mime, 'image/')) {
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$mime};base64,{$base64}",
                    ],
                ];
            } elseif ($mime === 'application/pdf') {
                $userContent[] = [
                    'type' => 'file',
                    'file' => [
                        'file_data' => "data:application/pdf;base64,{$base64}",
                        'filename' => $filename,
                    ],
                ];
            } elseif (str_starts_with($mime, 'text/')) {
                $userContent[] = [
                    'type' => 'text',
                    'text' => "Document Content ({$filename}):\n\n{$bytes}",
                ];
            } else {
                $userContent[] = [
                    'type' => 'file',
                    'file' => [
                        'file_data' => "data:{$mime};base64,{$base64}",
                        'filename' => $filename,
                    ],
                ];
            }

            $userContent[] = [
                'type' => 'text',
                'text' => $userPrompt,
            ];

            $messages[] = [
                'role' => 'user',
                'content' => $userContent,
            ];
        } else {
            $messages[] = [
                'role' => 'user',
                'content' => $userPrompt,
            ];
        }

        $payload = [
            'model' => $model->api_model_code,
            'temperature' => 0.4,
            'messages' => $messages,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout(180)
            ->withToken($apiKey)
            ->post("{$baseUrl}/chat/completions", $payload);

        if ($response->failed()) {
            throw new AiProviderException(
                "OpenAI request failed ({$response->status()}): ".$response->body()
            );
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            throw new AiProviderException('OpenAI returned an empty response.');
        }

        return new AiChatResponse(
            content: $content,
            promptTokens: $data['usage']['prompt_tokens'] ?? null,
            completionTokens: $data['usage']['completion_tokens'] ?? null,
        );
    }

    private function detectMimeType(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'txt' => 'text/plain',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => (function_exists('mime_content_type') && file_exists($filePath))
                ? (mime_content_type($filePath) ?: 'application/octet-stream')
                : 'application/octet-stream',
        };
    }

    private function isDocx(string $filePath, string $mime): bool
    {
        return str_ends_with(strtolower($filePath), '.docx')
            || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    private function readDocxText(string $filePath): ?string
    {
        if (! class_exists(\ZipArchive::class)) {
            return null;
        }

        $zip = new \ZipArchive;
        if ($zip->open($filePath) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        $clean = preg_replace('/<w:p[^>]*>/', "\n", $xml);
        $clean = preg_replace('/<w:br[^>]*>/', "\n", $clean);
        $clean = preg_replace('/<w:tab[^>]*>/', "\t", $clean);
        $clean = strip_tags($clean);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim($clean);
    }
}
