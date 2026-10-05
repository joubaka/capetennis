<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\ScheduleConflictService;
use App\Models\{Draw, Event, Fixture, OrderOfPlay, Player, Registration, TeamFixture, TeamFixturePlayer, Venue};
use App\Services\Scheduling\{EventVenueScheduleService, UnifiedTeamScheduleService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnifiedEventSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function setupDraw(bool $team = true): array
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        if ($team) {
            $type = DB::table('draw_types')->insertGetId(['type' => 'team', 'drawTypeName' => 'Team', 'btn_color' => 'primary']);
            $draw->forceFill(['drawType_id' => $type])->save();
        }
        $venue = Venue::forceCreate(['name' => 'Main courts']);
        $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        return [$event, $draw, $venue];
    }

    private function rubber(Draw $draw, array $fields = []): TeamFixture
    {
        return TeamFixture::create($fields + ['draw_id' => $draw->id, 'round_nr' => 1,
            'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0]);
    }

    private function schedulingOptions(array $fields = []): array
    {
        return $fields + ['start' => '2026-10-10 08:00:00', 'end' => '2026-10-10 18:00:00',
            'duration' => 60, 'wave_minutes' => 60, 'court_gap' => 0, 'player_rest' => 30];
    }

    public function test_team_preview_is_read_only_and_apply_writes_only_team_storage(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $draw->update(['published' => true]);
        $rubber = $this->rubber($draw);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $this->schedulingOptions());
        $this->assertCount(1, $preview['matches']);
        $this->assertSame('team', $preview['matches'][0]['fixture_kind']);
        $this->assertSame('team:'.$rubber->id, $preview['matches'][0]['fixture_key']);
        $this->assertNull($rubber->fresh()->scheduled_at);
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertSame(1, $service->apply($event, $this->schedulingOptions(), $preview['revision'])['count']);
        $this->assertSame($venue->id, $rubber->fresh()->venue_id);
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertSame(0, $service->apply($event, $this->schedulingOptions(), $service->preview($event, $this->schedulingOptions())['revision'])['count']);
    }

    public function test_individual_scheduler_respects_team_court_labels_and_shared_profile_identity(): void
    {
        [$event, $draw, $venue] = $this->setupDraw(false);
        $player = Player::factory()->create();
        $registration = Registration::factory()->create();
        DB::table('player_registrations')->insert(['registration_id' => $registration->id, 'player_id' => $player->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'bracket_id' => 1,
            'registration1_id' => $registration->id, 'registration2_id' => Registration::factory()->create()->id]);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $booking = $this->rubber($foreign, ['scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $venue->id,
            'court_label' => 'Court 1', 'duration_min' => 60, 'scheduled' => 1]);
        TeamFixturePlayer::create(['team_fixture_id' => $booking->id, 'team1_id' => $player->id]);
        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions());
        $this->assertSame('2026-10-10 09:30:00', $preview['matches'][0]['scheduled_at']);
        $this->assertNotNull(app(ScheduleConflictService::class)->conflict($draw, $fixture, $venue->id, '2', '2026-10-10 08:00:00'));
    }

    public function test_team_manual_assignment_respects_an_individual_player_booking_at_another_venue(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $player = Player::factory()->create();
        $rubber = $this->rubber($draw);
        TeamFixturePlayer::create(['team_fixture_id' => $rubber->id, 'team1_id' => $player->id]);
        $registration = Registration::factory()->create();
        DB::table('player_registrations')->insert(['registration_id' => $registration->id, 'player_id' => $player->id]);
        $individual = Fixture::factory()->create(['draw_id' => Draw::factory()->create()->id,
            'registration1_id' => $registration->id]);
        $otherVenue = Venue::forceCreate(['name' => 'Away courts']);
        OrderOfPlay::create(['fixture_id' => $individual->id, 'draw_id' => $individual->draw_id,
            'venue_id' => $otherVenue->id, 'court' => '1', 'time' => '2026-10-10 08:00:00', 'duration_minutes' => 60]);
        $error = app(UnifiedTeamScheduleService::class)->manualError($event, $rubber->fresh(),
            ['venue_id' => $venue->id, 'court' => '2', 'scheduled_at' => '2026-10-10 08:00:00', 'duration' => 60]);
        $this->assertStringContainsString('conflict', $error);
    }

    public function test_venue_continuity_is_preferred_and_required_moves_warn(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $other = Venue::forceCreate(['name' => 'Other courts']);
        $draw->venues()->attach($other->id, ['num_courts' => 1]);
        $player = Player::factory()->create();
        $prior = $this->rubber($draw, ['scheduled_at' => '2026-10-10 07:00:00', 'venue_id' => $venue->id,
            'court_label' => 'Court 1', 'duration_min' => 30, 'scheduled' => 1]);
        TeamFixturePlayer::create(['team_fixture_id' => $prior->id, 'team1_id' => $player->id]);
        $next = $this->rubber($draw, ['match_nr' => 2]);
        TeamFixturePlayer::create(['team_fixture_id' => $next->id, 'team1_id' => $player->id]);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $this->schedulingOptions());
        $this->assertSame($venue->id, $preview['matches'][0]['venue_id']);
        $this->assertEmpty($preview['matches'][0]['venue_changes']);
        $preview = $service->preview($event, $this->schedulingOptions(['venue_ids' => [$other->id]]));
        $this->assertSame($other->id, $preview['matches'][0]['venue_id']);
        $this->assertCount(1, $preview['matches'][0]['venue_changes']);
        $this->assertTrue(collect($preview['warnings'])->contains(fn ($warning) => str_contains($warning, $player->name)));
    }

    public function test_round_progression_can_wait_for_all_round_rubbers_or_only_the_teams(): void
    {
        [$event, $draw] = $this->setupDraw();
        $first = $this->rubber($draw, ['region1' => 1, 'region2' => 2]);
        $unrelated = $this->rubber($draw, ['region1' => 3, 'region2' => 4, 'tie_nr' => 2]);
        $next = $this->rubber($draw, ['region1' => 1, 'region2' => 2, 'round_nr' => 2]);
        $service = app(UnifiedTeamScheduleService::class);
        $ready = $service->nodes($draw, 'team_ready')['team:'.$next->id]['dependencies'];
        $barrier = $service->nodes($draw, 'all_round')['team:'.$next->id]['dependencies'];
        $this->assertSame(['team:'.$first->id], $ready);
        $this->assertEqualsCanonicalizing(['team:'.$first->id, 'team:'.$unrelated->id], $barrier);
    }

    public function test_in_progress_rubbers_are_protected_and_foreign_selection_is_rejected(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $fixture = $this->rubber($draw, ['match_status' => 1]);
        $this->assertSame([], app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions())['matches']);
        $this->assertStringContainsString('play or results', app(UnifiedTeamScheduleService::class)->manualError($event, $fixture,
            ['venue_id' => $venue->id, 'court' => '1', 'scheduled_at' => '2026-10-10 08:00:00']));
        $foreign = Draw::factory()->create();
        $this->expectException(\InvalidArgumentException::class);
        app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions(['draw_ids' => [$foreign->id]]));
    }

    public function test_mixed_numeric_ids_are_distinct_and_bulk_unapply_is_atomic(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $rubber = $this->rubber($draw);
        $individualDraw = Draw::factory()->create(['event_id' => $event->id]);
        $individualDraw->venues()->attach($venue->id, ['num_courts' => 2]);
        $fixture = Fixture::factory()->create(['id' => $rubber->id, 'draw_id' => $individualDraw->id, 'round' => 1, 'bracket_id' => 1,
            'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id]);
        $this->assertSame($fixture->id, $rubber->id);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $this->schedulingOptions());
        $this->assertCount(2, collect($preview['matches'])->pluck('fixture_key')->unique());
        $service->apply($event, $this->schedulingOptions(), $preview['revision']);
        $this->assertDatabaseCount('order_of_plays', 1);
        $this->assertNotNull($rubber->fresh()->scheduled_at);
        $individualDraw->update(['locked' => true]);
        try {
            $service->unapply($event, null, $venue->id);
            $this->fail('Locked individual booking did not protect mixed unapply.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('locked', $exception->getMessage());
        }
        $this->assertNotNull($rubber->fresh()->scheduled_at);
        $this->assertDatabaseCount('order_of_plays', 1);
        $individualDraw->update(['locked' => false]);
        $this->assertSame(2, $service->unapply($event, null, $venue->id)['count']);
        $this->assertNull($rubber->fresh()->scheduled_at);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_new_foreign_booking_invalidates_preview_and_preserves_both_events(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $rubber = $this->rubber($draw);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $this->schedulingOptions());
        $foreignDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $booking = $this->rubber($foreignDraw, ['venue_id' => $venue->id, 'court_label' => 'Court 1',
            'scheduled_at' => '2026-10-10 08:00:00', 'duration_min' => 60]);
        try {
            $service->apply($event, $this->schedulingOptions(), $preview['revision']);
            $this->fail('External booking did not invalidate the preview.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('fresh preview', $exception->getMessage());
        }
        $this->assertNull($rubber->fresh()->scheduled_at);
        $this->assertSame('08:00', $booking->fresh()->scheduled_at->format('H:i'));
    }

    public function test_published_team_manual_assignment_accepts_named_courts_and_records_history(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $draw->update(['published' => true]);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id,
            'label' => 'Centre', 'active' => true]);
        $rubber = $this->rubber($draw);
        app(UnifiedTeamScheduleService::class)->assign($event, ['fixture_id' => $rubber->id,
            'venue_id' => $venue->id, 'court' => 'Centre', 'scheduled_at' => '2026-10-10 08:00:00', 'duration' => 60]);
        $this->assertSame('Centre', $rubber->fresh()->court_label);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'event_venue_schedule_adjusted']);
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_saved_successor_protects_round_progression_during_manual_and_automatic_replanning(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $first = $this->rubber($draw, ['region1' => 1, 'region2' => 2]);
        $later = $this->rubber($draw, ['region1' => 1, 'region2' => 2, 'round_nr' => 2,
            'scheduled_at' => '2026-10-10 09:00:00', 'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        $error = app(UnifiedTeamScheduleService::class)->manualError($event, $first,
            ['scheduled_at' => '2026-10-10 08:30:00', 'venue_id' => $venue->id, 'court' => '2',
                'duration' => 60, 'player_rest' => 30, 'round_progression' => 'team_ready']);
        $this->assertStringContainsString('saved later team tie', $error);
        $preview = app(EventVenueScheduleService::class)->preview($event,
            $this->schedulingOptions(['start' => '2026-10-10 08:30:00']));
        $this->assertCount(1, $preview['unscheduled']);
        $this->assertSame($first->id, $preview['unscheduled'][0]['fixture_id']);
        $this->assertSame('09:00', $later->fresh()->scheduled_at->format('H:i'));
    }

    public function test_applied_team_court_gap_is_preserved_in_later_previews_and_manual_assignments(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $draw->venues()->updateExistingPivot($venue->id, ['num_courts' => 1]);
        $first = $this->rubber($draw);
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions(['court_gap' => 15, 'player_rest' => 0]);
        $preview = $service->preview($event, $options);
        $service->apply($event, $options, $preview['revision']);
        $this->assertSame(15, (int) $first->fresh()->gap_minutes);
        $next = $this->rubber($draw, ['match_nr' => 2]);
        $preview = $service->preview($event, $this->schedulingOptions(['court_gap' => 0, 'player_rest' => 0]));
        $this->assertSame('2026-10-10 09:15:00', $preview['matches'][0]['scheduled_at']);
        $error = app(UnifiedTeamScheduleService::class)->manualError($event, $next,
            ['scheduled_at' => '2026-10-10 09:00:00', 'venue_id' => $venue->id, 'court' => '1', 'duration' => 60]);
        $this->assertStringContainsString('conflict', $error);
    }

    public function test_removing_a_team_prerequisite_requires_removing_its_saved_successors_too(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $other = Venue::forceCreate(['name' => 'Other courts']);
        $draw->venues()->attach($other->id, ['num_courts' => 1]);
        $first = $this->rubber($draw, ['region1' => 1, 'region2' => 2, 'scheduled_at' => '2026-10-10 08:00:00',
            'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        $later = $this->rubber($draw, ['region1' => 1, 'region2' => 2, 'round_nr' => 2,
            'scheduled_at' => '2026-10-10 10:00:00', 'venue_id' => $other->id, 'court_label' => '1', 'duration_min' => 60]);
        $service = app(UnifiedTeamScheduleService::class);
        foreach ([['fixtureId' => $first->id], ['venueId' => $venue->id]] as $selection) {
            try {
                $service->unapply($event, ...$selection);
                $this->fail('Saved successor did not protect its prerequisite.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('depends', $exception->getMessage());
            }
        }
        $this->assertNotNull($first->fresh()->scheduled_at);
        $this->assertNotNull($later->fresh()->scheduled_at);
        $this->assertSame(2, $service->unapply($event, $draw->id)['count']);
    }

    public function test_in_progress_flexible_monrad_match_without_sets_cannot_be_replanned(): void
    {
        [$event, $draw, $venue] = $this->setupDraw(false);
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw->update(['category_event_id' => $category->id]);
        $registrations = Registration::factory()->count(4)->create();
        $slots = [];
        foreach (['aa', 'ab', 'ba', 'bb'] as $index => $path) {
            $registrations[$index]->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $slots[$path] = ['type' => 'player', 'id' => $registrations[$index]->id];
        }
        $monrad = app(\App\Services\Draw\FlexibleMonradService::class);
        $monrad->save($draw, ['size' => 4, 'slots' => $slots], 0);
        $record = $monrad->generate($draw, 1);
        $fixture = Fixture::findOrFail($record->fixture_map['main_a']);
        $fixture->update(['match_status' => 1]);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-10 08:00:00', 'duration_minutes' => 60]);
        $service = app(EventVenueScheduleService::class);
        $options = $this->schedulingOptions(['replan_venue_ids' => [$venue->id]]);
        $preview = $service->preview($event, $options);
        $this->assertFalse(collect($preview['matches'])->contains('fixture_id', $fixture->id));
        $this->assertFalse(collect($preview['existing_matches'])->firstWhere('fixture_id', $fixture->id)['editable']);
        $service->apply($event, $options, $preview['revision']);
        $this->assertSame('2026-10-10 08:00:00', $fixture->fresh()->orderOfPlay->time);
    }

    public function test_saved_all_round_rule_protects_unrelated_team_prerequisites_on_removal(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id,
            'options' => json_encode(['round_progression' => 'all_round'])]);
        $first = $this->rubber($draw, ['region1' => 1, 'region2' => 2, 'scheduled_at' => '2026-10-10 08:00:00',
            'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        $later = $this->rubber($draw, ['region1' => 3, 'region2' => 4, 'round_nr' => 2,
            'scheduled_at' => '2026-10-10 10:00:00', 'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
        $service = app(UnifiedTeamScheduleService::class);
        $this->assertStringContainsString('depends', $service->removalError($event, [$first->id]));
        $this->assertNull($service->removalError($event, [$first->id], 'team_ready'));
        $this->assertNull($service->removalError($event, [$first->id, $later->id]));
        $this->expectException(\InvalidArgumentException::class);
        $service->unapply($event, fixtureId: $first->id);
    }

    public function test_saved_match_player_rest_does_not_add_court_turnaround_twice(): void
    {
        [$event, $draw, $venue] = $this->setupDraw();
        $this->rubber($draw, ['region1' => 1, 'region2' => 2, 'scheduled_at' => '2026-10-10 08:00:00',
            'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60, 'gap_minutes' => 30]);
        $next = $this->rubber($draw, ['region1' => 1, 'region2' => 2, 'round_nr' => 2]);
        $preview = app(EventVenueScheduleService::class)->preview($event,
            $this->schedulingOptions(['wave_minutes' => 1, 'court_gap' => 0, 'player_rest' => 10]));
        $row = collect($preview['matches'])->firstWhere('fixture_id', $next->id);
        $this->assertSame('2026-10-10 09:10:00', $row['scheduled_at']);
        $this->assertSame('2', $row['court']);
    }
}
