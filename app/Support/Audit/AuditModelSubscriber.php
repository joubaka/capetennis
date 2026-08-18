<?php

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditModelSubscriber
{
    public function __construct(private readonly AuditWriter $writer) {}

    public function creating(Model $model): void
    {
        $this->attempt($model, 'create-attempted', null, $model->getAttributes());
    }

    public function updating(Model $model): void
    {
        $dirty = $model->getDirty();
        $before = [];
        foreach (array_keys($dirty) as $attribute) {
            $before[$attribute] = $model->getRawOriginal($attribute);
        }
        $this->attempt($model, 'update-attempted', $before, $dirty);
    }

    public function deleting(Model $model): void
    {
        $this->attempt($model, 'delete-attempted', $model->getAttributes(), null);
    }

    public function restoring(Model $model): void
    {
        $this->attempt($model, 'restore-attempted', null, $model->getAttributes());
    }

    public function created(Model $model): void
    {
        $this->write($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if ($changes === []) {
            return;
        }

        $previous = method_exists($model, 'getPrevious') ? $model->getPrevious() : [];
        $before = [];
        foreach (array_keys($changes) as $attribute) {
            $before[$attribute] = $previous[$attribute] ?? $model->getRawOriginal($attribute);
        }

        $this->write($model, 'updated', $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', $model->getAttributes(), null, true);
    }

    public function restored(Model $model): void
    {
        $this->write($model, 'restored', null, $model->getAttributes(), true);
    }

    private function write(Model $model, string $operation, ?array $before, ?array $after, bool $critical = false): void
    {
        if ($this->excluded($model)) {
            return;
        }

        $this->writer->record([
            'category' => 'data',
            'action' => Str::kebab(class_basename($model)).'.'.$operation,
            'subject' => $model,
            'before' => $before,
            'after' => $after,
            'metadata' => ['table' => $model->getTable(), 'operation' => $operation],
        ], $critical);
    }

    private function attempt(Model $model, string $operation, ?array $before, ?array $after): void
    {
        if ($this->excluded($model)) {
            return;
        }

        $this->writer->record([
            'category' => 'data',
            'action' => Str::kebab(class_basename($model)).'.'.$operation,
            'outcome' => 'attempted',
            'subject' => $model,
            'before' => $before,
            'after' => $after,
            'metadata' => ['table' => $model->getTable(), 'operation' => $operation],
        ], true);
    }

    private function excluded(Model $model): bool
    {
        foreach (config('audit.excluded_models', []) as $class) {
            if ($model instanceof $class) {
                return true;
            }
        }

        return in_array($model->getTable(), [
            'audit_events', 'activity_log', 'authentication_log', 'sessions',
            'jobs', 'failed_jobs', 'cache', 'cache_locks',
        ], true);
    }
}
