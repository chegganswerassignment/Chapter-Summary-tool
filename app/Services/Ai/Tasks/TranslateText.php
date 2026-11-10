<?php

namespace App\Services\Ai\Tasks;

use App\Models\AiModel;
use App\Services\Ai\AiProviderFactory;

class TranslateText
{
    public function __construct(private readonly AiProviderFactory $factory) {}

    /**
     * @return array{model: AiModel, content: string, prompt_tokens: ?int, completion_tokens: ?int}
     */
    public function handle(string $filePath, ?string $mimeType = null, string $targetLanguage = 'Arabic'): array
    {
        $system = <<<'PROMPT'
        You are an expert academic translator specializing in translating complex university-level documents into highly eloquent and precise Arabic (الفصحى الأكاديمية). 
        Your ONLY task is to translate the content of the attached document. 
        Rules:
        1. Maintain the exact academic tone and context.
        2. Translate technical and scientific terms accurately using recognized Arabic equivalents.
        3. Do not summarize, skip, or paraphrase heavily; maintain the original structure and paragraph flow.
        4. Do not add any conversational filler (e.g., do not say 'Here is the translation'). Just output the translated text directly.
        PROMPT;

        $user = 'Translate the attached document into professional academic Arabic following the strict rules provided.';

        $result = $this->factory->chat(
            taskSpecialty: AiModel::TASK_TRANSLATION,
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
