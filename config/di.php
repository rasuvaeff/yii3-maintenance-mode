<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceMiddleware;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;

/** @var array $params */

return [
    MaintenanceProvider::class => [
        '__construct()' => [
            'config' => $params['rasuvaeff/yii3-maintenance-mode'],
        ],
    ],
    MaintenanceMiddleware::class => MaintenanceMiddleware::class,
];
