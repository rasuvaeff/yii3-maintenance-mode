<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Redis;

use Rasuvaeff\Yii3MaintenanceMode\MaintenanceRedisClient;
use RuntimeException;

/**
 * phpredis-backed {@see MaintenanceRedisClient}. Requires `ext-redis`.
 *
 * @api
 */
final readonly class PhpRedisMaintenanceClient implements MaintenanceRedisClient
{
    public function __construct(
        private \Redis $client,
    ) {}

    #[\Override]
    public function get(string $key): ?string
    {
        /** @var mixed $value */
        $value = $this->client->get($key);

        return is_string($value) ? $value : null;
    }

    #[\Override]
    public function set(string $key, string $value): void
    {
        if ($this->client->set($key, $value) === false) {
            throw new RuntimeException('Redis SET failed');
        }
    }

    #[\Override]
    public function delete(string $key): void
    {
        if ($this->client->del($key) === false) {
            throw new RuntimeException('Redis DEL failed');
        }
    }
}
