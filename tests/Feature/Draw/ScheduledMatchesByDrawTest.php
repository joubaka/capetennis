<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, NoProfileTeamPlayer, OrderOfPlay, Player, Registration, Team, TeamFixture, TeamFixturePlayer, TeamTie, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledMatchesByDrawTest extends TestCase
{
    use RefreshDatabase;

    private function setupEvent(): array
    {
        $event = Event::factory()->create(['eventType' => 3]);
        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        return [$event, Venue::forceCreate(['name' => 'Main courts'])];
    }

    private function url(Event $event, array $query = []): string
    {
        return route('backend.event-venue-schedule.calendar', ['event' => $event->id, 'group' => 'draw', 'date' => 'all'] + $query);
    }

    private function match(Draw $draw, Venue $venue, string $time, int $rank = 1): TeamFixture
    {
        return TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 2, 'tie_nr' => 3, 'match_nr' => $rank,
            'fixture_type' => 2, 'home_rank_nr' => $rank, 'away_rank_nr' => $rank, 'scheduled_at' => $time,
            'venue_id' => $venue->id, 'court_label' => '2', 'duration_min' => 60]);
    }

    public function test_private_review_shows_actual_pairs_imports_metadata_counts_and_all_days(): void
    {
        [$event, $venue] = $this->setupEvent();
        $field = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => 'Under 12 doubles', 'published' => false]);
        $other = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Other scheduled draw']);
        Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Empty draw']);
        $home = Team::factory()->create(['category_event_id' => $field->id, 'name' => 'Home school']);
        $away = Team::factory()->create(['category_event_id' => $field->id, 'name' => 'Away school']);
        $tie = TeamTie::forceCreate(['draw_id' => $draw->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id,
            'round_nr' => 2, 'tie_nr' => 3]);
        $first = $this->match($draw, $venue, '2026-10-09 09:00:00', 4);
        $first->update(['team_tie_id' => $tie->id, 'match_status' => 1]);
        foreach ([1, 2] as $slot) {
            $player = Player::factory()->create(['name' => 'Assigned long first name '.$slot, 'surname' => 'Actual partner']);
            $home->players()->attach($player, ['rank' => $slot]);
            $import = NoProfileTeamPlayer::create(['team_id' => $away->id, 'name' => 'Imported', 'surname' => 'Partner '.$slot, 'rank' => $slot, 'pay_status' => 0]);
            $assignment = TeamFixturePlayer::create(['team_fixture_id' => $first->id, 'slot_no' => $slot, 'team1_id' => $player->id, 'team2_no_profile_id' => $import->id]);
            if ($slot === 1) {
                $assignment->forceFill(['participant_snapshot' => [1 => ['event_id' => $event->id, 'source_team_id' => $home->id,
                    'profile_id' => $player->id, 'imported_id' => null, 'name' => $player->full_name, 'rank' => 1]]])->save();
                $home->players()->detach($player);
            }
        }
        $lower = $this->match($draw, $venue, '2026-10-09 09:00:00', 1);
        $later = $this->match($draw, $venue, '2026-10-10 09:00:00', 2);
        $this->match($other, $venue, '2026-10-11 09:00:00');
        $response = $this->get($this->url($event, ['draw_id' => $draw->id]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Assigned long first name 1 Actual partner')->assertSee('Assigned long first name 2 Actual partner')
            ->assertSee('Imported Partner 1')->assertSee('Imported Partner 2')->assertSee('rank 1')->assertSee('rank 2')
            ->assertSee('Round 2')->assertSee('Tie 3')->assertSee('Doubles')->assertSee('Court 2')->assertSee('3 scheduled matches')
            ->assertSee('Other scheduled draw · 1 scheduled')->assertSee('Empty draw · 0 scheduled');
        $this->assertSame([$lower->id, $first->id, $later->id], $response->viewData('matches')->pluck('fixture_id')->all());
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->get($this->url($event, ['draw_id' => $other->id, 'date' => '2026-10-09']))->assertOk();
        if (getenv('CT_SCHEDULE_REVIEW_QA_HTML') === '1') file_put_contents(storage_path('framework/testing/scheduled-draw-review-qa.html'), $response->getContent());
    }

    public function test_every_saved_match_after_one_thousand_remains_accessible_through_pagination(): void
    {
        [$event, $venue] = $this->setupEvent();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $rows = [];
        foreach (range(1, 1001) as $number) $rows[] = ['draw_id' => $draw->id, 'match_nr' => $number, 'fixture_type' => 1,
            'scheduled_at' => '2026-10-09 09:00:00', 'venue_id' => $venue->id, 'court_label' => '1'];
        foreach (array_chunk($rows, 200) as $batch) DB::table('team_fixtures')->insert($batch);
        $first = $this->get($this->url($event, ['draw_id' => $draw->id]))->assertOk();
        $this->assertSame(1001, $first->viewData('matches')->total());
        $this->assertCount(100, $first->viewData('matches'));
        $last = $this->get($this->url($event, ['draw_id' => $draw->id, 'page' => 11]))->assertOk()->assertSee('Showing 1001–1001');
        $this->assertCount(1, $last->viewData('matches'));
        $this->assertStringContainsString('group=draw', $first->viewData('matches')->nextPageUrl());
        $this->assertDatabaseCount('team_fixtures', 1001);
    }

    public function test_individual_review_shows_same_event_players_and_marks_foreign_registration_without_names(): void
    {
        [$event, $venue] = $this->setupEvent();
        $field = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id]);
        $valid = Registration::factory()->create();
        $valid->players()->attach(Player::factory()->create(['name' => 'Local', 'surname' => 'Participant']));
        CategoryEventRegistration::factory()->create(['registration_id' => $valid->id, 'category_event_id' => $field->id]);
        $foreign = Registration::factory()->create();
        $foreign->players()->attach(Player::factory()->create(['name' => 'Foreign private', 'surname' => 'Participant']));
        CategoryEventRegistration::factory()->create(['registration_id' => $foreign->id, 'category_event_id' => CategoryEvent::factory()->create()->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $valid->id, 'registration2_id' => $foreign->id, 'round' => 3, 'stage' => 'RR']);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-09 10:00:00']);

        $this->get($this->url($event))->assertOk()->assertSee('Local Participant')->assertSee('Assigned registration is outside this event')
            ->assertDontSee('Foreign private Participant')->assertSee('Round 3')->assertSee('Stage RR');
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertDatabaseCount('order_of_plays', 1);
    }

    public function test_review_preserves_event_boundaries_empty_draws_and_denies_other_users(): void
    {
        [$event, $venue] = $this->setupEvent();
        $empty = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'No times yet']);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create(['eventType' => 3])->id, 'drawName' => 'Foreign draw']);
        $this->match($foreign, $venue, '2026-10-09 09:00:00');
        $this->get($this->url($event, ['draw_id' => $empty->id]))->assertOk()->assertSee('No scheduled matches')->assertDontSee('Foreign draw');
        $this->get($this->url($event, ['draw_id' => $foreign->id]))->assertStatus(422);
        $this->get($this->url($event, ['venue_id' => $venue->id]))->assertStatus(422);
        $this->actingAs(User::factory()->create());
        $this->get($this->url($event))->assertForbidden();
    }
}
