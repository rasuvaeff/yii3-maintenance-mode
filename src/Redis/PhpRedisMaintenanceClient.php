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

    /**
     * The connection must not use a serializer (`OPT_SERIALIZER` must be
     * `SERIALIZER_NONE`): a serializing connection would store the state JSON
     * wrapped in the serializer's envelope, which a plain GET from another
     * node (predis, redis-cli) could not read back. Share this rule in the
     * connection you pass, or dedicate a connection to the maintenance state.
     */

    #[\Override]
    public function get(string $key): ?string
    {
        // A phpredis GET answers false both for a missing key and for a failed
        // command; only the per-connection last error separates them. A failed
        // GET must surface — mapping it to a miss would silently read the
        // default disabled state while maintenance is enabled.
        $this->client->clearLastError();

        /** @var mixed $value */
        $value = $this->client->get($key);

        $error = $this->client->getLastError();

        if ($error !== null) {
            $this->client->clearLastError();

            throw new RuntimeException('Redis GET failed: ' . $error);
        }

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
