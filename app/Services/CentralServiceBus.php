<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Publishes async work to the separate central-service repo over a shared
 * Redis list (the 'bus' connection — see config/database.php). Payload keys
 * must match the target job's constructor parameter names exactly, since
 * central-service dispatches via named-argument unpacking (see its
 * RelayBusJobs command) — this is a plain JSON contract, not a shared PHP
 * class, so the two codebases can evolve independently.
 */
class CentralServiceBus
{
    private const QUEUE_KEY = 'central-service:jobs';

    public function publish(string $type, array $payload): void
    {
        Redis::connection('bus')->rpush(self::QUEUE_KEY, json_encode([
            'type' => $type,
            'payload' => $payload,
        ]));
    }
}
