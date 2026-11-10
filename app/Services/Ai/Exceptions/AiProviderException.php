<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;
use Throwable;

class AiProviderException extends RuntimeException
{
    /** Statuses worth retrying: timeout, rate limit, and any server-side error (incl. 503, 529 "overloaded"). */
    private const RETRYABLE_CLIENT_STATUSES = [408, 429];

    private ?int $statusCode;

    /**
     * When no status is passed, it is parsed from the "… request failed (503): …"
     * message format the provider clients use, so existing throw sites keep working.
     */
    public function __construct(string $message = '', ?int $statusCode = null, ?Throwable $previous = null)
    {
        $this->statusCode = $statusCode ?? self::parseStatusFromMessage($message);

        parent::__construct($message, $this->statusCode ?? 0, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function isRetryable(): bool
    {
        if ($this->statusCode === null) {
            return false;
        }

        return $this->statusCode >= 500 || in_array($this->statusCode, self::RETRYABLE_CLIENT_STATUSES, true);
    }

    private static function parseStatusFromMessage(string $message): ?int
    {
        return preg_match('/request failed \((\d{3})\)/i', $message, $matches) === 1
            ? (int) $matches[1]
            : null;
    }
}
