<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\DefaultMaintenanceResponseFactory;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceResponseFactory;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceState;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(DefaultMaintenanceResponseFactory::class)]
final class DefaultMaintenanceResponseFactoryTest
{
    public function implementsMaintenanceResponseFactory(): void
    {
        Assert::instanceOf(new DefaultMaintenanceResponseFactory(new FakeResponseFactory()), MaintenanceResponseFactory::class);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    #[DataProvider('responseProvider')]
    public function negotiatesResponse(array $headers, string $expectedContentType): void
    {
        $factory = new DefaultMaintenanceResponseFactory(new FakeResponseFactory());

        $response = $factory->createResponse(new FakeRequest(headers: $headers), new MaintenanceState(retryAfter: 120));

        Assert::same($response->getStatusCode(), 503);
        Assert::same($response->getHeaderLine('Content-Type'), $expectedContentType);
        Assert::same($response->getHeaderLine('Retry-After'), '120');
        Assert::same($response->getHeaderLine('Cache-Control'), 'no-store');
    }

    /** @return array<string, array{headers: array<string, list<string>>, expectedContentType: string}> */
    public static function responseProvider(): array
    {
        return [
            'default json' => [
                'headers' => [],
                'expectedContentType' => 'application/json',
            ],
            'problem json' => [
                'headers' => ['accept' => ['application/problem+json']],
                'expectedContentType' => 'application/json',
            ],
            'html preferred' => [
                'headers' => ['accept' => ['application/json;q=0.2, text/html;q=0.8']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
        ];
    }
}
