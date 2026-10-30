<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, Fixture, FixtureResult, Player, Registration, User};
use App\Services\Draw\AlternateReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlternateReplacementTest extends TestCase
{
    use RefreshDatabase;

    private function setupDraw(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-10-30']);
        $category = Category::factory()->create(['name' => 'U/19 Boys']);
        $ce = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $ce->id, 'published' => true, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin', 'num_sets' => 1]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $old = Registration::factory()->create();
        $old->players()->attach(Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2009-01-01'])->id);
        $opponent = Registration::factory()->create();
        $opponent->players()->attach(Player::factory()->create()->id);
        $draw->registrations()->attach([$old->id, $opponent->id]);
        $player = Player::factory()->create(['name' => 'Incoming', 'surname' => 'Alternate', 'gender' => 1, 'dateOfBirth' => '2009-01-01']);
        $group = $draw->groups()->create(['name' => 'A']);
        $group->registrations()->attach([$old->id, $opponent->id]);
        $past = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR', 'registration1_id' => $old->id, 'registration2_id' => $opponent->id, 'winner_registration' => null, 'match_status' => 0]);
        $future = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR', 'registration1_id' => $old->id, 'registration2_id' => $opponent->id, 'winner_registration' => null, 'match_status' => 0]);
        return [$draw, $old, $player, $past, $future];
    }

    public function test_preplay_alternate_replaces_all_positions_without_financial_changes(): void
    {
        [$draw, $old, $player, $past, $future] = $this->setupDraw();
        $pastName = $old->display_name;
        $entryCount = DB::table('category_event_registrations')->count();
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id, 'show_alternate' => 1])->assertRedirect();
        $incoming = $future->fresh()->registration1;
        $this->assertSame('Incoming Alternate (alternate)', $incoming->display_name);
        $this->assertSame('Incoming Alternate (alternate)', $incoming->players->first()->full_name);
        $this->assertSame('Incoming Alternate', $player->fresh()->full_name);
        $this->assertSame($incoming->id, $past->fresh()->registration1_id);
        $this->assertSame($pastName, $old->fresh()->display_name);
        $this->assertSame($entryCount, DB::table('category_event_registrations')->count());
        $this->assertDatabaseCount('registration_order_items', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])
            ->assertRedirect(route('draw.alternate.show', $draw))->assertSessionHasErrors('replacement');
        $this->assertSame(1, count($draw->fresh()->team_draw_selection['singles_alternates']));
    }

    public function test_authorization_category_duplicate_and_locked_guards(): void
    {
        [$draw, $old, $player] = $this->setupDraw();
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $player->update(['gender' => 2]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $player->update(['gender' => 1, 'dateOfBirth' => '1990-01-01']);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $player->update(['dateOfBirth' => '2009-01-01']);
        $draw->update(['locked' => true]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertForbidden();
        $draw->update(['locked' => false]);
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->postJson(route('draw.alternate.store', $draw), $input)->assertForbidden();
        $this->assertNull($draw->fresh()->team_draw_selection);
    }

    public function test_optional_label_can_be_disabled(): void
    {
        [$draw, $old, $player, , $future] = $this->setupDraw();
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertRedirect();
        $this->assertSame('Incoming Alternate', $future->fresh()->registration1->display_name);
        $this->get(route('draw.alternate.show', $draw, ['q' => 'Incoming']))->assertOk()->assertSee('Show “(alternate)”');
    }

    public function test_own_started_match_blocks_but_unrelated_completed_match_does_not(): void
    {
        [$draw, $old, $player, $first] = $this->setupDraw();
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $first->update(['match_status' => 2]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict();
        $first->update(['match_status' => 0]);
        FixtureResult::factory()->create(['fixture_id' => $first->id, 'set_nr' => 1, 'registration1_score' => 1, 'registration2_score' => 0]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict();
        $first->fixtureResults()->delete();
        $other = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id, 'match_status' => 1]);
        FixtureResult::factory()->create(['fixture_id' => $other->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $before = $other->fresh()->toArray();
        $this->post(route('draw.alternate.store', $draw), $input)->assertRedirect();
        $this->assertSame($before, $other->fresh()->toArray());
    }

    public function test_bye_and_opponent_feeder_do_not_block_first_match_alternate(): void
    {
        [$draw, $old, $player, $target] = $this->setupDraw();
        $bye = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $old->id, 'registration2_id' => null,
            'match_status' => 3, 'winner_registration' => $old->id, 'parent_fixture_id' => $target->id]);
        $opponent = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => Registration::factory()->create()->id,
            'registration2_id' => Registration::factory()->create()->id, 'match_status' => 1, 'parent_fixture_id' => $target->id]);
        FixtureResult::factory()->create(['fixture_id' => $opponent->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertRedirect();
        $this->assertNotSame($old->id, $bye->fresh()->winner_registration);
    }

    public function test_stop_reset_does_not_erase_first_point_cutoff(): void
    {
        [$draw, $old, $player, $first] = $this->setupDraw();
        \App\Models\DrawAuditLog::create(['draw_id' => $draw->id, 'user_id' => auth()->id(), 'fixture_id' => $first->id, 'action' => 'match_started']);
        $this->postJson(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertConflict();
        $this->assertNull($draw->fresh()->team_draw_selection);
    }

    public function test_browser_conflict_returns_to_form_with_reason_and_selected_alternate(): void
    {
        [$draw, $old, $player, $first] = $this->setupDraw();
        \App\Models\DrawAuditLog::create(['draw_id' => $draw->id, 'user_id' => auth()->id(), 'fixture_id' => $first->id, 'action' => 'match_started']);
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id, 'q' => 'Incoming', 'show_alternate' => 0];
        $this->post(route('draw.alternate.store', $draw), $input)
            ->assertRedirect(route('draw.alternate.show', ['draw' => $draw, 'q' => 'Incoming']))
            ->assertSessionHasErrors(['replacement' => 'An alternate cannot join after this player’s first match has started.'])
            ->assertSessionHas('_old_input.player_id', $player->id);
        $this->get(route('draw.alternate.show', ['draw' => $draw, 'q' => 'Incoming']))
            ->assertOk()->assertSee('An alternate cannot join after this player’s first match has started.')
            ->assertSee('value="'.$player->id.'" selected', false);
        $this->assertNull($draw->fresh()->team_draw_selection);
        $this->assertSame($old->id, $first->fresh()->registration1_id);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_suspended_duplicate_and_cross_draw_source_are_rejected(): void
    {
        [$draw, $old, $player] = $this->setupDraw();
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $suspension = \App\Models\PlayerSuspension::create(['player_id' => $player->id, 'triggered_at' => today(), 'suspension_number' => 1,
            'duration_months' => 1, 'starts_at' => today(), 'ends_at' => today()->addMonth()]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $suspension->delete();
        $this->postJson(route('draw.alternate.store', $draw), array_replace($input, ['outgoing_registration_id' => Registration::factory()->create()->id]))->assertUnprocessable();
        $otherDraw = Draw::factory()->create(['event_id' => $draw->event_id, 'category_event_id' => $draw->category_event_id]);
        $reg = Registration::factory()->create();
        $reg->players()->attach($player->id);
        $otherDraw->registrations()->attach($reg->id);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $otherDraw->registrations()->detach();
        $draw->registrations()->attach($reg->id);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        $this->assertNull($draw->fresh()->team_draw_selection);
    }

    public function test_trials_existing_paid_entry_stays_untouched_and_inactive_entries_remain_blocked(): void
    {
        [$draw, $old, $player] = $this->setupDraw();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => \App\Models\EventType::INDIVIDUAL, 'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $draw->event->update(['eventType' => $type]);
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $reg = Registration::factory()->create();
        $reg->players()->attach($player->id);
        $entry = \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $reg->id, 'payment_status_id' => 1]);
        foreach (['refunded', 'cancelled', 'refund_requested', 'withdrawn'] as $inactive) {
            $entry->update(['status' => $inactive]);
            $this->postJson(route('draw.alternate.store', $draw), $input)->assertUnprocessable();
        }
        $entry->update(['status' => 'active']);
        $before = $entry->fresh()->toArray();
        $this->post(route('draw.alternate.store', $draw), $input)->assertRedirect();
        $this->assertSame($before, $entry->fresh()->toArray());
        $incomingId = $draw->fresh()->registrations()->whereHas('players', fn ($q) => $q->whereKey($player->id))->first()->id;
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry->fresh());
        $this->assertFalse($draw->registrations()->where('registrations.id', $incomingId)->exists());
        $this->assertFalse($draw->drawFixtures()->whereIn('match_status', [0, 2])->where(fn ($q) => $q->where('registration1_id', $incomingId)->orWhere('registration2_id', $incomingId))->exists());
    }

    public function test_normal_paid_alternate_withdrawal_removes_draw_participation(): void
    {
        [$draw, $old, $player, , $future] = $this->setupDraw();
        $reg = Registration::factory()->create();
        $reg->players()->attach($player->id);
        $entry = \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $reg->id, 'payment_status_id' => 1]);
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])->assertRedirect();
        $incomingId = $future->fresh()->registration1_id;
        app(\App\Domain\Entries\Services\EntryService::class)->withdrawEntryAsAdmin($entry->fresh(), auth()->user());
        $this->assertNull($future->fresh()->registration1_id);
        $this->assertFalse($draw->registrations()->where('registrations.id', $incomingId)->exists());
        $this->assertSame(1, $entry->fresh()->payment_status_id);
    }

    private function setupFeedIn(): array
    {
        [$draw, $old, $player] = $this->setupDraw();
        $draw->drawFixtures()->delete();
        $draw->groups()->each(fn ($group) => $group->registrations()->detach());
        $draw->registrations()->detach();
        $draw->update(['published' => false]);
        $draw->settings->update(['workflow' => 'feed_in_monrad', 'playoff_config' => ['challenge_playoffs' => true, 'challenge_pairs' => [2, 4, 6, 8, 10, 12, 14]]]);
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => \App\Models\EventType::INDIVIDUAL, 'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $draw->event->update(['eventType' => $type]);
        $entrants = Registration::factory()->count(15)->create()->prepend($old);
        $slots = [];
        foreach ($entrants as $i => $registration) {
            if ($registration->players()->count() === 0) $registration->players()->attach(Player::factory()->create(['gender' => 1, 'dateOfBirth' => '2009-01-01'])->id);
            $registration->categoryEvents()->attach($draw->category_event_id, ['status' => 'active', 'payment_status_id' => 1]);
            $path = strtr(str_pad(decbin($i), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        // Match the reported main_aba opening-round position.
        [$slots['aaaa'], $slots['abab']] = [$slots['abab'], $slots['aaaa']];
        $source = Registration::factory()->create();
        $source->players()->attach($player->id);
        $entry = \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $draw->category_event_id, 'registration_id' => $source->id, 'payment_status_id' => 1, 'status' => 'active']);
        $service = app(\App\Services\Draw\FlexibleMonradService::class);
        $service->save($draw->fresh(), ['size' => 16, 'mode' => 'feed_in_monrad', 'slots' => $slots, 'challenge_playoffs' => true], 0);
        $record = $service->generate($draw->fresh(), 1);
        $draw->update(['published' => true]);
        return [$draw->fresh(), $old, $player, $record, $entry];
    }

    public function test_feed_in_alternate_updates_saved_graph_and_survives_state_resolution_and_source_withdrawal(): void
    {
        [$draw, $old, $player, $record, $entry] = $this->setupFeedIn();
        $this->assertTrue($record->graph['challenge_playoffs']);
        $this->assertArrayHasKey('challenge_14', $record->graph['nodes']);
        $first = Fixture::findOrFail($record->fixture_map['main_aba']);
        $this->assertContains($old->id, [$first->registration1_id, $first->registration2_id]);
        $other = Fixture::findOrFail($record->fixture_map['main_bbb']);
        $other->update(['match_status' => 1, 'winner_registration' => $other->registration1_id]);
        FixtureResult::factory()->create(['fixture_id' => $other->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        \App\Models\OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $first->id, 'venue_id' => \App\Models\Venue::forceCreate(['name' => 'Test venue'])->id, 'court' => 1, 'time' => '2026-11-01 09:00:00']);
        $schedule = DB::table('order_of_plays')->get()->map(fn ($row) => (array) $row)->all();
        $otherBefore = $other->fresh()->getAttributes();
        $entryBefore = $entry->fresh()->getAttributes();
        $mapBefore = $record->fixture_map;
        $registrationCount = Registration::count();
        $this->get(route('draw.alternate.show', $draw))->assertOk();
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id, 'show_alternate' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame($mapBefore, $record->fixture_map);
        $incoming = $draw->registrations()->whereHas('players', fn ($q) => $q->whereKey($player->id))->sole();
        $this->assertSame(3, $record->revision);
        $this->assertSame($incoming->id, $record->draft['slots']['abab']['id']);
        $this->assertContains($incoming->id, $record->graph['players']);
        $this->assertNotContains($old->id, $record->graph['players']);
        $this->assertSame($incoming->id, $record->graph['nodes']['main_aba']['sources'][1]['id']);
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        $this->assertSame($registrationCount + 1, Registration::count());
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        $this->assertSame($schedule, DB::table('order_of_plays')->get()->map(fn ($row) => (array) $row)->all());
        $state = app(\App\Services\Draw\FlexibleMonradService::class)->state($draw->fresh());
        $this->assertFalse($state['players']->firstWhere('id', $incoming->id)['withdrawn']);
        $this->assertNull($state['players']->firstWhere('id', $entry->registration_id));
        $this->assertContains($incoming->id, ((array) $state['matches'])['main_aba']['players']);
        $this->putJson(route('flexible-monrad.score', [$draw, $first]), ['revision' => 2, 'sets' => [[6, 2]]])->assertConflict();
        $map = $record->fixture_map;
        app(\App\Services\Draw\FlexibleMonradService::class)->reconcileWithdrawals($draw->fresh(), $record->revision);
        $this->assertSame($map, $record->fresh()->fixture_map);
        $this->assertContains($incoming->id, [$first->fresh()->registration1_id, $first->fresh()->registration2_id]);
        // Legacy paid entry status is accepted by the canonical replacement source resolver.
        \Illuminate\Support\Facades\Schema::table('category_event_registrations', fn (\Illuminate\Database\Schema\Blueprint $table) => $table->string('status')->nullable()->change());
        $entry->update(['status' => null]);
        $state = app(\App\Services\Draw\FlexibleMonradService::class)->state($draw->fresh());
        $this->assertFalse($state['players']->firstWhere('id', $incoming->id)['withdrawn']);
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry->fresh());
        $this->assertFalse($draw->registrations()->where('registrations.id', $incoming->id)->exists());
        $this->assertContains($incoming->id, $record->fresh()->graph['withdrawn']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('registration_order_items', 0);
    }

    public function test_feed_in_started_or_incomplete_graph_is_rejected_without_mutation(): void
    {
        [$draw, $old, $player, $record] = $this->setupFeedIn();
        $first = Fixture::findOrFail($record->fixture_map['main_aba']);
        $input = ['outgoing_registration_id' => $old->id, 'player_id' => $player->id];
        $first->update(['match_status' => 2]);
        $count = Registration::count();
        $before = $record->getAttributes();
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict();
        $this->assertSame($before, $record->fresh()->getAttributes());
        $first->update(['match_status' => 0]);
        $map = $record->fixture_map;
        unset($map['main_aba']);
        $record->update(['fixture_map' => $map]);
        $before = $record->getAttributes();
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict();
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertSame($count, Registration::count());
        $this->assertContains($old->id, [$first->fresh()->registration1_id, $first->fresh()->registration2_id]);
        $this->assertNull($draw->fresh()->team_draw_selection);
        $map['main_aba'] = $first->id;
        $record->update(['fixture_map' => $map]);
        DB::table('category_event_registrations')->where('registration_id', $old->id)->where('category_event_id', $draw->category_event_id)
            ->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict()
            ->assertJsonPath('message', 'This player is no longer active in the draw. Use the withdrawal workflow.');
        $this->assertSame($count, Registration::count());
        $graph = $record->graph;
        $graph['players'] = 'invalid legacy snapshot';
        $record->update(['graph' => $graph]);
        $this->postJson(route('draw.alternate.store', $draw), $input)->assertConflict()
            ->assertJsonPath('message', 'This draw’s saved positions and fixtures are incomplete. Restore the draw before replacing a player.');
        $this->assertSame($graph, $record->fresh()->graph);
        $this->assertSame($count, Registration::count());
    }

    public function test_feed_in_replacement_cannot_use_foreign_or_different_player_entry_as_active_source(): void
    {
        [$draw, $old, $player, $record, $entry] = $this->setupFeedIn();
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $incoming = $draw->registrations()->whereHas('players', fn ($q) => $q->whereKey($player->id))->sole();
        $metadata = $incoming->competition_replacement;
        $foreign = Registration::factory()->create();
        $foreign->players()->attach($player->id);
        $foreign->categoryEvents()->attach(CategoryEvent::factory()->create()->id, ['status' => 'active', 'payment_status_id' => 1]);
        $record->refresh();
        $draft = $record->draft;
        $draft['additional_registration_ids'] = [$incoming->id];
        $record->update(['draft' => $draft]);
        $incoming->update(['competition_replacement' => array_replace($metadata, ['source_registration_id' => $foreign->id])]);
        $state = app(\App\Services\Draw\FlexibleMonradService::class)->state($draw->fresh());
        $this->assertTrue($state['players']->firstWhere('id', $incoming->id)['withdrawn']);
        $this->assertTrue($state['withdrawals_pending']);
        $incoming->update(['competition_replacement' => $metadata]);
        $incoming->players()->sync([Player::factory()->create()->id]);
        $state = app(\App\Services\Draw\FlexibleMonradService::class)->state($draw->fresh());
        $this->assertTrue($state['players']->firstWhere('id', $incoming->id)['withdrawn']);
        $this->assertTrue($state['withdrawals_pending']);
        $this->assertNull($entry->fresh()->withdrawn_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function scheduledFeedInSwap(): array
    {
        [$draw, $old, , $record] = $this->setupFeedIn();
        $firstFixture = Fixture::findOrFail($record->fixture_map['main_aba']);
        $secondFixture = Fixture::findOrFail($record->fixture_map['main_bba']);
        $second = $secondFixture->registration1_id;
        $venue = \App\Models\Venue::forceCreate(['name' => 'Swap test venue']);
        $draw->venues()->attach($venue->id, ['num_courts' => 3]);
        foreach ([[$firstFixture, 9, 1], [$secondFixture, 13, 2]] as [$fixture, $hour, $court]) {
            \App\Models\OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => $court, 'time' => sprintf('2026-10-30 %02d:00:00', $hour), 'duration_minutes' => 60, 'gap_minutes' => 10]);
        }
        return [$draw, $old, $second, $record, $firstFixture, $secondFixture, $venue];
    }

    public function test_generated_player_swap_keeps_fixture_times_courts_and_public_schedule_and_swaps_names(): void
    {
        [$draw, $old, $second, $record, $firstFixture, $secondFixture] = $this->scheduledFeedInSwap();
        $publication = app(\App\Services\Scheduling\SchedulePublicationService::class);
        $publication->publish($draw->event, ['draw_id' => $draw->id]);
        $publicBefore = $publication->publishedRows($draw->event)->keyBy('fixture_id');
        $snapshots = DB::table('published_schedule_assignments')->get()->map(fn ($row) => (array) $row)->all();
        $schedule = DB::table('order_of_plays')->get()->map(fn ($row) => (array) $row)->all();
        $recordBefore = $record->fresh();
        $count = Registration::count();
        $this->postJson(route('flexible-monrad.players.swap', $draw), ['first_registration_id' => $old->id, 'second_registration_id' => $second, 'revision' => $record->revision])
            ->assertOk()->assertJsonPath('revision', $record->revision + 1);
        $record->refresh();
        $this->assertSame($recordBefore->fixture_map, $record->fixture_map);
        $this->assertSame($second, $record->draft['slots']['abab']['id']);
        $this->assertSame($old->id, $record->draft['slots']['bbaa']['id']);
        $this->assertSame($second, $firstFixture->fresh()->registration2_id);
        $this->assertSame($old->id, $secondFixture->fresh()->registration1_id);
        $this->assertSame($schedule, DB::table('order_of_plays')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($snapshots, DB::table('published_schedule_assignments')->get()->map(fn ($row) => (array) $row)->all());
        $publicAfter = $publication->publishedRows($draw->event->fresh())->keyBy('fixture_id');
        $this->assertSame($publicBefore[$secondFixture->id]['participants'][0], $publicAfter[$firstFixture->id]['participants'][1]);
        $this->assertSame($publicBefore[$firstFixture->id]['participants'][1], $publicAfter[$secondFixture->id]['participants'][0]);
        $this->assertSame($count, Registration::count());
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'players_swapped']);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_generated_swap_rejects_stale_started_foreign_and_unauthorized_players(): void
    {
        [$draw, $old, $second, $record, , $secondFixture] = $this->scheduledFeedInSwap();
        $input = ['first_registration_id' => $old->id, 'second_registration_id' => $second, 'revision' => $record->revision];
        $url = route('flexible-monrad.players.swap', $draw);
        $before = $record->getAttributes();
        $this->postJson($url, array_replace($input, ['revision' => $record->revision - 1]))->assertConflict();
        $secondFixture->update(['match_status' => 2]);
        $this->postJson($url, $input)->assertConflict();
        $secondFixture->update(['match_status' => 0]);
        \App\Models\DrawAuditLog::create(['draw_id' => $draw->id, 'user_id' => auth()->id(), 'fixture_id' => $secondFixture->id, 'action' => 'match_started']);
        $this->postJson($url, $input)->assertConflict();
        $this->postJson($url, array_replace($input, ['second_registration_id' => Registration::factory()->create()->id]))->assertConflict();
        $this->actingAs(User::factory()->create()->assignRole('admin'))->postJson($url, $input)->assertForbidden();
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseMissing('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'players_swapped']);
    }

    public function test_generated_swap_rolls_back_when_new_player_time_conflicts_with_another_draw(): void
    {
        [$draw, $old, $second, $record, $firstFixture, , $venue] = $this->scheduledFeedInSwap();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $otherFixture = Fixture::factory()->create(['draw_id' => $other->id, 'registration1_id' => $old->id,
            'registration2_id' => Registration::factory()->create()->id]);
        \App\Models\OrderOfPlay::create(['draw_id' => $other->id, 'fixture_id' => $otherFixture->id,
            'venue_id' => $venue->id, 'court' => 3, 'time' => '2026-10-30 13:00:00', 'duration_minutes' => 60]);
        $before = $record->getAttributes();
        $fixtures = $draw->drawFixtures()->get()->map->getAttributes()->all();
        $schedule = DB::table('order_of_plays')->get()->map(fn ($row) => (array) $row)->all();
        $this->postJson(route('flexible-monrad.players.swap', $draw), ['first_registration_id' => $old->id, 'second_registration_id' => $second, 'revision' => $record->revision])
            ->assertUnprocessable()->assertJsonValidationErrors('swap');
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertSame($fixtures, $draw->drawFixtures()->get()->map->getAttributes()->all());
        $this->assertSame($schedule, DB::table('order_of_plays')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($old->id, $firstFixture->fresh()->registration2_id);
        $this->assertDatabaseMissing('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'players_swapped']);
    }

    public function test_trials_private_alternate_needs_no_paid_entry_and_survives_resolution_and_swap_without_financial_changes(): void
    {
        foreach (['no_entry', 'unpaid_entry'] as $mode) {
            [$draw, $old, $player, $record, $entry] = $this->setupFeedIn();
            if ($mode === 'no_entry') $entry->forceDelete();
            else $entry->update(['payment_status_id' => 2]);
            $tables = ['category_event_registrations', 'registration_orders', 'registration_order_items', 'transactions_pf', 'wallet_transactions', 'user_players'];
            $before = [];
            foreach ($tables as $table) $before[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
            $this->get(route('draw.alternate.show', $draw))->assertOk()->assertSee('An in-app paid entry is not required.');
            $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])
                ->assertRedirect()->assertSessionHasNoErrors();
            $incoming = $draw->registrations()->whereHas('players', fn ($q) => $q->whereKey($player->id))->sole();
            $this->assertSame('private', $incoming->competition_replacement['entry_mode']);
            $this->assertSame((int) $draw->category_event_id, $incoming->competition_replacement['category_event_id']);
            $this->assertSame($mode === 'no_entry' ? null : $entry->registration_id, $incoming->competition_replacement['source_registration_id']);
            $state = app(\App\Services\Draw\FlexibleMonradService::class)->state($draw->fresh());
            $this->assertFalse($state['players']->firstWhere('id', $incoming->id)['withdrawn']);
            app(\App\Services\Draw\FlexibleMonradService::class)->reconcileWithdrawals($draw->fresh(), $record->fresh()->revision);
            $target = $record->fresh()->draft['slots']['bbaa']['id'];
            $this->postJson(route('flexible-monrad.players.swap', $draw), ['first_registration_id' => $incoming->id,
                'second_registration_id' => $target, 'revision' => $record->fresh()->revision])->assertOk();
            $state = app(\App\Services\Draw\FlexibleMonradService::class)->state($draw->fresh());
            $this->assertFalse($state['players']->firstWhere('id', $incoming->id)['withdrawn']);
            $this->assertSame($incoming->id, $record->fresh()->draft['slots']['bbaa']['id']);
            foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->get()->map(fn ($row) => (array) $row)->all(), $table.' changed during private replacement');
        }
    }

    public function test_private_trial_alias_marker_cannot_bypass_its_category_or_draw_boundary(): void
    {
        [$draw, $old, $player, $record, $entry] = $this->setupFeedIn();
        $entry->forceDelete();
        $this->post(route('draw.alternate.store', $draw), ['outgoing_registration_id' => $old->id, 'player_id' => $player->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $incoming = $draw->registrations()->whereHas('players', fn ($q) => $q->whereKey($player->id))->sole();
        $metadata = $incoming->competition_replacement;
        foreach ([['category_event_id' => CategoryEvent::factory()->create()->id], ['draw_id' => Draw::factory()->create()->id], ['entry_mode' => null]] as $override) {
            $incoming->update(['competition_replacement' => array_replace($metadata, $override)]);
            $state = app(\App\Services\Draw\FlexibleMonradService::class)->state($draw->fresh());
            $this->assertTrue($state['players']->firstWhere('id', $incoming->id)['withdrawn']);
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
