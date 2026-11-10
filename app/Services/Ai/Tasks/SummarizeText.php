<?php

namespace App\Services\Ai\Tasks;

use App\Models\AiModel;
use App\Services\Ai\AiProviderFactory;

class SummarizeText
{
    public function __construct(private readonly AiProviderFactory $factory) {}

    /**
     * @return array{model: AiModel, content: string, prompt_tokens: ?int, completion_tokens: ?int}
     */
    public function handle(string $filePath, ?string $mimeType = null): array
    {
        $system = <<<'PROMPT'
        You are an elite academic researcher and university professor. Your task is to analyze the provided document and produce a highly structured, comprehensive, and eloquent summary. Do not output a wall of text. Use professional Markdown formatting (headers, bullet points, bold text). The summary MUST include:
        1. Core Thesis / Main Idea.
        2. Key Arguments & Concepts (detailed).
        3. Methodology or Evidence presented (if any).
        4. Conclusion / Takeaways.
        Write the summary in highly professional Arabic (الفصحى الأكاديمية) unless the user explicitly requests otherwise, maintaining a strictly objective and scholarly tone.
        PROMPT;

        $user = 'Please analyze the attached document and provide a comprehensive academic summary based on your system instructions.';

        $result = $this->factory->chat(
            taskSpecialty: AiModel::TASK_SUMMARY,
            systemPrompt: $system,
            userPrompt: $user,
            filePath: $filePath,
            mimeType: $mimeType,
        );

        return [
            'model' => $result['model'],
            'content' => $result['response']->content,
            'prompt_tokens' => $result['response']->promptTokens,
            'completion_tokens' => $result['response']->completionTokens,
        ];
    }
}
