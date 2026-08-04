<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Resolves `MaintenanceMiddleware` from a real Yiisoft DI container built from
 * this package's own `config/di.php` and `config/params.php` — the same wiring
 * a Yii3 application gets automatically through the config-plugin. Requires
 * `yiisoft/di` and `nyholm/psr7`, which ship as dev dependencies of this
 * package for exactly this example (a consumer application already depends on
 * a DI container and a PSR-17 factory of its own).
 */
function buildMiddleware(bool $enabled): MaintenanceMiddleware
{
    $configDir = __DIR__ . '/../config';

    /** @var array<string, array<string, mixed>> $params */
    $params = require $configDir . '/params.php';
    $params['rasuvaeff/yii3-maintenance-mode']['enabled'] = $enabled;
    $params['rasuvaeff/yii3-maintenance-mode']['retryAfter'] = 60;

    $di = (static function () use ($params, $configDir): array {
        return require $configDir . '/di.php';
    })();

    $psr17 = new Psr17Factory();
    $container = new Container(ContainerConfig::create()->withDefinitions(array_merge($di, [
        ResponseFactoryInterface::class => $psr17,
    ])));

    /** @var MaintenanceMiddleware $middleware */
    $middleware = $container->get(MaintenanceMiddleware::class);

    return $middleware;
}

$psr17 = new Psr17Factory();
$request = $psr17->createServerRequest('GET', '/', ['REMOTE_ADDR' => '203.0.113.10']);

$terminalHandler = new class($psr17) implements Psr\Http\Server\RequestHandlerInterface {
    public function __construct(
        private Psr17Factory $psr17,
    ) {}

    #[\Override]
    public function handle(Psr\Http\Message\ServerRequestInterface $request): Psr\Http\Message\ResponseInterface
    {
        unset($request);

        return $this->psr17->createResponse(200);
    }
};

$normal = buildMiddleware(enabled: false)->process($request, $terminalHandler);
echo "Maintenance disabled: HTTP {$normal->getStatusCode()}\n";

$maintenance = buildMiddleware(enabled: true)->process($request, $terminalHandler);
echo "Maintenance enabled:  HTTP {$maintenance->getStatusCode()}, Retry-After: {$maintenance->getHeaderLine('Retry-After')}s\n";
