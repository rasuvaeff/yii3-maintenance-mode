<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class FakeStream implements StreamInterface
{
    private string $contents = '';

    public function __toString(): string
    {
        return $this->contents;
    }

    #[\Override]
    public function close(): void {}

    #[\Override]
    public function detach(): null
    {
        return null;
    }

    #[\Override]
    public function getSize(): int
    {
        return strlen($this->contents);
    }

    #[\Override]
    public function tell(): int
    {
        return strlen($this->contents);
    }

    #[\Override]
    public function eof(): bool
    {
        return true;
    }

    #[\Override]
    public function isSeekable(): bool
    {
        return false;
    }

    #[\Override]
    public function seek(int $offset, int $whence = SEEK_SET): void {}

    #[\Override]
    public function rewind(): void {}

    #[\Override]
    public function isWritable(): bool
    {
        return true;
    }

    #[\Override]
    public function write(string $string): int
    {
        $this->contents .= $string;

        return strlen($string);
    }

    #[\Override]
    public function isReadable(): bool
    {
        return true;
    }

    #[\Override]
    public function read(int $length): string
    {
        return '';
    }

    #[\Override]
    public function getContents(): string
    {
        return $this->contents;
    }

    #[\Override]
    public function getMetadata(?string $key = null): ?array
    {
        return null;
    }
}
