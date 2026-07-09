<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Central Service: writes one audit-log document to MongoDB
 * (audit_log_documents) off the request/response cycle. Request-scoped
 * values (actor, ip) are captured by AuditLogger before dispatch since
 * they aren't available once this runs on the queue worker.
 */
class LogAuditEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly string $action,
        private readonly string $entity,
        private readonly ?string $entityId,
        private readonly array $meta,
        private readonly ?string $actorId,
        private readonly ?string $actorRole,
        private readonly ?string $ip,
        private readonly string $at,
    ) {}

    public function handle(): void
    {
        DB::connection('mongodb')->table('audit_log_documents')->insert([
            'action'     => $this->action,
            'entity'     => $this->entity,
            'entity_id'  => $this->entityId,
            'actor_id'   => $this->actorId,
            'actor_role' => $this->actorRole,
            'meta'       => $this->meta,
            'ip'         => $this->ip,
            'at'         => $this->at,
        ]);
    }
}
