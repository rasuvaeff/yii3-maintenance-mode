<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class FakeResponse implements ResponseInterface
{
    private FakeStream $stream;

    private int $statusCode;

    /** @var array<string, list<string>> */
    private array $headers = [];

    public function __construct(int $statusCode = 200)
    {
        $this->statusCode = $statusCode;
        $this->stream = new FakeStream();
    }

    #[\Override]
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    #[\Override]
    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;

        return $clone;
    }

    #[\Override]
    public function getReasonPhrase(): string
    {
        return '';
    }

    #[\Override]
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    #[\Override]
    public function withProtocolVersion(string $version): self
    {
        return clone $this;
    }

    #[\Override]
    public function getHeaders(): array
    {
        return $this->headers;
    }

    #[\Override]
    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    #[\Override]
    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    #[\Override]
    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    #[\Override]
    public function withHeader(string $name, $value): self
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = is_array($value) ? array_values($value) : [$value];

        return $clone;
    }

    #[\Override]
    public function withAddedHeader(string $name, $value): self
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)][] = is_array($value) ? implode(', ', $value) : $value;

        return $clone;
    }

    #[\Override]
    public function withoutHeader(string $name): self
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);

        return $clone;
    }

    #[\Override]
    public function getBody(): StreamInterface
    {
        return $this->stream;
    }

    #[\Override]
    public function withBody(StreamInterface $body): self
    {
        return clone $this;
    }
}
