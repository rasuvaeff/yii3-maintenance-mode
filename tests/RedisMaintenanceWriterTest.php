<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\Internal\StateJson;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceWriter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(RedisMaintenanceWriter::class)]
#[Covers(StateJson::class)]
final class RedisMaintenanceWriterTest
{
    public function writesStateReadableByProvider(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $state = new MaintenanceState(
            enabled: true,
            retryAfter: 600,
            allowedIps: ['10.0.0.1'],
            bypassTokenHash: str_repeat('b', 64),
        );

        (new RedisMaintenanceWriter($client))->write($state);

        Assert::same($client->storage['maintenance:state'], '{"enabled":true,"retryAfter":600,"allowedIps":["10.0.0.1"],"bypassTokenHash":"' . str_repeat('b', 64) . '"}');

        $readBack = (new RedisMaintenanceProvider($client))->getState();

        Assert::true($readBack->enabled);
        Assert::same($readBack->retryAfter, 600);
        Assert::same($readBack->allowedIps, ['10.0.0.1']);
        Assert::same($readBack->bypassTokenHash, str_repeat('b', 64));
    }

    public function writesToCustomKey(): void
    {
        $client = new FakeMaintenanceRedisClient();

        (new RedisMaintenanceWriter($client, 'site:maintenance'))->write(new MaintenanceState(enabled: true));

        Assert::same($client->calls, ['SET site:maintenance']);
        Assert::true(isset($client->storage['site:maintenance']));
    }

    public function clearRemovesTheKey(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['maintenance:state'] = '{"enabled":true}';
        $writer = new RedisMaintenanceWriter($client);

        $writer->clear();

        Assert::same($client->calls, ['DEL maintenance:state']);
        Assert::false(isset($client->storage['maintenance:state']));
        Assert::false((new RedisMaintenanceProvider($client))->getState()->enabled);
    }

    public function clearOnMissingKeyIsNotAnError(): void
    {
        $client = new FakeMaintenanceRedisClient();

        (new RedisMaintenanceWriter($client))->clear();

        Assert::same($client->calls, ['DEL maintenance:state']);
    }
}
