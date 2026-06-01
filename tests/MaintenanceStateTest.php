<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;

#[CoversClass(MaintenanceState::class)]
final class MaintenanceStateTest extends TestCase
{
    #[Test]
    public function defaultsToDisabled(): void
    {
        $state = new MaintenanceState();

        $this->assertFalse($state->enabled);
        $this->assertSame(300, $state->retryAfter);
        $this->assertSame([], $state->allowedIps);
        $this->assertSame('', $state->bypassTokenHash);
    }

    #[Test]
    public function acceptsCustomValues(): void
    {
        $state = new MaintenanceState(
            enabled: true,
            retryAfter: 600,
            allowedIps: ['127.0.0.1'],
            bypassTokenHash: 'abc123',
        );

        $this->assertTrue($state->enabled);
        $this->assertSame(600, $state->retryAfter);
        $this->assertSame(['127.0.0.1'], $state->allowedIps);
        $this->assertSame('abc123', $state->bypassTokenHash);
    }
}
