<?php

namespace App\Console\Commands\Pilot;

use App\Services\Pilot\PilotReadinessMatrix;
use App\Models\PilotDrawApproval;
use Illuminate\Console\Command;

/**
 * pilot:readiness
 *
 * Prints the pilot readiness matrix across all five domains.
 * Domains are evaluated independently — never combined.
 *
 * Usage:
 *   php artisan pilot:readiness
 */
class PilotReadinessCommand extends Command
{
    protected $signature   = 'pilot:readiness';
    protected $description = 'Print the canonical engine readiness matrix (per-domain, not combined)';

    private array $levelColors = [
        PilotReadinessMatrix::LEVEL_READY        => "\033[32m",  // green
        PilotReadinessMatrix::LEVEL_IN_PROGRESS  => "\033[33m",  // yellow
        PilotReadinessMatrix::LEVEL_NOT_STARTED  => "\033[90m",  // grey
        PilotReadinessMatrix::LEVEL_BLOCKED      => "\033[31m",  // red
    ];

    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════════╗');
        $this->info('║     Cape Tennis — Engine Readiness Matrix                       ║');
        $this->info('║     Each domain is independent — do not combine readiness       ║');
        $this->info('╚══════════════════════════════════════════════════════════════════╝');
        $this->info('');

        $matrix = PilotReadinessMatrix::compute();

        $domainLabels = [
            PilotReadinessMatrix::DOMAIN_RR_CANONICAL      => 'RR Canonical Engine',
            PilotReadinessMatrix::DOMAIN_PLAYOFF_CANONICAL => 'Playoff Canonical Engine',
            PilotReadinessMatrix::DOMAIN_FEED_IN           => 'Feed-in / Consolation',
            PilotReadinessMatrix::DOMAIN_SCHEDULING        => 'Scheduling (OOP)',
            PilotReadinessMatrix::DOMAIN_RENDERING         => 'Rendering (Bracket/Standings)',
        ];

        foreach ($matrix as $domain => $data) {
            $label  = $domainLabels[$domain] ?? $domain;
            $level  = strtoupper($data['level']);
            $color  = $this->levelColors[$data['level']] ?? '';
            $reset  = $color ? "\033[0m" : '';

            $this->line("  ┌─── {$label}");
            $this->line("  │  Status: {$color}{$level}{$reset}");

            foreach ($data['notes'] as $note) {
                $this->line("  │  {$note}");
            }

            if (! empty($data['metrics'])) {
                $metricStr = collect($data['metrics'])
                    ->map(fn($v, $k) => "{$k}={$v}")
                    ->implode('  ');
                $this->line("  │  [{$metricStr}]");
            }

            $this->line('  └' . str_repeat('─', 40));
            $this->info('');
        }

        // Summary
        $rrLevel = $matrix[PilotReadinessMatrix::DOMAIN_RR_CANONICAL]['level'];
        $this->info('  Summary:');
        $this->line("  • RR Canonical:       {$rrLevel}");
        $this->line("  • Playoff Canonical:  not_started  (scope: future)");
        $this->line("  • Feed-in:            not_started  (scope: future)");

        $approvedCount = PilotDrawApproval::approved()->count();
        $revokedCount  = PilotDrawApproval::where('status', 'revoked')->count();
        $this->info('');
        $this->line("  Approved pilot draws: {$approvedCount}   Revoked: {$revokedCount}");
        $this->info('');

        return self::SUCCESS;
    }
}
