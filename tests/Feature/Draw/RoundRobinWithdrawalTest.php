<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\StandingsService;
use App\Models\{Draw, DrawAuditLog, Event, Fixture, Registration, User};
use App\Services\Draw\{IndividualScoreRevision, RoundRobinWithdrawalService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoundRobinWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private function programme(string $format = 'one_set_to_6'): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => false, 'published' => false]);
        $draw->settings()->create(['num_sets' => 1, 'score_format' => $format, 'workflow' => 'round_robin']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $draw->event_id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $players = Registration::factory()->count(4)->create();
        $group = $draw->groups()->create(['name' => 'A']);
        foreach ($players as $index => $player) {
            $draw->registrations()->attach($player->id);
            $group->registrations()->attach($player->id, ['seed' => $index + 1]);
        }
        $fixtures = collect();
        foreach ($players as $i => $home) foreach ($players->slice($i + 1) as $away) {
            $fixtures->push(Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
                'stage' => 'RR', 'match_status' => 0, 'winner_registration' => null,
                'registration1_id' => $home->id, 'registration2_id' => $away->id]));
        }
        return [$draw, $fixtures, $players];
    }

    private function confirm(Draw $draw, Fixture $fixture, array $extra = [])
    {
        return $this->postJson(route('backend.roundrobin.void-retired-player', [$draw, $fixture]), $extra + [
            'confirm_void' => 1, 'revision' => app(RoundRobinWithdrawalService::class)->revision($fixture->fresh()),
        ]);
    }

    public function test_retirement_before_play_can_void_all_group_matches_without_a_score(): void
    {
        [$draw, $fixtures, $players] = $this->programme();
        $fixture = $fixtures[0];
        $this->postJson(route('api.draws.fixtures.score.store', [$draw, $fixture]), [
            'sets' => [], 'retired_registration_id' => $players[0]->id,
            'score_revision' => app(IndividualScoreRevision::class)->forFixture($fixture->fresh()),
        ])->assertOk();
        $hub = app(\App\Services\DrawService::class)->loadRoundRobinHub($draw->fresh());
        $match = collect($hub['rrFixtures'][$fixture->draw_group_id])->firstWhere('id', $fixture->id);
        $this->assertSame('RET', $match['score']);
        $this->assertTrue($match['retired']);
        $this->assertSame([], $match['all_sets']);
        $this->confirm($draw, $fixture)->assertOk()->assertJsonPath('voided', 3);
        $this->assertSame(0, DB::table('fixture_results')->whereIn('fixture_id', $fixtures->pluck('id'))->count());
        $this->assertSame(3, $draw->drawFixtures()->whereNotNull('round_robin_voided_registration_id')->count());
        $rows = collect(app(StandingsService::class)->forDraw($draw->fresh())[$fixture->draw_group_id]);
        $this->assertCount(3, $rows);
        $this->assertFalse($rows->contains('reg_id', $players[0]->id));
        $this->assertSame(0, $rows->sum('wins'));
        $this->assertSame(0, $rows->sum('games_won'));
    }

    public function test_explicit_withdrawal_preserves_scores_and_excludes_every_match_from_regular_and_timed_standings(): void
    {
        foreach (['one_set_to_6', 'timed'] as $format) {
            [$draw, $fixtures, $players] = $this->programme($format);
            $retired = $fixtures[0];
            app(\App\Services\DrawService::class)->saveRetirement($retired, [[3, 2]], $players[0]->id);
            $earlier = $fixtures[1];
            $earlier->update(['match_status' => 1, 'winner_registration' => $players[0]->id]);
            $earlier->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
                'winner_registration' => $players[0]->id, 'loser_registration' => $players[2]->id]);
            foreach ($fixtures->slice(3) as $match) {
                $match->update(['match_status' => 1, 'winner_registration' => $match->registration1_id]);
                $match->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
                    'winner_registration' => $match->registration1_id, 'loser_registration' => $match->registration2_id]);
            }
            $saved = DB::table('fixture_results')->whereIn('fixture_id', $fixtures->pluck('id'))->orderBy('id')->get()->toJson();
            $revision = app(IndividualScoreRevision::class)->forFixture($retired->fresh());
            $this->confirm($draw, $retired)->assertOk()->assertJsonPath('voided', 3);
            $this->confirm($draw, $retired)->assertOk()->assertJsonPath('voided', 0);
            $this->assertSame($saved, DB::table('fixture_results')->whereIn('fixture_id', $fixtures->pluck('id'))->orderBy('id')->get()->toJson());
            $this->assertNotSame($revision, app(IndividualScoreRevision::class)->forFixture($retired->fresh()));
            $this->assertSame(1, DrawAuditLog::where('draw_id', $draw->id)->where('action', 'round_robin_player_withdrawn')->count());
            $this->assertSame(3, $draw->drawFixtures()->whereNotNull('round_robin_voided_registration_id')->count());
            $rows = collect(app(StandingsService::class)->forDraw($draw->fresh())[$retired->draw_group_id])->keyBy('reg_id');
            $this->assertCount(3, $rows);
            $this->assertFalse($rows->has($players[0]->id));
            $this->assertSame(3, $rows->sum('wins'));
            $this->assertSame(24, $rows->sum('games_won'));
            $this->assertSame(24, $rows->sum('games_lost'));
            $details = app(\App\Services\Draw\RoundRobinGroupResultService::class)->annotate($draw->fresh(), [$retired->draw_group_id => $rows->values()->all()]);
            $this->assertSame('complete', $details[$retired->draw_group_id][0]['group_result_status']);
            $this->assertSame(0, $details[$retired->draw_group_id][0]['remaining']);
            $this->assertTrue(app(\App\Services\Draw\RoundRobinProgressReadiness::class)->forDraw($draw->fresh())['ready']);
            $hub = app(\App\Services\DrawService::class)->loadRoundRobinHub($draw->fresh());
            $this->assertTrue(collect($hub['rrFixtures'][$retired->draw_group_id])->firstWhere('id', $retired->id)['voided']);
            $this->assertNull(app(\App\Services\IndividualMatchOutcomeService::class)->winner($retired->fresh()));
            $this->assertSame('3-2 RET', $retired->fresh()->score);
            $this->deleteJson(route('api.draws.fixtures.score.delete', [$draw, $retired]), ['score_revision' => app(IndividualScoreRevision::class)->forFixture($retired->fresh())])->assertConflict();
            $this->postJson(route('api.draws.fixtures.score.store', [$draw, $retired]), ['sets' => ['6-2'], 'score_revision' => app(IndividualScoreRevision::class)->forFixture($retired->fresh())])->assertForbidden();
        }
    }

    public function test_requires_retirement_confirmation_current_revision_and_event_authority(): void
    {
        [$draw, $fixtures, $players] = $this->programme();
        $fixture = $fixtures[0];
        $this->confirm($draw, $fixture)->assertUnprocessable();
        app(\App\Services\DrawService::class)->saveRetirement($fixture, [[3, 2]], $players[0]->id);
        $this->confirm($draw, $fixture, ['confirm_void' => 0])->assertUnprocessable();
        $this->confirm($draw, $fixture, ['revision' => str_repeat('0', 64)])->assertConflict();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $this->confirm($other, $fixture)->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        $this->confirm($draw, $fixture)->assertForbidden();
        $this->assertSame(0, $draw->drawFixtures()->whereNotNull('round_robin_voided_registration_id')->count());
    }

    public function test_super_user_cannot_void_locked_draw_and_other_groups_and_events_are_unchanged(): void
    {
        [$draw, $fixtures, $players] = $this->programme();
        $fixture = $fixtures[0];
        app(\App\Services\DrawService::class)->saveRetirement($fixture, [[3, 2]], $players[0]->id);
        $otherGroup = $draw->groups()->create(['name' => 'B']);
        $otherMatch = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $otherGroup->id, 'stage' => 'RR',
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id]);
        $foreign = Fixture::factory()->create(['stage' => 'RR', 'registration1_id' => $players[0]->id]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $draw->update(['locked' => true]);
        $this->confirm($draw, $fixture)->assertForbidden();
        $draw->update(['locked' => false]);
        $this->confirm($draw, $fixture)->assertOk();
        $this->assertNull($otherMatch->fresh()->round_robin_voided_registration_id);
        $this->assertNull($foreign->fresh()->round_robin_voided_registration_id);
    }

    public function test_confirmed_playoff_participants_block_void_without_partial_changes(): void
    {
        [$draw, $fixtures, $players] = $this->programme();
        $fixture = $fixtures[0];
        app(\App\Services\DrawService::class)->saveRetirement($fixture, [[3, 2]], $players[0]->id);
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'registration1_id' => $players[1]->id]);
        $this->confirm($draw, $fixture)->assertConflict();
        $this->assertSame(0, $draw->drawFixtures()->whereNotNull('round_robin_voided_registration_id')->count());
    }
}
