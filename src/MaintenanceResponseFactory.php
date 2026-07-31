<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
interface MaintenanceResponseFactory
{
    public function createResponse(ServerRequestInterface $request, MaintenanceState $state): ResponseInterface;
}
