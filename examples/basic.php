<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;

$provider = new ConfigMaintenanceProvider([
    'enabled' => true,
    'retryAfter' => 600,
    'allowedIps' => ['127.0.0.1'],
    'bypassTokenHash' => hash('sha256', 'secret-token'),
]);

$state = $provider->getState();

echo "Enabled: " . ($state->enabled ? 'yes' : 'no') . "\n";
echo "Retry-After: {$state->retryAfter}s\n";
echo "Allowed IPs: " . implode(', ', $state->allowedIps) . "\n";
echo "Has bypass token: " . ($state->bypassTokenHash !== '' ? 'yes' : 'no') . "\n";
