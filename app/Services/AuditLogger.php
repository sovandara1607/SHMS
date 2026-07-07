<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Writes audit-log documents to MongoDB (collection: audit_log_documents).
 * Important state-changing actions call this so there is a tamper-evident
 * trail independent of the relational tables.
 */
class AuditLogger
{
    public function log(string $action, string $entity, ?string $entityId = null, array $meta = []): void
    {
        $user = Auth::user();
        try {
            DB::connection('mongodb')->table('audit_log_documents')->insert([
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId,
                'actor_id'   => $user?->user_id,
                'actor_role' => $user?->role,
                'meta'       => $meta,
                'ip'         => Request::ip(),
                'at'         => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the primary request; log and continue.
            logger()->warning('Audit log write failed: ' . $e->getMessage());
        }
    }
}
