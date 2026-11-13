<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3MaintenanceMode\Redis;

use Predis\ClientInterface;
use Predis\Response\Status;
use Rasuvaeff\Yii3MaintenanceMode\MaintenanceRedisClient;
use RuntimeException;

/**
 * predis-backed {@see MaintenanceRedisClient}. Requires `predis/predis` (^2.2).
 *
 * @api
 */
final readonly class PredisMaintenanceClient implements MaintenanceRedisClient
{
    public function __construct(
        private ClientInterface $client,
    ) {}

    #[\Override]
    public function get(string $key): ?string
    {
        // createCommand/executeCommand are real ClientInterface methods; the
        // magic get()/set()/del() @method annotations are not resolvable by
        // psalm across every supported predis release.
        /** @var mixed $reply */
        $reply = $this->client->executeCommand(
            $this->client->createCommand('GET', [$key]),
        );

        if (is_string($reply)) {
            return $reply;
        }

        if ($reply === null) {
            return null;
        }

        throw new RuntimeException('Unexpected Redis reply for GET');
    }

    #[\Override]
    public function set(string $key, string $value): void
    {
        /** @var mixed $reply */
        $reply = $this->client->executeCommand(
            $this->client->createCommand('SET', [$key, $value]),
        );

        $payload = $reply instanceof Status ? $reply->getPayload() : $reply;

        if ($payload !== 'OK' && $payload !== true) {
            throw new RuntimeException('Unexpected Redis reply for SET');
        }
    }

    #[\Override]
    public function delete(string $key): void
    {
        /** @var mixed $reply */
        $reply = $this->client->executeCommand(
            $this->client->createCommand('DEL', [$key]),
        );

        if (!is_int($reply) && !(is_string($reply) && preg_match('/\A[0-9]+\z/', $reply) === 1)) {
            throw new RuntimeException('Unexpected Redis reply for DEL');
        }
    }
}
