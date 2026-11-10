<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\DataTransferObjects\AiChatResponse;
use App\Services\Ai\Exceptions\AiProviderException;
use App\Services\Ai\Tasks\GenerateQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GenerateQuizTest extends TestCase
{
    use RefreshDatabase;

    private function makeModel(): AiModel
    {
        $provider = AiProvider::create([
            'name' => 'OpenAI',
            'slug' => 'openai',
            'is_active' => true,
        ]);

        return AiModel::create([
            'provider_id' => $provider->id,
            'display_name' => 'GPT-4o mini',
            'api_model_code' => 'gpt-4o-mini',
            'context_window' => 128000,
            'task_specialty' => AiModel::TASK_QUIZ,
            'is_default' => true,
        ]);
    }

    public function test_it_parses_valid_json_quiz_response(): void
    {
        $model = $this->makeModel();

        $json = json_encode([
            'questions' => [
                [
                    'question' => 'What is the capital of France?',
                    'options' => ['Paris', 'London', 'Berlin', 'Madrid'],
                    'correct_index' => 0,
                    'explanation' => 'Paris is the capital of France.',
                ],
            ],
        ]);

        $factory = Mockery::mock(AiProviderFactory::class);
        $factory->shouldReceive('chat')
            ->once()
            ->with(AiModel::TASK_QUIZ, Mockery::type('string'), Mockery::type('string'), 'Some chapter text about geography.', null, true)
            ->andReturn([
                'model' => $model,
                'response' => new AiChatResponse($json, 100, 50),
            ]);

        $task = new GenerateQuiz($factory);
        $result = $task->handle('Some chapter text about geography.');

        $this->assertSame($model->id, $result['model']->id);
        $this->assertCount(1, $result['content']['questions']);
        $this->assertSame('Paris', $result['content']['questions'][0]['options'][0]);
    }

    public function test_it_strips_markdown_code_fences_before_decoding(): void
    {
        $model = $this->makeModel();

        $fenced = "```json\n".json_encode([
            'questions' => [
                ['question' => 'Q1', 'options' => ['A', 'B'], 'correct_index' => 1, 'explanation' => ''],
            ],
        ])."\n```";

        $factory = Mockery::mock(AiProviderFactory::class);
        $factory->shouldReceive('chat')->once()->andReturn([
            'model' => $model,
            'response' => new AiChatResponse($fenced, 10, 10),
        ]);

        $task = new GenerateQuiz($factory);
        $result = $task->handle('text');

        $this->assertSame(1, $result['content']['questions'][0]['correct_index']);
    }

    public function test_it_throws_on_malformed_json(): void
    {
        $model = $this->makeModel();

        $factory = Mockery::mock(AiProviderFactory::class);
        $factory->shouldReceive('chat')->once()->andReturn([
            'model' => $model,
            'response' => new AiChatResponse('not json at all', 10, 10),
        ]);

        $this->expectException(AiProviderException::class);

        (new GenerateQuiz($factory))->handle('text');
    }
}
