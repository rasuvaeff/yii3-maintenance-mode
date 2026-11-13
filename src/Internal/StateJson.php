<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Internal;

use JsonException;
use Rasuvaeff\Yii3MaintenanceMode\IpMatcher;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;

/**
 * Encodes and decodes {@see MaintenanceState} as the shared JSON document.
 *
 * One codec for every backend ({@see \Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider},
 * {@see \Rasuvaeff\Yii3MaintenanceMode\RedisMaintenanceProvider} and their writers), so a state
 * written by one backend reads back identically through another. Untrusted input is decoded
 * strictly: any shape mismatch degrades to the default disabled state, never to an exception —
 * a maintenance flag must not take the site down.
 *
 * @internal
 */
final readonly class StateJson
{
    public static function encode(MaintenanceState $state, bool $pretty = false): string
    {
        try {
            return json_encode([
                'enabled' => $state->enabled,
                'retryAfter' => $state->retryAfter,
                'allowedIps' => $state->allowedIps,
                'bypassTokenHash' => $state->bypassTokenHash,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0));
        } catch (JsonException $exception) {
            throw new \RuntimeException('Unable to encode maintenance state', $exception->getCode(), previous: $exception);
        }
    }

    public static function decode(string $json): MaintenanceState
    {
        try {
            /** @var mixed $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
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

        if ($bypassTokenHash !== '' && preg_match('/\A[a-f0-9]{64}\z/i', $bypassTokenHash) !== 1) {
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
