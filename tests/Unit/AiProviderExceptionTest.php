<?php

namespace Tests\Unit;

use App\Services\Ai\Exceptions\AiProviderException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AiProviderExceptionTest extends TestCase
{
    public function test_it_parses_the_status_from_the_client_message_format(): void
    {
        $e = new AiProviderException('Google AI request failed (503): {"error":"UNAVAILABLE"}');

        $this->assertSame(503, $e->getStatusCode());
        $this->assertSame(503, $e->getCode());
        $this->assertTrue($e->isRetryable());
    }

    public function test_an_explicit_status_wins_over_the_message(): void
    {
        $e = new AiProviderException('OpenAI request failed (500): x', 401);

        $this->assertSame(401, $e->getStatusCode());
        $this->assertFalse($e->isRetryable());
    }

    public function test_a_message_without_a_status_is_not_retryable(): void
    {
        $e = new AiProviderException('Quiz generation returned invalid JSON: nope');

        $this->assertNull($e->getStatusCode());
        $this->assertFalse($e->isRetryable());
    }

    #[DataProvider('statuses')]
    public function test_retryable_classification(int $status, bool $expected): void
    {
        $this->assertSame($expected, (new AiProviderException('x', $status))->isRetryable());
    }

    /** @return array<string, array{int, bool}> */
    public static function statuses(): array
    {
        return [
            '400' => [400, false], '401' => [401, false], '403' => [403, false], '404' => [404, false], '422' => [422, false],
            '408' => [408, true], '429' => [429, true], '500' => [500, true], '503' => [503, true], '529' => [529, true],
        ];
    }
}
