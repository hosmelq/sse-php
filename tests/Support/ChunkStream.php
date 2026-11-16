<?php

declare(strict_types=1);

namespace HosmelQ\SSE\Tests\Support;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

final class ChunkStream implements StreamInterface
{
    use StreamDecoratorTrait;

    public bool $closed = false;

    /**
     * @var list<int>
     */
    public array $reads = [];

    private StreamInterface $stream;

    /**
     * @param list<string|Throwable> $chunks
     */
    public function __construct(private array $chunks)
    {
        $this->stream = Utils::streamFor('');
    }

    public function close(): void
    {
        $this->closed = true;

        $this->stream->close();
    }

    public function eof(): bool
    {
        return $this->closed || $this->chunks === [];
    }

    public function read($length): string
    {
        if ($this->closed) {
            throw new RuntimeException('The stream is closed.');
        }

        $this->reads[] = $length;
        $chunk = array_shift($this->chunks);

        if ($chunk instanceof Throwable) {
            throw $chunk;
        }

        return $chunk ?? '';
    }
}
