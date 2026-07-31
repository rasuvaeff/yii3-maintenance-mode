<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

use InvalidArgumentException;

/**
 * @api
 */
final readonly class MaintenanceState
{
    public function __construct(
        public bool $enabled = false,
        public int $retryAfter = 300,
        /** @var list<string> */
        public array $allowedIps = [],
        public string $bypassTokenHash = '',
    ) {
        if ($retryAfter < 0) {
            throw new InvalidArgumentException('Retry-After must be non-negative');
        }
    }
}
