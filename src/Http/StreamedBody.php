<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Http;

use Fiber;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The body of a StreamedResponse, produced while it is read: each read() runs the
 * response's callback until its next output, so that Bref sends it right away.
 *
 * @internal
 */
final class StreamedBody implements StreamInterface
{
    private Fiber $fiber;

    /**
     * @param callable(): void $afterContent Runs once the content has been produced (e.g. kernel.terminate).
     */
    public function __construct(StreamedResponse $response, callable $afterContent)
    {
        $this->fiber = new Fiber(function () use ($response, $afterContent): void {
            ob_start(function (string $buffer): string {
                if ($buffer === '' || Fiber::getCurrent() === null) {
                    return $buffer;
                }
                Fiber::suspend($buffer);

                return '';
            }, 1);
            try {
                $response->sendContent();
            } finally {
                ob_end_clean();
            }
            $afterContent();
        });
    }

    public function read(int $length): string
    {
        $chunk = $this->fiber->isStarted() ? $this->fiber->resume() : $this->fiber->start();

        return is_string($chunk) ? $chunk : '';
    }

    public function eof(): bool
    {
        return $this->fiber->isTerminated();
    }

    public function getContents(): string
    {
        $contents = '';
        while (! $this->eof()) {
            $contents .= $this->read(8192);
        }

        return $contents;
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        throw new RuntimeException('Not supported');
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new RuntimeException('Not seekable');
    }

    public function rewind(): void
    {
        throw new RuntimeException('Not seekable');
    }

    public function write(string $string): int
    {
        throw new RuntimeException('Not writable');
    }

    public function close(): void
    {
    }

    public function detach()
    {
        return null;
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
