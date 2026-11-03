<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use ErrorException;
use Rasuvaeff\Yii3MaintenanceMode\IpMatcher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(IpMatcher::class)]
final class IpMatcherTest
{
    #[DataProvider('matchProvider')]
    public function matchesAgainstSingleRule(string $ip, string $rule, bool $expected): void
    {
        $this->withoutNotices(static function () use ($ip, $rule, $expected): void {
            Assert::same(IpMatcher::matches($ip, [$rule]), $expected);
        });
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function matchProvider(): iterable
    {
        yield 'exact ipv4' => ['10.0.0.1', '10.0.0.1', true];
        yield 'other ipv4' => ['10.0.0.2', '10.0.0.1', false];
        yield 'ipv6 compared by normalized form' => ['::1', '0:0:0:0:0:0:0:1', true];
        yield 'whole-byte prefix, same net' => ['10.1.2.3', '10.0.0.0/8', true];
        yield 'whole-byte prefix, high bit set in next byte' => ['10.200.0.1', '10.0.0.0/8', true];
        yield 'whole-byte prefix, other net' => ['11.0.0.1', '10.0.0.0/8', false];
        yield 'two whole bytes' => ['192.168.77.1', '192.168.0.0/16', true];
        yield 'two whole bytes, other net' => ['192.169.0.1', '192.168.0.0/16', false];
        yield 'host prefix matches' => ['1.2.3.4', '1.2.3.4/32', true];
        yield 'host prefix differs' => ['1.2.3.5', '1.2.3.4/32', false];
        yield 'zero prefix matches everything in family' => ['203.0.113.9', '0.0.0.0/0', true];
        yield 'one-bit prefix, bit clear' => ['127.0.0.1', '0.0.0.0/1', true];
        yield 'one-bit prefix, bit set' => ['128.0.0.1', '0.0.0.0/1', false];
        yield 'partial byte, last address inside' => ['192.168.1.255', '192.168.1.128/25', true];
        yield 'partial byte, first address inside' => ['192.168.1.128', '192.168.1.128/25', true];
        yield 'partial byte, just below' => ['192.168.1.127', '192.168.1.128/25', false];
        yield 'nibble prefix, low bits differ' => ['172.16.15.255', '172.16.0.0/20', true];
        yield 'nibble prefix, masked bit differs' => ['172.16.16.0', '172.16.0.0/20', false];
        yield 'partial byte with non-zero network bits' => ['10.0.7.1', '10.0.5.0/23', false];
        yield 'partial byte with non-zero network bits inside' => ['10.0.5.200', '10.0.4.0/23', true];
        yield 'ipv6 prefix inside' => ['2001:db8::1', '2001:db8::/32', true];
        yield 'ipv6 prefix outside' => ['2001:db9::1', '2001:db8::/32', false];
        yield 'ipv6 partial byte inside' => ['2001:db8:0:1::1', '2001:db8::/63', true];
        yield 'ipv6 partial byte outside' => ['2001:db8:0:2::1', '2001:db8::/63', false];
        yield 'ipv6 host prefix' => ['::1', '::1/128', true];
        yield 'ipv6 host prefix differs' => ['::2', '::1/128', false];
        yield 'ipv4 address, ipv6 rule' => ['10.0.0.1', '::/0', false];
        yield 'ipv6 address, ipv4 rule' => ['::1', '0.0.0.0/0', false];
        yield 'invalid address' => ['not-an-ip', '10.0.0.0/8', false];
        yield 'invalid address, exact rule' => ['not-an-ip', '10.0.0.1', false];
        yield 'invalid rule is skipped' => ['10.0.0.1', '10.0.0.0/abc', false];
    }

    public function matchesWhenAnyRuleMatches(): void
    {
        Assert::true(IpMatcher::matches('10.0.0.1', ['192.168.0.0/16', 'garbage', '10.0.0.0/8']));
        Assert::false(IpMatcher::matches('10.0.0.1', ['192.168.0.0/16', '172.16.0.0/12']));
        Assert::false(IpMatcher::matches('10.0.0.1', []));
    }

    #[DataProvider('ruleProvider')]
    public function validatesRules(string $rule, bool $expected): void
    {
        $this->withoutNotices(static function () use ($rule, $expected): void {
            Assert::same(IpMatcher::isValidRule($rule), $expected);
        });
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function ruleProvider(): iterable
    {
        yield 'plain ipv4' => ['10.0.0.1', true];
        yield 'plain ipv6' => ['::1', true];
        yield 'plain garbage' => ['not-an-ip', false];
        yield 'ipv4 cidr' => ['10.0.0.0/8', true];
        yield 'ipv4 widest prefix' => ['10.0.0.0/32', true];
        yield 'ipv4 prefix too long' => ['10.0.0.0/33', false];
        yield 'ipv4 prefix far too long' => ['10.0.0.0/36', false];
        yield 'ipv4 zero prefix' => ['0.0.0.0/0', true];
        yield 'ipv6 cidr' => ['2001:db8::/32', true];
        yield 'ipv6 widest prefix' => ['::/128', true];
        yield 'ipv6 prefix too long' => ['::/129', false];
        yield 'empty network' => ['/24', false];
        yield 'empty prefix' => ['10.0.0.0/', false];
        yield 'non-numeric prefix' => ['10.0.0.0/abc', false];
        yield 'trailing garbage after prefix' => ['10.0.0.0/8x', false];
        yield 'negative prefix' => ['10.0.0.0/-1', false];
        yield 'invalid network' => ['bad/8', false];
        yield 'huge prefix' => ['10.0.0.0/99999999999999999999', false];
    }

    private function withoutNotices(callable $assertions): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (str_contains($message, 'Unrecognized address')) {
                return true;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $assertions();
        } finally {
            restore_error_handler();
        }
    }
}
