<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;

#[CoversClass(ConfigMaintenanceProvider::class)]
final class ConfigMaintenanceProviderTest extends TestCase
{
    #[Test]
    public function defaultsToDisabled(): void
    {
        $provider = new ConfigMaintenanceProvider([]);

        $state = $provider->getState();

        $this->assertFalse($state->enabled);
    }

    #[Test]
    public function returnsEnabledState(): void
    {
        $provider = new ConfigMaintenanceProvider(['enabled' => true]);

        $this->assertTrue($provider->getState()->enabled);
    }

    #[Test]
    public function returnsCustomRetryAfter(): void
    {
        $provider = new ConfigMaintenanceProvider(['retryAfter' => 600]);

        $this->assertSame(600, $provider->getState()->retryAfter);
    }

    #[Test]
    public function returnsAllowedIps(): void
    {
        $provider = new ConfigMaintenanceProvider(['allowedIps' => ['10.0.0.1', '10.0.0.2']]);

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $provider->getState()->allowedIps);
    }

    #[Test]
    public function returnsBypassTokenHash(): void
    {
        $provider = new ConfigMaintenanceProvider(['bypassTokenHash' => 'hash123']);

        $this->assertSame('hash123', $provider->getState()->bypassTokenHash);
    }

    #[Test]
    public function implementsInterface(): void
    {
        $provider = new ConfigMaintenanceProvider();

        $this->assertInstanceOf(MaintenanceProvider::class, $provider);
    }

    /**
     * @return array<string, array{config: array{enabled?: bool, retryAfter?: int, allowedIps?: list<string>, bypassTokenHash?: string}, expectedEnabled: bool, expectedRetryAfter: int}>
     */
    public static function configProvider(): array
    {
        return [
            'empty config' => [
                'config' => [],
                'expectedEnabled' => false,
                'expectedRetryAfter' => 300,
            ],
            'full config' => [
                'config' => [
                    'enabled' => true,
                    'retryAfter' => 900,
                    'allowedIps' => ['192.168.1.1'],
                    'bypassTokenHash' => 'abc',
                ],
                'expectedEnabled' => true,
                'expectedRetryAfter' => 900,
            ],
        ];
    }

    /**
     * @param array{enabled?: bool, retryAfter?: int, allowedIps?: list<string>, bypassTokenHash?: string} $config
     */
    #[Test]
    #[DataProvider('configProvider')]
    public function respectsConfig(array $config, bool $expectedEnabled, int $expectedRetryAfter): void
    {
        $provider = new ConfigMaintenanceProvider($config);
        $state = $provider->getState();

        $this->assertSame($expectedEnabled, $state->enabled);
        $this->assertSame($expectedRetryAfter, $state->retryAfter);
    }
}
