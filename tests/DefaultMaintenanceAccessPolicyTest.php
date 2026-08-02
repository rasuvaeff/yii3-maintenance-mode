<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceAccessPolicy;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceAccessPolicy;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(DefaultMaintenanceAccessPolicy::class)]
final class DefaultMaintenanceAccessPolicyTest
{
    public function implementsAccessPolicy(): void
    {
        Assert::instanceOf(new DefaultMaintenanceAccessPolicy(), MaintenanceAccessPolicy::class);
    }

    public function allowsHeaderToken(): void
    {
        $token = 'secret';
        $policy = new DefaultMaintenanceAccessPolicy(allowQueryParameter: false);
        $request = new FakeRequest(headers: ['x-maintenance-bypass' => [$token]]);
        $state = new MaintenanceState(enabled: true, bypassTokenHash: hash('sha256', $token));

        Assert::true($policy->allows($request, $state));
    }

    public function queryTokenCanBeDisabled(): void
    {
        $token = 'secret';
        $policy = new DefaultMaintenanceAccessPolicy(allowQueryParameter: false);
        $request = new FakeRequest(queryParams: ['bypass' => $token]);
        $state = new MaintenanceState(enabled: true, bypassTokenHash: hash('sha256', $token));

        Assert::false($policy->allows($request, $state));
    }

    public function headerTokenTakesPrecedenceOverQueryWhenBothPresent(): void
    {
        $token = 'secret';
        $policy = new DefaultMaintenanceAccessPolicy();
        $request = new FakeRequest(
            queryParams: ['bypass' => 'wrong-token'],
            headers: ['x-maintenance-bypass' => [$token]],
        );
        $state = new MaintenanceState(enabled: true, bypassTokenHash: hash('sha256', $token));

        Assert::true($policy->allows($request, $state));
    }

    public function allowsQueryTokenWhenNoHeaderTokenPresent(): void
    {
        $token = 'secret';
        $policy = new DefaultMaintenanceAccessPolicy();
        $request = new FakeRequest(queryParams: ['bypass' => $token]);
        $state = new MaintenanceState(enabled: true, bypassTokenHash: hash('sha256', $token));

        Assert::true($policy->allows($request, $state));
    }

    public function neverAllowsAnEmptyTokenEvenIfItMatchesTheHashOfAnEmptyString(): void
    {
        $policy = new DefaultMaintenanceAccessPolicy(allowQueryParameter: false);
        $request = new FakeRequest();
        $state = new MaintenanceState(enabled: true, bypassTokenHash: hash('sha256', ''));

        Assert::false($policy->allows($request, $state));
    }

    public function requestTargetWithoutAPathComponentDefaultsToRootPath(): void
    {
        $policy = new DefaultMaintenanceAccessPolicy(excludedPathPrefixes: ['/health']);
        $request = new FakeRequest(requestTarget: '?probe=1');
        $state = new MaintenanceState();

        Assert::false($policy->allows($request, $state));
    }

    public function excludedPathPrefixWithTrailingSlashMatchesThePrefixItself(): void
    {
        $policy = new DefaultMaintenanceAccessPolicy(excludedPathPrefixes: ['/health/']);

        Assert::true($policy->allows(new FakeRequest(requestTarget: '/health'), new MaintenanceState()));
    }

    public function supportsIpv4AndIpv6Cidrs(): void
    {
        $ipv4Policy = new DefaultMaintenanceAccessPolicy(clientIpResolver: new FakeClientIpResolver('10.20.30.40'));
        $ipv6Policy = new DefaultMaintenanceAccessPolicy(clientIpResolver: new FakeClientIpResolver('2001:db8::42'));

        Assert::true($ipv4Policy->allows(new FakeRequest(), new MaintenanceState(allowedIps: ['10.20.0.0/16'])));
        Assert::true($ipv6Policy->allows(new FakeRequest(), new MaintenanceState(allowedIps: ['2001:db8::/32'])));
    }

    public function ignoresMalformedCidrsAndNormalizesIpv6(): void
    {
        $policy = new DefaultMaintenanceAccessPolicy(clientIpResolver: new FakeClientIpResolver('2001:db8::1'));

        Assert::false($policy->allows(new FakeRequest(), new MaintenanceState(allowedIps: ['2001:db8::/999'])));
        Assert::true($policy->allows(new FakeRequest(), new MaintenanceState(allowedIps: ['2001:0db8:0:0:0:0:0:1'])));
    }

    public function allowsExcludedPathPrefix(): void
    {
        $policy = new DefaultMaintenanceAccessPolicy(excludedPathPrefixes: ['/health']);

        Assert::true($policy->allows(new FakeRequest(requestTarget: '/health/ready?probe=1'), new MaintenanceState()));
        Assert::false($policy->allows(new FakeRequest(requestTarget: '/healthy'), new MaintenanceState()));
    }

    public function rejectsInvalidConfiguration(): void
    {
        try {
            new DefaultMaintenanceAccessPolicy(excludedPathPrefixes: ['health']);
            Assert::fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            Assert::same($exception->getMessage(), 'Invalid excluded path prefix');
        }
    }
}
