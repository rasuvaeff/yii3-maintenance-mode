<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3MaintenanceMode\TrustedProxyClientIpResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(TrustedProxyClientIpResolver::class)]
final class TrustedProxyClientIpResolverTest
{
    public function resolvesClientAcrossTrustedProxyChain(): void
    {
        $resolver = new TrustedProxyClientIpResolver(['10.0.0.0/8']);
        $request = new FakeRequest(
            serverParams: ['REMOTE_ADDR' => '10.0.0.2'],
            headers: ['x-forwarded-for' => ['203.0.113.5, 10.0.0.1']],
        );

        Assert::same($resolver->resolve($request), '203.0.113.5');
    }

    public function ignoresForwardedHeaderFromUntrustedPeer(): void
    {
        $resolver = new TrustedProxyClientIpResolver(['10.0.0.0/8']);
        $request = new FakeRequest(
            serverParams: ['REMOTE_ADDR' => '198.51.100.7'],
            headers: ['x-forwarded-for' => ['203.0.113.5']],
        );

        Assert::same($resolver->resolve($request), '198.51.100.7');
    }

    public function rejectsMalformedForwardedAddress(): void
    {
        $resolver = new TrustedProxyClientIpResolver(['10.0.0.0/8']);
        $request = new FakeRequest(
            serverParams: ['REMOTE_ADDR' => '10.0.0.2'],
            headers: ['x-forwarded-for' => ['not-an-ip']],
        );

        Assert::null($resolver->resolve($request));
    }

    public function rejectsInvalidTrustedProxyRule(): void
    {
        try {
            new TrustedProxyClientIpResolver(['not-an-ip']);
            Assert::fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            Assert::same($exception->getMessage(), 'Invalid trusted proxy rule');
        }
    }
}
