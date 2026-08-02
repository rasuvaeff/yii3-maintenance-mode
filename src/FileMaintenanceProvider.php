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
        if (!is_file($this->filePath) || !is_readable($this->filePath)) {
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

        $enabled = $data['enabled'] ?? false;
        $retryAfter = $data['retryAfter'] ?? 300;
        $ips = $data['allowedIps'] ?? [];
        $bypassTokenHash = $data['bypassTokenHash'] ?? '';

        if (!is_bool($enabled) || !is_int($retryAfter) || $retryAfter < 0 || !is_array($ips) || !is_string($bypassTokenHash)) {
            return new MaintenanceState();
        }

        if ($bypassTokenHash !== '' && !preg_match('/\A[a-f0-9]{64}\z/i', $bypassTokenHash)) {
            return new MaintenanceState();
        }

        /** @var list<string> $filteredIps */
        $filteredIps = [];

        foreach ($ips as $ip) {
            if (is_string($ip) && IpMatcher::isValidRule($ip)) {
                $filteredIps[] = $ip;
            }
        }

        return new MaintenanceState(
            enabled: $enabled,
            retryAfter: $retryAfter,
            allowedIps: $filteredIps,
            bypassTokenHash: $bypassTokenHash,
        );
    }
}
