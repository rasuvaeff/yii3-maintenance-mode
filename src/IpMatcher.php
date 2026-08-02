<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode;

/**
 * @internal
 */
final readonly class IpMatcher
{
    private function __construct() {}

    /**
     * @param list<string> $rules
     */
    public static function matches(string $ip, array $rules): bool
    {
        foreach ($rules as $rule) {
            if (self::isValidRule($rule) && self::matchesRule($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    public static function isValidRule(string $rule): bool
    {
        $slash = strpos($rule, '/');

        if ($slash === false) {
            return inet_pton($rule) !== false;
        }

        $network = substr($rule, 0, $slash);
        $prefix = substr($rule, $slash + 1);

        if ($network === '' || !preg_match('/\A\d+\z/', $prefix)) {
            return false;
        }

        $networkBytes = inet_pton($network);

        if ($networkBytes === false) {
            return false;
        }

        $prefixLength = (int) $prefix;

        return $prefixLength <= strlen($networkBytes) * 8;
    }

    private static function matchesRule(string $ip, string $rule): bool
    {
        $slash = strpos($rule, '/');

        if ($slash === false) {
            return inet_pton($ip) === inet_pton($rule);
        }

        $network = substr($rule, 0, $slash);
        $prefixLength = (int) substr($rule, $slash + 1);
        $ipBytes = inet_pton($ip);
        $networkBytes = inet_pton($network);

        if ($ipBytes === false || $networkBytes === false || strlen($ipBytes) !== strlen($networkBytes)) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);

        if ($fullBytes > 0 && substr($ipBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $prefixLength % 8;

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($ipBytes[$fullBytes]) & $mask) === (ord($networkBytes[$fullBytes]) & $mask);
    }
}
