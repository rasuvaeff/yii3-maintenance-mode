<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\ConfigMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(ConfigMaintenanceProvider::class)]
final class ConfigMaintenanceProviderTest
{
    public function defaultsToDisabled(): void
    {
        $provider = new ConfigMaintenanceProvider([]);

        Assert::false($provider->getState()->enabled);
    }

    public function returnsEnabledState(): void
    {
        $provider = new ConfigMaintenanceProvider(['enabled' => true]);

        Assert::true($provider->getState()->enabled);
    }

    public function returnsCustomRetryAfter(): void
    {
        $provider = new ConfigMaintenanceProvider(['retryAfter' => 600]);

        Assert::same($provider->getState()->retryAfter, 600);
    }

    public function returnsAllowedIps(): void
    {
        $provider = new ConfigMaintenanceProvider(['allowedIps' => ['10.0.0.1', '10.0.0.2']]);

        Assert::same($provider->getState()->allowedIps, ['10.0.0.1', '10.0.0.2']);
    }

    public function returnsBypassTokenHash(): void
    {
        $provider = new ConfigMaintenanceProvider(['bypassTokenHash' => 'hash123']);

        Assert::same($provider->getState()->bypassTokenHash, 'hash123');
    }

    public function implementsInterface(): void
    {
        $provider = new ConfigMaintenanceProvider();

        Assert::instanceOf($provider, MaintenanceProvider::class);
    }

    /**
     * @param array{enabled?: bool, retryAfter?: int, allowedIps?: list<string>, bypassTokenHash?: string} $config
     */
    #[DataProvider('configProvider')]
    public function respectsConfig(array $config, bool $expectedEnabled, int $expectedRetryAfter): void
    {
        $provider = new ConfigMaintenanceProvider($config);
        $state = $provider->getState();

        Assert::same($state->enabled, $expectedEnabled);
        Assert::same($state->retryAfter, $expectedRetryAfter);
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
}
