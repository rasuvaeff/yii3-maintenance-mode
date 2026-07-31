<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
final readonly class DefaultMaintenanceAccessPolicy implements MaintenanceAccessPolicy
{
    private ClientIpResolver $clientIpResolver;

    /** @var list<string> */
    private array $excludedPathPrefixes;

    /**
     * @param list<string> $excludedPathPrefixes
     */
    public function __construct(
        ?ClientIpResolver $clientIpResolver = null,
        array $excludedPathPrefixes = [],
        private string $bypassHeader = 'X-Maintenance-Bypass',
        private string $bypassQueryParameter = 'bypass',
        private bool $allowQueryParameter = true,
    ) {
        if (!preg_match('/\A[A-Za-z0-9-]+\z/', $this->bypassHeader)) {
            throw new InvalidArgumentException('Invalid bypass header name');
        }

        if (!preg_match('/\A[A-Za-z0-9_]+\z/', $this->bypassQueryParameter)) {
            throw new InvalidArgumentException('Invalid bypass query parameter');
        }

        foreach ($excludedPathPrefixes as $prefix) {
            if ($prefix === '' || !str_starts_with($prefix, '/')) {
                throw new InvalidArgumentException('Invalid excluded path prefix');
            }
        }

        $this->clientIpResolver = $clientIpResolver ?? new RemoteAddrClientIpResolver();
        $this->excludedPathPrefixes = $excludedPathPrefixes;
    }

    #[\Override]
    public function allows(ServerRequestInterface $request, MaintenanceState $state): bool
    {
        if ($this->isExcludedPath($request)) {
            return true;
        }

        $ip = $this->clientIpResolver->resolve($request);

        if ($ip !== null && IpMatcher::matches($ip, $state->allowedIps)) {
            return true;
        }

        $token = $request->getHeaderLine($this->bypassHeader);

        if ($token === '' && $this->allowQueryParameter) {
            $queryParams = $request->getQueryParams();
            $queryToken = $queryParams[$this->bypassQueryParameter] ?? null;
            $token = is_string($queryToken) ? $queryToken : '';
        }

        if ($token === '' || $state->bypassTokenHash === '') {
            return false;
        }

        return hash_equals($state->bypassTokenHash, hash('sha256', $token));
    }

    private function isExcludedPath(ServerRequestInterface $request): bool
    {
        $path = parse_url($request->getRequestTarget(), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        foreach ($this->excludedPathPrefixes as $prefix) {
            $normalizedPrefix = rtrim($prefix, '/');

            if ($normalizedPrefix === '' || $path === $normalizedPrefix || str_starts_with($path, $normalizedPrefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
