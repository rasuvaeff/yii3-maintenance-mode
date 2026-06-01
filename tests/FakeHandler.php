<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 */
final class FakeHandler implements RequestHandlerInterface
{
    #[\Override]
    public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
    {
        return new FakeResponse(200);
    }
}
