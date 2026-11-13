<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use Rasuvaeff\Yii3MaintenanceMode\Internal\StateJson;

/**
 * Reads the maintenance state from a shared Redis key — the multi-node counterpart of
 * {@see FileMaintenanceProvider}: every node of the deployment sees the same toggle at
 * the same time, because all of them read the same key.
 *
 * A missing key, an unreachable-shape value or invalid JSON degrades to the default
 * disabled state, never to an exception — the maintenance flag must not take the site
 * down. Toggle with {@see RedisMaintenanceWriter}.
 *
 * @api
 */
final readonly class RedisMaintenanceProvider implements MaintenanceProvider
{
    public const string DEFAULT_KEY = 'maintenance:state';

    /**
     * An unreachable or failing Redis is NOT silently mapped to the disabled
     * state: a throwing client propagates from {@see getState()} and the
     * request fails loudly. Silently failing open during an outage would serve
     * traffic through an enabled maintenance window with nobody noticing —
     * wrap this provider in your own try/catch if you accept that trade-off.
     */

    public function __construct(
        private MaintenanceRedisClient $client,
        private string $key = self::DEFAULT_KEY,
    ) {}

    #[\Override]
    public function getState(): MaintenanceState
    {
        $json = $this->client->get($this->key);

        return $json === null ? new MaintenanceState() : StateJson::decode($json);
    }
}
