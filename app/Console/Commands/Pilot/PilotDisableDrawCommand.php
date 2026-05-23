<?php

namespace App\Console\Commands\Pilot;

use App\Models\Draw;
use App\Models\PilotDrawApproval;
use App\Services\PlatformAuditLogger;
use Illuminate\Console\Command;

/**
 * pilot:disable-draw {draw_id}
 *
 * Revoke a draw's canonical RR pilot approval and downgrade to hybrid.
 *
 * Usage:
 *   php artisan pilot:disable-draw 42
 *   php artisan pilot:disable-draw 42 --reason="Too many fallbacks"
 */
class PilotDisableDrawCommand extends Command
{
    protected $signature = 'pilot:disable-draw
                            {draw_id : ID of the draw to revoke from canonical RR pilot}
                            {--reason= : Reason for revocation (for audit trail)}';

    protected $description = 'Revoke a draw from the canonical RR pilot (sets engine_mode = hybrid)';

    public function handle(): int
    {
        $drawId = (int) $this->argument('draw_id');
        $draw   = Draw::find($drawId);

        if (! $draw) {
            $this->error("[pilot:disable-draw] Draw #{$drawId} not found.");
            return self::FAILURE;
        }

        $reason  = $this->option('reason') ?? 'Manual revocation via pilot:disable-draw';
        $approval = PilotDrawApproval::where('draw_id', $drawId)
            ->where('status', PilotDrawApproval::STATUS_APPROVED)
            ->first();

        if (! $approval && $draw->engine_mode !== 'canonical') {
            $this->warn("[pilot:disable-draw] Draw #{$drawId} is not approved or canonical. Nothing to do.");
            return self::SUCCESS;
        }

        // ── Revoke approval record
        if ($approval) {
            $approval->update([
                'status'     => PilotDrawApproval::STATUS_REVOKED,
                'revoked_at' => now(),
                'notes'      => $approval->notes . ' | Revoked: ' . $reason,
            ]);
            $this->line("  ✓ Approval record revoked.");
        }

        // ── Downgrade engine mode
        if ($draw->engine_mode === 'canonical') {
            $draw->update(['engine_mode' => 'hybrid']);
            $this->line("  ✓ engine_mode downgraded to hybrid.");
        }

        // ── Audit log
        PlatformAuditLogger::log(
            'draw.pilot_disabled',
            $draw,
            ['engine_mode' => 'canonical'],
            ['engine_mode' => 'hybrid'],
            [
                'source' => 'pilot:disable-draw',
                'reason' => $reason,
            ]
        );

        $this->info("[pilot:disable-draw] Draw #{$drawId} ({$draw->drawName}) revoked from canonical pilot.");

        return self::SUCCESS;
    }
}
