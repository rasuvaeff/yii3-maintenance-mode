<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
interface MaintenanceAccessPolicy
{
    public function allows(ServerRequestInterface $request, MaintenanceState $state): bool;
}
