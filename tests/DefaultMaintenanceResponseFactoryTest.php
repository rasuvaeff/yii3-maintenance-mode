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

    public function jsonBodyContainsExactErrorMessageAndRetryAfter(): void
    {
        $factory = new DefaultMaintenanceResponseFactory(new FakeResponseFactory());

        $response = $factory->createResponse(new FakeRequest(), new MaintenanceState(retryAfter: 120));
        $body = $response->getBody();
        $body->rewind();

        Assert::same(
            json_decode($body->getContents(), true, 512, JSON_THROW_ON_ERROR),
            [
                'error' => 'Service Unavailable',
                'message' => 'The server is currently undergoing maintenance.',
                'retryAfter' => 120,
            ],
        );
    }

    public function htmlBodyContainsTheMaintenancePage(): void
    {
        $factory = new DefaultMaintenanceResponseFactory(new FakeResponseFactory());

        $response = $factory->createResponse(new FakeRequest(headers: ['accept' => ['text/html']]), new MaintenanceState());
        $body = $response->getBody();
        $body->rewind();

        Assert::string($body->getContents())->contains('<html');
        Assert::string($body->getContents())->contains('Maintenance');
    }

    /**
     * @param array<string, list<string>> $headers
     */
    #[DataProvider('prefersJsonProvider')]
    public function prefersJsonDecidesCorrectlyForEdgeCaseAcceptHeaders(array $headers, string $expectedContentType): void
    {
        $factory = new DefaultMaintenanceResponseFactory(new FakeResponseFactory());

        $response = $factory->createResponse(new FakeRequest(headers: $headers), new MaintenanceState());

        Assert::same($response->getHeaderLine('Content-Type'), $expectedContentType);
    }

    /** @return array<string, array{headers: array<string, list<string>>, expectedContentType: string}> */
    public static function prefersJsonProvider(): array
    {
        return [
            'unrecognized accept falls back to json' => [
                'headers' => ['accept' => ['text/plain']],
                'expectedContentType' => 'application/json',
            ],
            'explicit json quality outranks an absent html quality' => [
                'headers' => ['accept' => ['application/json;q=0.3']],
                'expectedContentType' => 'application/json',
            ],
            'zero quality tie between explicit json and html favors html' => [
                'headers' => ['accept' => ['application/json;q=0, text/html;q=0']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
            'nonzero quality tie favors json' => [
                'headers' => ['accept' => ['application/json;q=0.5, text/html;q=0.5']],
                'expectedContentType' => 'application/json',
            ],
            'accept media type matching is case-insensitive' => [
                'headers' => ['accept' => ['TEXT/HTML']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
            'leading whitespace before the quality parameter is trimmed' => [
                'headers' => ['accept' => ['text/html ; q=0.5']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
            'leading whitespace inside a quality parameter is trimmed' => [
                'headers' => ['accept' => ['application/json;  q=0.3, text/html;q=0.9']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
            'a malformed quality value with an extra equals sign is ignored' => [
                'headers' => ['accept' => ['application/json;q=0.3=bogus, text/html;q=0.5']],
                'expectedContentType' => 'application/json',
            ],
            'the quality parameter name is matched case-insensitively' => [
                'headers' => ['accept' => ['application/json;Q=0.3, text/html;q=0.9']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
            'an unrelated parameter never sets the quality' => [
                'headers' => ['accept' => ['application/json;level=0.3, text/html']],
                'expectedContentType' => 'application/json',
            ],
            'application/xml is not a json media type' => [
                'headers' => ['accept' => ['application/xml, text/html;q=0.9']],
                'expectedContentType' => 'text/html; charset=utf-8',
            ],
            'a +json vendor media type is recognized as json' => [
                'headers' => ['accept' => ['application/vnd.api+json, text/html;q=0.9']],
                'expectedContentType' => 'application/json',
            ],
            'exact application/json is recognized over competing html' => [
                'headers' => ['accept' => ['application/json, text/html;q=0.9']],
                'expectedContentType' => 'application/json',
            ],
            'a positive-quality wildcard outranks an explicit zero-quality json' => [
                'headers' => ['accept' => ['application/json;q=0, */*;q=0.5']],
                'expectedContentType' => 'application/json',
            ],
        ];
    }
}
