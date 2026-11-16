<?php

declare(strict_types=1);

namespace HosmelQ\SSE;

use function Safe\preg_replace;

use Generator;

/**
 * @internal
 */
final class LineSplitter
{
    private bool $atStart = true;

    private string $buffer = '';

    private bool $skipLf = false;

    /**
     * @return Generator<int, string>
     */
    public function split(string $chunk): Generator
    {
        if ($this->atStart) {
            $this->buffer .= $chunk;

            if (
                $this->buffer !== "\xEF\xBB\xBF"
                && str_starts_with("\xEF\xBB\xBF", $this->buffer)
            ) {
                return;
            }

            $chunk = preg_replace('/\A\xEF\xBB\xBF/', '', $this->buffer);
            $this->buffer = '';
            $this->atStart = false;
        }

        if ($this->skipLf) {
            $chunk = preg_replace('/\A\n/', '', $chunk);
        }

        $this->skipLf = str_ends_with($chunk, "\r");

        if (strpbrk($chunk, "\r\n") === false) {
            $this->buffer .= $chunk;

            return;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $chunk));
        $lines[0] = $this->buffer.$lines[0];
        $this->buffer = array_pop($lines);

        yield from $lines;
    }
}
