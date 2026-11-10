<?php

namespace App\Services\Ai\Tasks;

use App\Models\AiModel;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\Exceptions\AiProviderException;

class GenerateQuiz
{
    public function __construct(private readonly AiProviderFactory $factory) {}

    /**
     * @return array{model: AiModel, content: array, prompt_tokens: ?int, completion_tokens: ?int}
     */
    public function handle(string $filePath, ?string $mimeType = null, int $questionCount = 5): array
    {
        $system = <<<PROMPT
        You are an expert psychometrician and university assessment designer. Your task is to analyze the attached document and generate a highly challenging, multiple-choice quiz (MCQ) that tests higher-order thinking (Bloom's Taxonomy: Application, Analysis, Evaluation), not just simple rote memorization.
        Rules:
        1. Generate exactly {$questionCount} questions in professional Arabic.
        2. Each question must have exactly 4 plausible options. Distractors (wrong options) must be scientifically/academically reasonable, not obvious throwaways.
        3. Provide a clear, educational 'explanation' for why the correct answer is right.
        4. You MUST return the output STRICTLY as a valid JSON object matching this schema, with no markdown code blocks, no formatting, and no conversational text:
        {
          "questions": [
            {
              "question": "String",
              "options": ["Option A", "Option B", "Option C", "Option D"],
              "correct_index": 0,
              "explanation": "String"
            }
          ]
        }
        PROMPT;

        $user = 'Analyze the attached document and generate the JSON quiz array based on its core academic concepts.';

        $result = $this->factory->chat(
            taskSpecialty: AiModel::TASK_QUIZ,
            systemPrompt: $system,
            userPrompt: $user,
            filePath: $filePath,
            mimeType: $mimeType,
            jsonMode: true,
        );

        $decoded = $this->decode($result['response']->content);

        return [
            'model' => $result['model'],
            'content' => $decoded,
            'prompt_tokens' => $result['response']->promptTokens,
            'completion_tokens' => $result['response']->completionTokens,
        ];
    }

    private function decode(string $raw): array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(json)?/i', '', $cleaned);
        $cleaned = preg_replace('/```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded) || ! isset($decoded['questions']) || ! is_array($decoded['questions'])) {
            throw new AiProviderException('Quiz generation returned invalid JSON: '.$raw);
        }

        foreach ($decoded['questions'] as $question) {
            if (
                ! isset($question['question'], $question['options'], $question['correct_index'])
                || ! is_array($question['options'])
                || count($question['options']) < 2
            ) {
                throw new AiProviderException('Quiz generation returned a malformed question.');
            }
        }

        return $decoded;
    }
}
