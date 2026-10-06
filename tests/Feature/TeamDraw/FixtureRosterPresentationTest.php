<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, DrawAuditLog, Event, EventType, NoProfileTeamPlayer, Player, Team,
    TeamFixture, TeamFixturePlayer, TeamPlayer, TeamRegion, TeamTie, User, Venue};
use App\Services\{TeamFixtureLineupPresenter, TeamParticipantHistoryService};
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FixtureRosterPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function weekend(): array
    {
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => 3, 'name' => 'Roster weekend',
            'start_date' => '2026-10-09', 'end_date' => '2026-10-11']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $format = ['rubbers' => [
            ['sequence' => 1, 'home_positions' => [2], 'away_positions' => [3]],
            ['sequence' => 2, 'home_positions' => [2, 4], 'away_positions' => [3, 4]],
        ]];
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'u/13 Doubles weekend',
            'published' => true, 'team_format_snapshot' => $format]);
        $teams = [];
        foreach (['Overberg' => '', 'Cape Winelands' => '', 'West Coast' => '', 'Drakenstein' => 'Drak'] as $name => $code) {
            $region = TeamRegion::create(['region_name' => $name, 'short_name' => $code]);
            $team = Team::factory()->create(['category_event_id' => $category->id,
                'region_id' => $region->id, 'name' => $name.' A']);
            $profile = Player::factory()->create(['name' => $name, 'surname' => 'Player']);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $profile->id, 'rank' => 2, 'pay_status' => 0]);
            $imported = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $name, 'surname' => 'Partner', 'rank' => 3, 'pay_status' => 0]);
            $second = NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $name, 'surname' => 'Second', 'rank' => 4, 'pay_status' => 0]);
            $teams[] = compact('team', 'profile', 'imported', 'second', 'region');
        }
        $venue = new Venue();
        $venue->forceFill(['name' => 'Published Tennis Courts'])->save();
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        $number = 0;
        foreach ([1 => [[0, 1], [2, 3]], 2 => [[0, 2], [1, 3]]] as $round => $pairs) {
            foreach ($pairs as $index => [$home, $away]) {
                $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => $round, 'tie_nr' => $index + 1,
                    'home_team_id' => $teams[$home]['team']->id, 'away_team_id' => $teams[$away]['team']->id,
                    'status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now(), 'format_snapshot' => $format]);
                foreach ([1, 2] as $sequence) {
                    $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
                        'round_nr' => $round, 'tie_nr' => $index + 1, 'rubber_sequence' => $sequence,
                        'match_nr' => ++$number, 'fixture_type' => $sequence === 1 ? 1 : 2,
                        'rubber_code' => $sequence === 1 ? 'singles' : 'doubles', 'match_status' => 0, 'numSets' => 3,
                        'scheduled_at' => '2026-10-09 '.sprintf('%02d:00:00', 7 + $number),
                        'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
                    TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1,
                        'team1_id' => $teams[$home]['profile']->id, 'team2_no_profile_id' => $teams[$away]['imported']->id]);
                    if ($sequence === 2) TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 2,
                        'team1_no_profile_id' => $teams[$home]['second']->id, 'team2_no_profile_id' => $teams[$away]['second']->id]);
                }
            }
        }
        return compact('event', 'draw', 'teams', 'venue');
    }

    private function export(string $name, $response): void
    {
        if (!getenv('CT_ROSTER_BROWSER_FIXTURE')) return;
        $directory = storage_path('framework/testing');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        file_put_contents($directory.'/'.$name.'.html', $response->getContent());
    }

    public function test_public_fixture_rounds_ties_and_names_are_read_only_and_private_times_stay_hidden(): void
    {
        extract($this->weekend());
        $before = [TeamFixture::count(), TeamFixturePlayer::count(), DrawAuditLog::count()];
        $response = $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertSee('Round 1')->assertSee('Round 2')->assertSee('Overberg A')->assertSee('Cape Winelands A')
            ->assertSee('(2)')->assertSee('(3)')->assertSee('(4)')->assertSee('(Over)')->assertSee('(CW)')
            ->assertSee('(WC)')->assertSee('(Drak)')->assertSee('Team roster rank')
            ->assertDontSee('Published Tennis Courts')->assertDontSee('2026-10-09 08:00');
        $this->assertSame(2, substr_count($response->getContent(), 'class="fixture-round '));
        $this->assertSame(4, substr_count($response->getContent(), 'class="fixture-tie '));
        $this->assertSame($before, [TeamFixture::count(), TeamFixturePlayer::count(), DrawAuditLog::count()]);
        $this->assertNull(TeamFixture::first()->home_rank_nr);
        $this->export('fixture-roster-public', $response);
        // A second render must not redeclare view helpers; both frontend entry points remain usable.
        $this->get(route('frontend.fixtures.show', $draw))->assertOk()->assertSee('(Over)');
    }

    public function test_published_order_of_play_uses_the_same_roster_labels_and_keeps_time_order(): void
    {
        extract($this->weekend());
        app(SchedulePublicationService::class)->publish($event, ['date' => '2026-10-09']);
        $response = $this->get(route('fixtures.order', [$event->id, $venue->id, 'all']))->assertOk()
            ->assertSee('(2)')->assertSee('(3)')->assertSee('(4)')->assertSee('(Over)')->assertSee('(CW)')
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->pluck('match_nr')->all() === range(1, 8));
        $this->export('fixture-roster-order', $response);
        $this->get(route('fixtures.order', [$event->id, $venue->id, '2026-10-09']))->assertOk()->assertSee('(CW)');
        $this->get(route('fixtures.venue', [$event->id, $venue->id]))->assertOk()->assertSee('(4)');
    }

    public function test_draft_preview_requires_the_event_authority_and_does_not_publish(): void
    {
        extract($this->weekend());
        $draw->update(['published' => false]);
        $this->get(route('frontend.fixtures.index', $draw))->assertForbidden();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $response = $this->actingAs($admin)->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertSee('Draft preview')->assertSee('Round 2')->assertSee('(Over)')->assertSee('(2)');
        $this->export('fixture-roster-draft', $response);
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_admin_fixture_badges_link_only_resolved_profiles_and_keep_imported_names(): void
    {
        extract($this->weekend());
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $linked = Player::factory()->create();
        $teams[1]['imported']->update(['player_profile' => $linked->id]);
        $before = [TeamFixture::count(), TeamFixturePlayer::count()];
        $response = $this->actingAs($admin)->get(route('backend.team-fixtures.index', ['draw_id' => $draw->id]))
            ->assertOk()->assertSee('Team fixtures')->assertSee('Round 1')->assertSee('Awaiting score')
            ->assertSee('fixture-region-badge', false)
            ->assertSee('background-color:#1e40af', false)
            ->assertSee('background-color:#b45309', false)
            ->assertSee('src="'.asset('assets/img/logos/overberg.png').'"', false)
            ->assertSee('src="'.asset('assets/img/logos/capeWinelandsLogo.jpeg').'"', false)
            ->assertSee('src="'.asset('assets/img/logos/weskusLogo.jpg').'"', false)
            ->assertSee('href="'.route('backend.player.profile', $teams[0]['profile']->id).'"', false)
            ->assertSee('href="'.route('backend.player.profile', $linked->id).'"', false)
            ->assertSee('Cape Winelands Partner');
        $this->assertSame($before, [TeamFixture::count(), TeamFixturePlayer::count()]);
        $this->export('fixture-roster-admin', $response);

        $fixture = TeamFixture::first();
        app(TeamFixtureLineupPresenter::class)->prepare(collect([$fixture]));
        $this->assertSame('#1e40af', $fixture->lineup_display['home']['region_color']);
        $this->assertSame('#b45309', $fixture->lineup_display['away']['region_color']);
        $html = view('backend.team-fixtures.partials.away-cell', ['team_fixture' => $fixture])->render();
        $this->assertStringContainsString(route('backend.player.profile', $linked->id), $html);
        $this->assertStringContainsString('Cape Winelands Partner', $html);

        $unlinked = TeamFixture::where('rubber_sequence', 2)->first();
        app(TeamFixtureLineupPresenter::class)->prepare(collect([$unlinked]));
        $html = view('backend.team-fixtures.partials.side-badges', ['fixture' => $unlinked, 'side' => 'home'])->render();
        $this->assertSame(1, substr_count($html, 'href='));
        $this->assertStringContainsString('Overberg Second', $html);

        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $html = view('backend.team-fixtures.partials.home-cell', ['team_fixture' => $fixture])->render();
        $this->assertStringNotContainsString('href=', $html);
        $this->actingAs(User::factory()->create());
        $html = view('backend.team-fixtures.partials.home-cell', ['team_fixture' => $fixture])->render();
        $this->assertStringNotContainsString('href=', $html);
    }

    public function test_historical_snapshots_keep_names_ranks_and_original_regions_after_roster_changes(): void
    {
        extract($this->weekend());
        $fixture = TeamFixture::first();
        app(TeamParticipantHistoryService::class)->captureFixture($fixture);
        $fixture->update(['match_status' => 1]);
        $teams[0]['profile']->update(['name' => 'Renamed']);
        TeamPlayer::where('player_id', $teams[0]['profile']->id)->update(['rank' => 9]);
        $teams[0]['team']->update(['region_id' => $teams[1]['region']->id]);
        app(TeamFixtureLineupPresenter::class)->prepare(collect([$fixture->fresh()]));
        $display = $fixture->fresh();
        app(TeamFixtureLineupPresenter::class)->prepare(collect([$display]));
        $this->assertSame(['name' => 'Overberg Player', 'rank' => 2], $display->lineup_display['home']['players'][0]);
        $this->assertSame('Over', $display->lineup_display['home']['region']);
        $this->assertSame('assets/img/logos/overberg.png', $display->lineup_display['home']['region_logo']);
        $this->assertSame('#1e40af', $display->lineup_display['home']['region_color']);
        $this->assertSame(1, $display->fixturePlayers->count());
    }

    public function test_foreign_tie_and_mismatched_snapshots_do_not_supply_names_or_ranks(): void
    {
        extract($this->weekend());
        $fixture = TeamFixture::first();
        $row = $fixture->fixturePlayers->first();
        $other = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $foreign = TeamTie::create(['draw_id' => $other->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]['team']->id, 'away_team_id' => $teams[1]['team']->id, 'status' => 'draft']);
        $row->forceFill(['participant_snapshot' => [1 => ['event_id' => $event->id, 'source_team_id' => $teams[0]['team']->id,
            'profile_id' => $teams[0]['profile']->id, 'imported_id' => null, 'name' => 'Private snapshot name', 'rank' => 99]]])->save();
        $fixture->update(['team_tie_id' => $foreign->id]);
        $display = $fixture->fresh();
        app(TeamFixtureLineupPresenter::class)->prepare(collect([$display]));
        $this->assertSame('Overberg Player', $display->lineup_display['home']['players'][0]['name']);
        $this->assertSame(2, $display->lineup_display['home']['players'][0]['rank']);
        $this->assertSame('TBD', $display->tie_display['home']);
        $fixture->update(['team_tie_id' => TeamTie::where('draw_id', $draw->id)->first()->id]);
        $snapshot = $row->participant_snapshot;
        $snapshot[1]['event_id'] = $other->event_id;
        $row->forceFill(['participant_snapshot' => $snapshot])->save();
        $display = $fixture->fresh();
        app(TeamFixtureLineupPresenter::class)->prepare(collect([$display]));
        $this->assertSame('Overberg Player', $display->lineup_display['home']['players'][0]['name']);
        $this->assertSame(2, $display->lineup_display['home']['players'][0]['rank']);
    }
}
