<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class FakeRequest implements ServerRequestInterface
{
    /**
     * @param array<string, mixed> $serverParams
     * @param array<string, string|int|list<string>> $queryParams
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private readonly array $serverParams = [],
        private readonly array $queryParams = [],
        private readonly array $headers = [],
    ) {}

    #[\Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    #[\Override]
    public function getCookieParams(): array
    {
        return [];
    }

    #[\Override]
    public function withCookieParams(array $cookies): self
    {
        return clone $this;
    }

    #[\Override]
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    #[\Override]
    public function withQueryParams(array $query): self
    {
        return clone $this;
    }

    #[\Override]
    public function getUploadedFiles(): array
    {
        return [];
    }

    #[\Override]
    public function withUploadedFiles(array $uploadedFiles): self
    {
        return clone $this;
    }

    #[\Override]
    public function getParsedBody(): null
    {
        return null;
    }

    #[\Override]
    public function withParsedBody($data): self
    {
        return clone $this;
    }

    #[\Override]
    public function getAttributes(): array
    {
        return [];
    }

    #[\Override]
    public function getAttribute(string $name, $default = null): null
    {
        return null;
    }

    #[\Override]
    public function withAttribute(string $name, $value): self
    {
        return clone $this;
    }

    #[\Override]
    public function withoutAttribute(string $name): self
    {
        return clone $this;
    }

    #[\Override]
    public function getRequestTarget(): string
    {
        return '/';
    }

    #[\Override]
    public function withRequestTarget(string $requestTarget): self
    {
        return clone $this;
    }

    #[\Override]
    public function getMethod(): string
    {
        return 'GET';
    }

    #[\Override]
    public function withMethod(string $method): self
    {
        return clone $this;
    }

    #[\Override]
    public function getUri(): UriInterface
    {
        throw new \LogicException('Not implemented');
    }

    #[\Override]
    public function withUri(UriInterface $uri, bool $preserveHost = false): self
    {
        return clone $this;
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
        return clone $this;
    }

    #[\Override]
    public function withAddedHeader(string $name, $value): self
    {
        return clone $this;
    }

    #[\Override]
    public function withoutHeader(string $name): self
    {
        return clone $this;
    }

    #[\Override]
    public function getBody(): StreamInterface
    {
        throw new \LogicException('Not implemented');
    }

    #[\Override]
    public function withBody(StreamInterface $body): self
    {
        return clone $this;
    }
}
