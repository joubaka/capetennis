<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, EventType, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPlayer, User};
use App\Services\{FeatureFlags, TeamEventRulesService, TeamStandingsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamEventAcceptanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public static function rosters(): array
    {
        return ['six profile players' => [6, false], 'eight including imported players' => [8, true]];
    }

    #[DataProvider('rosters')]
    public function test_event_setup_to_public_standings_preserves_history(int $size, bool $imported): void
    {
        FeatureFlags::enable(FeatureFlags::TEAM_DRAW_V2);
        try {
            $this->runEvent($size, $imported);
        } finally {
            FeatureFlags::clearOverride(FeatureFlags::TEAM_DRAW_V2);
        }
    }

    private function runEvent(int $size, bool $imported): void
    {
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team event', 'type' => EventType::TEAM]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 3]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $teams = Team::factory()->count(3)->create(['category_event_id' => $category->id]);
        foreach ($teams as $team) {
            for ($rank = 1; $rank <= $size; $rank++) {
                if ($imported && $rank === $size) {
                    NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Rank '.$rank, 'rank' => $rank, 'pay_status' => 0]);
                } else {
                    $player = Player::factory()->create(['gender' => $rank % 2 ? 1 : 2]);
                    TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank]);
                }
            }
        }
        $historical = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'locked' => true]);
        $historicFixture = TeamFixture::create(['draw_id' => $historical->id, 'round_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1]);
        DB::table('team_fixture_results')->insert(['team_fixture_id' => $historicFixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        $history = DB::table('team_fixture_results')->where('team_fixture_id', $historicFixture->id)->get()->toArray();
        $historyDraw = $historical->fresh()->getRawOriginal();

        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['rubbers']['singles']['straight_win'] = 5;
        $rules['tie_win'] = 3;
        $this->putJson(route('backend.team-rules.update', $event), ['rules' => $rules])->assertOk();
        $rubbers = [];
        foreach ([['singles', [1], [2], null], ['reverse_singles', [2], [1], null],
            ['mixed_doubles', [3, 4], [3, 4], 'mixed'], ['singles', [$size], [$size], null]] as $index => [$code, $home, $away, $gender]) {
            $rubbers[] = ['sequence' => $index + 1, 'name' => 'Rubber '.($index + 1), 'rubber_code' => $code,
                'player_count_per_team' => count($home), 'home_positions' => $home, 'away_positions' => $away,
                'gender_rule' => $gender, 'is_required' => true];
        }
        $format = $this->postJson(route('team-draw.formats.store', $event), ['name' => $size.' player acceptance',
            'min_roster_size' => $size, 'max_roster_size' => $size, 'allow_player_reuse' => true,
            'is_default' => true, 'rubbers' => $rubbers])->assertCreated()->json('format.id');
        $drawType = DB::table('draw_types')->insertGetId(['drawTypeName' => 'Round robin', 'btn_color' => 'primary']);
        $payload = ['drawName' => 'Acceptance '.$size, 'draw_type_id' => $drawType, 'category_ids' => [$category->id], 'format_id' => $format];
        $this->postJson(route('headoffice.previewTeamDraw', $event), $payload)->assertOk()
            ->assertJsonPath('readiness.ready', true)->assertJsonPath('readiness.tie_count', 3)
            ->assertJsonPath('readiness.bye_count', 3)->assertJsonPath('readiness.rubber_count', 12);
        $this->assertDatabaseCount('draws', 1);
        $draw = Draw::findOrFail($this->postJson(route('headoffice.createSingleDraw.team', $event), $payload)->assertOk()->json('draw.id'));
        $this->assertEquals(5, $draw->team_scoring_rules['rubbers']['singles']['straight_win']);
        $this->assertSame(3, $draw->teamTies()->count());
        $fixtures = TeamFixture::where('draw_id', $draw->id)->orderBy('id')->get();
        $this->assertCount(12, $fixtures);
        foreach ($fixtures as $fixture) {
            $definition = $rubbers[$fixture->rubber_sequence - 1];
            foreach ($fixture->fixturePlayers as $slot) {
                foreach (['home' => ['home_team_id', 'team1'], 'away' => ['away_team_id', 'team2']] as $side => [$teamField, $prefix]) {
                    $position = $definition[$side.'_positions'][$slot->slot_no - 1];
                    $teamId = $fixture->teamTie->$teamField;
                    if ($imported && $position === $size) {
                        $this->assertSame(NoProfileTeamPlayer::where('team_id', $teamId)->where('rank', $position)->value('id'), $slot->{$prefix.'_no_profile_id'});
                        $this->assertNull($slot->{$prefix.'_id'});
                    } else {
                        $this->assertSame(TeamPlayer::where('team_id', $teamId)->where('rank', $position)->value('player_id'), $slot->{$prefix.'_id'});
                    }
                }
            }
        }
        foreach ($draw->teamTies as $tie) {
            $this->postJson(route('team-draw.ties.validate', $tie))->assertOk();
            $this->postJson(route('team-draw.ties.publish', $tie))->assertOk();
        }

        $venue = DB::table('venues')->insertGetId(['name' => 'Acceptance courts']);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $schedule = ['start' => '2026-10-10 08:00:00', 'end' => '2026-10-10 22:00:00', 'duration' => 60, 'gap' => 10, 'venues' => [$venue]];
        $this->postJson(route('backend.team-schedule.auto', $draw), $schedule)->assertOk()->assertJsonPath('count', 12);
        $this->postJson(route('backend.team-schedule.auto', $draw), $schedule)->assertOk()->assertJsonPath('count', 0);
        $scheduled = TeamFixture::where('draw_id', $draw->id)->get();
        foreach ($scheduled as $first) {
            $this->assertNotNull($first->scheduled_at);
            foreach ($scheduled as $second) {
                if ($second->id <= $first->id || abs($first->scheduled_at->diffInMinutes($second->scheduled_at, false)) >= 60) {
                    continue;
                }
                $this->assertNotSame($first->court_label, $second->court_label);
                $this->assertSame([], array_intersect($this->players($first), $this->players($second)));
            }
        }
        $first = $scheduled->first();
        $other = $scheduled->last();
        $originalSchedule = $other->getRawOriginal('scheduled_at');
        $this->postJson(route('backend.team-schedule.save', $draw), ['fixture_id' => $other->id,
            'scheduled_at' => $first->scheduled_at->format('Y-m-d H:i:s'), 'venue_id' => $venue,
            'court_label' => $first->court_label])->assertUnprocessable();
        $this->assertSame($originalSchedule, $other->fresh()->getRawOriginal('scheduled_at'));

        $rules['rubbers']['singles']['straight_win'] = 9;
        $this->putJson(route('backend.team-rules.update', $event), ['rules' => $rules])->assertOk();
        $this->assertEquals(5, $draw->fresh()->team_scoring_rules['rubbers']['singles']['straight_win']);
        $outsider = User::factory()->create()->assignRole('admin');
        $foreign = Event::factory()->create(['eventType' => 3]);
        DB::table('event_admins')->insert(['event_id' => $foreign->id, 'user_id' => $outsider->id]);
        $this->actingAs($outsider)->postJson(route('frontend.fixtures.score.store', $first), ['set1_home' => 6, 'set1_away' => 2])->assertForbidden();
        $this->putJson(route('backend.team-rules.update', $event), ['rules' => $rules])->assertForbidden();
        $this->actingAs($admin);
        foreach ($fixtures as $fixture) {
            $scores = ['set1_home' => 6, 'set1_away' => 2];
            if (!$fixture->isDoubles()) {
                $scores += ['set2_home' => 6, 'set2_away' => 3];
            }
            $this->postJson(route('frontend.fixtures.score.store', $fixture), $scores)->assertOk()->assertJsonPath('winner', 'home');
        }
        $this->assertSame(3, $draw->teamTies()->where('status', 'completed')->count());
        $this->assertEquals(54, array_sum(array_column(app(TeamStandingsService::class)->forDraw($draw->fresh()), 'points')));
        $this->postJson(route('frontend.fixtures.score.store', $first), ['set1_home' => 2, 'set1_away' => 6, 'set2_home' => 3, 'set2_away' => 6])->assertOk()->assertJsonPath('winner', 'away');
        $this->assertSame(2, $first->teamResults()->count());
        $this->delete(route('frontend.fixtures.score.delete', $first))->assertRedirect();
        $this->assertSame(0, $first->teamResults()->count());
        $this->assertSame('published', $first->teamTie()->first()->status);
        $this->postJson(route('frontend.fixtures.score.store', $first), ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3])->assertOk();
        $this->get(route('backend.team-draw.standings', $draw))->assertOk();
        $draw->update(['published' => true]);
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get(route('frontend.team-draw.standings', $draw))->assertOk()->assertSee($teams[0]->name);
        $draw->update(['published' => false]);
        $this->get(route('frontend.team-draw.standings', $draw))->assertForbidden();
        $this->assertEquals($history, DB::table('team_fixture_results')->where('team_fixture_id', $historicFixture->id)->get()->toArray());
        $this->assertSame($historyDraw, $historical->fresh()->getRawOriginal());
        $this->assertSame(1, TeamFixture::where('draw_id', $historical->id)->count());
        $this->assertDatabaseCount('team_fixture_results', 22);
    }

    private function players(TeamFixture $fixture): array
    {
        $ids = [];
        foreach ($fixture->fixturePlayers as $slot) {
            foreach (['team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id'] as $field) {
                if ($slot->$field) {
                    $ids[] = (str_contains($field, 'no_profile') ? 'imported:' : 'profile:').$slot->$field;
                }
            }
        }
        return $ids;
    }

    public function test_venue_scorekeeper_can_open_only_authorized_team_score_rows_and_actions_keep_names(): void
    {
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        Role::firstOrCreate(['name' => 'score-keeper', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $allowedVenue = DB::table('venues')->insertGetId(['name' => 'Allowed court']);
        $otherVenue = DB::table('venues')->insertGetId(['name' => 'Other court']);
        $scorer = User::factory()->create()->assignRole('score-keeper');
        $assignment = \App\Models\EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorer->id,
            'venue_id' => $allowedVenue, 'role' => 'score-keeper']);
        $allowed = TeamFixture::create(['draw_id' => $draw->id, 'venue_id' => $allowedVenue, 'fixture_type' => 1, 'match_nr' => 1]);
        $other = TeamFixture::create(['draw_id' => $draw->id, 'venue_id' => $otherVenue, 'fixture_type' => 1, 'match_nr' => 2]);
        $unscheduled = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'match_nr' => 3]);
        $home = Player::factory()->create(['name' => 'Profile', 'surname' => 'Home']);
        $away = NoProfileTeamPlayer::create(['team_id' => Team::factory()->create()->id, 'name' => 'Imported', 'surname' => 'Away', 'rank' => 1, 'pay_status' => 0]);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $allowed->id, 'slot_no' => 1,
            'team1_id' => $home->id, 'team2_no_profile_id' => $away->id]);
        $this->actingAs($scorer)->get(route('frontend.fixtures.enter-scores', $draw))->assertOk()
            ->assertSee('row-'.$allowed->id)->assertDontSee('row-'.$other->id)->assertDontSee('row-'.$unscheduled->id);
        $this->get(route('frontend.fixtures.enter-scores.venue', [$event, $allowedVenue]))->assertOk();
        $this->get(route('frontend.fixtures.enter-scores.venue', [$event, $otherVenue]))->assertForbidden();
        $this->get(route('frontend.fixtures.venue', $allowedVenue))->assertOk()->assertSee('Allowed court');
        $this->get(route('frontend.fixtures.venue', $otherVenue))->assertForbidden();
        $scores = ['set1_home' => 6, 'set1_away' => 2];
        $save = $this->postJson(route('frontend.fixtures.score.store', $allowed), $scores)->assertOk();
        $this->assertStringContainsString('Profile Home', $save->json('actionsHtml'));
        $this->assertStringContainsString('Imported Away', $save->json('actionsHtml'));
        $this->postJson(route('frontend.fixtures.score.store', $other), $scores)->assertForbidden();
        $this->postJson(route('frontend.fixtures.score.store', $unscheduled), $scores)->assertForbidden();
        $delete = $this->deleteJson(route('frontend.fixtures.score.delete', $allowed))->assertOk();
        $this->assertStringContainsString('Profile Home', $delete->json('actionsHtml'));
        $this->assertStringContainsString('Imported Away', $delete->json('actionsHtml'));
        $this->assertStringNotContainsString('data-set1_home', $delete->json('actionsHtml'));
        $this->assertSame(0, $allowed->teamResults()->count());
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create(['eventType' => 3])->id]);
        $this->get(route('frontend.fixtures.enter-scores', $foreign))->assertForbidden();
        $assignment->update(['expires_at' => now()->subDay()]);
        $this->get(route('frontend.fixtures.enter-scores', $draw))->assertForbidden();
        $this->get(route('frontend.fixtures.venue', $allowedVenue))->assertForbidden();
        $this->postJson(route('frontend.fixtures.score.store', $allowed), $scores)->assertForbidden();
        $this->assertDatabaseCount('team_fixture_results', 0);
        $assignment->update(['expires_at' => null, 'starts_at' => now()->addDay()]);
        $this->get(route('frontend.fixtures.venue', $allowedVenue))->assertForbidden();
        $this->get(route('frontend.fixtures.enter-scores', $draw))->assertForbidden();
        $assignment->update(['starts_at' => null]);
        $draw->update(['locked' => true]);
        $this->get(route('frontend.fixtures.enter-scores', $draw))->assertForbidden();
        $this->get(route('frontend.fixtures.venue', $allowedVenue))->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->isEmpty());
        $draw->update(['locked' => false, 'team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
        $this->get(route('frontend.fixtures.enter-scores', $draw))->assertForbidden();
        $this->get(route('frontend.fixtures.enter-scores.venue', [$event, $allowedVenue]))->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->isEmpty());
        $this->get(route('frontend.fixtures.venue', $allowedVenue))->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->isEmpty());
        $draw->update(['team_scoring_rules' => null]);
        foreach (['admin', 'convenor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $operator = User::factory()->create()->assignRole($role);
            if ($role === 'admin') {
                DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $operator->id]);
            } else {
                \App\Models\EventConvenor::create(['event_id' => $event->id, 'user_id' => $operator->id, 'role' => 'hulp']);
            }
            $this->actingAs($operator)->get(route('frontend.fixtures.enter-scores', $draw))->assertOk()
                ->assertSee('row-'.$allowed->id)->assertSee('row-'.$other->id)->assertSee('row-'.$unscheduled->id);
            $this->get(route('frontend.fixtures.venue', $otherVenue))->assertOk()->assertViewHas('fixtures', fn ($fixtures) => $fixtures->contains('id', $other->id));
        }
    }
}
