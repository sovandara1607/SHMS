<?php

namespace App\Services;

use App\Jobs\LogAuditEventJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Queues audit-log documents for MongoDB (collection: audit_log_documents)
 * via the Central Service's queue worker, so writing the tamper-evident
 * trail never adds latency to (or risks failing) the primary request.
 */
class AuditLogger
{
    public function log(string $action, string $entity, ?string $entityId = null, array $meta = []): void
    {
        $user = Auth::user();

        LogAuditEventJob::dispatch(
            $action,
            $entity,
            $entityId,
            $meta,
            $user?->user_id,
            $user?->role,
            Request::ip(),
            now()->toIso8601String(),
        );
    }
}
