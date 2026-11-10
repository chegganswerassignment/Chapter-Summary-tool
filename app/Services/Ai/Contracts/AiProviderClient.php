<?php

namespace App\Services\Ai\Contracts;

use App\Models\AiModel;
use App\Services\Ai\DataTransferObjects\AiChatResponse;

interface AiProviderClient
{
    /**
     * Send a multimodal chat/completion request to the underlying provider.
     *
     * @param  string  $systemPrompt  System instructions for the AI model.
     * @param  string  $userPrompt  Prompt instructions from user/task.
     * @param  AiModel  $model  Model configuration.
     * @param  string  $apiKey  API key for the provider.
     * @param  string|null  $filePath  Absolute path to the document file (or null for text-only).
     * @param  string|null  $mimeType  MIME type of the document (e.g. application/pdf).
     * @param  bool  $jsonMode  When true, instruct the provider to return strict JSON.
     */
    public function chat(
        string $systemPrompt,
        string $userPrompt,
        AiModel $model,
        string $apiKey,
        ?string $filePath = null,
        ?string $mimeType = null,
        bool $jsonMode = false
    ): AiChatResponse;
}
