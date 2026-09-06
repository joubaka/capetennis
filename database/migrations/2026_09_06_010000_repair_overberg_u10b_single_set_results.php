<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('draws') || ! Schema::hasTable('draw_settings')
            || ! Schema::hasTable('fixtures') || ! Schema::hasTable('fixture_results')) {
            return;
        }

        DB::transaction(function (): void {
            $draws = DB::table('draws')
                ->where('event_id', 233)
                ->get(['id', 'drawName'])
                ->filter(fn ($draw) => in_array(strtolower(trim((string) $draw->drawName)), [
                    'u/10b boys',
                    'u/10b girls',
                ], true))
                ->keyBy(fn ($draw) => strtolower(trim((string) $draw->drawName)));

            foreach ($draws as $draw) {
                $settings = [
                    'num_sets' => 1,
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('draw_settings', 'require_full_sets')) {
                    $settings['require_full_sets'] = false;
                }

                $existingSettings = DB::table('draw_settings')->where('draw_id', $draw->id)->exists();
                if ($existingSettings) {
                    DB::table('draw_settings')->where('draw_id', $draw->id)->update($settings);
                } else {
                    DB::table('draw_settings')->insert(['draw_id' => $draw->id, 'created_at' => now()] + $settings);
                }
            }

            $boys = $draws->get('u/10b boys');
            if (! $boys) {
                return;
            }

            $fixtures = DB::table('fixtures')
                ->where('draw_id', $boys->id)
                ->whereExists(fn ($query) => $query->selectRaw('1')
                    ->from('fixture_results')
                    ->whereColumn('fixture_results.fixture_id', 'fixtures.id')
                    ->where('fixture_results.set_nr', '>', 1))
                ->lockForUpdate()
                ->get(['id', 'registration1_id', 'registration2_id']);

            foreach ($fixtures as $fixture) {
                $first = DB::table('fixture_results')
                    ->where('fixture_id', $fixture->id)
                    ->where('set_nr', 1)
                    ->orderBy('id')
                    ->first();

                if (! $first || (int) $first->registration1_score === (int) $first->registration2_score) {
                    throw new RuntimeException("U/10B Boys fixture {$fixture->id} has no decisive first set; no result was changed.");
                }

                $removed = DB::table('fixture_results')
                    ->where('fixture_id', $fixture->id)
                    ->where('set_nr', '>', 1)
                    ->orderBy('set_nr')
                    ->get()
                    ->map(fn ($set) => [
                        'set_nr' => (int) $set->set_nr,
                        'registration1_score' => (int) $set->registration1_score,
                        'registration2_score' => (int) $set->registration2_score,
                    ])->all();

                $winner = (int) $first->registration1_score > (int) $first->registration2_score
                    ? (int) $fixture->registration1_id
                    : (int) $fixture->registration2_id;
                $loser = $winner === (int) $fixture->registration1_id
                    ? (int) $fixture->registration2_id
                    : (int) $fixture->registration1_id;

                DB::table('fixture_results')->where('id', $first->id)->update([
                    'winner_registration' => $winner,
                    'loser_registration' => $loser,
                    'updated_at' => now(),
                ]);
                DB::table('fixture_results')
                    ->where('fixture_id', $fixture->id)
                    ->where('set_nr', '>', 1)
                    ->delete();
                $fixtureUpdate = [
                    'winner_registration' => $winner,
                    'match_status' => 1,
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('fixtures', 'loser_registration')) {
                    $fixtureUpdate['loser_registration'] = $loser;
                }
                DB::table('fixtures')->where('id', $fixture->id)->update($fixtureUpdate);

                if (Schema::hasTable('draw_audit_logs')) {
                    DB::table('draw_audit_logs')->updateOrInsert(
                        [
                            'draw_id' => $boys->id,
                            'fixture_id' => $fixture->id,
                            'action' => 'u10b_single_set_data_patch',
                        ],
                        [
                            'user_id' => null,
                            'payload' => json_encode([
                                'retained_set' => [
                                    'set_nr' => 1,
                                    'registration1_score' => (int) $first->registration1_score,
                                    'registration2_score' => (int) $first->registration2_score,
                                ],
                                'removed_sets' => $removed,
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        });
    }

    public function down(): void
    {
        // This is an audited correction of live tournament results. Removed
        // score rows must not be recreated automatically during rollback.
    }
};
