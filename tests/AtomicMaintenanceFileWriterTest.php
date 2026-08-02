<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\AtomicMaintenanceFileWriter;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(AtomicMaintenanceFileWriter::class)]
final class AtomicMaintenanceFileWriterTest
{
    private string $filePath;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->filePath = sys_get_temp_dir() . '/maintenance_writer_' . uniqid() . '.json';
    }

    #[AfterTest]
    public function tearDown(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    public function writesStateReadableByProvider(): void
    {
        $hash = hash('sha256', 'secret');
        $writer = new AtomicMaintenanceFileWriter($this->filePath);
        $writer->write(new MaintenanceState(
            enabled: true,
            retryAfter: 900,
            allowedIps: ['10.0.0.0/8'],
            bypassTokenHash: $hash,
        ));

        $state = (new \Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider($this->filePath))->getState();

        Assert::true($state->enabled);
        Assert::same($state->retryAfter, 900);
        Assert::same($state->allowedIps, ['10.0.0.0/8']);
        Assert::same($state->bypassTokenHash, $hash);
    }

    public function overwritesStateAtomically(): void
    {
        $writer = new AtomicMaintenanceFileWriter($this->filePath);
        $writer->write(new MaintenanceState(enabled: true));
        $writer->write(new MaintenanceState(enabled: false));

        $state = (new \Rasuvaeff\Yii3MaintenanceMode\FileMaintenanceProvider($this->filePath))->getState();

        Assert::false($state->enabled);
    }

    public function writesExactPrettyPrintedJsonWithTrailingNewlineAndRestrictivePermissions(): void
    {
        $writer = new AtomicMaintenanceFileWriter($this->filePath);
        $writer->write(new MaintenanceState(
            enabled: true,
            retryAfter: 900,
            allowedIps: ['10.0.0.0/8'],
            bypassTokenHash: hash('sha256', 'secret'),
        ));

        $expectedJson = json_encode([
            'enabled' => true,
            'retryAfter' => 900,
            'allowedIps' => ['10.0.0.0/8'],
            'bypassTokenHash' => hash('sha256', 'secret'),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        Assert::same(file_get_contents($this->filePath), $expectedJson);
        Assert::same(fileperms($this->filePath) & 0o777, 0o600);
    }

    public function throwsAndCleansUpTemporaryFileWhenRenameFails(): void
    {
        mkdir($this->filePath);
        $tempFilePattern = dirname($this->filePath) . '/' . basename($this->filePath) . '.tmp-*';

        try {
            $writer = new AtomicMaintenanceFileWriter($this->filePath);

            set_error_handler(static fn(): bool => true);

            try {
                $writer->write(new MaintenanceState(enabled: true));
                Assert::fail('Expected a RuntimeException');
            } catch (RuntimeException $exception) {
                Assert::same($exception->getMessage(), 'Unable to replace maintenance file');
            } finally {
                restore_error_handler();
            }

            Assert::same(glob($tempFilePattern), []);
        } finally {
            rmdir($this->filePath);
        }
    }
}
