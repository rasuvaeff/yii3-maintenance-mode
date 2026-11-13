<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

/**
 * Typed seam over the three Redis commands the maintenance state needs.
 *
 * Isolates the untyped Redis-client boundary from {@see RedisMaintenanceProvider} and
 * {@see RedisMaintenanceWriter}, which stay fully typed. Implement this to back the
 * state with a different client. Ready implementations: {@see \Rasuvaeff\Yii3MaintenanceMode\Redis\PhpRedisMaintenanceClient}
 * over `ext-redis` and {@see \Rasuvaeff\Yii3MaintenanceMode\Redis\PredisMaintenanceClient} over `predis/predis`.
 *
 * @api
 */
interface MaintenanceRedisClient
{
    /**
     * Returns the value stored under the key, or null when the key does not exist
     * (a missing key is the default disabled state, not an error).
     */
    public function get(string $key): ?string;

    /**
     * Stores the value under the key, overwriting any previous value.
     *
     * @throws \RuntimeException when the server rejects the write
     */
    public function set(string $key, string $value): void;

    /**
     * Removes the key. Removing a missing key is not an error.
     *
     * @throws \RuntimeException when the server rejects the delete
     */
    public function delete(string $key): void;
}
