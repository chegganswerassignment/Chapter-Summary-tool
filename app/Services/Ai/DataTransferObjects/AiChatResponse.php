<?php

namespace App\Services\Ai\DataTransferObjects;

final class AiChatResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
    ) {}
}
