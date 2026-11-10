<?php

namespace App\Services\Ai\Clients;

use App\Models\AiModel;
use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\DataTransferObjects\AiChatResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

class AnthropicAiClient implements AiProviderClient
{
    private const DEFAULT_BASE_URL = 'https://api.anthropic.com/v1';

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
        $url = "{$baseUrl}/messages";

        $userContent = [];

        if ($filePath && file_exists($filePath)) {
            $mime = $mimeType ?: $this->detectMimeType($filePath);
            $filename = basename($filePath);
            $bytes = file_get_contents($filePath);
            $base64 = base64_encode($bytes);

            if ($mime === 'application/pdf') {
                $userContent[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => $base64,
                    ],
                ];
            } elseif (str_starts_with($mime, 'image/')) {
                $userContent[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime,
                        'data' => $base64,
                    ],
                ];
            } elseif ($this->isDocx($filePath, $mime)) {
                $extractedText = $this->readDocxText($filePath);
                $textBody = ($extractedText !== null && trim($extractedText) !== '')
                    ? $extractedText
                    : $bytes;

                $userContent[] = [
                    'type' => 'text',
                    'text' => "Document Content ({$filename}):\n\n{$textBody}",
                ];
            } elseif (str_starts_with($mime, 'text/')) {
                $userContent[] = [
                    'type' => 'text',
                    'text' => "Document Content ({$filename}):\n\n{$bytes}",
                ];
            } else {
                $userContent[] = [
                    'type' => 'document',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'application/pdf',
                        'data' => $base64,
                    ],
                ];
            }
        }

        $userContent[] = [
            'type' => 'text',
            'text' => $userPrompt,
        ];

        $effectiveSystem = $systemPrompt;
        if ($jsonMode) {
            $effectiveSystem .= "\n\nCRITICAL: Respond ONLY with a valid, raw JSON object. Do not include markdown code fences, preambles, or conversational commentary.";
        }

        $payload = [
            'model' => $model->api_model_code,
            'max_tokens' => 4096,
            'temperature' => 0.4,
            'system' => $effectiveSystem,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
        ];

        $headers = [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'anthropic-beta' => 'pdfs-2024-09-25',
            'Content-Type' => 'application/json',
        ];

        $response = Http::timeout(180)
            ->withHeaders($headers)
            ->post($url, $payload);

        if ($response->failed()) {
            throw new AiProviderException(
                "Anthropic request failed ({$response->status()}): ".$response->body()
            );
        }

        $data = $response->json();
        $textParts = [];

        foreach ($data['content'] ?? [] as $part) {
            if (($part['type'] ?? '') === 'text' && isset($part['text'])) {
                $textParts[] = $part['text'];
            }
        }

        $content = implode("\n", $textParts);

        if (trim($content) === '') {
            throw new AiProviderException('Anthropic returned an empty response.');
        }

        return new AiChatResponse(
            content: $content,
            promptTokens: $data['usage']['input_tokens'] ?? null,
            completionTokens: $data['usage']['output_tokens'] ?? null,
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
