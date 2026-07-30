<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Predis\PredisException;

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

    /**
     * The primary Postgres write this accompanies has already succeeded by
     * the time callers reach this — a Redis outage here (audit trail, Mongo
     * version sync, report generation) must never turn into a 500 for a
     * clinician mid-request, so failures are logged and swallowed rather
     * than thrown.
     */
    public function publish(string $type, array $payload): void
    {
        try {
            Redis::connection('bus')->rpush(self::QUEUE_KEY, json_encode([
                'type' => $type,
                'payload' => $payload,
            ]));
        } catch (PredisException $e) {
            Log::warning('central-service.bus: publish failed, message dropped', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
