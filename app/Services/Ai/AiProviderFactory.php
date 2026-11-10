<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Ai\Clients\AnthropicAiClient;
use App\Services\Ai\Clients\GoogleAiClient;
use App\Services\Ai\Clients\OpenAiClient;
use App\Services\Ai\Contracts\AiProviderClient;
use App\Services\Ai\DataTransferObjects\AiChatResponse;
use App\Services\Ai\Exceptions\AiProviderException;

class AiProviderFactory
{
    /**
     * Map of provider slug => client class. New providers can be registered
     * here (or bound in a service provider) without touching call sites.
     *
     * @var array<string, class-string<AiProviderClient>>
     */
    private array $clients = [
        'google' => GoogleAiClient::class,
        'openai' => OpenAiClient::class,
        'anthropic' => AnthropicAiClient::class,
    ];

    public function clientFor(AiProvider $provider): AiProviderClient
    {
        $class = $this->clients[$provider->slug] ?? null;

        if (! $class) {
            throw new AiProviderException("No AI client registered for provider slug [{$provider->slug}].");
        }

        return new $class($provider->base_url);
    }

    /**
     * Resolve the default AiModel configured for a given task specialty
     * (e.g. "summary", "translation", "quiz") from an active provider,
     * falling back to an active default model configured for "all" tasks.
     */
    public function resolveModelFor(string $taskSpecialty): AiModel
    {
        $model = AiModel::query()
            ->with('provider')
            ->where('task_specialty', $taskSpecialty)
            ->where('is_default', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $model && $taskSpecialty !== AiModel::TASK_ALL) {
            $model = AiModel::query()
                ->with('provider')
                ->where('task_specialty', AiModel::TASK_ALL)
                ->where('is_default', true)
                ->whereHas('provider', fn ($query) => $query->where('is_active', true))
                ->first();
        }

        if (! $model) {
            throw new AiProviderException("No default AI model is configured for task [{$taskSpecialty}].");
        }

        return $model;
    }

    /**
     * @return array{model: AiModel, response: AiChatResponse}
     */
    public function chat(
        string $taskSpecialty,
        string $systemPrompt,
        string $userPrompt,
        ?string $filePath = null,
        ?string $mimeType = null,
        bool $jsonMode = false
    ): array {
        $model = $this->resolveModelFor($taskSpecialty);
        $provider = $model->provider;
        $apiKey = $provider->api_key;

        if (! $apiKey) {
            throw new AiProviderException("Provider [{$provider->slug}] has no API key configured.");
        }

        $client = $this->clientFor($provider);
        $response = $client->chat(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: $model,
            apiKey: $apiKey,
            filePath: $filePath,
            mimeType: $mimeType,
            jsonMode: $jsonMode
        );

        return [
            'model' => $model,
            'response' => $response,
        ];
    }
}
