<?php

declare(strict_types=1);

use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceAccessPolicy;
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceResponseFactory;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceAccessPolicy;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceResponseFactory;

/** @var array $params */

return [
    MaintenanceProvider::class => [
        'class' => ConfigMaintenanceProvider::class,
        '__construct()' => [
            'config' => $params['rasuvaeff/yii3-maintenance-mode'],
        ],
    ],
    MaintenanceAccessPolicy::class => [
        'class' => DefaultMaintenanceAccessPolicy::class,
        '__construct()' => $params['rasuvaeff/yii3-maintenance-mode']['access'],
    ],
    MaintenanceResponseFactory::class => DefaultMaintenanceResponseFactory::class,
    MaintenanceMiddleware::class => MaintenanceMiddleware::class,
];
