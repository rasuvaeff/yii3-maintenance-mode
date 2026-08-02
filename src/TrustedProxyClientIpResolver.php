<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @api
 */
final readonly class TrustedProxyClientIpResolver implements ClientIpResolver
{
    /**
     * @param list<string> $trustedProxies
     */
    public function __construct(
        private array $trustedProxies,
        private string $forwardedForHeader = 'X-Forwarded-For',
    ) {
        if ($this->trustedProxies === []) {
            throw new InvalidArgumentException('Trusted proxy list must not be empty');
        }

        foreach ($this->trustedProxies as $proxy) {
            if (!IpMatcher::isValidRule($proxy)) {
                throw new InvalidArgumentException('Invalid trusted proxy rule');
            }
        }

        if (!preg_match('/\A[A-Za-z0-9-]+\z/', $this->forwardedForHeader)) {
            throw new InvalidArgumentException('Invalid forwarded-for header name');
        }
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): ?string
    {
        $remoteAddress = (new RemoteAddrClientIpResolver())->resolve($request);

        if ($remoteAddress === null || !IpMatcher::matches($remoteAddress, $this->trustedProxies)) {
            return $remoteAddress;
        }

        $forwardedFor = $request->getHeaderLine($this->forwardedForHeader);

        if ($forwardedFor === '') {
            return $remoteAddress;
        }

        $current = $remoteAddress;
        $forwardedAddresses = array_reverse(array_map(trim(...), explode(',', $forwardedFor)));

        foreach ($forwardedAddresses as $forwardedAddress) {
            if (!IpMatcher::matches($current, $this->trustedProxies)) {
                return $current;
            }

            if (filter_var($forwardedAddress, FILTER_VALIDATE_IP) === false) {
                return null;
            }

            $current = $forwardedAddress;
        }

        return $current;
    }
}
