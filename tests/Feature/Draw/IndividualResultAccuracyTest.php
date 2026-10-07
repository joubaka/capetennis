<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, DrawGroup, Event, Fixture, Registration};
use App\Services\{DrawService, InterproDrawBuilder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndividualResultAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('FIELD', static function ($value, ...$values): int {
                $index = array_search($value, $values, true);
                return $index === false ? 0 : $index + 1;
            });
        }
    }

    private function draw(): array
    {
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $draw->settings()->create(['num_sets' => 3]);
        $groups = []; $fixtures = [];
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $group = DrawGroup::create(['draw_id' => $draw->id, 'name' => $name]);
            $registrations = Registration::factory()->count(3)->create();
            foreach ($registrations as $registration) $group->groupRegistrations()->create(['registration_id' => $registration->id]);
            $fixtures[] = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR',
                'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id]);
            $groups[] = $group;
        }
        return [$draw, $groups, $fixtures];
    }

    public function test_both_hubs_use_aggregate_match_winner_for_historical_results_and_interpro_seeding(): void
    {
        [$draw, $groups, $fixtures] = $this->draw();
        foreach ($fixtures as $fixture) {
            // Historical legacy formats accepted an extra set after the match
            // was won. Its losing final set must not reverse the match result.
            foreach ([[6, 2], [6, 3], [2, 6]] as $index => [$home, $away]) {
                $fixture->fixtureResults()->create(['set_nr' => $index + 1, 'registration1_score' => $home, 'registration2_score' => $away,
                    'winner_registration' => $home > $away ? $fixture->registration1_id : $fixture->registration2_id]);
            }
            $fixture->update(['winner_registration' => $fixture->registration2_id]);
        }
        foreach ([DrawService::class, InterproDrawBuilder::class] as $class) {
            $service = app($class);
            $hub = $service->loadRoundRobinHub($draw->fresh());
            $this->assertSame($fixtures[0]->registration1_id, $hub['rrFixtures'][$groups[0]->id][0]['winner']);
            $rows = collect($hub['standings'][$groups[0]->id])->keyBy('reg_id');
            $home = $rows[$fixtures[0]->registration1_id];
            $this->assertSame(1, $home['wins']);
            $this->assertSame(2, $home['sets_won']);
            $this->assertSame(14, $home['games_won']);
            $seeds = $service->buildMainSeedsFromRRStandings($draw->fresh());
            $this->assertSame($fixtures[0]->registration1_id, $seeds['A1']);
            $plate = $service->buildSecondThirdSeedsFromRRStandings($draw->fresh());
            $this->assertNotContains($fixtures[0]->registration1_id, [$plate['A2'], $plate['A3']]);
            $this->assertContains($fixtures[0]->registration2_id, [$plate['A2'], $plate['A3']]);
        }
    }

    public function test_partial_rr_scores_in_both_services_have_no_match_win_or_hub_winner(): void
    {
        foreach ([DrawService::class, InterproDrawBuilder::class] as $class) {
            [$draw, $groups, $fixtures] = $this->draw();
            app($class)->saveScore($fixtures[0], [[6, 2]]);
            $this->assertNull($fixtures[0]->fresh()->winner_registration);
            $hub = app($class)->loadRoundRobinHub($draw->fresh());
            $this->assertNull($hub['rrFixtures'][$groups[0]->id][0]['winner']);
            $this->assertSame(0, array_sum(array_column($hub['standings'][$groups[0]->id], 'wins')));
        }
    }

    public function test_partial_bracket_scores_in_both_services_do_not_advance_players(): void
    {
        foreach ([DrawService::class, InterproDrawBuilder::class] as $class) {
            [$draw, $groups, $fixtures] = $this->draw();
            $parent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2]);
            $fixture = $fixtures[0];
            $fixture->update(['stage' => 'MAIN', 'parent_fixture_id' => $parent->id]);
            app($class)->saveBracketScore($fixture, [[6, 2]]);
            $this->assertNull($fixture->fresh()->winner_registration);
            $this->assertNull($parent->fresh()->registration1_id);
            $this->assertNull($parent->fresh()->registration2_id);
        }
    }

    public function test_explicit_one_set_format_still_completes_in_both_services(): void
    {
        foreach ([DrawService::class, InterproDrawBuilder::class] as $class) {
            [$draw, $groups, $fixtures] = $this->draw();
            $draw->settings()->update(['score_format' => \App\Domain\Draws\Services\TennisScoreFormat::ONE_FULL_SET]);
            app($class)->saveScore($fixtures[0]->fresh(), [[6, 2]]);
            $this->assertSame($fixtures[0]->registration1_id, $fixtures[0]->fresh()->winner_registration);
            $this->assertSame(1, $fixtures[0]->fresh()->match_status);
        }
    }
}
