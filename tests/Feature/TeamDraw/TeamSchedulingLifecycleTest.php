<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\User;
use App\Models\Fixture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSchedulingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Draw $draw;
    private int $venue;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team event', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['eventType' => 3]);
        $this->draw = $this->makeDraw($this->event);
        $this->venue = DB::table('venues')->insertGetId(['name' => 'Shared courts']);
        $this->draw->venues()->attach($this->venue, ['num_courts' => 2]);
    }

    private function makeDraw(Event $event): Draw
    {
        return Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
    }

    private function fixture(?Draw $draw = null, array $fields = []): TeamFixture
    {
        return TeamFixture::create(array_merge(['draw_id' => ($draw ?? $this->draw)->id,
            'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0], $fields));
    }

    private function payload(): array
    {
        return ['start' => '2026-10-10 08:00:00', 'end' => '2026-10-10 12:00:00',
            'duration' => 60, 'gap' => 10, 'venues' => [$this->venue]];
    }

    public function test_event_auto_scheduling_uses_shared_courts_without_overlap_and_is_repeatable(): void
    {
        $other = $this->makeDraw($this->event);
        $other->venues()->attach($this->venue, ['num_courts' => 2]);
        $first = $this->fixture();
        $second = $this->fixture($other);
        $third = $this->fixture($other, ['match_nr' => 2]);
        $this->postJson(route('backend.team-schedule.all.auto', $this->event), $this->payload())
            ->assertOk()->assertJsonPath('count', 3);
        $this->assertSame('08:00', $first->fresh()->scheduled_at->format('H:i'));
        $this->assertSame('08:00', $second->fresh()->scheduled_at->format('H:i'));
        $this->assertNotSame($first->fresh()->court_label, $second->fresh()->court_label);
        $this->assertSame('09:10', $third->fresh()->scheduled_at->format('H:i'));
        $this->postJson(route('backend.team-schedule.all.auto', $this->event), $this->payload())
            ->assertOk()->assertJsonPath('count', 0);
        $this->assertDatabaseCount('team_fixtures', 3);
    }

    public function test_existing_bookings_in_other_events_and_individual_draws_are_preserved(): void
    {
        $foreign = $this->makeDraw(Event::factory()->create(['eventType' => 3]));
        $booking = $this->fixture($foreign, ['scheduled_at' => '2026-10-10 08:00:00',
            'venue_id' => $this->venue, 'court_label' => 'Court 1', 'duration_min' => 60, 'scheduled' => 1]);
        DB::table('order_of_plays')->insert(['fixture_id' => Fixture::factory()->create(['draw_id' => $foreign->id])->id, 'draw_id' => $foreign->id, 'venue_id' => $this->venue,
            'court' => '2', 'time' => '2026-10-10 08:00:00', 'duration_minutes' => 60]);
        $fixture = $this->fixture();
        $this->postJson(route('backend.team-schedule.auto', $this->draw), $this->payload())
            ->assertOk()->assertJsonPath('count', 1);
        $this->assertSame('09:10', $fixture->fresh()->scheduled_at->format('H:i'));
        $this->assertSame('08:00', $booking->fresh()->scheduled_at->format('H:i'));
    }

    public function test_shared_profile_player_cannot_play_on_two_courts_at_once(): void
    {
        $player = Player::factory()->create();
        $first = $this->fixture();
        $second = $this->fixture(null, ['match_nr' => 2]);
        foreach ([$first, $second] as $fixture) {
            TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $player->id]);
        }
        $this->postJson(route('backend.team-schedule.auto', $this->draw), $this->payload())->assertOk();
        $this->assertSame('09:10', $second->fresh()->scheduled_at->format('H:i'));
    }

    public function test_shared_imported_player_cannot_play_on_two_courts_at_once(): void
    {
        $player = \App\Models\NoProfileTeamPlayer::create(['team_id' => \App\Models\Team::factory()->create()->id,
            'name' => 'Imported', 'surname' => 'Player', 'rank' => 1, 'pay_status' => 0]);
        $first = $this->fixture();
        $second = $this->fixture(null, ['match_nr' => 2]);
        foreach ([$first, $second] as $fixture) {
            TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_no_profile_id' => $player->id]);
        }
        $this->postJson(route('backend.team-schedule.auto', $this->draw), $this->payload())->assertOk();
        $this->assertSame('09:10', $second->fresh()->scheduled_at->format('H:i'));
    }

    public function test_manual_save_rejects_nonexistent_court_and_overlapping_booking(): void
    {
        $fixture = $this->fixture();
        foreach (['Court 0', 'Court 3', 'Unknown court'] as $court) {
            $this->postJson(route('backend.team-schedule.save', $this->draw), ['fixture_id' => $fixture->id,
                'scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $this->venue, 'court_label' => $court])
                ->assertUnprocessable()->assertJsonValidationErrors('court_label');
        }
        $this->fixture(null, ['scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $this->venue,
            'court_label' => 'Court 1', 'duration_min' => 60]);
        $this->postJson(route('backend.team-schedule.save', $this->draw), ['fixture_id' => $fixture->id,
            'scheduled_at' => '2026-10-10 08:30:00', 'venue_id' => $this->venue, 'court_label' => 'Court 1'])
            ->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->assertNull($fixture->fresh()->scheduled_at);
    }

    public function test_event_schedule_data_and_controls_use_event_and_draw_routes(): void
    {
        $this->fixture();
        DB::table('venues')->insert(['name' => 'Unrelated venue']);
        $this->getJson(route('backend.team-schedule.all.data', $this->event))->assertOk()
            ->assertJsonCount(1, 'venues')->assertJsonPath('draws.0.id', $this->draw->id);
        $this->get(route('backend.team-schedule.all', $this->event))
            ->assertRedirect(route('backend.event-venue-schedule.index', $this->event));
    }

    public function test_no_capacity_reports_skipped_without_overwriting_bookings(): void
    {
        $fixture = $this->fixture();
        $payload = $this->payload();
        $payload['end'] = '2026-10-10 08:30:00';
        $this->postJson(route('backend.team-schedule.auto', $this->draw), $payload)
            ->assertOk()->assertJsonPath('count', 0)->assertJsonCount(1, 'skipped');
        $this->assertNull($fixture->fresh()->scheduled_at);
    }

    public function test_super_user_cannot_save_or_delete_scores_on_locked_draw(): void
    {
        $fixture = $this->fixture();
        $this->draw->update(['locked' => true]);
        $this->postJson(route('backend.team-fixtures.insertScore', $fixture), ['set1_home' => 6, 'set1_away' => 1])->assertStatus(409);
        $this->deleteJson(route('backend.team-fixtures.destroyResult', $fixture))->assertStatus(409);
        $this->assertDatabaseCount('team_fixture_results', 0);
    }

    public function test_invalid_reset_preserves_existing_schedule(): void
    {
        $fixture = $this->fixture(null, ['scheduled_at' => '2026-10-10 08:00:00',
            'venue_id' => $this->venue, 'court_label' => 'Court 1', 'scheduled' => 1]);
        $this->postJson(route('backend.draw.schedule.reset', $this->draw), ['duration' => 10])->assertUnprocessable();
        $this->assertSame('08:00', $fixture->fresh()->scheduled_at->format('H:i'));
        $this->assertSame($this->venue, (int) $fixture->fresh()->venue_id);
    }

    public function test_reset_with_foreign_venue_rolls_back_without_clearing(): void
    {
        $fixture = $this->fixture(null, ['scheduled_at' => '2026-10-10 08:00:00']);
        $foreign = DB::table('venues')->insertGetId(['name' => 'Other event venue']);
        $payload = $this->payload();
        $payload['venues'] = [$foreign];
        $this->postJson(route('backend.draw.schedule.reset', $this->draw), $payload)->assertUnprocessable();
        $this->assertNotNull($fixture->fresh()->scheduled_at);
    }

    public function test_super_user_cannot_schedule_locked_draw(): void
    {
        $fixture = $this->fixture();
        $this->draw->update(['locked' => true]);
        $this->postJson(route('backend.team-schedule.auto', $this->draw), $this->payload())->assertStatus(409);
        $this->postJson(route('backend.team-schedule.save', $this->draw), ['fixture_id' => $fixture->id,
            'scheduled_at' => '2026-10-10 08:00:00'])->assertStatus(409);
        $this->assertNull($fixture->fresh()->scheduled_at);
    }

    public function test_clear_all_is_event_scoped_and_atomic_when_one_draw_is_protected(): void
    {
        $first = $this->fixture(null, ['scheduled_at' => '2026-10-10 08:00:00']);
        $protected = $this->makeDraw($this->event);
        $protected->update(['published' => true]);
        $second = $this->fixture($protected, ['scheduled_at' => '2026-10-10 09:00:00']);
        $foreign = $this->fixture($this->makeDraw(Event::factory()->create(['eventType' => 3])),
            ['scheduled_at' => '2026-10-10 10:00:00']);
        $this->postJson(route('backend.team-schedule.all.clear', $this->event))->assertStatus(409);
        $this->assertNotNull($first->fresh()->scheduled_at);
        $this->assertNotNull($second->fresh()->scheduled_at);
        $protected->update(['published' => false]);
        $this->postJson(route('backend.team-schedule.all.clear', $this->event))->assertOk();
        $this->assertNull($first->fresh()->scheduled_at);
        $this->assertNull($second->fresh()->scheduled_at);
        $this->assertNotNull($foreign->fresh()->scheduled_at);
    }

    public function test_recorded_result_survives_super_user_delete_and_rebuild_attempts(): void
    {
        $fixture = $this->fixture();
        DB::table('team_fixture_results')->insert(['team_fixture_id' => $fixture->id,
            'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        $this->deleteJson(route('backend.team-fixtures.destroy', $fixture))->assertStatus(409);
        $this->postJson(route('headoffice.recreateFixturesForDraw', $this->draw))->assertStatus(409);
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertDatabaseCount('team_fixture_results', 1);
    }

    public function test_manual_save_rejects_foreign_fixture_and_foreign_venue(): void
    {
        $fixture = $this->fixture();
        $other = $this->makeDraw(Event::factory()->create(['eventType' => 3]));
        $this->postJson(route('backend.team-schedule.save', $other), ['fixture_id' => $fixture->id,
            'scheduled_at' => '2026-10-10 08:00:00'])->assertNotFound();
        $foreign = DB::table('venues')->insertGetId(['name' => 'Foreign court']);
        $this->postJson(route('backend.team-schedule.save', $this->draw), ['fixture_id' => $fixture->id,
            'venue_id' => $foreign])->assertUnprocessable();
        $this->assertNull($fixture->fresh()->venue_id);
    }

    public function test_schedule_only_edit_keeps_fixture_pending_and_creates_no_score(): void
    {
        $fixture = $this->fixture();
        $this->putJson(route('backend.team-fixtures.update', $fixture), ['scheduled_at' => '2026-10-10 08:00:00',
            'venue_id' => $this->venue, 'court_label' => 'Court 1', 'duration_min' => 60,
            'set1_home' => null, 'set1_away' => null, 'set2_home' => null, 'set2_away' => null,
            'set3_home' => null, 'set3_away' => null])->assertRedirect();
        $this->assertSame(0, $fixture->fresh()->match_status);
        $this->assertDatabaseCount('team_fixture_results', 0);
    }

    public function test_auto_scheduling_is_atomic_when_later_draw_is_locked(): void
    {
        $fixture = $this->fixture();
        $other = $this->makeDraw($this->event);
        $other->venues()->attach($this->venue, ['num_courts' => 2]);
        $other->update(['locked' => true]);
        $this->postJson(route('backend.team-schedule.all.auto', $this->event), $this->payload())->assertStatus(409);
        $this->assertNull($fixture->fresh()->scheduled_at);
    }
}
