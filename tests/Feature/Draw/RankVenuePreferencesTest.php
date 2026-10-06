<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamFixturePlayer, TeamPlayer, TeamTie, User, Venue};
use App\Services\Scheduling\{EventVenueScheduleService, RankVenuePreferences, UnifiedTeamScheduleService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RankVenuePreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function setupEvent(): array
    {
        $event = Event::factory()->create(['start_date' => '2026-10-09', 'end_date' => '2026-10-11']);
        $type = DB::table('draw_types')->insertGetId(['type' => 'team', 'drawTypeName' => 'Team', 'btn_color' => 'primary']);
        $draws = collect(['u/10 Boys', 'u/10 Girls', 'u/12 Boys'])->map(fn ($name) => Draw::factory()->create(['event_id' => $event->id, 'drawType_id' => $type, 'drawName' => $name]));
        $venues = collect(['Hermanus Sports Club', 'Hermanus High School', 'Hermanus Primary School'])->map(fn ($name) => Venue::forceCreate(['name' => $name]));
        foreach ($venues as $venue) {
            $event->venues()->attach($venue->id, ['num_courts' => 2]);
            foreach (['1', '2'] as $label) DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => $label, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($draws as $draw) $draw->venues()->attach($venue->id, ['num_courts' => 2]);
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return [$event, $draws, $venues];
    }

    private function rubber(Draw $draw, array $home, array $away, bool $imported = false): TeamFixture
    {
        $category = CategoryEvent::factory()->create(['event_id' => $draw->event_id]);
        $teams = [Team::factory()->create(['category_event_id' => $category->id]), Team::factory()->create(['category_event_id' => $category->id])];
        $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => TeamTie::where('draw_id', $draw->id)->count() + 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'status' => TeamTie::STATUS_DRAFT]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'round_nr' => 1, 'tie_nr' => $tie->tie_nr,
            'rubber_sequence' => 1, 'match_nr' => TeamFixture::where('draw_id', $draw->id)->count() + 1,
            'fixture_type' => count($home) === 2 ? 2 : 1, 'player_count_per_team' => count($home), 'match_status' => 0]);
        foreach ($home as $slot => $rank) {
            $row = ['team_fixture_id' => $fixture->id, 'slot_no' => $slot + 1];
            foreach ([$rank, $away[$slot]] as $side => $position) {
                if ($imported && $side === 1) {
                    $player = NoProfileTeamPlayer::create(['team_id' => $teams[$side]->id, 'rank' => $position, 'name' => 'Imported', 'surname' => 'Rank '.$position, 'pay_status' => 0]);
                    $row['team2_no_profile_id'] = $player->id;
                } else {
                    $player = Player::factory()->create();
                    TeamPlayer::create(['team_id' => $teams[$side]->id, 'player_id' => $player->id, 'rank' => $position, 'pay_status' => 0]);
                    $row['team'.($side + 1).'_id'] = $player->id;
                }
            }
            TeamFixturePlayer::create($row);
        }
        return $fixture;
    }

    private function rules($draws, $venues): array
    {
        return collect([[1, 4], [5, 6], [7, 8]])->map(fn ($range, $i) => ['draw_ids' => $draws->take(2)->pluck('id')->all(),
            'min_rank' => $range[0], 'max_rank' => $range[1], 'venue_id' => $venues[$i]->id])->all();
    }

    private function schedulingOptions($draws, array $extra = []): array
    {
        return $extra + ['start' => '2026-10-10 08:00:00', 'end' => '2026-10-10 18:00:00', 'duration' => 60,
            'wave_minutes' => 60, 'court_gap' => 0, 'player_rest' => 0, 'draw_ids' => $draws->take(2)->pluck('id')->all(), 'allow_partial' => true];
    }

    public function test_boys_girls_and_imported_doubles_use_actual_roster_bands_and_keep_saved_matches(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixtures = [$this->rubber($draws[0], [1], [4]), $this->rubber($draws[1], [5, 6], [5, 6], true), $this->rubber($draws[0], [7], [8])];
        $fixed = $this->rubber($draws[1], [5], [6]);
        $fixed->update(['scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $venues[0]->id, 'court_label' => '1', 'duration_min' => 60]);
        $before = $fixed->fresh()->getAttributes();
        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions($draws, ['rank_venue_preferences' => $this->rules($draws, $venues)]));
        $matches = collect($preview['matches'])->keyBy('fixture_id');
        foreach ($fixtures as $i => $fixture) $this->assertSame($venues[$i]->id, $matches[$fixture->id]['venue_id']);
        $this->assertArrayNotHasKey($fixed->id, $matches->all());
        $this->assertSame($before, $fixed->fresh()->getAttributes());
        $this->assertSame([5, 5, 6, 6], $matches[$fixtures[1]->id]['rank_preference']['ranks']);
        if (getenv('CT_RANK_BROWSER_FIXTURE') === '1') {
            DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode($preview['input']), 'created_at' => now(), 'updated_at' => now()]);
            $response = $this->get(route('backend.event-venue-schedule.index', ['event' => $event, 'draw_ids' => $draws->take(2)->pluck('id')->all()]))->assertOk();
            file_put_contents(storage_path('framework/testing/scheduler-rank-bands.html'), $response->getContent());
            file_put_contents(storage_path('framework/testing/scheduler-rank-bands.json'), json_encode($preview, JSON_THROW_ON_ERROR));
        }
    }

    public function test_cross_band_policy_can_prefer_highest_rank_or_leave_manual_and_manual_override_warns(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixture = $this->rubber($draws[0], [2], [5]);
        $options = $this->schedulingOptions($draws, ['rank_venue_preferences' => $this->rules($draws, $venues)]);
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($event, $options);
        $this->assertSame($venues[0]->id, $preview['matches'][0]['venue_id']);
        $this->assertTrue(collect($preview['warnings'])->contains(fn ($w) => str_contains($w, 'highest-ranked')));
        $manual = $service->preview($event, ['cross_band_policy' => 'manual'] + $options);
        $this->assertSame([], $manual['matches']);
        $this->assertStringContainsString('manually', $manual['unscheduled'][0]['reason']);
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode($preview['input']), 'created_at' => now(), 'updated_at' => now()]);
        $warnings = app(UnifiedTeamScheduleService::class)->warnings($fixture, ['scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $venues[2]->id]);
        $this->assertTrue(collect($warnings)->contains(fn ($w) => str_contains($w, 'differs')));
        $this->postJson(route('backend.event-venue-schedule.manual-assignment', $event), ['fixture_kind' => 'team', 'fixture_id' => $fixture->id,
            'scheduled_at' => '2026-10-10 08:00:00', 'venue_id' => $venues[2]->id, 'court' => '1', 'duration' => 60, 'court_gap' => 0, 'player_rest' => 0])->assertOk();
        $this->assertSame($venues[2]->id, $fixture->fresh()->venue_id);
    }

    public function test_preferred_venue_capacity_can_fall_back_and_unknown_template_positions_do_not_route(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixture = $this->rubber($draws[0], [1], [2]);
        $foreign = Draw::factory()->create();
        foreach (['1', '2'] as $court) TeamFixture::create(['draw_id' => $foreign->id, 'scheduled_at' => '2026-10-10 08:00:00',
            'venue_id' => $venues[0]->id, 'court_label' => $court, 'duration_min' => 120, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0]);
        $preview = app(EventVenueScheduleService::class)->preview($event, $this->schedulingOptions($draws, ['end' => '2026-10-10 09:00:00', 'rank_venue_preferences' => $this->rules($draws, $venues)]));
        $this->assertNotSame($venues[0]->id, $preview['matches'][0]['venue_id']);
        $this->assertTrue(collect($preview['warnings'])->contains(fn ($w) => str_contains($w, 'unavailable')));
        TeamPlayer::where('player_id', $fixture->fixturePlayers->first()->team1_id)->delete();
        $draws[0]->update(['team_format_snapshot' => ['rubbers' => [['sequence' => 1, 'home_positions' => [1], 'away_positions' => [2]]]]]);
        $unknown = app(RankVenuePreferences::class)->choices(collect([$fixture->fresh()]), $this->rules($draws, $venues), 'highest_ranked')[$fixture->id];
        $this->assertNull($unknown['venue_id']);
        $this->assertStringContainsString('unavailable', $unknown['warning']);
    }

    public function test_changed_canonical_rank_invalidates_apply_and_unassigned_saved_rule_warns_instead_of_blocking(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixture = $this->rubber($draws[0], [1], [2]);
        $options = $this->schedulingOptions($draws, ['rank_venue_preferences' => $this->rules($draws, $venues)]);
        $service = app(EventVenueScheduleService::class);
        $before = $service->preview($event, $options);
        TeamPlayer::where('player_id', $fixture->fixturePlayers->first()->team1_id)->update(['rank' => 3]);
        $after = $service->preview($event, $options);
        $this->assertNotSame($before['revision'], $after['revision']);
        try { $service->apply($event, $options, $before['revision']); $this->fail('Stale rank revision accepted.'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString('changed', $e->getMessage()); }
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode($before['input']), 'created_at' => now(), 'updated_at' => now()]);
        $draws[0]->venues()->detach($venues[0]->id);
        $fallback = $service->preview($event, $this->schedulingOptions($draws));
        $this->assertCount(1, $fallback['matches']);
        $this->assertTrue(collect($fallback['warnings'])->contains(fn ($w) => str_contains($w, 'needs review')));
    }
    private function savePayload($draws, $venues, array $schedule): array
    {
        return ['venues' => $venues->map(fn ($v) => ['id' => $v->id, 'courts' => 2])->all(),
            'assignments' => $draws->map(fn ($d) => ['draw_id' => $d->id, 'venue_ids' => $venues->pluck('id')->all(),
                'court_allocations' => $venues->map(fn ($v) => ['venue_id' => $v->id, 'court_labels' => ['1', '2']])->all()])->all(),
            'schedule' => $schedule + ['draw_starts' => [], 'venue_starts' => [], 'reschedule_existing' => false]];
    }

    public function test_setup_only_save_preserves_timing_other_draws_and_saved_matches(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixture = $this->rubber($draws[0], [1], [2]);
        $fixture->update(['scheduled_at' => '2026-10-10 09:00:00', 'venue_id' => $venues[0]->id, 'court_label' => '1', 'duration_min' => 60]);
        $beforeFixture = $fixture->fresh()->getAttributes();
        $stored = $this->schedulingOptions($draws, [
            'draw_starts' => [['draw_id' => $draws[1]->id, 'start' => '2026-10-10 10:00:00']],
            'venue_starts' => [], 'reschedule_existing' => true, 'cross_band_policy' => 'manual',
            'draw_rounds' => [['draw_id' => $draws[1]->id, 'rounds' => [2]]],
            'rank_venue_preferences' => $this->rules($draws, $venues),
        ]);
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode($stored), 'created_at' => now(), 'updated_at' => now()]);
        $newRule = ['draw_ids' => [$draws[0]->id], 'min_rank' => 1, 'max_rank' => 8, 'venue_id' => $venues[0]->id];
        $payload = $this->savePayload($draws->take(1), $venues, []);
        $payload['setup_only'] = true;
        $payload['schedule'] = ['rank_preference_draw_ids' => [$draws[0]->id], 'rank_venue_preferences' => [$newRule],
            'duration' => -1, 'start' => 'invalid dirty timing', 'cross_band_policy' => 'highest_ranked'];
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), $payload)->assertOk()
            ->assertJsonPath('message', 'Venues, courts and position bands saved.')->assertJsonPath('unscheduled', 0);
        $saved = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        foreach ($stored as $key => $value) {
            if ($key !== 'rank_venue_preferences') $this->assertEquals($value, $saved[$key], $key);
        }
        $this->assertEquals([$newRule], app(RankVenuePreferences::class)->active($saved['rank_venue_preferences'], [$draws[0]->id]));
        $this->assertCount(3, app(RankVenuePreferences::class)->active($saved['rank_venue_preferences'], [$draws[1]->id]));
        $this->assertSame($beforeFixture, $fixture->fresh()->getAttributes());
        $this->assertSame(6, (int) $draws[1]->venues()->sum('draw_venues.num_courts'));
    }

    public function test_setup_only_cannot_remove_booked_venues_or_courts(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixture = $this->rubber($draws[0], [1], [2]);
        $fixture->update(['scheduled_at' => '2026-10-10 09:00:00', 'venue_id' => $venues[0]->id, 'court_label' => '1', 'duration_min' => 60]);
        $before = $fixture->fresh()->getAttributes();
        $payload = $this->savePayload($draws->take(1), $venues, []);
        $payload['setup_only'] = true;
        $payload['schedule'] = ['rank_preference_draw_ids' => [$draws[0]->id], 'rank_venue_preferences' => []];
        $withoutVenue = $payload;
        $withoutVenue['assignments'][0]['venue_ids'] = $venues->skip(1)->pluck('id')->all();
        $withoutVenue['assignments'][0]['court_allocations'] = array_slice($payload['assignments'][0]['court_allocations'], 1);
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), $withoutVenue)->assertUnprocessable();
        $payload['assignments'][0]['court_allocations'][0]['court_labels'] = ['2'];
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), $payload)->assertUnprocessable();
        $this->assertSame($before, $fixture->fresh()->getAttributes());
        $this->assertSame(3, $draws[0]->venues()->count());
        $this->assertDatabaseCount('draw_venue_court_allocations', 0);
        $this->assertDatabaseCount('event_venue_schedule_drafts', 0);
    }

    public function test_setup_only_rejects_unrelated_band_scope_and_unauthorized_actor(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $payload = $this->savePayload($draws->take(1), $venues, []);
        $payload['setup_only'] = true;
        $payload['schedule'] = ['rank_preference_draw_ids' => [$draws[1]->id], 'rank_venue_preferences' => []];
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), $payload)->assertUnprocessable();
        $payload['schedule']['rank_preference_draw_ids'] = [$draws[0]->id];
        $this->actingAs(User::factory()->create())->postJson(route('backend.event-venue-schedule.assignments', $event), $payload)->assertForbidden();
        $this->assertDatabaseCount('draw_venue_court_allocations', 0);
        $this->assertDatabaseCount('event_venue_schedule_drafts', 0);
    }

    public function test_http_save_replaces_only_selected_team_draw_rules_and_preview_accepts_unsaved_rules(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixture = $this->rubber($draws[0], [5], [6]);
        $old = $this->rules($draws, $venues);
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode(['rank_venue_preferences' => $old]), 'created_at' => now(), 'updated_at' => now()]);
        $new = [['draw_ids' => [$draws[0]->id], 'min_rank' => 1, 'max_rank' => 8, 'venue_id' => $venues[2]->id]];
        $schedule = $this->schedulingOptions($draws, ['rank_venue_preferences' => $new, 'rank_preference_draw_ids' => [$draws[0]->id], 'cross_band_policy' => 'highest_ranked']);
        $saved = $this->postJson(route('backend.event-venue-schedule.assignments', $event), $this->savePayload($draws, $venues, $schedule));
        $this->assertSame(200, $saved->status(), $saved->getContent());
        $stored = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertCount(4, $stored['rank_venue_preferences']);
        $girlRules = app(RankVenuePreferences::class)->active($stored['rank_venue_preferences'], [$draws[1]->id]);
        $this->assertSame(array_column($old, 'venue_id'), array_column($girlRules, 'venue_id'));
        $changed = [['draw_ids' => [$draws[0]->id], 'min_rank' => 1, 'max_rank' => 8, 'venue_id' => $venues[1]->id]];
        $request = $this->schedulingOptions($draws, ['rank_venue_preferences' => $changed]);
        $preview = $this->postJson(route('backend.event-venue-schedule.preview', $event), $request)->assertOk()->json();
        $this->assertSame($venues[1]->id, $preview['matches'][0]['venue_id']);
        $this->assertSame($changed, $preview['input']['rank_venue_preferences']);
        $this->postJson(route('backend.event-venue-schedule.apply', $event), ['revision' => $preview['revision']] + $this->schedulingOptions($draws, ['rank_venue_preferences' => $new]))->assertUnprocessable();
        $this->assertNull($fixture->fresh()->scheduled_at);
        $reopen = $this->get(route('backend.event-venue-schedule.index', ['event' => $event, 'draw_ids' => [$draws[1]->id]]))->assertOk();
        $reopen->assertSee('cross-band-policy')->assertSee('Hermanus Primary School');
    }

    public function test_http_accepts_three_shared_boys_and_girls_bands_with_repeated_draw_ids(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $this->rubber($draws[0], [1], [2]);
        $rules = $this->rules($draws, $venues);
        $schedule = $this->schedulingOptions($draws, ['rank_venue_preferences' => $rules, 'rank_preference_draw_ids' => $draws->take(2)->pluck('id')->all()]);
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), $this->savePayload($draws, $venues, $schedule))->assertOk();
        $this->postJson(route('backend.event-venue-schedule.preview', $event), $schedule)->assertOk()->assertJsonCount(3, 'input.rank_venue_preferences');
        $stored = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertEquals($rules, $stored['rank_venue_preferences']);
    }

    public function test_explicit_venue_unassignment_clears_only_affected_rank_mapping_with_a_warning(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $old = $this->rules($draws, $venues);
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode(['rank_venue_preferences' => $old]), 'created_at' => now(), 'updated_at' => now()]);
        $new = array_map(fn ($rule) => $rule + [], array_slice($old, 1));
        foreach ($new as &$rule) $rule['draw_ids'] = [$draws[0]->id];
        unset($rule);
        $payload = $this->savePayload($draws, $venues, $this->schedulingOptions($draws,
            ['rank_venue_preferences' => $new, 'rank_preference_draw_ids' => [$draws[0]->id]]));
        $payload['assignments'][0]['venue_ids'] = $venues->slice(1)->pluck('id')->all();
        $payload['assignments'][0]['court_allocations'] = array_slice($payload['assignments'][0]['court_allocations'], 1);
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), $payload)->assertOk()->assertJsonCount(1, 'warnings');
        $stored = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertCount(2, app(RankVenuePreferences::class)->active($stored['rank_venue_preferences'], [$draws[0]->id]));
        $girlRules = app(RankVenuePreferences::class)->active($stored['rank_venue_preferences'], [$draws[1]->id]);
        $this->assertCount(3, $girlRules);
        $this->assertSame(array_column($old, 'venue_id'), array_column($girlRules, 'venue_id'));
    }

    public function test_rank_rules_reject_foreign_scope_unassigned_venues_overlaps_and_unauthorized_users(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $this->rubber($draws[0], [1], [2]);
        $foreign = Draw::factory()->create();
        $outsideVenue = Venue::forceCreate(['name' => 'Unassigned venue']);
        $base = $this->schedulingOptions($draws);
        foreach ([
            [['draw_ids' => [$foreign->id], 'min_rank' => 1, 'max_rank' => 4, 'venue_id' => $venues[0]->id]],
            [['draw_ids' => [$draws[0]->id], 'min_rank' => 1, 'max_rank' => 4, 'venue_id' => $outsideVenue->id]],
            [['draw_ids' => [$draws[0]->id], 'min_rank' => 1, 'max_rank' => 4, 'venue_id' => $venues[0]->id], ['draw_ids' => [$draws[0]->id], 'min_rank' => 4, 'max_rank' => 6, 'venue_id' => $venues[1]->id]],
        ] as $rules) {
            $this->postJson(route('backend.event-venue-schedule.preview', $event), ['rank_venue_preferences' => $rules] + $base)->assertUnprocessable();
        }
        $individual = Draw::factory()->create(['event_id' => $event->id]);
        $this->postJson(route('backend.event-venue-schedule.assignments', $event), $this->savePayload($draws, $venues,
            ['rank_venue_preferences' => [], 'rank_preference_draw_ids' => [$individual->id]] + $base))->assertUnprocessable();
        $this->assertDatabaseCount('event_venue_schedule_drafts', 0);
        $this->actingAs(User::factory()->create())->postJson(route('backend.event-venue-schedule.preview', $event),
            ['rank_venue_preferences' => $this->rules($draws, $venues)] + $base)->assertForbidden();
    }

    public function test_mixed_sources_and_valid_snapshot_ranks_are_used_but_foreign_snapshots_are_ignored(): void
    {
        [$event, $draws, $venues] = $this->setupEvent();
        $fixture = $this->rubber($draws[0], [5, 6], [5, 6], true);
        $tie = $fixture->teamTie;
        $map = [];
        foreach (['home', 'away'] as $side) {
            $primary = Team::findOrFail($tie->{$side.'_team_id'});
            $girls = Team::factory()->create(['category_event_id' => $primary->category_event_id]);
            $slot = $fixture->fixturePlayers()->where('slot_no', 2)->first();
            if ($side === 'home') TeamPlayer::where('player_id', $slot->team1_id)->update(['team_id' => $girls->id]);
            else NoProfileTeamPlayer::whereKey($slot->team2_no_profile_id)->update(['team_id' => $girls->id]);
            $map[$primary->id] = ['boys' => $primary->id, 'girls' => $girls->id, 'name' => $side.' mixed'];
        }
        $draws[0]->forceFill(['team_draw_selection' => ['mixed_sides' => $map]])->save();
        $first = $fixture->fixturePlayers()->where('slot_no', 1)->first();
        $first->forceFill(['participant_snapshot' => [1 => ['event_id' => 999999, 'source_team_id' => $tie->home_team_id,
            'profile_id' => $first->team1_id, 'imported_id' => null, 'rank' => 1, 'name' => 'Foreign snapshot']]])->save();
        $choice = app(RankVenuePreferences::class)->choices(collect([$fixture->fresh()]), $this->rules($draws, $venues), 'highest_ranked')[$fixture->id];
        $this->assertSame([5, 5, 6, 6], $choice['ranks']);
        $this->assertSame($venues[1]->id, $choice['venue_id']);
        $first->forceFill(['participant_snapshot' => [1 => ['event_id' => $event->id, 'source_team_id' => $tie->home_team_id,
            'profile_id' => $first->team1_id, 'imported_id' => null, 'rank' => 2, 'name' => 'Captured participant']]])->save();
        $captured = app(RankVenuePreferences::class)->choices(collect([$fixture->fresh()]), $this->rules($draws, $venues), 'highest_ranked')[$fixture->id];
        $this->assertSame([2, 5, 6, 6], $captured['ranks']);
        $this->assertSame($venues[0]->id, $captured['venue_id']);
    }

}
