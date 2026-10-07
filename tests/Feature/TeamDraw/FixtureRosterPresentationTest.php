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
        $this->get(route('fixtures.order', [$event->id, $venue->id, '2026-10-09', 'draw_id' => $draw->id]))
            ->assertOk()->assertSee('Court')->assertSee('Draw / Match')
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->every(fn ($fixture) => $fixture->draw_id === $draw->id));
        $this->get(route('fixtures.order', [$event->id, $venue->id, 'all', 'draw_id' => 999999]))->assertNotFound();
        $this->get(route('fixtures.venue', [$event->id, $venue->id]))->assertOk()->assertSee('(4)');
    }

    public function test_draw_backend_schedule_scoring_and_order_of_play_share_match_and_player_order(): void
    {
        extract($this->weekend());
        $matches = TeamFixture::where('draw_id', $draw->id)->orderBy('match_nr')->get();
        $matches[0]->update(['scheduled_at' => '2026-10-09 09:00:00']);
        $matches[1]->update(['scheduled_at' => '2026-10-09 08:00:00']);
        // Recorded doubles slots deliberately disagree with insertion and roster-rank order.
        $slots = $matches[1]->fixturePlayers;
        $slots[0]->update(['slot_no' => 2]);
        $slots[1]->update(['slot_no' => 1]);
        app(SchedulePublicationService::class)->publish($event, ['date' => '2026-10-09']);
        $expected = [$matches[1]->id, $matches[0]->id, ...$matches->slice(2)->modelKeys()];
        $before = [TeamFixture::count(), TeamFixturePlayer::count(), DrawAuditLog::count()];

        foreach (['frontend.fixtures.index', 'frontend.fixtures.show'] as $route) {
            $response = $this->get(route($route, $draw))->assertOk()
                ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->modelKeys() === $expected);
            $this->export('ordering-public', $response);
        }
        $response = $this->get(route('fixtures.order', [$event->id, $venue->id, 'all']))->assertOk()
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->modelKeys() === $expected);
        $this->export('ordering-oop', $response);

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('super-user');
        $this->actingAs($admin);
        $this->get(route('backend.team-fixtures.index', ['draw_id' => $draw->id]))->assertOk()
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->getCollection()->modelKeys() === $expected);
        $response = $this->get(route('draw.show', $draw))->assertOk()
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->modelKeys() === $expected);
        $this->export('ordering-backend', $response);
        $this->get(route('backend.team-schedule.data', $draw))->assertOk()
            ->assertJsonPath('fixtures.0.id', $matches[1]->id)
            ->assertJsonPath('fixtures.0.p1', 'Overberg Second + Overberg Player')
            ->assertJsonPath('fixtures.0.p2', 'Cape Winelands Second + Cape Winelands Partner');
        $this->get(route('backend.team-schedule.all.data', $event))->assertOk()
            ->assertJsonPath('draws.0.fixtures.0.id', $matches[1]->id)
            ->assertJsonPath('draws.0.fixtures.0.p2', 'Cape Winelands Second + Cape Winelands Partner');
        $response = $this->get(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id]))->assertOk()
            ->assertViewHas('matches', function ($fixtures) use ($expected) {
                $this->assertSame($expected, $fixtures->modelKeys());
                $this->assertSame(['Overberg Second', 'Overberg Player'], array_column($fixtures->first()->lineup_display['home']['players'], 'name'));
                $this->assertSame(['Cape Winelands Second', 'Cape Winelands Partner'], array_column($fixtures->first()->lineup_display['away']['players'], 'name'));
                return true;
            });
        $this->export('ordering-scoring', $response);
        $double = $matches[1]->fresh();
        $this->assertSame([1, 2], $double->fixturePlayers->pluck('slot_no')->all());
        $this->assertSame($teams[0]['profile']->id, $double->team1->first()->id);

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')->twice()->andReturn(response('Test PDF'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->twice()
            ->withArgs(function ($view, $data) use ($expected) {
                $this->assertSame('backend.draw.pdf.pdf-team', $view);
                $this->assertSame($expected, $data['fixtures']->modelKeys());
                $html = view($view, $data)->render();
                $this->assertStringContainsString('Overberg Second', $html);
                $this->assertStringContainsString('Cape Winelands Partner', $html);
                $this->assertLessThan(strpos($html, 'fixture-'.$expected[1].'"'), strpos($html, 'fixture-'.$expected[0].'"'));
                return true;
            })->andReturn($pdf);
        $this->get(route('fixture.create.pdf', ['fixtures' => $draw->id]))->assertOk();
        $this->get(route('fixture.create.pdf.venue', ['fixtures' => array_reverse($expected)]))->assertOk();
        $this->assertSame($before, [TeamFixture::count(), TeamFixturePlayer::count(), DrawAuditLog::count()]);
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

    public function test_venue_pdf_rejects_a_foreign_draw_even_when_the_first_fixture_is_authorized(): void
    {
        extract($this->weekend());
        $ownFixture = TeamFixture::where('draw_id', $draw->id)->first();
        $foreignDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $foreignFixture = TeamFixture::create(['draw_id' => $foreignDraw->id, 'round_nr' => 1,
            'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'numSets' => 3]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->never();
        $this->actingAs($admin)->get(route('fixture.create.pdf.venue', [
            'fixtures' => [$ownFixture->id, $foreignFixture->id],
        ]))->assertForbidden();
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
