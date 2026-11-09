<?php declare(strict_types=1);

namespace Bref\SymfonyBridge\Http;

use Bref\Event\Http\PushStream;
use Closure;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The body of a StreamedResponse: Bref runs its callback and sends each output as it is written.
 *
 * @internal
 */
final class StreamedBody implements StreamInterface, PushStream
{
    private bool $consumed = false;

    /**
     * @param Closure(): void $afterContent Runs once the content has been produced (e.g. kernel.terminate).
     */
    public function __construct(private StreamedResponse $response, private Closure $afterContent)
    {
    }

    public function writeTo(callable $write): void
    {
        $this->consumed = true;
        ob_start(function (string $buffer) use ($write): string {
            if ($buffer !== '') {
                $write($buffer);
            }

            return '';
        }, 1);
        try {
            $this->response->sendContent();
        } finally {
            ob_end_clean();
        }
        ($this->afterContent)();
    }

    /**
     * For readers other than Bref: the whole content at once.
     */
    public function getContents(): string
    {
        $contents = '';
        if (! $this->consumed) {
            $this->writeTo(function (string $chunk) use (&$contents): void {
                $contents .= $chunk;
            });
        }

        return $contents;
    }

    public function read(int $length): string
    {
        return $this->getContents();
    }

    public function eof(): bool
    {
        return $this->consumed;
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
