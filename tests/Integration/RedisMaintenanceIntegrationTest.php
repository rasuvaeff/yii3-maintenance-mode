<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests\Integration;

use Predis\Client;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use Rasuvaeff\Yii3MaintenanceMode\Redis\PhpRedisMaintenanceClient;
use Rasuvaeff\Yii3MaintenanceMode\Redis\PredisMaintenanceClient;
use Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceWriter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Covers the real Redis clients (phpredis + predis): the unit suite mocks the
 * client seam and cannot see a broken command/reply mapping. `#[Covers]` (not
 * `#[CoversNothing]`) is deliberate — these classes have no unit coverage, and
 * CoversNothing would zero their mutation attribution entirely. Self-skips
 * without REDIS_HOST.
 */
#[Test]
#[Covers(PredisMaintenanceClient::class)]
#[Covers(PhpRedisMaintenanceClient::class)]
final class RedisMaintenanceIntegrationTest
{
    private function skipWithoutRedis(): bool
    {
        return getenv('REDIS_HOST') === false;
    }

    public function predisClientRoundTripsState(): void
    {
        if ($this->skipWithoutRedis()) {
            return;
        }

        $client = new PredisMaintenanceClient(self::predisClient());
        $this->roundTrip($client);
    }

    public function phpRedisClientRoundTripsState(): void
    {
        if ($this->skipWithoutRedis() || !extension_loaded('redis')) {
            return;
        }

        $phpRedis = new \Redis();
        $phpRedis->connect((string) getenv('REDIS_HOST'));

        $this->roundTrip(new \Rasuvaeff\Yii3MaintenanceMode\Redis\PhpRedisMaintenanceClient($phpRedis));
    }

    private function roundTrip(\Rasuvaeff\Yii3MaintenanceMode\MaintenanceRedisClient $client): void
    {
        $key = 'test:maintenance:' . uniqid();
        $provider = new RedisMaintenanceProvider($client, $key);
        $writer = new RedisMaintenanceWriter($client, $key);

        Assert::false($provider->getState()->enabled);

        $state = new MaintenanceState(
            enabled: true,
            retryAfter: 600,
            allowedIps: ['10.0.0.1'],
            bypassTokenHash: str_repeat('c', 64),
        );
        $writer->write($state);

        $readBack = $provider->getState();

        Assert::true($readBack->enabled);
        Assert::same($readBack->retryAfter, $state->retryAfter);
        Assert::same($readBack->allowedIps, $state->allowedIps);
        Assert::same($readBack->bypassTokenHash, $state->bypassTokenHash);

        $writer->clear();

        Assert::false($provider->getState()->enabled);
    }

    private static function predisClient(): Client
    {
        return new Client(['host' => (string) getenv('REDIS_HOST')]);
    }
}
