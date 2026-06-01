<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

/**
 * @api
 */
final class ConfigMaintenanceProvider implements MaintenanceProvider
{
    private readonly MaintenanceState $state;

    /**
     * @param array{enabled?: bool, retryAfter?: int, allowedIps?: list<string>, bypassTokenHash?: string} $config
     */
    public function __construct(
        array $config = [],
    ) {
        $this->state = new MaintenanceState(
            enabled: $config['enabled'] ?? false,
            retryAfter: $config['retryAfter'] ?? 300,
            allowedIps: $config['allowedIps'] ?? [],
            bypassTokenHash: $config['bypassTokenHash'] ?? '',
        );
    }

    #[\Override]
    public function getState(): MaintenanceState
    {
        return $this->state;
    }
}
