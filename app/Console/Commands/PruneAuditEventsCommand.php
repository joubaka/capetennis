<?php

namespace App\Console\Commands;

use App\Support\Audit\AuditWriter;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PruneAuditEventsCommand extends Command
{
    protected $signature = 'audit:prune {--apply : Permanently remove records beyond configured retention}';
    protected $description = 'Report or apply the configured tiered audit-event retention policy';

    public function handle(AuditWriter $writer): int
    {
        $tiers = [
            'journey' => [
                'days' => (int) config('audit.retention.journey_days', 180),
                'query' => fn () => DB::table('audit_events')->whereIn('category', ['navigation', 'interaction', 'request']),
            ],
            'security' => [
                'days' => (int) config('audit.retention.security_days', 730),
                'query' => fn () => DB::table('audit_events')->where('category', 'security'),
            ],
            'business' => [
                'days' => (int) config('audit.retention.business_days', 2555),
                'query' => fn () => DB::table('audit_events')->whereNotIn('category', ['navigation', 'interaction', 'request', 'security']),
            ],
        ];

        $summary = [];
        foreach ($tiers as $name => $tier) {
            /** @var Builder $query */
            $query = $tier['query']()->where('occurred_at', '<', now()->subDays($tier['days']));
            $count = (clone $query)->count();
            $summary[$name] = ['days' => $tier['days'], 'records' => $count];
            $this->line(ucfirst($name).": {$count} records older than {$tier['days']} days.");

            if ($this->option('apply') && $count > 0) {
                $query->orderBy('id')->chunkById(1000, function ($events): void {
                    DB::table('audit_events')->whereIn('id', $events->pluck('id'))->delete();
                }, 'id');
            }
        }

        if ($this->option('apply')) {
            $writer->record([
                'category' => 'security',
                'action' => 'audit.retention-pruned',
                'source' => 'console',
                'metadata' => $summary,
            ], true);
            $this->info('Audit retention applied.');
        } else {
            $this->warn('Dry run only. Re-run with --apply to prune these records.');
        }

        return self::SUCCESS;
    }
}
