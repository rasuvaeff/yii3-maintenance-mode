<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceProvider;

#[CoversClass(FileMaintenanceProvider::class)]
final class FileMaintenanceProviderTest extends TestCase
{
    private string $tmpFile;

    #[\Override]
    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/maintenance_test_' . uniqid() . '.json';
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    #[Test]
    public function returnsDisabledWhenFileNotExists(): void
    {
        $provider = new FileMaintenanceProvider('/nonexistent/file.json');

        $state = $provider->getState();

        $this->assertFalse($state->enabled);
        $this->assertSame(300, $state->retryAfter);
        $this->assertSame([], $state->allowedIps);
        $this->assertSame('', $state->bypassTokenHash);
    }

    #[Test]
    public function readsEnabledStateFromFile(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'retryAfter' => 600,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        $this->assertTrue($state->enabled);
        $this->assertSame(600, $state->retryAfter);
    }

    #[Test]
    public function readsAllowedIpsFromFile(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1', '10.0.0.2'],
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $state->allowedIps);
    }

    #[Test]
    public function readsBypassTokenHashFromFile(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'bypassTokenHash' => 'somehash',
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        $this->assertSame('somehash', $state->bypassTokenHash);
    }

    #[Test]
    public function returnsDefaultWhenFileContainsInvalidJson(): void
    {
        file_put_contents($this->tmpFile, 'not json');

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertFalse($provider->getState()->enabled);
    }

    #[Test]
    public function returnsDefaultWhenFileContainsNonArray(): void
    {
        file_put_contents($this->tmpFile, json_encode('string', JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertFalse($provider->getState()->enabled);
    }

    #[Test]
    public function filtersNonStringIps(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
            'allowedIps' => ['10.0.0.1', 123, null, '10.0.0.2'],
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);
        $state = $provider->getState();

        $this->assertSame(['10.0.0.1', '10.0.0.2'], $state->allowedIps);
    }

    #[Test]
    public function defaultsEnabledToFalseWhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'retryAfter' => 100,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertFalse($provider->getState()->enabled);
    }

    #[Test]
    public function defaultsRetryAfterTo300WhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertSame(300, $provider->getState()->retryAfter);
    }

    #[Test]
    public function castsEnabledToBool(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => 1,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertTrue($provider->getState()->enabled);
    }

    #[Test]
    public function castsRetryAfterToInt(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'retryAfter' => '600',
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertSame(600, $provider->getState()->retryAfter);
    }

    #[Test]
    public function castsBypassTokenHashToString(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'bypassTokenHash' => 12345,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertSame('12345', $provider->getState()->bypassTokenHash);
    }

    #[Test]
    public function defaultsBypassTokenHashToEmptyStringWhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertSame('', $provider->getState()->bypassTokenHash);
    }

    #[Test]
    public function defaultsAllowedIpsToEmptyArrayWhenMissing(): void
    {
        file_put_contents($this->tmpFile, json_encode([
            'enabled' => true,
        ], JSON_THROW_ON_ERROR));

        $provider = new FileMaintenanceProvider($this->tmpFile);

        $this->assertSame([], $provider->getState()->allowedIps);
    }

    #[Test]
    public function implementsInterface(): void
    {
        $provider = new FileMaintenanceProvider('/nonexistent');

        $this->assertInstanceOf(MaintenanceProvider::class, $provider);
    }
}
