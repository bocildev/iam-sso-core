<?php

namespace App\Domain\Audit;

use Illuminate\Support\Facades\DB;

/** Append-only: hanya ada metode insert. */
class AuditLogger
{
    public function log(
        string $action,
        ?string $actorType = null,
        string|int|null $actorId = null,
        ?string $target = null,
        string $result = 'success',
        string $severity = 'info',
        array $meta = [],
    ): void {
        $request = app()->bound('request') ? request() : null;

        DB::table('audit_logs')->insert([
            'action' => $action,
            'actor_type' => $actorType,
            'actor_id' => $actorId === null ? null : (string) $actorId,
            'target' => $target,
            'result' => $result,
            'severity' => $severity,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 250) : null,
            'meta' => $meta ? json_encode($meta) : null,
            'created_at' => now(),
        ]);
    }
}
