<?php

use App\Services\Draw\DrawFinalPlacementService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('draws')
            || ! Schema::hasTable('fixtures') || ! Schema::hasTable('fixture_results')) {
            return;
        }

        DB::transaction(function (): void {
            $draw = DB::table('draws')
                ->join('events', 'events.id', '=', 'draws.event_id')
                ->where('events.id', 231)
                ->whereRaw('LOWER(TRIM(draws.drawName)) = ?', ['u/13 boys'])
                ->first(['draws.id', 'draws.category_event_id', 'events.name as event_name']);

            if (! $draw) {
                return;
            }

            if (stripos((string) $draw->event_name, 'Overberg Tennis Trials') === false
                || stripos((string) $draw->event_name, 'Leg 1') === false) {
                throw new RuntimeException('Event 231 is not the expected Overberg Tennis Trials Leg 1 event.');
            }

            $originalFinal = DB::table('fixtures')
                ->where('draw_id', $draw->id)
                ->where('stage', 'MAIN')
                ->where('match_nr', 1000)
                ->lockForUpdate()
                ->first(['id', 'registration1_id', 'registration2_id', 'winner_registration']);
            $originalThirdFourth = DB::table('fixtures')
                ->where('draw_id', $draw->id)
                ->where('stage', 'PLATE')
                ->where('match_nr', 1001)
                ->lockForUpdate()
                ->first(['id', 'registration1_id', 'registration2_id', 'winner_registration']);

            if (! $originalFinal || ! $originalThirdFourth) {
                throw new RuntimeException('The expected U/13 Boys placement fixtures were not found; no adjustment was recorded.');
            }

            $romeRegistrationId = $this->loserOf($originalFinal);
            $gerhardRegistrationId = (int) $originalThirdFourth->winner_registration;
            $this->assertPlayerName($romeRegistrationId, 'Rome Sargeant');
            $this->assertPlayerName($gerhardRegistrationId, 'Gerhard Bruwer');

            $fixtureKey = [
                'draw_id' => (int) $draw->id,
                'stage' => DrawFinalPlacementService::ADJUSTMENT_STAGE,
                'match_nr' => 1003,
            ];
            $fixtureValues = [
                'scheduled' => false,
                'round' => 1,
                'match_status' => 1,
                'position' => 2,
                'playoff_type' => 'Additional 2nd/3rd placement match',
                'registration1_id' => $gerhardRegistrationId,
                'registration2_id' => $romeRegistrationId,
                'winner_registration' => $gerhardRegistrationId,
                'draw_group_id' => null,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('fixtures', 'loser_registration')) {
                $fixtureValues['loser_registration'] = $romeRegistrationId;
            }

            $fixture = DB::table('fixtures')->where($fixtureKey)->lockForUpdate()->first();
            if ($fixture) {
                DB::table('fixtures')->where('id', $fixture->id)->update($fixtureValues);
                $fixtureId = (int) $fixture->id;
            } else {
                $fixtureId = (int) DB::table('fixtures')->insertGetId($fixtureKey + $fixtureValues + ['created_at' => now()]);
            }

            DB::table('fixture_results')->updateOrInsert(
                ['fixture_id' => $fixtureId, 'set_nr' => 1],
                [
                    'registration1_score' => 7,
                    'registration2_score' => 5,
                    'winner_registration' => $gerhardRegistrationId,
                    'loser_registration' => $romeRegistrationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->ensurePublishedPositions($draw, $gerhardRegistrationId, $romeRegistrationId);

            if (Schema::hasTable('draw_audit_logs')) {
                DB::table('draw_audit_logs')->updateOrInsert(
                    [
                        'draw_id' => (int) $draw->id,
                        'fixture_id' => $fixtureId,
                        'action' => 'placement_adjustment_recorded',
                    ],
                    [
                        'user_id' => null,
                        'payload' => json_encode([
                            'reason' => 'Once-off additional match played after the scheduled placement matches.',
                            'winner_registration' => $gerhardRegistrationId,
                            'loser_registration' => $romeRegistrationId,
                            'score' => '7-5',
                            'positions' => ['winner' => 2, 'loser' => 3],
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
        // This records a real historical match and an audited official result.
        // Rollback must not silently remove or reverse tournament history.
    }

    private function loserOf(object $fixture): int
    {
        $winner = (int) $fixture->winner_registration;
        $first = (int) $fixture->registration1_id;
        $second = (int) $fixture->registration2_id;

        if (! $winner || ! $first || ! $second || ! in_array($winner, [$first, $second], true)) {
            throw new RuntimeException("Fixture {$fixture->id} has no valid completed winner.");
        }

        return $winner === $first ? $second : $first;
    }

    private function assertPlayerName(int $registrationId, string $expected): void
    {
        $actual = DB::table('player_registrations')
            ->join('players', 'players.id', '=', 'player_registrations.player_id')
            ->where('player_registrations.registration_id', $registrationId)
            ->selectRaw("TRIM(CONCAT(COALESCE(players.name, ''), ' ', COALESCE(players.surname, ''))) as full_name")
            ->value('full_name');

        if (mb_strtolower(trim((string) $actual)) !== mb_strtolower($expected)) {
            throw new RuntimeException("Expected {$expected} for registration {$registrationId}; found {$actual}. No adjustment was recorded.");
        }
    }

    private function ensurePublishedPositions(object $draw, int $gerhardRegistrationId, int $romeRegistrationId): void
    {
        if (! $draw->category_event_id || ! Schema::hasTable('category_events') || ! Schema::hasTable('category_results')) {
            throw new RuntimeException('The U/13 Boys category link or results table is unavailable.');
        }

        $categoryId = DB::table('category_events')
            ->where('id', $draw->category_event_id)
            ->where('event_id', 231)
            ->value('category_id');
        if (! $categoryId) {
            throw new RuntimeException('The U/13 Boys category link does not belong to event 231.');
        }

        foreach ([
            $gerhardRegistrationId => 2,
            $romeRegistrationId => 3,
        ] as $registrationId => $position) {
            $row = DB::table('category_results')
                ->where('event_id', 231)
                ->where('category_id', $categoryId)
                ->where('registration_id', $registrationId)
                ->first();

            if (! $row) {
                throw new RuntimeException("Published result for registration {$registrationId} is missing; no partial correction was applied.");
            }

            DB::table('category_results')->where('id', $row->id)->update([
                'position' => $position,
                'updated_at' => now(),
            ]);
        }
    }
};
