<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Rasuvaeff\Yii3MaintenanceMode\Redis\PredisMaintenanceClient;
use RuntimeException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(PredisMaintenanceClient::class)]
final class PredisMaintenanceClientTest
{
    public function getReturnsStringValue(): void
    {
        $client = new FakePredisClient();
        $client->getReply = '{"enabled":true}';

        Assert::same((new PredisMaintenanceClient($client))->get('maintenance:state'), '{"enabled":true}');
        Assert::same($client->verbs, ['GET']);
    }

    public function getReturnsNullOnMissingKey(): void
    {
        $client = new FakePredisClient();
        $client->getReply = null;

        Assert::null((new PredisMaintenanceClient($client))->get('maintenance:state'));
    }

    public function getThrowsOnUnexpectedReply(): void
    {
        $client = new FakePredisClient();
        $client->getReply = 42;

        try {
            (new PredisMaintenanceClient($client))->get('maintenance:state');
            Assert::fail('Expected a RuntimeException');
        } catch (RuntimeException $exception) {
            Assert::same($exception->getMessage(), 'Unexpected Redis reply for GET');
        }
    }

    public function setAcceptsOkStatus(): void
    {
        $client = new FakePredisClient();
        $client->setReply = new \Predis\Response\Status('OK');

        (new PredisMaintenanceClient($client))->set('maintenance:state', '{}');

        Assert::same($client->verbs, ['SET']);
    }

    public function setAcceptsTrueReply(): void
    {
        $client = new FakePredisClient();
        $client->setReply = true;

        (new PredisMaintenanceClient($client))->set('maintenance:state', '{}');

        Assert::same($client->verbs, ['SET']);
    }

    public function setThrowsOnUnexpectedReply(): void
    {
        $client = new FakePredisClient();
        $client->setReply = 'QUEUED';

        try {
            (new PredisMaintenanceClient($client))->set('k', 'v');
            Assert::fail('Expected a RuntimeException');
        } catch (RuntimeException $exception) {
            Assert::same($exception->getMessage(), 'Unexpected Redis reply for SET');
        }
    }

    public function deleteAcceptsIntegerReply(): void
    {
        $client = new FakePredisClient();
        $client->delReply = 1;

        (new PredisMaintenanceClient($client))->delete('maintenance:state');

        Assert::same($client->verbs, ['DEL']);
    }

    public function deleteAcceptsNumericStringReply(): void
    {
        $client = new FakePredisClient();
        $client->delReply = '0';

        (new PredisMaintenanceClient($client))->delete('maintenance:state');

        Assert::same($client->verbs, ['DEL']);
    }

    public function deleteThrowsOnNonNumericStringReply(): void
    {
        $client = new FakePredisClient();
        $client->delReply = 'x1';

        try {
            (new PredisMaintenanceClient($client))->delete('k');
            Assert::fail('Expected a RuntimeException');
        } catch (RuntimeException $exception) {
            Assert::same($exception->getMessage(), 'Unexpected Redis reply for DEL');
        }
    }

    public function deleteThrowsOnUnexpectedReplyType(): void
    {
        $client = new FakePredisClient();
        $client->delReply = 1.5;

        try {
            (new PredisMaintenanceClient($client))->delete('k');
            Assert::fail('Expected a RuntimeException');
        } catch (RuntimeException $exception) {
            Assert::same($exception->getMessage(), 'Unexpected Redis reply for DEL');
        }
    }
}
