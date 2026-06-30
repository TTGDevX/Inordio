<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Records a trail (who/what/when) for sensitive models. Add `use Auditable;`
 * to a model and create/update/delete events are written to audit_logs.
 * Tenant scoping is handled by AuditLog's BelongsToTenant. Number-assignment
 * via saveQuietly() (see booted() hooks) does not fire events, so it isn't logged.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->recordAudit('created'));
        static::updated(fn ($model) => $model->recordAudit('updated', $model->getChanges()));
        static::deleted(fn ($model) => $model->recordAudit('deleted'));
    }

    public function recordAudit(string $action, array $changes = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'changes' => $changes !== [] ? $changes : null,
            'ip' => request()?->ip(),
        ]);
    }
}
