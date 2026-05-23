<?php

namespace App\Console\Commands\Pilot;

use App\Models\Draw;
use App\Models\PilotDrawApproval;
use App\Services\Pilot\PilotEligibility;
use App\Services\PlatformAuditLogger;
use Illuminate\Console\Command;

/**
 * pilot:enable-draw {draw_id}
 *
 * Approve a draw for the canonical RR pilot and set engine_mode = canonical.
 * Performs full eligibility checks before enabling.
 *
 * Usage:
 *   php artisan pilot:enable-draw 42
 *   php artisan pilot:enable-draw 42 --force   (bypass eligibility — admin only)
 *   php artisan pilot:enable-draw 42 --notes="Trusted convenor, 8-player RR"
 */
class PilotEnableDrawCommand extends Command
{
    protected $signature = 'pilot:enable-draw
                            {draw_id : ID of the draw to approve for canonical RR pilot}
                            {--force : Skip eligibility checks (use with caution)}
                            {--notes= : Optional notes for the approval record}
                            {--email= : Approver email for audit trail}';

    protected $description = 'Approve a draw for the canonical RR pilot (sets engine_mode = canonical)';

    public function handle(): int
    {
        $drawId = (int) $this->argument('draw_id');
        $draw   = Draw::find($drawId);

        if (! $draw) {
            $this->error("[pilot:enable-draw] Draw #{$drawId} not found.");
            return self::FAILURE;
        }

        $this->info("[pilot:enable-draw] Checking draw #{$drawId}: {$draw->drawName}");

        // ── Eligibility check
        if (! $this->option('force')) {
            $result = PilotEligibility::check($draw);

            if (! empty($result['warnings'])) {
                foreach ($result['warnings'] as $w) {
                    $this->warn("  ⚠  {$w}");
                }
            }

            if (! $result['eligible']) {
                $this->error("[pilot:enable-draw] Draw is NOT eligible for the canonical RR pilot:");
                foreach ($result['reasons'] as $r) {
                    $this->error("  ✗  {$r}");
                }
                $this->line("  Use --force to override (admin only, logged).");
                return self::FAILURE;
            }

            $this->line("  ✓ Eligibility passed");
        } else {
            $this->warn("  [--force] Eligibility checks bypassed — this will be logged.");
        }

        // ── Already approved? Update player_count if stale
        if (PilotDrawApproval::isApproved($drawId)) {
            $approval    = PilotDrawApproval::where('draw_id', $drawId)->first();
            $liveCount   = $draw->registrations()->count();
            if ($liveCount === 0) {
                $liveCount = \DB::table('draw_group_registrations')
                    ->join('draw_groups', 'draw_groups.id', '=', 'draw_group_registrations.draw_group_id')
                    ->where('draw_groups.draw_id', $drawId)
                    ->distinct('draw_group_registrations.registration_id')
                    ->count('draw_group_registrations.registration_id');
            }
            if ($approval && $approval->player_count !== $liveCount) {
                $approval->update(['player_count' => $liveCount]);
                $this->info("[pilot:enable-draw] Updated player count to {$liveCount}.");
            }
            $this->warn("[pilot:enable-draw] Draw #{$drawId} is already approved. No other changes made.");
            return self::SUCCESS;
        }

        // ── Record approval
        $playerCount = $draw->registrations()->count();
        if ($playerCount === 0) {
            $playerCount = \DB::table('draw_group_registrations')
                ->join('draw_groups', 'draw_groups.id', '=', 'draw_group_registrations.draw_group_id')
                ->where('draw_groups.draw_id', $drawId)
                ->distinct('draw_group_registrations.registration_id')
                ->count('draw_group_registrations.registration_id');
        }
        $settings    = $draw->settings;

        PilotDrawApproval::create([
            'draw_id'            => $drawId,
            'event_id'           => $draw->event_id,
            'approved_by_email'  => $this->option('email'),
            'status'             => PilotDrawApproval::STATUS_APPROVED,
            'notes'              => $this->option('notes') . ($this->option('force') ? ' [--force used]' : ''),
            'player_count'       => $playerCount,
            'is_rr'              => $settings ? (bool) $settings->supports_boxes : false,
            'has_consolation'    => false,
            'has_feed_in'        => $settings ? ($settings->supports_playoff && ($settings->playoff_size ?? 0) > 0) : false,
            'is_national'        => false,
            'engine_mode_before' => $draw->engine_mode ?? 'hybrid',
            'approved_at'        => now(),
        ]);

        // ── Enable canonical mode on the draw
        $draw->update(['engine_mode' => 'canonical']);

        // ── Audit log
        PlatformAuditLogger::log(
            'draw.pilot_enabled',
            $draw,
            ['engine_mode' => $draw->engine_mode],
            ['engine_mode' => 'canonical'],
            [
                'source'   => 'pilot:enable-draw',
                'forced'   => $this->option('force') ? true : false,
                'approver' => $this->option('email'),
            ]
        );

        $this->info("  ✓ Draw #{$drawId} approved for canonical RR pilot.");
        $this->info("  ✓ engine_mode = canonical");
        $this->info("  Run: php artisan pilot:daily-audit  to monitor.");

        return self::SUCCESS;
    }
}
