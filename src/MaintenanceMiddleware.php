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
final class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly MaintenanceProvider $provider,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $state = $this->provider->getState();

        if (!$state->enabled) {
            return $handler->handle($request);
        }

        if ($this->isAllowedIp($request, $state)) {
            return $handler->handle($request);
        }

        if ($this->hasValidBypassToken($request, $state)) {
            return $handler->handle($request);
        }

        return $this->createResponse($request, $state);
    }

    private function isAllowedIp(ServerRequestInterface $request, MaintenanceState $state): bool
    {
        if ($state->allowedIps === []) {
            return false;
        }

        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? '';

        if (!is_string($ip) || $ip === '') {
            return false;
        }

        return in_array($ip, $state->allowedIps, true);
    }

    private function hasValidBypassToken(ServerRequestInterface $request, MaintenanceState $state): bool
    {
        if ($state->bypassTokenHash === '') {
            return false;
        }

        $params = $request->getQueryParams();
        $token = $params['bypass'] ?? '';

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($state->bypassTokenHash, hash('sha256', $token));
    }

    private function createResponse(ServerRequestInterface $request, MaintenanceState $state): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(503);

        $response = $response->withHeader(name: 'Retry-After', value: (string) $state->retryAfter);

        $accept = $request->getHeaderLine('Accept');

        if (str_contains($accept, 'application/json') || $accept === '') {
            $response = $response->withHeader(name: 'Content-Type', value: 'application/json');
            $response->getBody()->write(json_encode([
                'error' => 'Service Unavailable',
                'message' => 'The server is currently undergoing maintenance.',
                'retryAfter' => $state->retryAfter,
            ], JSON_THROW_ON_ERROR));
        } else {
            $response = $response->withHeader(name: 'Content-Type', value: 'text/html; charset=utf-8');
            $response->getBody()->write($this->htmlBody());
        }

        return $response;
    }

    private function htmlBody(): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head><title>Maintenance</title></head>
            <body>
            <h1>Service Unavailable</h1>
            <p>The server is currently undergoing maintenance.</p>
            <p>Please try again later.</p>
            </body>
            </html>
            HTML;
    }
}
