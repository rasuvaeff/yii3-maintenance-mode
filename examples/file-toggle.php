<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\Yii3MaintenanceMode\AtomicMaintenanceFileWriter;
use Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;

$filePath = sys_get_temp_dir() . '/yii3-maintenance-mode-example.json';
$writer = new AtomicMaintenanceFileWriter($filePath);

try {
    $writer->write(new MaintenanceState(enabled: true, retryAfter: 600));
    $enabledState = (new FileMaintenanceProvider($filePath))->getState();
    echo 'Enabled: ' . ($enabledState->enabled ? 'yes' : 'no') . PHP_EOL;

    $writer->write(new MaintenanceState(enabled: false));
    $disabledState = (new FileMaintenanceProvider($filePath))->getState();
    echo 'Enabled after disable: ' . ($disabledState->enabled ? 'yes' : 'no') . PHP_EOL;
} finally {
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}
