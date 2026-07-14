<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Publishes audit-log documents for MongoDB (collection: audit_log_documents)
 * to the separate central-service repo over the shared Redis bus, so writing
 * the tamper-evident trail never adds latency to (or risks failing) the
 * primary request.
 */
class AuditLogger
{
    public function __construct(private readonly CentralServiceBus $bus) {}

    public function log(string $action, string $entity, ?string $entityId = null, array $meta = []): void
    {
        $user = Auth::user();

        $this->bus->publish('log_audit_event', [
            'action' => $action,
            'entity' => $entity,
            'entityId' => $entityId,
            'meta' => $meta,
            'actorId' => $user?->user_id,
            'actorRole' => $user?->role,
            'ip' => Request::ip(),
            'at' => now()->toIso8601String(),
        ]);
    }
}
