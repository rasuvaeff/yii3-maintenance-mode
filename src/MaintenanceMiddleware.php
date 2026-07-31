<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @api
 */
final readonly class MaintenanceMiddleware implements MiddlewareInterface
{
    private MaintenanceAccessPolicy $accessPolicy;

    private MaintenanceResponseFactory $maintenanceResponseFactory;

    public function __construct(
        private MaintenanceProvider $provider,
        ResponseFactoryInterface $responseFactory,
        ?MaintenanceAccessPolicy $accessPolicy = null,
        ?MaintenanceResponseFactory $maintenanceResponseFactory = null,
    ) {
        $this->accessPolicy = $accessPolicy ?? new DefaultMaintenanceAccessPolicy();
        $this->maintenanceResponseFactory = $maintenanceResponseFactory ?? new DefaultMaintenanceResponseFactory($responseFactory);
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $state = $this->provider->getState();

        if (!$state->enabled || $this->accessPolicy->allows($request, $state)) {
            return $handler->handle($request);
        }

        return $this->maintenanceResponseFactory->createResponse($request, $state);
    }
}
