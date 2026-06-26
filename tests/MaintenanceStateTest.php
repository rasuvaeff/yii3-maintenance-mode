<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(MaintenanceState::class)]
final class MaintenanceStateTest
{
    public function defaultsToDisabled(): void
    {
        $state = new MaintenanceState();

        Assert::false($state->enabled);
        Assert::same($state->retryAfter, 300);
        Assert::same($state->allowedIps, []);
        Assert::same($state->bypassTokenHash, '');
    }

    public function acceptsCustomValues(): void
    {
        $state = new MaintenanceState(
            enabled: true,
            retryAfter: 600,
            allowedIps: ['127.0.0.1'],
            bypassTokenHash: 'abc123',
        );

        Assert::true($state->enabled);
        Assert::same($state->retryAfter, 600);
        Assert::same($state->allowedIps, ['127.0.0.1']);
        Assert::same($state->bypassTokenHash, 'abc123');
    }
}
