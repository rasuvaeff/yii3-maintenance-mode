<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
interface ClientIpResolver
{
    public function resolve(ServerRequestInterface $request): ?string;
}
