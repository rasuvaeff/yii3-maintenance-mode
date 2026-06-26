<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Psr\Http\Server\MiddlewareInterface;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(MaintenanceMiddleware::class)]
final class MaintenanceMiddlewareTest
{
    public function implementsMiddlewareInterface(): void
    {
        $middleware = $this->createMiddleware(['enabled' => false]);

        Assert::instanceOf($middleware, MiddlewareInterface::class);
    }

    public function passesThroughWhenDisabled(): void
    {
        $middleware = $this->createMiddleware(['enabled' => false]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 200);
    }

    public function returns503WhenEnabled(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 503);
    }

    public function setsRetryAfterHeader(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true, 'retryAfter' => 600]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getHeaderLine('Retry-After'), '600');
    }

    public function returnsJsonByDefault(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $body = $this->getResponseBody($response);

        Assert::same($response->getHeaderLine('Content-Type'), 'application/json');
        Assert::string($body)->contains('Service Unavailable');
        Assert::string($body)->contains('retryAfter');
    }

    public function returnsJsonWhenAcceptHeaderIsJson(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest(headers: ['accept' => ['application/json']]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getHeaderLine('Content-Type'), 'application/json');
    }

    public function returnsHtmlWhenAcceptHeaderIsHtml(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest(headers: ['accept' => ['text/html']]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $body = $this->getResponseBody($response);

        Assert::same($response->getHeaderLine('Content-Type'), 'text/html; charset=utf-8');
        Assert::string($body)->contains('<html');
        Assert::string($body)->contains('Maintenance');
    }

    public function allowsIpInAllowList(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => '10.0.0.1']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 200);
    }

    public function blocksIpNotInAllowList(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => '10.0.0.2']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 503);
    }

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

        Assert::same($response->getStatusCode(), 200);
    }

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

        Assert::same($response->getStatusCode(), 503);
    }

    public function blocksWhenBypassTokenHashIsEmpty(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'bypassTokenHash' => '',
        ]);
        $request = new FakeRequest(queryParams: ['bypass' => 'any-token']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 503);
    }

    public function blocksWhenNoRemoteAddr(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1'],
        ]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 503);
    }

    public function blocksWhenRemoteAddrIsNonString(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => 12345]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 503);
    }

    public function blocksWhenRemoteAddrIsEmptyString(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1', ''],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => '']);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 503);
    }

    public function blocksWhenRemoteAddrIsArray(): void
    {
        $middleware = $this->createMiddleware([
            'enabled' => true,
            'allowedIps' => ['127.0.0.1'],
        ]);
        $request = new FakeRequest(serverParams: ['REMOTE_ADDR' => ['127.0.0.1', '10.0.0.1']]);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getStatusCode(), 503);
    }

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

        Assert::same($response->getStatusCode(), 503);
    }

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

        Assert::same($response->getStatusCode(), 503);
    }

    public function jsonResponseBodyContainsAllFields(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true, 'retryAfter' => 600]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $body = $this->getResponseBody($response);
        /** @var array<string, mixed> $data */
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        Assert::same($data['error'], 'Service Unavailable');
        Assert::same($data['retryAfter'], 600);
    }

    public function retryAfterHeaderIsString(): void
    {
        $middleware = $this->createMiddleware(['enabled' => true, 'retryAfter' => 300]);
        $request = new FakeRequest();
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);
        $retryAfterHeaders = $response->getHeader('Retry-After');

        Assert::same($retryAfterHeaders, ['300']);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    #[DataProvider('acceptHeaderProvider')]
    public function contentNegotiation(array $headers, string $expectedContentType): void
    {
        $middleware = $this->createMiddleware(['enabled' => true]);
        $request = new FakeRequest(headers: $headers);
        $handler = new FakeHandler();

        $response = $middleware->process($request, $handler);

        Assert::same($response->getHeaderLine('Content-Type'), $expectedContentType);
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
