<?php

namespace App\Support\Audit;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

class AuditQueryListener
{
    private static bool $writing = false;

    public function __construct(
        private readonly AuditContext $context,
        private readonly AuditWriter $writer,
    ) {}

    public function handle(QueryExecuted $query): void
    {
        if (self::$writing || ! config('audit.enabled', true)) {
            return;
        }

        $sql = ltrim($query->sql);
        // Never issue another INSERT from an INSERT query callback on the same
        // connection: that would change MySQL's LAST_INSERT_ID before Eloquent
        // reads it. Creates are covered by model and request audit events.
        if (! preg_match('/^(update|delete\s+from)\s+[`\"]?([A-Za-z0-9_]+)/i', $sql, $matches)) {
            return;
        }

        $table = strtolower($matches[2]);
        if (in_array($table, [
            'audit_events', 'audit_daily_seals', 'activity_log', 'authentication_log',
            'sessions', 'jobs', 'failed_jobs', 'cache', 'cache_locks',
        ], true)) {
            return;
        }

        $operation = str_starts_with(strtolower($matches[1]), 'delete') ? 'deleted' : 'updated';

        self::$writing = true;
        try {
            $this->writer->record([
                'category' => 'database',
                'action' => "database.{$table}.{$operation}",
                'subject_type' => $table,
                'metadata' => [
                    'sql_template' => $this->safeSqlTemplate($query->sql),
                    'binding_count' => count($query->bindings),
                    'duration_ms' => $query->time,
                    'connection' => $query->connectionName,
                ],
            ]);
        } finally {
            self::$writing = false;
        }
    }

    private function safeSqlTemplate(string $sql): string
    {
        $sql = preg_replace("/'(?:''|[^'])*'/", '?', $sql) ?? $sql;
        $sql = preg_replace('/\b0x[0-9a-f]+\b/i', '?', $sql) ?? $sql;
        $sql = preg_replace('/(?<![A-Za-z0-9_])[-+]?\d+(?:\.\d+)?(?![A-Za-z0-9_])/', '?', $sql) ?? $sql;
        return Str::limit($sql, 4000, '…');
    }
}
