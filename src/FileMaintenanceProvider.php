<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Rasuvaeff\Yii3MaintenanceMode\Internal\StateJson;

/**
 * @api
 */
final readonly class FileMaintenanceProvider implements MaintenanceProvider
{
    public function __construct(
        private string $filePath,
    ) {}

    #[\Override]
    public function getState(): MaintenanceState
    {
        if (!is_file($this->filePath) || !is_readable($this->filePath)) {
            return new MaintenanceState();
        }

        /** @var mixed $content */
        $content = file_get_contents($this->filePath);

        if (!is_string($content)) {
            return new MaintenanceState();
        }

        return StateJson::decode($content);
    }
}
