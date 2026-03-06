<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Auditable Trait — Automatically logs create, update, delete, restore events.
 *
 * Usage: Add `use Auditable;` to any Eloquent model.
 *
 * This trait hooks into Eloquent model events (created, updated, deleted, restored)
 * and records an audit log entry with old/new values.
 *
 * You can customize which fields are excluded from logging by setting
 * `$auditExclude` on the model:
 *
 *     protected array $auditExclude = ['password', 'remember_token'];
 */
trait Auditable
{
    /**
     * Boot the trait — register model event listeners.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->logAudit('created', null, $model->getAuditableAttributes());
        });

        static::updated(function (Model $model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) return;

            $oldValues = [];
            $newValues = [];

            foreach ($dirty as $key => $newValue) {
                if ($model->isExcludedFromAudit($key)) continue;

                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $newValue;
            }

            if (!empty($newValues)) {
                $model->logAudit('updated', $oldValues, $newValues);
            }
        });

        static::deleted(function (Model $model) {
            $model->logAudit('deleted', $model->getAuditableAttributes(), null);
        });

        // If model uses SoftDeletes, also track restores
        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->logAudit('restored', null, $model->getAuditableAttributes());
            });
        }
    }

    /**
     * Get the audit log entries for this model.
     */
    public function auditLogs()
    {
        return AuditLog::where('auditable_type', get_class($this))
            ->where('auditable_id', $this->getKey())
            ->orderBy('created_at', 'desc');
    }

    /**
     * Write an audit log record.
     */
    protected function logAudit(string $action, ?array $oldValues, ?array $newValues): void
    {
        try {
            AuditLog::create([
                'user_id'         => auth()->id(),
                'action'          => $action,
                'auditable_type'  => get_class($this),
                'auditable_id'    => $this->getKey(),
                'old_values'      => $oldValues,
                'new_values'      => $newValues,
                'ip_address'      => request()?->ip(),
                'user_agent'      => request()?->userAgent(),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // Silently fail audit logging so it never breaks business logic
            Log::warning('Audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Get attributes safe for audit logging.
     */
    protected function getAuditableAttributes(): array
    {
        $attributes = $this->attributesToArray();

        foreach ($this->getAuditExcludedFields() as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }

    /**
     * Check if a field should be excluded from audit logging.
     */
    protected function isExcludedFromAudit(string $field): bool
    {
        return in_array($field, $this->getAuditExcludedFields());
    }

    /**
     * Get fields excluded from audit logging.
     */
    protected function getAuditExcludedFields(): array
    {
        return property_exists($this, 'auditExclude')
            ? $this->auditExclude
            : ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    }
}
