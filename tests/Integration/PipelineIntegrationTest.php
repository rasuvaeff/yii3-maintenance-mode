<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Renderer\PlainTextRenderer;
use Yiisoft\ErrorHandler\RendererProvider\ClosureRendererProvider;
use Yiisoft\ErrorHandler\ThrowableResponseFactory;

/**
 * Builds a real Yiisoft DI container from the package's own `config/di.php` +
 * `config/params.php` and runs an actual PSR-15 pipeline through it, matching
 * the middleware order documented in README.md: `ErrorCatcher` wraps
 * `MaintenanceMiddleware`, which wraps routing/the application handler.
 *
 * Every pipeline in this file uses an inner handler that throws. A `503` proves
 * `MaintenanceMiddleware` short-circuited before that handler ran; a `500`
 * (`ErrorCatcher`'s rendered failure) proves the request reached it and the
 * throw was caught by the middleware wrapping it from the outside.
 */
#[Test]
#[CoversNothing]
final class PipelineIntegrationTest
{
    public function maintenanceDisabledPassesThroughToHandler(): void
    {
        $middleware = $this->resolveMiddleware(['enabled' => false]);
        $response = $this->runPipeline($middleware, $this->request('GET', '/'));

        Assert::same($response->getStatusCode(), 500);
    }

    public function maintenanceEnabledReturns503WithRetryAfter(): void
    {
        $middleware = $this->resolveMiddleware(['enabled' => true, 'retryAfter' => 120]);
        $response = $this->runPipeline($middleware, $this->request('GET', '/'));

        Assert::same($response->getStatusCode(), 503);
        Assert::same($response->getHeaderLine('Retry-After'), '120');
    }

    public function headerBypassAllowsThroughDespiteMaintenance(): void
    {
        $middleware = $this->resolveMiddleware([
            'enabled' => true,
            'bypassTokenHash' => hash('sha256', 'a-very-long-random-token'),
        ]);

        $request = $this->request('GET', '/')->withHeader('X-Maintenance-Bypass', 'a-very-long-random-token');
        $response = $this->runPipeline($middleware, $request);

        Assert::same($response->getStatusCode(), 500);
    }

    public function healthPathExclusionAllowsThroughDespiteMaintenance(): void
    {
        $middleware = $this->resolveMiddleware([
            'enabled' => true,
            'access' => ['excludedPathPrefixes' => ['/health']],
        ]);

        $response = $this->runPipeline($middleware, $this->request('GET', '/health/live'));

        Assert::same($response->getStatusCode(), 500);
    }

    /**
     * @param array<string, mixed> $paramOverrides
     */
    private function resolveMiddleware(array $paramOverrides): MaintenanceMiddleware
    {
        $container = $this->buildContainer($paramOverrides);
        $middleware = $container->get(MaintenanceMiddleware::class);

        Assert::instanceOf($middleware, MaintenanceMiddleware::class);

        return $middleware;
    }

    /**
     * @param array<string, mixed> $paramOverrides
     */
    private function buildContainer(array $paramOverrides): Container
    {
        $configDir = dirname(__DIR__, 2) . '/config';

        Assert::true(is_file($configDir . '/di.php'));
        Assert::true(is_file($configDir . '/params.php'));

        /** @var array<string, array<string, mixed>> $baseParams */
        $baseParams = require $configDir . '/params.php';
        $params = [
            'rasuvaeff/yii3-maintenance-mode' => array_replace_recursive(
                $baseParams['rasuvaeff/yii3-maintenance-mode'],
                $paramOverrides,
            ),
        ];

        /** @var array<string, mixed> $di */
        $di = require $configDir . '/di.php';

        $psr17 = new Psr17Factory();

        return new Container(ContainerConfig::create()->withDefinitions(array_merge($di, [
            ResponseFactoryInterface::class => $psr17,
        ])));
    }

    private function runPipeline(MaintenanceMiddleware $middleware, ServerRequestInterface $request): ResponseInterface
    {
        $throwingInner = new CallableRequestHandler(static function (): never {
            throw new RuntimeException('Inner handler reached past maintenance check');
        });

        $maintenanceStage = new CallableRequestHandler(
            static fn(ServerRequestInterface $req): ResponseInterface => $middleware->process($req, $throwingInner),
        );

        return $this->buildErrorCatcher()->process($request, $maintenanceStage);
    }

    private function buildErrorCatcher(): ErrorCatcher
    {
        $psr17 = new Psr17Factory();
        $renderer = new PlainTextRenderer();
        $errorHandler = new ErrorHandler(new NullLogger(), $renderer);

        $unusedContainer = new class implements ContainerInterface {
            #[\Override]
            public function get(string $id): never
            {
                throw new RuntimeException('Unused container path: ' . $id);
            }

            #[\Override]
            public function has(string $id): bool
            {
                return false;
            }
        };

        $rendererProvider = new ClosureRendererProvider(
            static fn(): PlainTextRenderer => $renderer,
            $unusedContainer,
        );

        return new ErrorCatcher(new ThrowableResponseFactory($psr17, $errorHandler, $rendererProvider));
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest($method, $path, ['REMOTE_ADDR' => '198.51.100.7']);
    }
}
