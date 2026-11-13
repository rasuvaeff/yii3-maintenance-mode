<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Rasuvaeff\Yii3MaintenanceMode\Internal\StateJson;
use RuntimeException;

/**
 * @api
 */
final readonly class AtomicMaintenanceFileWriter
{
    public function __construct(
        private string $filePath,
    ) {}

    public function write(MaintenanceState $state): void
    {
        $directory = dirname($this->filePath);

        if (!is_dir($directory)) {
            throw new RuntimeException('Maintenance file directory does not exist');
        }

        $temporaryPath = tempnam($directory, basename($this->filePath) . '.tmp-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create temporary maintenance file');
        }

        try {
            $content = StateJson::encode($state, pretty: true) . PHP_EOL;

            if (file_put_contents($temporaryPath, $content, LOCK_EX) !== strlen($content)) {
                throw new RuntimeException('Unable to write temporary maintenance file');
            }

            if (!chmod($temporaryPath, 0o600) || !rename($temporaryPath, $this->filePath)) {
                throw new RuntimeException('Unable to replace maintenance file');
            }
        } finally {
            if (file_exists($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
