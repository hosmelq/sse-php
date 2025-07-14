<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

class SSEProtocolException extends SSEException
{
    /**
     * Factory for invalid content-type violations.
     */
    public static function invalidContentType(string $received): self
    {
        return new self(sprintf("Expected 'text/event-stream', got '%s'", $received));
    }
}
