<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

/**
 * @api
 */
interface MaintenanceProvider
{
    public function getState(): MaintenanceState;
}
