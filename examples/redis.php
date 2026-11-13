<?php

declare(strict_types=1);

use Predis\Client;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use Rasuvaeff\Yii3MaintenanceMode\Redis\PredisMaintenanceClient;
use Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceWriter;

require dirname(__DIR__) . '/vendor/autoload.php';

$host = getenv('REDIS_HOST');

if ($host === false) {
    fwrite(STDOUT, "REDIS_HOST is not set — skipping the Redis example\n");
    return;
}

$client = new PredisMaintenanceClient(new Client(['host' => (string) $host]));

$provider = new RedisMaintenanceProvider($client);
$writer = new RedisMaintenanceWriter($client);

fwrite(STDOUT, 'Current state: ' . ($provider->getState()->enabled ? 'ENABLED' : 'disabled') . "\n");

$writer->write(new MaintenanceState(enabled: true, retryAfter: 600));
fwrite(STDOUT, 'After write: ' . ($provider->getState()->enabled ? 'ENABLED' : 'disabled') . "\n");

$writer->clear();
fwrite(STDOUT, 'After clear: ' . ($provider->getState()->enabled ? 'ENABLED' : 'disabled') . "\n");
