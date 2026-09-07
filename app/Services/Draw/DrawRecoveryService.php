<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\ScoreValidationService;
use App\Models\Draw;
use App\Models\DrawAuditLog;
use App\Models\DrawRecoveryCase;
use App\Models\Fixture;
use App\Services\DrawService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DrawRecoveryService
{
    public function __construct(
        private readonly DrawRecoveryImpactService $impact,
        private readonly DrawRecoverySnapshotService $snapshots,
        private readonly ScoreValidationService $scoreValidation,
        private readonly DrawService $draws,
    ) {}

    public function preview(Draw $draw, Fixture $source): array
    {
        return [
            'impact' => $this->impact->preview($draw, $source),
            'fingerprint' => $this->snapshots->checksum($draw),
        ];
    }

    public function apply(
        Draw $draw,
        Fixture $source,
        array $sets,
        string $reason,
        string $fingerprint,
    ): DrawRecoveryCase {
        $validation = $this->scoreValidation->validate($source, $sets);
        abort_unless($validation['valid'], 422, $validation['message']);

        return DB::transaction(function () use ($draw, $source, $sets, $reason, $fingerprint) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $source = Fixture::whereKey($source->id)->where('draw_id', $draw->id)->lockForUpdate()->firstOrFail();
            $this->lockCompetitionRows($draw);
            abort_unless(hash_equals($this->snapshots->checksum($draw), $fingerprint), 409,
                'The draw changed after the recovery preview. Generate a fresh preview before applying it.');

            $previousSets = DB::table('fixture_results')
                ->where('fixture_id', $source->id)
                ->orderBy('set_nr')
                ->get(['set_nr', 'registration1_score', 'registration2_score'])
                ->map(fn ($set): array => [
                    'set_nr' => (int) $set->set_nr,
                    'registration1_score' => (int) $set->registration1_score,
                    'registration2_score' => (int) $set->registration2_score,
                ])
                ->all();
            $correctedSets = collect($sets)
                ->values()
                ->map(fn (array $set, int $index): array => [
                    'set_nr' => $index + 1,
                    'registration1_score' => (int) $set[0],
                    'registration2_score' => (int) $set[1],
                ])
                ->all();
            abort_if($previousSets === $correctedSets, 422,
                'The corrected result is unchanged. Recovery was not applied.');

            $impact = $this->impact->preview($draw, $source);
            $case = DrawRecoveryCase::create([
                'draw_id' => $draw->id,
                'source_fixture_id' => $source->id,
                'requested_by' => auth()->id(),
                'approved_by' => $impact['requires_super_user'] ? auth()->id() : null,
                'operation' => 'round_robin_result_correction',
                'status' => 'applying',
                'reason' => $reason,
                'impact' => $impact,
                'preview_fingerprint' => $fingerprint,
                'approved_at' => $impact['requires_super_user'] ? now() : null,
            ]);
            $before = $this->snapshots->capture($draw, 'before_recovery', $case);
            $playoffIds = collect($impact['playoff_fixture_ids']);

            if ($playoffIds->isNotEmpty()) {
                DB::table('fixtures')
                    ->where('draw_id', $draw->id)
                    ->whereIn('parent_fixture_id', $playoffIds)
                    ->update(['parent_fixture_id' => null, 'feeder_slot' => null]);
                DB::table('fixtures')
                    ->where('draw_id', $draw->id)
                    ->whereIn('loser_parent_fixture_id', $playoffIds)
                    ->update(['loser_parent_fixture_id' => null, 'loser_feeder_slot' => null]);
                if (Schema::hasTable('order_of_plays')) {
                    DB::table('order_of_plays')->whereIn('fixture_id', $playoffIds)->delete();
                }
                if (Schema::hasTable('schedules')) {
                    DB::table('schedules')->whereIn('fixture_id', $playoffIds)->delete();
                }
                DB::table('fixture_results')->whereIn('fixture_id', $playoffIds)->delete();
                DB::table('fixtures')->whereIn('id', $playoffIds)->update([
                    'parent_fixture_id' => null,
                    'loser_parent_fixture_id' => null,
                ]);
                DB::table('fixtures')->whereIn('id', $playoffIds)->delete();
            }

            $this->draws->saveScore($source, $sets);
            $draw->forceFill([
                'published' => false,
                'oop_published' => false,
                'locked' => true,
                'oop_created' => false,
            ])->save();

            $after = $this->snapshots->capture($draw->fresh(), 'after_recovery', $case);
            $case->update([
                'status' => 'applied',
                'before_snapshot_id' => $before->id,
                'after_snapshot_id' => $after->id,
                'applied_at' => now(),
            ]);

            DrawAuditLog::record($draw->id, 'recovery_applied', $source->id, [
                'recovery_case_id' => $case->id,
                'reason' => $reason,
                'before_snapshot_id' => $before->id,
                'after_snapshot_id' => $after->id,
                'impact' => $impact,
                'corrected_sets' => $sets,
            ]);

            return $case->fresh(['beforeSnapshot', 'afterSnapshot']);
        });
    }

    public function restore(DrawRecoveryCase $case, string $reason): DrawRecoveryCase
    {
        return DB::transaction(function () use ($case, $reason) {
            $case = DrawRecoveryCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            abort_unless($case->status === 'applied' && $case->beforeSnapshot && $case->afterSnapshot, 409,
                'Only an applied recovery case with both snapshots can be restored.');
            $draw = Draw::whereKey($case->draw_id)->lockForUpdate()->firstOrFail();
            $this->lockCompetitionRows($draw);
            abort_unless(hash_equals($case->afterSnapshot->checksum, $this->snapshots->checksum($draw)), 409,
                'The draw changed after this recovery. Create a new recovery case instead of restoring stale data.');

            $preRestore = $this->snapshots->capture($draw, 'before_restore', $case);
            $counts = $this->snapshots->restore($case->beforeSnapshot);
            $case->update([
                'status' => 'restored',
                'restored_by' => auth()->id(),
                'restore_reason' => $reason,
                'restored_at' => now(),
            ]);

            DrawAuditLog::record($draw->id, 'recovery_restored', $case->source_fixture_id, [
                'recovery_case_id' => $case->id,
                'restored_snapshot_id' => $case->before_snapshot_id,
                'pre_restore_snapshot_id' => $preRestore->id,
                'reason' => $reason,
                'counts' => $counts,
            ]);

            return $case->fresh();
        });
    }

    private function lockCompetitionRows(Draw $draw): void
    {
        $fixtureIds = DB::table('fixtures')->where('draw_id', $draw->id)->lockForUpdate()->pluck('id');
        DB::table('fixture_results')->whereIn('fixture_id', $fixtureIds)->lockForUpdate()->get(['id']);
        foreach (['order_of_plays', 'schedules'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)
                    ->where('draw_id', $draw->id)
                    ->orWhereIn('fixture_id', $fixtureIds)
                    ->lockForUpdate()
                    ->get(['id']);
            }
        }
    }
}
