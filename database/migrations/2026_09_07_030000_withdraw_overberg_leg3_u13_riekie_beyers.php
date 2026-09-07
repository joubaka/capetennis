<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $requiredTables = [
            'events',
            'category_events',
            'draws',
            'fixtures',
            'category_event_registrations',
            'category_results',
            'player_registrations',
            'players',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        DB::transaction(function (): void {
            $draw = DB::table('draws')
                ->join('events', 'events.id', '=', 'draws.event_id')
                ->where('events.id', 233)
                ->whereRaw('LOWER(TRIM(draws.drawName)) = ?', ['u/13 boys'])
                ->first(['draws.id', 'draws.category_event_id', 'events.name as event_name']);

            if (! $draw) {
                return;
            }

            if (stripos((string) $draw->event_name, 'Overberg Tennis Trials') === false
                || stripos((string) $draw->event_name, 'Leg 3') === false) {
                throw new RuntimeException('Event 233 is not the expected Overberg Tennis Trials Leg 3 event.');
            }

            if (! $draw->category_event_id) {
                throw new RuntimeException('The U/13 Boys draw is not linked to a category event.');
            }

            $entry = DB::table('category_event_registrations as cer')
                ->join('player_registrations as pr', 'pr.registration_id', '=', 'cer.registration_id')
                ->join('players as p', 'p.id', '=', 'pr.player_id')
                ->where('cer.category_event_id', $draw->category_event_id)
                ->whereRaw("LOWER(TRIM(CONCAT(COALESCE(p.name, ''), ' ', COALESCE(p.surname, '')))) = ?", ['riekie beyers'])
                ->lockForUpdate()
                ->first([
                    'cer.id',
                    'cer.registration_id',
                    'cer.status',
                    'cer.withdrawn_at',
                    'cer.withdrawal_reason',
                    'cer.refund_status',
                    'cer.refund_gross',
                    'cer.refund_fee',
                    'cer.refund_net',
                    'cer.refunded_at',
                ]);

            if (! $entry) {
                throw new RuntimeException('Riekie Beyers was not found in the Overberg Leg 3 U/13 Boys entries.');
            }

            $hasRefund = ! in_array($entry->refund_status, [null, '', 'not_refunded'], true)
                || (float) $entry->refund_gross !== 0.0
                || (float) $entry->refund_fee !== 0.0
                || (float) $entry->refund_net !== 0.0
                || $entry->refunded_at !== null;

            if ($hasRefund) {
                throw new RuntimeException('Riekie Beyers has refund activity; the late-withdrawal correction requires financial review.');
            }

            $fixtureCount = DB::table('fixtures')
                ->where('draw_id', $draw->id)
                ->where(function ($query) use ($entry): void {
                    $query->where('registration1_id', $entry->registration_id)
                        ->orWhere('registration2_id', $entry->registration_id);
                })
                ->count();

            if ($fixtureCount !== 0) {
                throw new RuntimeException('Riekie Beyers appears in a Leg 3 fixture; no played history was changed.');
            }

            $categoryId = DB::table('category_events')
                ->where('id', $draw->category_event_id)
                ->where('event_id', 233)
                ->value('category_id');

            if (! $categoryId) {
                throw new RuntimeException('The U/13 Boys category link does not belong to event 233.');
            }

            $result = DB::table('category_results')
                ->where('event_id', 233)
                ->where('category_id', $categoryId)
                ->where('registration_id', $entry->registration_id)
                ->lockForUpdate()
                ->first(['id', 'position']);

            if ($result && (int) $result->position !== 4) {
                throw new RuntimeException('Riekie Beyers is not in the expected fourth position; no result was removed.');
            }

            $remainingPositions = DB::table('category_results')
                ->where('event_id', 233)
                ->where('category_id', $categoryId)
                ->where('registration_id', '<>', $entry->registration_id)
                ->orderBy('position')
                ->pluck('position')
                ->map(static fn ($position): int => (int) $position)
                ->all();

            if ($remainingPositions !== [1, 2, 3]) {
                throw new RuntimeException('The remaining U/13 Boys published positions are not the expected 1, 2 and 3.');
            }

            $reason = 'Late withdrawal confirmed after the event; player did not compete and receives no ranking points for Leg 3.';

            DB::table('category_event_registrations')->where('id', $entry->id)->update([
                'status' => 'withdrawn',
                'withdrawn_at' => $entry->withdrawn_at ?: now(),
                'withdrawal_reason' => $reason,
                'refund_status' => 'not_refunded',
                'updated_at' => now(),
            ]);

            if ($result) {
                DB::table('category_results')->where('id', $result->id)->delete();
            }

            if (Schema::hasTable('draw_audit_logs')) {
                DB::table('draw_audit_logs')->updateOrInsert(
                    [
                        'draw_id' => (int) $draw->id,
                        'fixture_id' => null,
                        'action' => 'late_withdrawal_result_removed',
                    ],
                    [
                        'user_id' => null,
                        'payload' => json_encode([
                            'category_event_registration_id' => (int) $entry->id,
                            'registration_id' => (int) $entry->registration_id,
                            'player' => 'Riekie Beyers',
                            'previous_position' => $result ? (int) $result->position : 4,
                            'reason' => $reason,
                            'refund_status' => 'not_refunded',
                            'ranking_rebuild_required' => true,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        // This records a real historical late withdrawal and removes an
        // erroneous official result. Rollback must not recreate either state.
    }
};
