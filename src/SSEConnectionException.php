<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use Throwable;

class SSEConnectionException extends SSEException
{
    /**
     * Factory for connection failures.
     */
    public static function connectionFailed(string $url, Throwable $previous): self
    {
        return new self('Failed to connect to SSE endpoint: '.$url, 0, $previous);
    }
}
