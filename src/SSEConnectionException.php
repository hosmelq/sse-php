<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use GuzzleHttp\Psr7\Uri;
use Throwable;

class SSEConnectionException extends SSEException
{
    public static function connectionFailed(string $url, Throwable $previous): self
    {
        $endpoint = new Uri($url)->withUserInfo('')->withQuery('')->withFragment('');

        return new self('Failed to connect to SSE endpoint: '.$endpoint, 0, $previous);
    }

    public static function readFailed(Throwable $previous): self
    {
        return new self('Failed to read from SSE stream.', 0, $previous);
    }
}
