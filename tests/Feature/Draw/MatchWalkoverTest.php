<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\StandingsService;
use App\Models\{Draw, Event, Fixture, Registration, User};
use App\Services\IndividualMatchOutcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatchWalkoverTest extends TestCase
{
    use RefreshDatabase;

    private function match(string $stage = 'MAIN', ?string $format = null): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'locked' => false, 'published' => false]);
        $draw->settings()->create(['num_sets' => 1, 'score_format' => $format]);
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

    public function test_walkover_advances_both_paths_without_set_scores_and_is_idempotent(): void
    {
        foreach (['legacy', 'canonical'] as $mode) {
            [$draw, $fixture, $url] = $this->match();
            $draw->update(['engine_mode' => $mode]);
            $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'match_status' => 0]);
            $loserParent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'CONS', 'match_status' => 0]);
            $fixture->update(['parent_fixture_id' => $parent->id, 'loser_parent_fixture_id' => $loserParent->id]);
            $payload = ['walkover_winner_id' => $fixture->registration2_id];
            $this->postJson($url, $payload)->assertOk();
            $this->postJson($url, $payload)->assertOk();
            $this->assertTrue($fixture->fresh()->isWalkover());
            $this->assertSame((int) $fixture->registration2_id, app(IndividualMatchOutcomeService::class)->winner($fixture->fresh()));
            $this->assertSame(0, $fixture->fixtureResults()->count());
            $this->assertContains((int) $fixture->registration2_id, [(int) $parent->fresh()->registration1_id, (int) $parent->fresh()->registration2_id]);
            $this->assertContains((int) $fixture->registration1_id, [(int) $loserParent->fresh()->registration1_id, (int) $loserParent->fresh()->registration2_id]);
        }
    }

    public function test_round_robin_walkover_counts_a_win_without_invented_games_or_sets(): void
    {
        foreach ([null, 'timed'] as $format) {
            [$draw, $fixture, $url] = $this->match('RR', $format);
            $this->postJson($url, ['walkover_winner_id' => $fixture->registration2_id])->assertOk();
            $rows = app(StandingsService::class)->forDraw($draw->fresh())[$fixture->draw_group_id];
            $winner = collect($rows)->firstWhere('reg_id', $fixture->registration2_id);
            $this->assertSame(1, $winner['wins']);
            $this->assertSame(0, $winner['sets_won']);
            $this->assertSame(0, $winner['games_won']);
            if ($format === 'timed') $this->assertSame(2, $winner['points']);
            $this->deleteJson(route('api.draws.fixtures.score.delete', [$draw, $fixture]))->assertOk();
            $this->assertFalse($fixture->fresh()->isWalkover());
            $this->assertNull($fixture->fresh()->winner_registration);
        }
    }

    public function test_invalid_winner_scores_foreign_fixture_and_unauthorized_actor_are_rejected(): void
    {
        [$draw, $fixture, $url] = $this->match();
        $this->postJson($url, ['walkover_winner_id' => Registration::factory()->create()->id])->assertUnprocessable();
        $this->postJson($url, ['walkover_winner_id' => $fixture->registration1_id, 'sets' => ['6-0']])->assertUnprocessable();
        $other = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        DB::table('event_admins')->insert(['event_id' => $other->event_id, 'user_id' => auth()->id()]);
        $this->postJson(route('api.draws.fixtures.score.store', [$other, $fixture]), ['walkover_winner_id' => $fixture->registration1_id])->assertUnprocessable();
        $draw->update(['locked' => true]);
        $this->postJson($url, ['walkover_winner_id' => $fixture->registration1_id])->assertForbidden();
        $draw->update(['locked' => false]);
        $this->actingAs(User::factory()->create());
        $this->postJson($url, ['walkover_winner_id' => $fixture->registration1_id])->assertForbidden();
        $this->assertNull($fixture->fresh()->winner_registration);
        $this->assertSame(0, $fixture->fixtureResults()->count());
    }

    public function test_round_robin_same_winner_conversion_preserves_generated_playoffs(): void
    {
        [$draw, $fixture, $url] = $this->match('RR');
        $this->postJson($url, ['sets' => ['6-2']])->assertOk();
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'match_status' => 0,
            'registration1_source_group_id' => null, 'registration2_source_group_id' => null]);
        $this->postJson($url, ['walkover_winner_id' => $fixture->registration1_id])->assertConflict();
        $this->assertSame(1, $fixture->fixtureResults()->count());
        $this->assertFalse($fixture->fresh()->isWalkover());
    }

    public function test_later_walkover_protects_upstream_result_from_correction_or_deletion(): void
    {
        [$draw, $fixture, $url] = $this->match();
        $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'match_status' => 3,
            'registration1_id' => $fixture->registration1_id, 'registration2_id' => Registration::factory()->create()->id,
            'winner_registration' => $fixture->registration1_id]);
        $fixture->update(['parent_fixture_id' => $parent->id, 'winner_registration' => $fixture->registration1_id, 'match_status' => 3]);
        $this->postJson($url, ['walkover_winner_id' => $fixture->registration2_id])->assertConflict();
        $this->deleteJson(route('api.draws.fixtures.score.delete', [$draw, $fixture]))->assertConflict();
    }
}
