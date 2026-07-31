<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
final readonly class RemoteAddrClientIpResolver implements ClientIpResolver
{
    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
    }
}
