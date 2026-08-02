<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
final readonly class DefaultMaintenanceResponseFactory implements MaintenanceResponseFactory
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
    ) {}

    #[\Override]
    public function createResponse(ServerRequestInterface $request, MaintenanceState $state): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(503);
        $response = $response->withHeader(name: 'Retry-After', value: (string) $state->retryAfter);
        $response = $response->withHeader(name: 'Cache-Control', value: 'no-store');

        if ($this->prefersJson($request->getHeaderLine('Accept'))) {
            $response = $response->withHeader(name: 'Content-Type', value: 'application/json');
            $response->getBody()->write(json_encode([
                'error' => 'Service Unavailable',
                'message' => 'The server is currently undergoing maintenance.',
                'retryAfter' => $state->retryAfter,
            ], JSON_THROW_ON_ERROR));

            return $response;
        }

        $response = $response->withHeader(name: 'Content-Type', value: 'text/html; charset=utf-8');
        $response->getBody()->write($this->htmlBody());

        return $response;
    }

    private function prefersJson(string $accept): bool
    {
        if (trim($accept) === '') {
            return true;
        }

        $jsonQuality = 0.0;
        $htmlQuality = 0.0;
        $wildcardQuality = 0.0;
        $jsonMentioned = false;

        foreach (explode(',', $accept) as $item) {
            $parts = explode(';', trim($item));
            $mediaType = strtolower(trim(array_shift($parts)));
            $quality = 1.0;

            foreach ($parts as $parameter) {
                $parameterParts = explode('=', trim($parameter), 2);
                $name = $parameterParts[0];
                $value = $parameterParts[1] ?? null;

                if (strtolower($name) === 'q' && $value !== null && is_numeric($value)) {
                    $quality = max(0.0, min(1.0, (float) $value));
                }
            }

            if ($mediaType === 'application/json' || (str_starts_with($mediaType, 'application/') && str_ends_with($mediaType, '+json'))) {
                $jsonMentioned = true;
                $jsonQuality = max($jsonQuality, $quality);
            } elseif ($mediaType === 'text/html') {
                $htmlQuality = max($htmlQuality, $quality);
            } elseif ($mediaType === '*/*') {
                $wildcardQuality = max($wildcardQuality, $quality);
            }
        }

        if ($jsonQuality > $htmlQuality) {
            return true;
        }

        if ($htmlQuality > $jsonQuality) {
            return false;
        }

        if ($jsonQuality > 0.0 || $wildcardQuality > 0.0) {
            return true;
        }

        return !$jsonMentioned;
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
