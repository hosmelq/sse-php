<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use Psr\Http\Message\ResponseInterface;

class SSEProtocolException extends SSEException
{
    public function __construct(string $message, private readonly ResponseInterface $response)
    {
        parent::__construct($message);
    }

    public static function invalidContentType(ResponseInterface $response): self
    {
        return new self(
            sprintf(
                "Expected Content-Type 'text/event-stream', got '%s'.",
                $response->getHeaderLine('Content-Type'),
            ),
            $response,
        );
    }

    public static function invalidStatus(ResponseInterface $response): self
    {
        return new self(
            sprintf('Expected status code 200, got %d.', $response->getStatusCode()),
            $response,
        );
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
