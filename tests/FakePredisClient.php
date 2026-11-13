<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Tests;

use Predis\Command\CommandInterface;
use Predis\Command\Redis\DEL;
use Predis\Command\Redis\GET;
use Predis\Command\Redis\SET;
use Predis\Response\Status;

/**
 * Scripts {@see \Predis\ClientInterface} replies: records every created
 * command and replays the queued reply for the matching verb.
 */
final class FakePredisClient implements \Predis\ClientInterface
{
    /** @var list<string> */
    public array $verbs = [];

    public mixed $getReply = null;

    public Status|bool|string $setReply = true;

    public int|string|float $delReply = 1;

    #[\Override]
    public function getCommandFactory(): \Predis\Command\Factory\CommandFactoryInterface
    {
        throw new \RuntimeException('Not implemented in fake');
    }

    #[\Override]
    public function getOptions(): \Predis\Configuration\OptionsInterface
    {
        throw new \RuntimeException('Not implemented in fake');
    }

    #[\Override]
    public function connect(): void {}

    #[\Override]
    public function disconnect(): void {}

    #[\Override]
    public function getConnection(): \Predis\Connection\ConnectionInterface
    {
        throw new \RuntimeException('Not implemented in fake');
    }

    #[\Override]
    public function createCommand($method, $arguments = []): CommandInterface
    {
        $this->verbs[] = strtoupper((string) $method);

        $command = match (strtoupper((string) $method)) {
            'GET' => new GET(),
            'SET' => new SET(),
            'DEL' => new DEL(),
            default => throw new \RuntimeException('Unexpected command ' . $method),
        };

        $command->setArguments(array_values($arguments));

        return $command;
    }

    #[\Override]
    public function executeCommand(CommandInterface $command): mixed
    {
        return match ($command->getId()) {
            'GET' => $this->getReply,
            'SET' => $this->setReply,
            'DEL' => $this->delReply,
            default => throw new \RuntimeException('Unexpected command'),
        };
    }

    #[\Override]
    public function __call($method, $arguments): mixed
    {
        throw new \RuntimeException('Magic calls are not expected');
    }
}
