<?php
// app/Models/Concerns/LogsActivity.php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/**
 * Records created / updated / deleted events for a model in activity_logs.
 * Secrets (passwords, tokens) are always stripped before anything is stored.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->writeActivity('created', $model->cleanForLog($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->cleanForLog($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);
            $model->writeActivity('updated', ['old' => $model->cleanForLog($old), 'new' => $changes]);
        });

        static::deleted(fn ($model) => $model->writeActivity('deleted', []));
    }

    private function writeActivity(string $action, array $changes): void
    {
        $label = class_basename($this) . ' #' . $this->getKey();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => class_basename($this),
            'subject_id' => $this->getKey(),
            'description' => "{$label} {$action}",
            'changes' => $changes ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    private function cleanForLog(array $attributes): array
    {
        $secret = array_merge($this->getHidden(), ['password', 'remember_token', 'updated_at', 'created_at']);

        return array_diff_key($attributes, array_flip($secret));
    }
}
