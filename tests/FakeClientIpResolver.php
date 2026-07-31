<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Psr\Http\Message\ServerRequestInterface;
use Rasuvaeff\Yii3MaintenanceMode\ClientIpResolver;

/**
 * @internal
 */
final readonly class FakeClientIpResolver implements ClientIpResolver
{
    public function __construct(
        private ?string $ip,
    ) {}

    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        return $this->ip;
    }
}
