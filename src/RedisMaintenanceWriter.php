<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Rasuvaeff\Yii3MaintenanceMode\Internal\StateJson;

/**
 * Writes the maintenance state to a shared Redis key — the counterpart of
 * {@see RedisMaintenanceProvider} and the Redis analogue of {@see AtomicMaintenanceFileWriter}.
 *
 * `write()` stores the full state document; `clear()` removes the key entirely (a missing
 * key reads back as the default disabled state), so the hot path stays one GET with no
 * document to parse while maintenance is off.
 *
 * @api
 */
final readonly class RedisMaintenanceWriter
{
    public function __construct(
        private MaintenanceRedisClient $client,
        private string $key = RedisMaintenanceProvider::DEFAULT_KEY,
    ) {}

    public function write(MaintenanceState $state): void
    {
        $this->client->set($this->key, StateJson::encode($state));
    }

    public function clear(): void
    {
        $this->client->delete($this->key);
    }
}
