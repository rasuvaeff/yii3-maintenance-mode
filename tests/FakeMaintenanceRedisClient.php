<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use RuntimeException;

/**
 * In-memory {@see \Rasuvaeff\Yii3MaintenanceMode\MaintenanceRedisClient} for unit tests:
 * a key-value array plus a call log, so tests can assert both values and commands used.
 */
final class FakeMaintenanceRedisClient implements \Rasuvaeff\Yii3MaintenanceMode\MaintenanceRedisClient
{
    /** @var array<string, string> */
    public array $storage = [];

    /** @var list<string> */
    public array $calls = [];

    public bool $failWrites = false;

    #[\Override]
    public function get(string $key): ?string
    {
        $this->calls[] = 'GET ' . $key;

        return $this->storage[$key] ?? null;
    }

    #[\Override]
    public function set(string $key, string $value): void
    {
        $this->calls[] = 'SET ' . $key;

        if ($this->failWrites) {
            throw new RuntimeException('Redis SET failed');
        }

        $this->storage[$key] = $value;
    }

    #[\Override]
    public function delete(string $key): void
    {
        $this->calls[] = 'DEL ' . $key;

        if ($this->failWrites) {
            throw new RuntimeException('Redis DEL failed');
        }

        unset($this->storage[$key]);
    }
}
