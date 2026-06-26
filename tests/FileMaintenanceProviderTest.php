<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(FileMaintenanceProvider::class)]
final class FileMaintenanceProviderTest
{
    private string $tmpFile;

    #[BeforeTest]
    public function createTmpFile(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/maintenance_test_' . uniqid() . '.json';
    }

    #[AfterTest]
    public function removeTmpFile(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function returnsDisabledWhenFileNotExists(): void
    {
        $provider = new FileMaintenanceProvider('/nonexistent/file.json');

        $state = $provider->getState();

        Assert::false($state->enabled);
        Assert::same($state->retryAfter, 300);
        Assert::same($state->allowedIps, []);
        Assert::same($state->bypassTokenHash, '');
    }

    public function readsEnabledStateFromFile(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'retryAfter' => 600,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        Assert::true($state->enabled);
        Assert::same($state->retryAfter, 600);
    }

    public function readsAllowedIpsFromFile(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1', '10.0.0.2'],
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        Assert::same($state->allowedIps, ['10.0.0.1', '10.0.0.2']);
    }

    public function readsBypassTokenHashFromFile(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'bypassTokenHash' => 'somehash',
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        Assert::same($state->bypassTokenHash, 'somehash');
    }

    public function returnsDefaultWhenFileContainsInvalidJson(): void
    {
        file_put_contents($this->tmpFile, 'not json');

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::false($provider->getState()->enabled);
    }

    public function returnsDefaultWhenFileContainsNonArray(): void
    {
        file_put_contents($this->tmpFile, json_encode('string', JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::false($provider->getState()->enabled);
    }

    public function filtersNonStringIps(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1', 123, null, '10.0.0.2'],
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        Assert::same($state->allowedIps, ['10.0.0.1', '10.0.0.2']);
    }

    public function defaultsEnabledToFalseWhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'retryAfter' => 100,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::false($provider->getState()->enabled);
    }

    public function defaultsRetryAfterTo300WhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::same($provider->getState()->retryAfter, 300);
    }

    public function castsEnabledToBool(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => 1,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::true($provider->getState()->enabled);
    }

    public function castsRetryAfterToInt(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'retryAfter' => '600',
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::same($provider->getState()->retryAfter, 600);
    }

    public function castsBypassTokenHashToString(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'bypassTokenHash' => 12345,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::same($provider->getState()->bypassTokenHash, '12345');
    }

    public function defaultsBypassTokenHashToEmptyStringWhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::same($provider->getState()->bypassTokenHash, '');
    }

    public function defaultsAllowedIpsToEmptyArrayWhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::same($provider->getState()->allowedIps, []);
    }

    public function implementsInterface(): void
    {
        $provider = new FileMaintenanceProvider('/nonexistent');

        Assert::instanceOf($provider, MaintenanceProvider::class);
    }

    public function parsesJsonAtDepthLimit(): void
    {
        $json = '{"enabled":' . str_repeat('[', 510) . '1' . str_repeat(']', 510) . '}';

        file_put_contents($this->tmpFile, $json);

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::true($provider->getState()->enabled);
    }

    public function rejectsJsonBeyondDepthLimit(): void
    {
        $json = '{"enabled":' . str_repeat('[', 511) . '1' . str_repeat(']', 511) . '}';

        file_put_contents($this->tmpFile, $json);

        $provider = new FileMaintenanceProvider($this->tmpFile);

        Assert::false($provider->getState()->enabled);
    }
}
