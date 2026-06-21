<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Benchmarks;

use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use Testo\Bench;

final class MaintenanceStateBench
{
    #[Bench(
        callables: [
            'with-allowed-ips' => [self::class, 'constructWithIps'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function constructSimple(): MaintenanceState
    {
        return new MaintenanceState(enabled: true);
    }

    public static function constructWithIps(): MaintenanceState
    {
        return new MaintenanceState(
            enabled: true,
            retryAfter: 600,
            allowedIps: ['127.0.0.1', '10.0.0.1', '10.0.0.2', '192.168.1.1', '192.168.1.2'],
            bypassTokenHash: hash('sha256', 'secret-token'),
        );
    }
}
