<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests\Integration;

use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceAccessPolicy;
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceResponseFactory;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceAccessPolicy;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceResponseFactory;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

#[Test]
#[CoversNothing]
final class ConfigIntegrationTest
{
    public function packageConfigProvidesCompleteDiGraph(): void
    {
        $params = require dirname(__DIR__, 2) . '/config/params.php';
        $definitions = require dirname(__DIR__, 2) . '/config/di.php';

        Assert::same($definitions[MaintenanceProvider::class]['class'], ConfigMaintenanceProvider::class);
        Assert::same($definitions[MaintenanceAccessPolicy::class]['class'], DefaultMaintenanceAccessPolicy::class);
        Assert::same($definitions[MaintenanceResponseFactory::class], DefaultMaintenanceResponseFactory::class);
        Assert::same($definitions[MaintenanceMiddleware::class], MaintenanceMiddleware::class);
        Assert::false($params['rasuvaeff/yii3-maintenance-mode']['enabled']);
    }
}
