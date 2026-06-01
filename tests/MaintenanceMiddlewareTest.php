<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;

#[CoversClass(MaintenanceMiddleware::class)]
final class MaintenanceMiddlewareTest extends TestCase
{
    #[Test]
    public function implementsMiddlewareInterface(): void
    {
        $middleware = $this->createMiddleware(['enabled' => false]);

        $this->assertInstanceOf(MiddlewareInterface::class, $middleware);
    }

    #[Test]
    public function passesThroughWhenDisabled(): void
    {
        $middleware = $this->createMiddleware(['enabled' => false]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function returns503WhenEnabled(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function setsRetryAfterHeader(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true, 'retryAfter' => 600]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame('600', $response->getHeaderLine('Retry-After'));
    }

    #[Test]
    public function returnsJsonByDefault(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $body = $this->getResponseBody($response);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('Service Unavailable', $body);
        $this->assertStringContainsString('retryAfter', $body);
    }

    #[Test]
    public function returnsJsonWhenAcceptHeaderIsJson(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest(headers: ['accept' => ['application/json']]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function returnsHtmlWhenAcceptHeaderIsHtml(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest(headers: ['accept' => ['text/html']]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $body = $this->getResponseBody($response);

        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<html', $body);
        $this->assertStringContainsString('Maintenance', $body);
    }

    #[Test]
    public function allowsIpInAllowList(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => '10.0.0.1']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function blocksIpNotInAllowList(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => '10.0.0.2']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function allowsBypassToken(): void
    {
        $token = 'my-secret-token';
        $hash = hash('sha256', $token);

        $middleware = $this->createMiddleware([
            'enabled' => true,
            'bypassTokenHash' => $hash,
        ]);
        $request = new FakeRequest(queryParams: ['bypass' => $token]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function blocksInvalidBypassToken(): void
    {
        $hash = hash('sha256', 'correct-token');

        $middleware = $this->createMiddleware([
            'enabled' => true,
            'bypassTokenHash' => $hash,
        ]);
        $request = new FakeRequest(queryParams: ['bypass' => 'wrong-token']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function blocksWhenBypassTokenHashIsEmpty(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'bypassTokenHash' => '',
        ]);
        $request = new FakeRequest(queryParams: ['bypass' => 'any-token']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function blocksWhenNoRemoteAddr(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1'],
        ]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function blocksWhenRemoteAddrIsNonString(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => 12345]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function blocksWhenRemoteAddrIsEmptyString(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1', ''],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => '']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function blocksWhenRemoteAddrIsArray(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => ['127.0.0.1', '10.0.0.1']]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function blocksWhenBypassParamIsNonString(): void
    {
        $hash = hash('sha256', 'correct-token');
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'bypassTokenHash' => $hash,
        ]);
        $request = new FakeRequest(queryParams: ['bypass' => 12345]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function blocksWhenBypassParamIsEmpty(): void
    {
        $hash = hash('sha256', 'correct-token');
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'bypassTokenHash' => $hash,
        ]);
        $request = new FakeRequest(queryParams: ['bypass' => '']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(503, $response->getStatusCode());
    }

    #[Test]
    public function jsonResponseBodyContainsAllFields(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true, 'retryAfter' => 600]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $body = $this->getResponseBody($response);
        /** @var array<string, mixed> $data */
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Service Unavailable', $data['error']);
        $this->assertSame(600, $data['retryAfter']);
    }

    #[Test]
    public function retryAfterHeaderIsString(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true, 'retryAfter' => 300]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $retryAfterHeaders = $response->getHeader('Retry-After');

        $this->assertSame(['300'], $retryAfterHeaders);
    }

    /**
     * @return array<string, array{headers: array<string, list<string>>, expectedContentType: string}>
     */
    public static function acceptHeaderProvider(): array
    {
        return [
            'json accept' => [
                'headers' => ['accept' => ['application/json']],
                'expectedContentType' => 'application/json',
            ],
            'html accept' => [
                'headers' => ['accept' => ['text/html']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
            'empty accept fallback to json' => [
                'headers' => [],
                'expectedContentType' => 'application/json',
            ],
        ];
    }

    /**
     * @param array<string, list<string>> $headers
     */
    #[Test]
    #[DataProvider('acceptHeaderProvider')]
    public function contentNegotiation(array $headers, string $expectedContentType): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest(headers: $headers);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame($expectedContentType, $response->getHeaderLine('Content-Type'));
    }

    /**
     * @param array{enabled?: bool, retryAfter?: int, allowedIps?: list<string>, bypassTokenHash?: string} $config
     */
    private function createMiddleware(array $config): MaintenanceMiddleware
    {
        return new MaintenanceMiddleware(
            provider: new ConfigMaintenanceProvider($config),
            responseFactory: new FakeResponseFactory(),
        );
    }

    private function getResponseBody(\Psr\Http\Message\ResponseInterface $response): string
    {
        $body = $response->getBody();
        $body->rewind();

        return $body->getContents();
    }
}
