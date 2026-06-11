<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

/**
 * @api
 */
final readonly class FileMaintenanceProvider implements MaintenanceProvider
{
    public function __construct(
        private string $filePath,
    ) {}

    #[\Override]
    public function getState(): MaintenanceState
    {
        if (!file_exists($this->filePath)) {
            return new MaintenanceState();
        }

        /** @var mixed $content */
        $content = file_get_contents($this->filePath);

        if (!is_string($content)) {
            return new MaintenanceState();
        }

        try {
            /** @var mixed $data */
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new MaintenanceState();
        }

        if (!is_array($data)) {
            return new MaintenanceState();
        }

        $ips = $data['allowedIps'] ?? [];
        $filteredIps = [];

        foreach ($ips as $ip) {
            if (is_string($ip)) {
                $filteredIps[] = $ip;
            }
        }

        return new MaintenanceState(
            enabled: (bool) ($data['enabled'] ?? false),
            retryAfter: (int) ($data['retryAfter'] ?? 300),
            allowedIps: $filteredIps,
            bypassTokenHash: (string) ($data['bypassTokenHash'] ?? ''),
        );
    }
}
