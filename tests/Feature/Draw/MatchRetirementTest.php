<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\StandingsService;
use App\Models\{Draw, DrawAuditLog, Event, Fixture, Registration, User};
use App\Services\IndividualMatchOutcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatchRetirementTest extends TestCase
{
    use RefreshDatabase;

    private function match(string $stage = 'MAIN', string $format = 'best_of_3_full'): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => false, 'published' => false]);
        $draw->settings()->create(['num_sets' => 3, 'score_format' => $format]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $draw->event_id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $players = Registration::factory()->count(2)->create();
        $group = $draw->groups()->create(['name' => 'A']);
        foreach ($players as $index => $player) {
            $draw->registrations()->attach($player->id);
            $group->registrations()->attach($player->id, ['seed' => $index + 1]);
        }
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
            'stage' => $stage, 'match_status' => 0, 'winner_registration' => null,
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id]);
        return [$draw, $fixture, route('api.draws.fixtures.score.store', [$draw, $fixture])];
    }

    public function test_retirement_keeps_partial_scores_and_advances_opponent_even_when_retired_player_leads(): void
    {
        foreach (['legacy', 'canonical'] as $mode) {
            [$draw, $fixture, $url] = $this->match();
            $draw->update(['engine_mode' => $mode]);
            $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'match_status' => 0]);
            $loserParent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'CONS', 'match_status' => 0]);
            $fixture->update(['parent_fixture_id' => $parent->id, 'loser_parent_fixture_id' => $loserParent->id]);
            $payload = ['retired_registration_id' => $fixture->registration1_id, 'sets' => ['6-4', '3-2']];
            $this->postJson($url, $payload)->assertOk();
            $this->postJson($url, $payload)->assertOk();
            $fresh = $fixture->fresh();
            $this->assertTrue($fresh->isRetired());
            $this->assertSame((int) $fixture->registration2_id, app(IndividualMatchOutcomeService::class)->winner($fresh));
            $this->assertSame((int) $fixture->registration1_id, $fresh->retiredRegistrationId());
            $this->assertSame('6-4 3-2 RET', $fresh->score);
            $this->assertSame(2, $fixture->fixtureResults()->count());
            $this->assertNull($fixture->fixtureResults()->where('set_nr', 2)->first()->winner_registration);
            $this->assertSame(1, DrawAuditLog::where('draw_id', $draw->id)->where('action', 'retirement_saved')->count());
            $this->assertContains((int) $fixture->registration2_id, [(int) $parent->fresh()->registration1_id, (int) $parent->fresh()->registration2_id]);
            $this->assertContains((int) $fixture->registration1_id, [(int) $loserParent->fresh()->registration1_id, (int) $loserParent->fresh()->registration2_id]);
        }
    }

    public function test_round_robin_counts_only_completed_sets_and_real_games_with_opponent_match_win(): void
    {
        [$draw, $fixture, $url] = $this->match('RR');
        $this->postJson($url, ['retired_registration_id' => $fixture->registration1_id, 'sets' => ['6-4', '3-2']])->assertOk();
        $rows = collect(app(StandingsService::class)->forDraw($draw->fresh())[$fixture->draw_group_id])->keyBy('reg_id');
        $this->assertSame(1, $rows[$fixture->registration2_id]['wins']);
        $this->assertSame(1, $rows[$fixture->registration1_id]['sets_won']);
        $this->assertSame(0, $rows[$fixture->registration2_id]['sets_won']);
        $this->assertSame(9, $rows[$fixture->registration1_id]['games_won']);
        $this->assertSame(6, $rows[$fixture->registration2_id]['games_won']);
        $this->deleteJson(route('api.draws.fixtures.score.delete', [$draw, $fixture]))->assertOk();
        $this->assertFalse($fixture->fresh()->isRetired());
        $this->assertSame(0, $fixture->fixtureResults()->count());
        $log = DrawAuditLog::where('draw_id', $draw->id)->where('action', 'score_deleted')->firstOrFail();
        $this->assertSame((int) $fixture->registration1_id, $log->payload['previous_retired_registration_id']);
    }

    public function test_invalid_scores_conflicting_outcomes_foreign_players_and_unauthorized_requests_are_rejected(): void
    {
        [$draw, $fixture, $url] = $this->match();
        $payload = ['retired_registration_id' => $fixture->registration1_id, 'sets' => ['3-2']];
        $this->postJson($url, $payload + ['walkover_winner_id' => $fixture->registration2_id])->assertUnprocessable();
        $this->postJson($url, $payload + ['deciding_point_winner_id' => $fixture->registration2_id])->assertUnprocessable();
        foreach ([[], ['3-2', '1-0'], ['6-4', '6-4'], ['6-4', '9-1']] as $sets) {
            $this->postJson($url, array_replace($payload, ['sets' => $sets]))->assertUnprocessable();
        }
        $this->postJson($url, array_replace($payload, ['retired_registration_id' => Registration::factory()->create()->id]))->assertUnprocessable();
        $other = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        DB::table('event_admins')->insert(['event_id' => $other->event_id, 'user_id' => auth()->id()]);
        $this->postJson(route('api.draws.fixtures.score.store', [$other, $fixture]), $payload)->assertUnprocessable();
        $draw->update(['locked' => true]);
        $this->postJson($url, $payload)->assertForbidden();
        $draw->update(['locked' => false]);
        $this->actingAs(User::factory()->create());
        $this->postJson($url, $payload)->assertForbidden();
        $this->assertNull($fixture->fresh()->winner_registration);
        $this->assertSame(0, $fixture->fixtureResults()->count());
    }

    public function test_timed_retirement_awards_opponent_points_and_corrections_protect_later_results(): void
    {
        [$draw, $fixture, $url] = $this->match('RR', 'timed');
        $this->postJson($url, ['retired_registration_id' => $fixture->registration1_id, 'sets' => ['3-1']])->assertOk();
        $rows = collect(app(StandingsService::class)->forDraw($draw->fresh())[$fixture->draw_group_id])->keyBy('reg_id');
        $this->assertSame(2, $rows[$fixture->registration2_id]['points']);
        $this->assertSame(0, $rows[$fixture->registration1_id]['sets_won']);
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'winner_registration' => $fixture->registration2_id, 'match_status' => 1]);
        $this->postJson($url, ['sets' => ['1-3']])->assertConflict();
        $this->deleteJson(route('api.draws.fixtures.score.delete', [$draw, $fixture]))->assertConflict();
    }
}
