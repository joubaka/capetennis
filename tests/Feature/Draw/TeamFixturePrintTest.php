<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, TeamFixture, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamFixturePrintTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;
    private Draw $draw;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->event = Event::factory()->create(['name' => 'Cape / Schools \\ Tournament', 'eventType' => 3]);
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $this->draw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'u/10 Boys \\ Singles']);
    }

    public function test_draw_pdf_download_sanitizes_filename_and_preview_preserves_title(): void
    {
        $this->actingAs($this->admin)->get(route('fixture.create.pdf', ['fixtures' => $this->draw->id]))
            ->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertDownload('cape-schools-tournament-u10-boys-singles.pdf');

        $this->actingAs($this->admin)->get(route('fixture.create.pdf', ['fixtures' => $this->draw->id, 'preview' => 1]))
            ->assertOk()->assertSee('Print / Save as PDF')->assertSee($this->event->name.' '.$this->draw->drawName)
            ->assertSee('window.print()', false);
    }

    public function test_venue_sheet_defaults_to_public_snapshot_and_retains_private_working_preview(): void
    {
        $venue = Venue::forceCreate(['name' => 'Published courts']);
        $other = Venue::forceCreate(['name' => 'Private moved courts']);
        $this->event->venues()->attach([$venue->id => ['num_courts' => 2], $other->id => ['num_courts' => 2]]);
        $this->draw->update(['published' => true]);
        $fixtures = collect([['11:00:00', '2'], ['08:00:00', '1']])->map(fn ($slot, $index) => TeamFixture::create([
            'draw_id' => $this->draw->id, 'round_nr' => 1, 'match_nr' => $index + 1,
            'fixture_type' => 1, 'match_status' => 0, 'scheduled' => true,
            'scheduled_at' => '2026-10-09 '.$slot[0], 'venue_id' => $venue->id,
            'court_label' => $slot[1], 'duration_min' => 60,
        ]));
        $hiddenDraw = Draw::factory()->create(['event_id' => $this->event->id, 'published' => false]);
        $hidden = TeamFixture::create(['draw_id' => $hiddenDraw->id, 'round_nr' => 1, 'match_nr' => 3,
            'fixture_type' => 1, 'scheduled' => true, 'scheduled_at' => '2026-10-09 07:00:00',
            'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($this->event, ['date' => '2026-10-09']);
        $fixtures[1]->update(['scheduled_at' => '2026-10-10 15:00:00', 'venue_id' => $other->id, 'court_label' => '9']);
        $public = $this->get(route('fixtures.order', [$this->event->id, $venue->id, 'all']))->assertOk();
        $snapshot = fn ($rows) => $rows->map(fn ($fixture) => [
            $fixture->id, $fixture->scheduled_at->format('Y-m-d H:i:s'), $fixture->venue_id, $fixture->court_label,
        ])->all();
        $url = route('headoffice.venue.fixtures', ['event' => $this->event, 'venue' => $venue]);
        $default = $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('Published schedule');
        $this->assertSame('published', $default->viewData('scheduleSource'));
        $this->assertSame($snapshot($public->viewData('fixtures')), $snapshot($default->viewData('fixtures')));
        $this->assertSame([$fixtures[1]->id, $fixtures[0]->id], $default->viewData('fixtures')->pluck('id')->all());
        $this->assertSame(['2026-10-09'], $default->viewData('availableDays')->all());
        $day = $this->get($url.'?source=published&date=2026-10-09')->assertOk();
        $day->assertSee('source=published', false);
        $this->assertCount(0, $this->get($url.'?source=published&date=2026-10-10')->assertOk()->viewData('fixtures'));
        $working = $this->get($url.'?source=working')->assertOk()->assertSee('Working schedule');
        $this->assertSame([$hidden->id, $fixtures[0]->id], $working->viewData('fixtures')->pluck('id')->all());
        $foreignEvent = Event::factory()->create(['eventType' => 3]);
        $foreignEvent->venues()->attach($venue, ['num_courts' => 2]);
        $foreignDraw = Draw::factory()->create(['event_id' => $foreignEvent->id, 'published' => true]);
        TeamFixture::create(['draw_id' => $foreignDraw->id, 'round_nr' => 1, 'match_nr' => 1,
            'fixture_type' => 1, 'scheduled' => true, 'scheduled_at' => '2026-10-11 07:00:00',
            'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($foreignEvent, ['date' => '2026-10-11']);
        $this->assertSame([$fixtures[1]->id, $fixtures[0]->id], $this->get($url)->assertOk()->viewData('fixtures')->pluck('id')->all());
        $this->assertSame(['2026-10-09'], $this->get($url)->assertOk()->viewData('availableDays')->all());
        $this->assertSame([$hidden->id, $fixtures[0]->id], $this->get($url.'?source=working')->assertOk()->viewData('fixtures')->pluck('id')->all());
        $otherUrl = route('headoffice.venue.fixtures', ['event' => $this->event, 'venue' => $other]);
        $this->assertSame('working', $this->get($otherUrl)->assertOk()->viewData('scheduleSource'));
        $this->assertCount(0, $this->get($otherUrl.'?source=published')->assertOk()->viewData('fixtures'));
        $this->assertSame('9', $fixtures[1]->fresh()->court_label);
        $this->getJson($url.'?source=invalid')->assertUnprocessable()->assertJsonValidationErrors('source');
        $otherAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($otherAdmin)->get($url)->assertForbidden();
    }

    public function test_venue_pdf_download_sanitizes_filename_and_preview_preserves_title(): void
    {
        $venue = Venue::forceCreate(['name' => 'Club / Courts \\ West']);
        $fixture = TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 1, 'venue_id' => $venue->id]);

        $this->actingAs($this->admin)->get(route('fixture.create.pdf.venue', ['fixtures' => [$fixture->id]]))
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload('club-courts-west.pdf');
        $this->actingAs($this->admin)->get(route('fixture.create.pdf.venue', ['fixtures' => [$fixture->id], 'preview' => 1]))
            ->assertOk()->assertSee('Print / Save as PDF')->assertSee($venue->name);
    }

    public function test_team_draw_print_preview_requires_draw_authorization(): void
    {
        $otherAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($otherAdmin)->get(route('fixture.create.pdf', ['fixtures' => $this->draw->id, 'preview' => 1]))
            ->assertForbidden();
    }

    public function test_venue_print_preview_denies_a_foreign_fixture_in_either_order(): void
    {
        $local = TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 1]);
        $foreign = TeamFixture::create(['draw_id' => Draw::factory()->create()->id, 'match_nr' => 1]);
        foreach ([[$local->id, $foreign->id], [$foreign->id, $local->id]] as $ids) {
            $this->actingAs($this->admin)->get(route('fixture.create.pdf.venue', ['fixtures' => $ids, 'preview' => 1]))
                ->assertForbidden();
        }
    }

    public function test_venue_sheet_only_adds_headings_when_draw_or_round_changes(): void
    {
        $venue = Venue::forceCreate(['name' => 'Compact print venue']);
        $this->event->venues()->attach($venue, ['num_courts' => 2]);
        $otherDraw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'z Other draw']);
        $fixtures = collect();
        foreach ([[$this->draw, 1], [$this->draw, 1], [$this->draw, 2], [$otherDraw, 1]] as $index => [$draw, $round]) {
            $fixtures->push(TeamFixture::create([
                'draw_id' => $draw->id, 'round_nr' => $round, 'match_nr' => $index + 1,
                'venue_id' => $venue->id, 'scheduled' => true,
                'scheduled_at' => '2026-10-09 '.(9 + $index).':00:00',
            ]));
        }

        $response = $this->actingAs($this->admin)->get(route('headoffice.venue.fixtures', ['event' => $this->event, 'venue' => $venue]))
            ->assertOk()->assertDontSee('Tie starts:')->assertDontSee('Players start:')
            ->assertSee('2026-10-09 09:00')->assertSee('2026-10-09 12:00');
        $this->assertSame(3, substr_count($response->getContent(), 'data-print-draw-round='));
        $this->assertSame(1, substr_count($response->getContent(), 'data-print-draw-round="'.$this->draw->id.':1"'));
        $offsets = $fixtures->map(fn ($fixture) => strpos($response->getContent(), 'id="row-'.$fixture->id.'"'))->all();
        $sorted = $offsets;
        sort($sorted);
        $this->assertSame($sorted, $offsets);
    }

    public function test_venue_sheet_can_print_one_day_without_foreign_matches_or_day_options(): void
    {
        $venue = Venue::forceCreate(['name' => 'Daily print venue']);
        $this->event->venues()->attach($venue, ['num_courts' => 2]);
        $first = TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 1, 'venue_id' => $venue->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 09:00:00']);
        $second = TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 2, 'venue_id' => $venue->id, 'scheduled' => true, 'scheduled_at' => '2026-10-10 10:00:00']);
        TeamFixture::create(['draw_id' => Draw::factory()->create()->id, 'match_nr' => 3, 'venue_id' => $venue->id, 'scheduled' => true, 'scheduled_at' => '2026-10-11 11:00:00']);
        TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 4, 'venue_id' => Venue::forceCreate(['name' => 'Another venue'])->id, 'scheduled' => true, 'scheduled_at' => '2026-10-12 12:00:00']);
        $url = route('headoffice.venue.fixtures', ['event' => $this->event, 'venue' => $venue]);

        $all = $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('All days');
        $this->assertSame([$first->id, $second->id], $all->viewData('fixtures')->pluck('id')->all());
        $this->assertSame(['2026-10-09', '2026-10-10'], $all->viewData('availableDays')->all());
        $selected = $this->get($url.'?date=2026-10-10')->assertOk()->assertSee('Saturday 10 Oct 2026')->assertDontSee('2026-10-09 09:00')->assertDontSee('2026-10-11 11:00');
        $this->assertSame([$second->id], $selected->viewData('fixtures')->pluck('id')->all());
        $empty = $this->get($url.'?date=2026-10-12')->assertOk()->assertSee('No fixtures found.')->assertSee('Monday 12 Oct 2026');
        $this->assertCount(0, $empty->viewData('fixtures'));
        $this->getJson($url.'?date=2026-02-30')->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_team_draw_sheet_day_filter_uses_compact_short_labels_and_downloads_same_day(): void
    {
        $region = \App\Models\TeamRegion::create(['region_name' => 'Very Long School Name', 'short_name' => 'VLS']);
        $player = \App\Models\Player::factory()->create(['name' => 'Jay', 'surname' => 'Jacobs']);
        $venue = Venue::forceCreate(['name' => 'Daily / Draw Court']);
        $first = TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 1, 'round_nr' => 1, 'scheduled_at' => '2026-10-09 09:00:00']);
        $second = TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 2, 'round_nr' => 1, 'region1' => $region->id, 'home_rank_nr' => 3, 'venue_id' => $venue->id, 'court_label' => '2', 'scheduled_at' => '2026-10-10 10:00:00']);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $second->id, 'team1_id' => $player->id, 'slot_no' => 1]);
        $url = route('fixture.create.pdf', ['fixtures' => $this->draw->id, 'preview' => 1]);
        $all = $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('Day to print');
        $this->assertSame([$first->id, $second->id], $all->viewData('fixtures')->pluck('id')->all());
        $this->assertSame(1, substr_count($all->getContent(), 'data-print-draw-round='));
        $selected = $this->get($url.'&date=2026-10-10')->assertOk()->assertSee('VLS')->assertSee('Jay Jacobs')->assertSee('(3)')->assertSee('Court 2')->assertSee('Saturday 10 Oct 2026')->assertDontSee('Very Long School Name')->assertDontSee('09 Oct 09:00');
        $this->assertSame([$second->id], $selected->viewData('fixtures')->pluck('id')->all());
        $this->assertSame(['2026-10-09', '2026-10-10'], $selected->viewData('availableDays')->all());
        $this->get(route('fixture.create.pdf', ['fixtures' => $this->draw->id, 'date' => '2026-10-10']))
            ->assertOk()->assertHeader('content-type', 'application/pdf')->assertDownload('cape-schools-tournament-u10-boys-singles.pdf');
        $this->get($url.'&date=2026-10-12')->assertOk()->assertSee('No fixtures found.');
        $this->getJson($url.'&date=invalid')->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_venue_sheet_orders_times_across_ages_and_unknown_draws(): void
    {
        $venue = Venue::forceCreate(['name' => 'Age ordered venue']);
        $this->event->venues()->attach($venue, ['num_courts' => 2]);
        $expected = [];
        foreach ([13, 10] as $age) {
            $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->event->id,
                'category_id' => \App\Models\Category::factory()->create(['name' => 'u/'.$age.' Boys'])->id]);
            $draw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'u/'.$age.' Boys Singles', 'category_event_id' => $category->id]);
            $expected[$age] = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'match_nr' => $age, 'venue_id' => $venue->id, 'scheduled' => true, 'scheduled_at' => $age === 13 ? '2026-10-09 08:00:00' : '2026-10-09 11:00:00']);
        }
        $unknown = TeamFixture::create(['draw_id' => $this->draw->id, 'round_nr' => 1, 'match_nr' => 99, 'venue_id' => $venue->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 07:00:00']);
        $later = TeamFixture::create(['draw_id' => $expected[13]->draw_id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 14, 'venue_id' => $venue->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 12:00:00']);
        $response = $this->actingAs($this->admin)->get(route('headoffice.venue.fixtures', ['event' => $this->event, 'venue' => $venue]))->assertOk();
        $this->assertSame([$unknown->id, $expected[13]->id, $expected[10]->id, $later->id], $response->viewData('fixtureGroups')->pluck('fixture.id')->all());
        $response->assertSee('2026-10-09 11:00')->assertSee('2026-10-09 08:00')->assertSee('2026-10-09 07:00');
    }

    public function test_print_options_groups_venue_cards_by_youngest_assigned_age(): void
    {
        $teamType = \App\Models\DrawType::forceCreate(['drawTypeName' => 'Team age print', 'type' => 'team', 'btn_color' => 'primary']);
        $older = Venue::forceCreate(['name' => 'Alphabet older venue']);
        $younger = Venue::forceCreate(['name' => 'Zebra younger venue']);
        $shared = Venue::forceCreate(['name' => 'Shared ages venue']);
        $unknown = Venue::forceCreate(['name' => 'Unassigned venue']);
        $this->event->venues()->attach([$older->id => ['num_courts' => 1], $younger->id => ['num_courts' => 1], $shared->id => ['num_courts' => 1], $unknown->id => ['num_courts' => 1]]);
        foreach ([13 => [$older], 10 => [$younger, $shared]] as $age => $venues) {
            $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->event->id,
                'category_id' => \App\Models\Category::factory()->create(['name' => 'u/'.$age.' Boys'])->id]);
            $draw = Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $category->id, 'drawType_id' => $teamType->id, 'drawName' => 'u/'.$age.' Boys Singles']);
            foreach ($venues as $venue) {
                $draw->venues()->attach($venue, ['num_courts' => 1]);
                TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1, 'venue_id' => $venue->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 09:00:00']);
            }
            if ($age === 10) $draw->venues()->attach($older, ['num_courts' => 1]);
            if ($age === 13) TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 2, 'venue_id' => $shared->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 10:00:00']);
        }
        TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 99, 'venue_id' => $unknown->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 11:00:00']);
        $foreign = Venue::forceCreate(['name' => 'Foreign private venue']);
        Event::factory()->create()->venues()->attach($foreign, ['num_courts' => 1]);
        $response = $this->actingAs($this->admin)->get(route('headoffice.printOptions', $this->event))->assertOk()->assertDontSee('Foreign private venue')->assertSee('U10 · U13');
        $response->assertSee('data-venue-print-controls', false)
            ->assertSee('data-print-selection-count', false)
            ->assertSee("window.addEventListener('pageshow', updatePrintOptions)", false)
            ->assertSee('Select at least one draw', false);
        if (getenv('CT_BATCHES91011_QA')) {
            $directory = storage_path('app/batches91011-qa');
            if (!is_dir($directory)) { mkdir($directory, 0755, true); }
            file_put_contents($directory.'/print.html', $response->getContent());
        }
        $this->assertSame(['Under 10', 'Under 13', 'Other venues'], $response->viewData('venueGroups')->keys()->all());
        $this->assertSame([$shared->id, $younger->id], $response->viewData('venueGroups')->get('Under 10')->pluck('id')->all());
        $this->assertSame([10, 13], $response->viewData('venueAges')->get($shared->id)->all());
        $this->assertSame([13], $response->viewData('venueAges')->get($older->id)->all());
    }

    public function test_print_options_only_shows_venues_with_this_events_scheduled_timed_matches(): void
    {
        $teamType = \App\Models\DrawType::forceCreate(['drawTypeName' => 'Team scheduled print', 'type' => 'team', 'btn_color' => 'primary']);
        $this->draw->update(['drawType_id' => $teamType->id]);
        $venues = collect(['Valid scheduled venue', 'Assigned unused venue', 'Not scheduled venue', 'No match time venue', 'Foreign-only venue'])
            ->map(fn ($name) => Venue::forceCreate(['name' => $name]));
        foreach ($venues as $venue) $this->event->venues()->attach($venue, ['num_courts' => 1]);
        TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 1, 'venue_id' => $venues[0]->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 09:00:00']);
        TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 2, 'venue_id' => $venues[2]->id, 'scheduled' => false, 'scheduled_at' => '2026-10-09 10:00:00']);
        TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 3, 'venue_id' => $venues[3]->id, 'scheduled' => true, 'scheduled_at' => null]);
        TeamFixture::create(['draw_id' => Draw::factory()->create()->id, 'match_nr' => 4, 'venue_id' => $venues[4]->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 11:00:00']);
        TeamFixture::create(['draw_id' => $this->draw->id, 'match_nr' => 5, 'venue_id' => null, 'scheduled' => true, 'scheduled_at' => '2026-10-09 12:00:00']);

        $response = $this->actingAs($this->admin)->get(route('headoffice.printOptions', $this->event))->assertOk()->assertSee('Valid scheduled venue');
        foreach ($venues->skip(1) as $venue) $response->assertDontSee($venue->name);
        $this->assertSame([$venues[0]->id], $response->viewData('venueGroups')->flatten(1)->pluck('id')->all());
        $this->assertCount(5, $response->viewData('venues'));
    }

    public function test_age_venue_pack_groups_only_exact_age_matches_and_supports_day_selection(): void
    {
        $teamType = \App\Models\DrawType::forceCreate(['drawTypeName' => 'Team bulk print', 'type' => 'team', 'btn_color' => 'primary']);
        $draws = collect([10, 13])->mapWithKeys(function ($age) use ($teamType) {
            $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->event->id,
                'category_id' => \App\Models\Category::factory()->create(['name' => 'u/'.$age.' Boys'])->id]);
            return [$age => Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $category->id, 'drawType_id' => $teamType->id, 'drawName' => 'u/'.$age.' Boys Singles'])];
        });
        $shared = Venue::forceCreate(['name' => 'Shared age venue']);
        $other = Venue::forceCreate(['name' => 'Another age venue']);
        foreach ([$shared, $other] as $venue) $this->event->venues()->attach($venue, ['num_courts' => 1]);
        $first = TeamFixture::create(['draw_id' => $draws[10]->id, 'round_nr' => 1, 'match_nr' => 1, 'venue_id' => $shared->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 09:00:00']);
        $second = TeamFixture::create(['draw_id' => $draws[10]->id, 'round_nr' => 1, 'match_nr' => 2, 'venue_id' => $other->id, 'scheduled' => true, 'scheduled_at' => '2026-10-10 10:00:00']);
        $older = TeamFixture::create(['draw_id' => $draws[13]->id, 'round_nr' => 1, 'match_nr' => 3, 'venue_id' => $shared->id, 'scheduled' => true, 'scheduled_at' => '2026-10-09 11:00:00']);
        TeamFixture::create(['draw_id' => $draws[10]->id, 'match_nr' => 4, 'venue_id' => $other->id, 'scheduled' => false, 'scheduled_at' => '2026-10-11 12:00:00']);
        TeamFixture::create(['draw_id' => $draws[10]->id, 'match_nr' => 5, 'venue_id' => $other->id, 'scheduled' => true]);
        $foreignEvent = Event::factory()->create();
        $foreignCategory = \App\Models\CategoryEvent::factory()->create(['event_id' => $foreignEvent->id,
            'category_id' => \App\Models\Category::factory()->create(['name' => 'u/10 Boys'])->id]);
        $foreignDraw = Draw::factory()->create(['event_id' => $foreignEvent->id, 'category_event_id' => $foreignCategory->id, 'drawType_id' => $teamType->id, 'drawName' => 'Foreign u/10 Boys Singles']);
        TeamFixture::create(['draw_id' => $foreignDraw->id, 'match_nr' => 6, 'venue_id' => $shared->id, 'scheduled' => true, 'scheduled_at' => '2026-10-12 12:00:00']);
        $outside = Venue::forceCreate(['name' => 'Venue outside event membership']);
        TeamFixture::create(['draw_id' => $draws[10]->id, 'match_nr' => 7, 'venue_id' => $outside->id, 'scheduled' => true, 'scheduled_at' => '2026-10-13 13:00:00']);
        $url = route('headoffice.venuePrintPack', ['event' => $this->event, 'age' => 10]);

        $all = $this->actingAs($this->admin)->get($url)->assertOk()->assertSee('Print / Save as PDF')->assertSee('Day to print')->assertSee('Another age venue')->assertSee('Shared age venue')->assertDontSee('u/13 Boys Singles')->assertDontSee('Foreign u/10 Boys Singles')->assertDontSee('Venue outside event membership');
        $this->assertSame([$first->id, $second->id], $all->viewData('fixtures')->pluck('id')->all());
        $this->assertSame(['2026-10-09', '2026-10-10'], $all->viewData('availableDays')->all());
        $this->assertSame([$other->id, $shared->id], $all->viewData('venueSections')->pluck('venue.id')->all());
        $this->assertSame(2, substr_count($all->getContent(), 'data-venue-id='));
        $selected = $this->get($url.'&date=2026-10-10')->assertOk()->assertSee('Saturday 10 Oct 2026')->assertDontSee('Shared age venue');
        $this->assertSame([$second->id], $selected->viewData('fixtures')->pluck('id')->all());
        $olderPack = $this->get(route('headoffice.venuePrintPack', ['event' => $this->event, 'age' => 13]))->assertOk()->assertSee('Shared age venue')->assertDontSee('u/10 Boys Singles');
        $this->assertSame([$older->id], $olderPack->viewData('fixtures')->pluck('id')->all());
        $hub = $this->get(route('headoffice.printOptions', $this->event))->assertOk()->assertSee(route('headoffice.venuePrintPack', ['event' => $this->event, 'age' => 13]), false);
        $this->assertCount(0, $hub->viewData('venueGroups')->get('Under 13'));
        $this->get($url.'&date=2026-10-12')->assertOk()->assertSee('No fixtures found');
        $this->getJson($url.'&date=2026-02-30')->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson(route('headoffice.venuePrintPack', ['event' => $this->event, 'age' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('age');
        $otherAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($otherAdmin)->get($url)->assertForbidden();
    }
}
