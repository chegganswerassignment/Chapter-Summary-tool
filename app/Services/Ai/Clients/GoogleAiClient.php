<?php

namespace App\Services\Ai\Clients;

use App\Models\AiModel;
use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\DataTransferObjects\AiChatResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

class GoogleAiClient implements AiProviderClient
{
    private const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

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
        $url = "{$baseUrl}/models/{$model->api_model_code}:generateContent";

        $generationConfig = [
            'temperature' => 0.4,
        ];

        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $userParts = [];

        if ($filePath && file_exists($filePath)) {
            $mime = $mimeType ?: $this->detectMimeType($filePath);
            $filename = basename($filePath);
            $bytes = file_get_contents($filePath);

            if ($this->isDocx($filePath, $mime)) {
                $extractedText = $this->readDocxText($filePath);
                if ($extractedText !== null && trim($extractedText) !== '') {
                    $userParts[] = [
                        'text' => "Document Content ({$filename}):\n\n{$extractedText}",
                    ];
                } else {
                    $userParts[] = [
                        'inlineData' => [
                            'mimeType' => $mime,
                            'data' => base64_encode($bytes),
                        ],
                    ];
                }
            } else {
                $userParts[] = [
                    'inlineData' => [
                        'mimeType' => $mime,
                        'data' => base64_encode($bytes),
                    ],
                ];
            }
        }

        $userParts[] = ['text' => $userPrompt];

        $response = Http::timeout(180)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withQueryParameters(['key' => $apiKey])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => $userParts,
                    ],
                ],
                'generationConfig' => $generationConfig,
            ]);

        if ($response->failed()) {
            throw new AiProviderException(
                "Google AI request failed ({$response->status()}): ".$response->body()
            );
        }

        $data = $response->json();
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($content === null) {
            throw new AiProviderException('Google AI returned an empty response.');
        }

        return new AiChatResponse(
            content: $content,
            promptTokens: $data['usageMetadata']['promptTokenCount'] ?? null,
            completionTokens: $data['usageMetadata']['candidatesTokenCount'] ?? null,
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
