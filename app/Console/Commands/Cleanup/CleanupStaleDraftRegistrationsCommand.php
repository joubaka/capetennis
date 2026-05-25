<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * data:cleanup-stale-draft-registrations
 *
 * Purges category_event_registrations rows that are:
 *   - status = 'active'
 *   - payment_status_id = 0   (never paid)
 *   - deleted_at IS NULL
 *   - created at least 24 hours ago (configurable via --hours)
 *
 * These rows are left behind when a player starts checkout but never
 * completes payment (browser close, PayFast cancel, etc.). They block
 * the player from attempting to register again.
 *
 * Safety rules:
 *   - Never touches payment_status_id = 1 rows.
 *   - Never touches withdrawn / cancelled rows.
 *   - Only hard-deletes rows that have no pf_transaction_id and no
 *     refund amounts (i.e. truly zero-financial-impact rows).
 *   - --dry-run previews without mutating.
 *   - --confirm required for actual deletion.
 *
 * Usage:
 *   php artisan data:cleanup-stale-draft-registrations --dry-run
 *   php artisan data:cleanup-stale-draft-registrations --dry-run --export=storage/cleanup/stale_drafts.csv
 *   php artisan data:cleanup-stale-draft-registrations --confirm --export=storage/cleanup/stale_drafts.csv
 *   php artisan data:cleanup-stale-draft-registrations --confirm --hours=1
 */
class CleanupStaleDraftRegistrationsCommand extends BaseCleanupCommand
{
    protected $signature = "data:cleanup-stale-draft-registrations
                            {--dry-run  : Preview only — no changes made}
                            {--confirm  : Required to apply any changes}
                            {--limit=0  : Cap number of rows processed}
                            {--export=  : Write affected rows to this CSV path}
                            {--hours=24 : Minimum age in hours before a draft is considered stale}";

    protected $description = "Hard-delete unpaid draft category_event_registration rows left by abandoned checkouts.";

    protected function scan(): iterable
    {
        $hours      = max(1, (int) ($this->option('hours') ?? 24));
        $cutoff     = Carbon::now()->subHours($hours);

        return DB::table('category_event_registrations')
            ->where(fn ($q) => $q
                ->where('payment_status_id', 0)
                ->orWhereNull('payment_status_id')
            )
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNull('pf_transaction_id')
            ->where(fn ($q) => $q->whereNull('refund_gross')->orWhere('refund_gross', 0))
            ->where(fn ($q) => $q
                ->whereNull('created_at')           // NULL created_at = definitely abandoned
                ->orWhere('created_at', '<=', $cutoff)
            )
            ->orderBy('id')
            ->get();
    }

    protected function fix(object $row): void
    {
        // Soft-delete: sets deleted_at so the row is invisible to all
        // SoftDeletes-aware queries but remains recoverable if needed.
        DB::table('category_event_registrations')
            ->where('id', $row->id)
            ->update([
                'deleted_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }

    protected function headers(): array
    {
        return ['id', 'category_event_id', 'registration_id', 'user_id', 'status', 'payment_status_id', 'created_at'];
    }

    protected function rowToCsv(object $row): array
    {
        return [
            $row->id,
            $row->category_event_id,
            $row->registration_id,
            $row->user_id,
            $row->status,
            $row->payment_status_id,
            $row->created_at,
        ];
    }

    public function handle(): int
    {
        return $this->runCleanup('Stale Draft CER');
    }
}
