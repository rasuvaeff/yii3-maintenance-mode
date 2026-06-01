<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class FakeResponseFactory implements ResponseFactoryInterface
{
    #[\Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new FakeResponse($code);
    }
}
