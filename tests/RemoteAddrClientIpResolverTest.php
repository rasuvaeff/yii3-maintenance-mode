<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\RemoteAddrClientIpResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(RemoteAddrClientIpResolver::class)]
final class RemoteAddrClientIpResolverTest
{
    public function resolvesRemoteAddress(): void
    {
        $resolver = new RemoteAddrClientIpResolver();

        Assert::same($resolver->resolve(new FakeRequest(serverParams: ['REMOTE_ADDR' => '10.0.0.1'])), '10.0.0.1');
    }

    public function rejectsMissingOrInvalidRemoteAddress(): void
    {
        $resolver = new RemoteAddrClientIpResolver();

        Assert::null($resolver->resolve(new FakeRequest()));
        Assert::null($resolver->resolve(new FakeRequest(serverParams: ['REMOTE_ADDR' => 123])));
        Assert::null($resolver->resolve(new FakeRequest(serverParams: ['REMOTE_ADDR' => 'not-an-ip'])));
    }
}
