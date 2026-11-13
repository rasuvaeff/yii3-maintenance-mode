<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(RedisMaintenanceProvider::class)]
#[Covers(\Rasuvaeff\Yii3MaintenanceMode\Internal\StateJson::class)]
final class RedisMaintenanceProviderTest
{
    public function returnsDisabledWhenKeyMissing(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $provider = new RedisMaintenanceProvider($client);

        $state = $provider->getState();

        Assert::false($state->enabled);
        Assert::same($state->retryAfter, 300);
        Assert::same($state->allowedIps, []);
        Assert::same($state->bypassTokenHash, '');
        Assert::same($client->calls, ['GET maintenance:state']);
    }

    public function readsEnabledStateFromRedis(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['maintenance:state'] = json_encode([
            'enabled' => true,
            'retryAfter' => 600,
            'allowedIps' => ['10.0.0.1'],
            'bypassTokenHash' => str_repeat('a', 64),
        ], JSON_THROW_ON_ERROR);

        $state = (new RedisMaintenanceProvider($client))->getState();

        Assert::true($state->enabled);
        Assert::same($state->retryAfter, 600);
        Assert::same($state->allowedIps, ['10.0.0.1']);
        Assert::same($state->bypassTokenHash, str_repeat('a', 64));
    }

    public function usesCustomKey(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['site:maintenance'] = '{"enabled":true}';

        $state = (new RedisMaintenanceProvider($client, 'site:maintenance'))->getState();

        Assert::true($state->enabled);
        Assert::same($client->calls, ['GET site:maintenance']);
    }

    #[DataProvider('invalidPayloadProvider')]
    public function degradesToDisabledStateOnInvalidPayload(string $json): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['maintenance:state'] = $json;

        $state = (new RedisMaintenanceProvider($client))->getState();

        Assert::false($state->enabled);
        Assert::same($state->retryAfter, 300);
        Assert::same($state->allowedIps, []);
        Assert::same($state->bypassTokenHash, '');
    }

    public static function invalidPayloadProvider(): iterable
    {
        yield 'not json' => ['{invalid'];
        yield 'json string' => ['"enabled"'];
        yield 'json null' => ['null'];
        yield 'json int' => ['1'];
        yield 'wrong enabled type' => ['{"enabled":"yes"}'];
        yield 'negative retryAfter' => ['{"enabled":true,"retryAfter":-1}'];
        yield 'wrong retryAfter type' => ['{"enabled":true,"retryAfter":"300"}'];
        yield 'allowedIps not array' => ['{"enabled":true,"allowedIps":"10.0.0.1"}'];
        yield 'allowedIps json object' => ['{"enabled":true,"allowedIps":{"entry":"10.0.0.1"}}'];
        yield 'invalid bypass hash' => ['{"enabled":true,"bypassTokenHash":"zz"}'];
        yield 'bypass hash not string' => ['{"enabled":true,"bypassTokenHash":123}'];
    }

    public function dropsInvalidIpRulesButKeepsValidOnes(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['maintenance:state'] = json_encode([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1', 'not-an-ip', '999.1.1.1'],
        ], JSON_THROW_ON_ERROR);

        $state = (new RedisMaintenanceProvider($client))->getState();

        Assert::same($state->allowedIps, ['10.0.0.1']);
    }

    public function appliesDefaultsForMissingKeys(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['maintenance:state'] = '{}';

        $state = (new RedisMaintenanceProvider($client))->getState();

        Assert::false($state->enabled);
        Assert::same($state->retryAfter, 300);
        Assert::same($state->allowedIps, []);
        Assert::same($state->bypassTokenHash, '');
    }

    public function acceptsZeroRetryAfter(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['maintenance:state'] = '{"enabled":true,"retryAfter":0}';

        $state = (new RedisMaintenanceProvider($client))->getState();

        Assert::true($state->enabled);
        Assert::same($state->retryAfter, 0);
    }

    public function acceptsUppercaseBypassHash(): void
    {
        $client = new FakeMaintenanceRedisClient();
        $client->storage['maintenance:state'] = json_encode([
            'enabled' => true,
            'bypassTokenHash' => strtoupper(str_repeat('a', 64)),
        ], JSON_THROW_ON_ERROR);

        $state = (new RedisMaintenanceProvider($client))->getState();

        Assert::same($state->bypassTokenHash, strtoupper(str_repeat('a', 64)));
    }

    public function implementsMaintenanceProvider(): void
    {
        $provider = new RedisMaintenanceProvider(new FakeMaintenanceRedisClient());

        Assert::instanceOf($provider, MaintenanceProvider::class);
    }
}
