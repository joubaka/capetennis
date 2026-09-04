<?php

namespace Tests\Feature\Draw;

use App\Models\{Category, CategoryEvent, Draw, Event, Fixture, FlexibleMonradDraw, Player, RankingList, Registration, Series, SeriesRanking, User};
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlexibleMonradTest extends TestCase
{
    use RefreshDatabase;

    private function setupDraw(): array
    {
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $players = Registration::factory()->count(4)->create();
        foreach ($players as $player) $player->categoryEvents()->attach($category->id, ['status' => 'registered']);
        $slots = [];
        foreach (['aa', 'ab', 'ba', 'bb'] as $i => $path) $slots[$path] = ['type' => 'player', 'id' => $players[$i]->id];
        $this->actingAs($admin);
        return [$draw, ['size' => 4, 'slots' => $slots], $players];
    }

    public function test_draft_revision_isolation_generation_and_publication(): void
    {
        [$draw, $draft] = $this->setupDraw();
        $this->get(route('flexible-monrad.show', $draw))->assertRedirect(route('backend.draw.roundrobin.show', $draw));
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $draft, 'revision' => 0])->assertOk()->assertJsonPath('revision', 1);
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $draft, 'revision' => 0])->assertConflict();
        $this->get(route('public.flexible-monrad.show', $draw))->assertNotFound();
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 1])->assertOk()->assertJsonPath('generated', true);
        $this->assertSame(4, $draw->drawFixtures()->count());
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 2])->assertOk();
        $this->assertSame(4, $draw->drawFixtures()->count());
        $this->postJson(route('flexible-monrad.publish', $draw), ['revision' => 2, 'published' => true])->assertOk();
        $this->get(route('public.flexible-monrad.show', $draw))->assertOk();
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $draft, 'revision' => 3])->assertForbidden();
    }

    public function test_workspace_shows_the_active_series_ranking_for_the_draw_category(): void
    {
        [$draw, $draft] = $this->setupDraw();
        app(FlexibleMonradService::class)->save($draw, $draft, 0);
        $series = Series::factory()->create(['name' => 'CAT Tennis Series', 'year' => 2026]);
        $category = Category::factory()->create(['name' => 'Boys U14']);
        $draw->event->update(['series_id' => $series->id]);
        $draw->categoryEvent->update(['category_id' => $category->id]);
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $first = Player::factory()->create(['name' => 'Alex', 'surname' => 'Topseed']);
        $second = Player::factory()->create(['name' => 'Blake', 'surname' => 'Second']);
        $otherCategory = Category::factory()->create(['name' => 'Girls U14']);

        foreach ([
            [$first, 1, 320, $category->id, $list->id],
            [$second, 2, 275, $category->id, $list->id],
            [Player::factory()->create(['name' => 'Other', 'surname' => 'Category']), 1, 500, $otherCategory->id, null],
        ] as [$player, $position, $points, $categoryId, $rankingListId]) {
            SeriesRanking::create([
                'series_id' => $series->id,
                'ranking_list_id' => $rankingListId,
                'category_id' => $categoryId,
                'player_id' => $player->id,
                'rank_position' => $position,
                'total_points' => $points,
                'status' => 'reviewed',
                'run_id' => 'current-run',
            ]);
        }

        SeriesRanking::create([
            'series_id' => $series->id,
            'ranking_list_id' => $list->id,
            'category_id' => $category->id,
            'player_id' => Player::factory()->create(['name' => 'Old', 'surname' => 'Snapshot'])->id,
            'rank_position' => 1,
            'total_points' => 999,
            'status' => 'archived',
            'run_id' => 'old-run',
        ]);

        $this->get(route('backend.draw.roundrobin.show', $draw))
            ->assertOk()
            ->assertSee('Boys U14 rankings')
            ->assertSee('CAT Tennis Series')
            ->assertSee('Reviewed')
            ->assertSeeInOrder(['Alex Topseed', 'Blake Second'])
            ->assertDontSee('Other Category')
            ->assertDontSee('Old Snapshot');
    }

    public function test_foreign_withdrawn_duplicate_and_overlapping_assignments_are_rejected(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $foreign = Registration::factory()->create();
        $bad = $draft; $bad['slots']['aa']['id'] = $foreign->id;
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $bad, 'revision' => 0])->assertUnprocessable();
        $bad = $draft; $bad['slots']['aa']['id'] = $players[1]->id;
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $bad, 'revision' => 0])->assertUnprocessable();
        $bad = $draft; $bad['slots']['a'] = ['type' => 'bye'];
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $bad, 'revision' => 0])->assertUnprocessable();
        DB::table('category_event_registrations')->where('registration_id', $players[0]->id)->update(['status' => 'withdrawn']);
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $draft, 'revision' => 0])->assertUnprocessable();
        $this->assertDatabaseCount('flexible_monrad_draws', 0);
    }

    public function test_unassigned_slots_are_not_byes_and_other_event_admin_cannot_edit(): void
    {
        [$draw, $draft] = $this->setupDraw();
        unset($draft['slots']['aa']);
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $draft, 'revision' => 0])->assertOk();
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 1])->assertUnprocessable();
        $this->assertSame(0, $draw->drawFixtures()->count());
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->get(route('flexible-monrad.show', $draw))->assertForbidden();
        $this->putJson(route('flexible-monrad.save', $draw), ['draft' => $draft, 'revision' => 1])->assertForbidden();
    }

    public function test_published_scoring_both_paths_idempotency_and_guarded_correction(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $service->publish($draw, 2, true);
        $a = $record->fixture_map['main_a']; $b = $record->fixture_map['main_b']; $final = $record->fixture_map['main_final'];
        $url = fn ($id) => route('flexible-monrad.score', [$draw, $id]);
        $this->putJson($url($final), ['revision' => 3, 'sets' => [[6, 2]]])->assertUnprocessable();
        $this->putJson($url($a), ['revision' => 3, 'sets' => [[6, 2]]])->assertOk()->assertJsonPath('revision', 4);
        $this->putJson($url($a), ['revision' => 4, 'sets' => [[6, 2]]])->assertOk()->assertJsonPath('revision', 4);
        $this->assertSame(1, Fixture::find($a)->fixtureResults()->count());
        $this->putJson($url($b), ['revision' => 4, 'sets' => [[6, 2]]])->assertOk();
        $this->assertSame($players[0]->id, Fixture::find($final)->registration1_id);
        $placement = $draw->drawFixtures()->whereNotIn('id', [$a, $b, $final])->firstOrFail();
        $this->assertEquals([$players[1]->id, $players[3]->id], [$placement->registration1_id, $placement->registration2_id]);
        $this->putJson($url($final), ['revision' => 5, 'sets' => [[6, 3]]])->assertOk();
        $this->putJson($url($placement->id), ['revision' => 6, 'sets' => [[6, 4]]])->assertOk();
        $this->putJson($url($a), ['revision' => 7, 'sets' => [[2, 6]]])->assertConflict();
        $this->assertSame($players[0]->id, Fixture::find($final)->winner_registration);
        $this->putJson($url($a), ['revision' => 7, 'sets' => [[2, 6]], 'reset_dependents' => true])->assertOk();
        $this->assertNull(Fixture::find($final)->winner_registration);
        $this->assertSame(0, $placement->fixtureResults()->count());
        $this->assertSame($players[1]->id, Fixture::find($final)->registration1_id);
        $foreign = Fixture::factory()->create();
        $this->putJson($url($foreign->id), ['revision' => 8, 'sets' => [[6, 2]]])->assertNotFound();
        $draw->update(['locked' => true]);
        $this->putJson($url($b), ['revision' => 8, 'sets' => null])->assertForbidden();
    }

    public function test_reopen_preserves_assignments_and_legacy_mutations_are_blocked(): void
    {
        [$draw, $draft] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $this->postJson(route('draws.players.update', $draw), ['players' => []])->assertConflict();
        $this->postJson(route('draw.toggle.publish', $draw))->assertConflict();
        $this->postJson(route('flexible-monrad.reopen', $draw), ['revision' => 2])->assertOk()->assertJsonPath('generated', false);
        $this->assertSame(0, $draw->drawFixtures()->count());
        $this->assertEquals($draft, FlexibleMonradDraw::where('draw_id', $draw->id)->first()->draft);
        $record = $service->generate($draw, 3);
        $service->score($draw, $record->fixture_map['main_a'], [[6, 2]], 4, false);
        $this->postJson(route('flexible-monrad.reopen', $draw), ['revision' => 5])->assertConflict();
        $this->assertSame(4, $draw->drawFixtures()->count());
    }

    public function test_mixed_round_generation_uses_correct_direct_slots_and_public_view_hides_unassigned_players(): void
    {
        [$draw, , $players] = $this->setupDraw();
        foreach (Registration::factory()->count(19)->create() as $player) {
            $player->categoryEvents()->attach($draw->category_event_id, ['status' => 'registered']);
            $players->push($player);
        }
        $draft = \Tests\Unit\FlexibleMonradCompilerTest::mixedDraft();
        foreach ($draft['slots'] as &$slot) $slot['id'] = $players[$slot['id'] - 1]->id;
        unset($slot);
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $this->assertSame(45, $draw->drawFixtures()->count());
        $qf = Fixture::findOrFail($record->fixture_map['main_aa']);
        $this->assertSame($players[0]->id, $qf->registration1_id);
        $this->assertNull($qf->registration2_id);
        $record = $service->score($draw, $record->fixture_map['main_aabb'], [[6, 1]], 2, false);
        $record = $service->score($draw, $record->fixture_map['main_aab'], [[6, 1]], $record->revision, false);
        $this->assertSame($players[0]->id, $qf->fresh()->registration1_id);
        $this->assertSame($players[2]->id, $qf->fresh()->registration2_id);
        $service->publish($draw, $record->revision, true);
        auth()->logout();
        $response = $this->get(route('public.flexible-monrad.show', $draw))->assertOk();
        $response->assertDontSee('Manage players');
        $response->assertDontSee('"id":'.$players[22]->id.',"name"', false);
        $this->assertSame(45, $draw->drawFixtures()->count());
    }

    public function test_legacy_score_requests_cannot_write_before_rejection(): void
    {
        [$draw, $draft] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0);
        $record = $service->generate($draw, 1);
        $id = $record->fixture_map['main_a'];
        $service->score($draw, $id, [[6, 2]], 2, false);
        foreach (['individual', 'individualNew'] as $type) {
            $before = Fixture::findOrFail($id)->getAttributes();
            $results = Fixture::findOrFail($id)->fixtureResults->toArray();
            $this->postJson(route('draw.insert.result'), ['type' => $type, 'fixture_id' => $id,
                'set_player1' => [2], 'set_player2' => [6], 'sets' => [['player1' => 2, 'player2' => 6]]])->assertConflict();
            $this->assertEquals($before, Fixture::findOrFail($id)->getAttributes());
            $this->assertEquals($results, Fixture::findOrFail($id)->fixtureResults->toArray());
            $this->assertSame(3, $record->fresh()->revision);
        }
    }

    public function test_venues_remain_editable_but_bracket_mutations_and_unauthorized_changes_are_blocked(): void
    {
        [$draw, $draft] = $this->setupDraw();
        $admin = auth()->user();
        app(FlexibleMonradService::class)->save($draw, $draft, 0);
        $venue = DB::table('venues')->insertGetId(['name' => 'Monrad courts', 'event_id' => $draw->event_id]);
        $this->postJson(route('backend.draw.venues.store', $draw), ['venue_id' => [$venue], 'num_courts' => [3]])->assertRedirect();
        $this->assertDatabaseHas('draw_venues', ['draw_id' => $draw->id, 'venue_id' => $venue, 'num_courts' => 3]);
        $this->deleteJson(route('remove.venue.draw', $draw), ['venue' => $venue])->assertOk();
        $this->postJson(route('add.venue.draw', $draw), ['venue' => $venue, 'numCourts' => 2])->assertOk();
        $this->post(route('save.draw.venues'), ['draw' => $draw->id, 'venues' => [$venue]])->assertRedirect();
        $this->postJson(route('draws.players.update', $draw), ['players' => []])->assertConflict();
        $this->actingAs(User::factory()->create()->assignRole('admin'))
            ->postJson(route('backend.draw.venues.store', $draw), ['venue_id' => [$venue], 'num_courts' => [1]])->assertForbidden();
        $this->actingAs($admin);
        $draw->update(['locked' => true]);
        $this->postJson(route('backend.draw.venues.store', $draw), ['venue_id' => [$venue], 'num_courts' => [1]])->assertForbidden();
    }

    public function test_withdrawal_preserves_results_names_and_unrelated_scoring_with_walkovers(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0);
        $record = $service->generate($draw, 1);
        $a = $record->fixture_map['main_a']; $b = $record->fixture_map['main_b']; $final = $record->fixture_map['main_final'];
        $service->score($draw, $a, [[6, 2]], 2, false);
        $original = Fixture::findOrFail($a)->fixtureResults->toArray();
        $this->withdrawRegistration($draw, $players[0]->id);
        // Reading projects the new paths but must never write fixtures or revisions.
        $before = $draw->drawFixtures()->get()->toArray();
        $state = $service->state($draw);
        $this->assertTrue($state['withdrawals_pending']);
        $this->assertSame($players[0]->displayName(), $state['players']->firstWhere('id', $players[0]->id)['name']);
        $this->assertTrue($state['players']->firstWhere('id', $players[0]->id)['withdrawn']);
        $this->assertEquals($before, $draw->drawFixtures()->get()->toArray());
        $this->putJson(route('flexible-monrad.score', [$draw, $b]), ['revision' => 3, 'sets' => [[6, 3]]])
            ->assertOk()->assertJsonPath('revision', 4)->assertJsonPath('matches.main_final.automatic', 'walkover');
        $this->assertEquals($original, Fixture::findOrFail($a)->fixtureResults->toArray());
        $this->assertSame($players[2]->id, Fixture::findOrFail($final)->winner_registration);
        $this->assertSame(0, Fixture::findOrFail($final)->fixtureResults()->count());
        $this->assertDatabaseMissing('draw_registrations', ['draw_id' => $draw->id, 'registration_id' => $players[0]->id]);
        // Clearing an old result is permitted and recomputes automatic paths.
        $this->putJson(route('flexible-monrad.score', [$draw, $a]), ['revision' => 4, 'sets' => null])
            ->assertOk()->assertJsonPath('matches.main_a.automatic', 'walkover')
            ->assertJsonPath('matches.main_final.automatic', null);
        $this->assertSame($players[1]->id, Fixture::findOrFail($final)->registration1_id);
        $this->assertNull(Fixture::findOrFail($final)->winner_registration);
    }

    public function test_double_withdrawal_waits_for_unresolved_opponents_and_reconciliation_is_guarded(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $admin = auth()->user();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $this->withdrawRegistration($draw, $players[0]->id);
        $this->withdrawRegistration($draw, $players[1]->id);
        $url = route('flexible-monrad.withdrawals', $draw);
        $this->actingAs(User::factory()->create()->assignRole('admin'))->postJson($url, ['revision' => 2])->assertForbidden();
        $this->actingAs($admin)->postJson($url, ['revision' => 1])->assertConflict();
        $this->postJson($url, ['revision' => 2])->assertOk()->assertJsonPath('revision', 3)
            ->assertJsonPath('matches.main_a.automatic', 'void')
            ->assertJsonPath('matches.main_final.automatic', null);
        $this->postJson($url, ['revision' => 3])->assertOk()->assertJsonPath('revision', 3);
        $this->putJson(route('flexible-monrad.score', [$draw, $record->fixture_map['main_b']]), ['revision' => 3, 'sets' => [[6, 1]]])
            ->assertOk()->assertJsonPath('matches.main_final.automatic', 'walkover');
        $state = $service->state($draw);
        $this->assertCount(2, array_filter($state['positions'], fn ($p) => $p['vacant']));
        $draw->update(['locked' => true]);
        $this->postJson($url, ['revision' => 4])->assertForbidden();
    }

    public function test_withdrawal_correction_still_requires_reset_of_played_descendants(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $a = $record->fixture_map['main_a']; $b = $record->fixture_map['main_b'];
        $service->score($draw, $a, [[6, 2]], 2, false);
        $service->score($draw, $b, [[6, 2]], 3, false);
        $service->score($draw, $record->fixture_map['main_final'], [[6, 2]], 4, false);
        $this->withdrawRegistration($draw, $players[0]->id);
        $url = route('flexible-monrad.score', [$draw, $a]);
        $this->putJson($url, ['revision' => 5, 'sets' => null])->assertConflict();
        $this->assertSame(1, Fixture::findOrFail($record->fixture_map['main_final'])->fixtureResults()->count());
        $this->putJson($url, ['revision' => 5, 'sets' => null, 'reset_dependents' => true])->assertOk();
        $this->assertSame(0, Fixture::findOrFail($record->fixture_map['main_final'])->fixtureResults()->count());
        $this->assertSame($players[1]->id, Fixture::findOrFail($record->fixture_map['main_final'])->registration1_id);
    }

    private function withdrawRegistration(Draw $draw, int $registration): void
    {
        // Mirror the entry lifecycle's category status and unplayed-slot cleanup.
        DB::table('category_event_registrations')->where('registration_id', $registration)
            ->where('category_event_id', $draw->category_event_id)->update(['status' => 'withdrawn']);
        foreach (['registration1_id', 'registration2_id'] as $column) {
            $draw->drawFixtures()->whereNull('winner_registration')->where($column, $registration)->update([$column => null]);
        }
    }

    public function test_withdrawal_reconciliation_is_category_scoped_and_removes_only_closed_match_schedules(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $draw->event_id]);
        $players[0]->categoryEvents()->attach($otherCategory->id, ['status' => 'withdrawn']);
        $this->assertSame(2, $service->reconcileWithdrawals($draw, 2)->revision);
        $a = $record->fixture_map['main_a']; $b = $record->fixture_map['main_b'];
        foreach ([$a, $b] as $id) DB::table('schedules')->insert([
            'fixture_id' => $id, 'draw_id' => $draw->id, 'venue_id' => 1, 'time' => now(),
        ]);
        $this->withdrawRegistration($draw, $players[0]->id);
        $service->reconcileWithdrawals($draw, 2);
        $this->assertDatabaseMissing('schedules', ['fixture_id' => $a, 'draw_id' => $draw->id]);
        $this->assertDatabaseHas('schedules', ['fixture_id' => $b, 'draw_id' => $draw->id]);
        $this->assertSame($players[1]->id, Fixture::findOrFail($a)->winner_registration);
        $this->assertSame(0, Fixture::findOrFail($a)->fixtureResults()->count());
        $this->assertCount(4, $service->state($draw)['players']);
        $this->postJson(route('flexible-monrad.publish', $draw), ['revision' => 3, 'published' => true])->assertOk();
        auth()->logout();
        $this->get(route('public.flexible-monrad.show', $draw))->assertOk()
            ->assertSee($players[0]->displayName())->assertSee('"withdrawn":true', false)->assertDontSee('Manage draw');
    }

    public function test_all_inactive_entry_states_are_excluded_from_monrad(): void
    {
        foreach (['withdrawn_pending_refund', 'withdrawn_refunded', 'refund_requested', 'refunded', 'cancelled'] as $status) {
            [$draw, $draft, $players] = $this->setupDraw();
            $service = app(FlexibleMonradService::class);
            $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
            DB::table('category_event_registrations')->where('category_event_id', $draw->category_event_id)
                ->where('registration_id', $players[0]->id)->update(['status' => $status]);
            $this->assertFalse($service->eligible($draw)->where('registrations.id', $players[0]->id)->exists(), $status);
            $this->assertTrue($service->state($draw)['players']->firstWhere('id', $players[0]->id)['withdrawn'], $status);
            $this->putJson(route('flexible-monrad.score', [$draw, $record->fixture_map['main_a']]), ['revision' => 2, 'sets' => [[6, 2]]])->assertUnprocessable();
            $service->score($draw, $record->fixture_map['main_b'], [[6, 2]], 2, false);
            $this->assertSame($players[1]->id, Fixture::findOrFail($record->fixture_map['main_final'])->registration1_id);
            $this->assertDatabaseMissing('draw_registrations', ['draw_id' => $draw->id, 'registration_id' => $players[0]->id]);
        }
    }

    public function test_withdrawn_timestamp_cannot_be_reactivated_by_a_stale_status(): void
    {
        [$draw, , $players] = $this->setupDraw();
        DB::table('category_event_registrations')->where('category_event_id', $draw->category_event_id)
            ->where('registration_id', $players[0]->id)->update(['withdrawn_at' => now()]);
        $this->assertFalse(app(FlexibleMonradService::class)->eligible($draw)->where('registrations.id', $players[0]->id)->exists());
    }

    private function setupMixedMonrad(): array
    {
        [$draw, , $players] = $this->setupDraw();
        foreach (Registration::factory()->count(18)->create() as $player) {
            $player->categoryEvents()->attach($draw->category_event_id, ['status' => 'registered']);
            $players->push($player);
        }
        $draft = \Tests\Unit\FlexibleMonradCompilerTest::mixedDraft();
        foreach ($draft['slots'] as &$slot) $slot['id'] = $players[$slot['id'] - 1]->id;
        unset($slot);
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0);
        return [$draw, $service->generate($draw, 1), $players];
    }

    public function test_reconciliation_is_a_true_no_op_after_mysql_json_key_reordering(): void
    {
        [$draw, $record] = $this->setupMixedMonrad();
        DB::table('category_event_registrations')->where('category_event_id', $draw->category_event_id)->update(['status' => 'withdrawn']);
        $service = app(FlexibleMonradService::class);
        $record = $service->reconcileWithdrawals($draw, 2);
        $revision = $record->revision;
        $fixtures = $draw->drawFixtures()->get()->toArray();
        $logs = \App\Models\DrawAuditLog::where('draw_id', $draw->id)->count();
        $this->travel(1)->minutes();
        $this->assertSame($revision, $service->reconcileWithdrawals($draw, $revision)->revision);
        $this->assertSame($revision, $service->score($draw, $record->fixture_map['main_final'], null, $revision, false)->revision);
        $this->assertEquals($fixtures, $draw->drawFixtures()->get()->toArray());
        $this->assertSame($logs, \App\Models\DrawAuditLog::where('draw_id', $draw->id)->count());
    }

    public function test_name_only_edits_work_without_allowing_structural_or_unauthorized_changes(): void
    {
        [$draw, $draft] = $this->setupDraw();
        $admin = auth()->user();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $graph = $record->graph;
        $settings = $draw->settings->getAttributes();
        unset($settings['updated_at']); // Saving unchanged settings may touch their timestamp.
        $this->postJson(route('backend.draw.update-settings', $draw), ['name' => 'Monrad Singles'])->assertOk();
        $this->postJson(route('draws.update', $draw), ['name' => 'Updated Monrad'])->assertRedirect();
        $this->assertSame('Updated Monrad', $draw->fresh()->drawName);
        $actualSettings = $draw->settings->fresh()->getAttributes();
        unset($actualSettings['updated_at']);
        $this->assertEquals($settings, $actualSettings);
        $this->assertEquals($graph, $record->fresh()->graph);
        $this->get(route('draws.manage', $draw))->assertRedirect(route('backend.draw.roundrobin.show', $draw).'#settings');
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()
            ->assertSee('name="name"', false)->assertDontSee('name="draw_type"', false)->assertDontSee('name="num_sets"', false);
        $this->postJson(route('backend.draw.update-settings', $draw), ['name' => 'Bad edit', 'boxes' => 2])->assertConflict();
        $this->postJson(route('draws.update', $draw), ['name' => 'Bad edit', 'draw_type' => 1])->assertConflict();
        $this->postJson(route('backend.draw.update-settings', $draw), ['name' => str_repeat('x', 256)])->assertUnprocessable();
        $this->actingAs(User::factory()->create()->assignRole('admin'))->postJson(route('backend.draw.update-settings', $draw), ['name' => 'Other event'])->assertForbidden();
        $this->actingAs($admin);
        $draw->update(['locked' => true]);
        $this->postJson(route('backend.draw.update-settings', $draw), ['name' => 'Locked'])->assertForbidden();
        $draw->update(['locked' => false, 'published' => true]);
        $this->postJson(route('backend.draw.update-settings', $draw), ['name' => 'Published'])->assertForbidden();
    }

    private function setupScheduledDraw(): array
    {
        [$draw, $draft] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0);
        $record = $service->generate($draw, 1);
        $venue = DB::table('venues')->insertGetId(['name' => 'Shared courts', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 1]);
        $this->postJson(route('backend.individual-schedule.auto', $draw), [
            'start' => '2026-09-05 08:00:00', 'duration' => 60,
        ])->assertOk();
        return [$draw, $record->fresh(), $venue];
    }

    public function test_failed_monrad_reset_preserves_schedule_and_success_keeps_played_history(): void
    {
        [$draw, $record] = $this->setupScheduledDraw();
        $snapshot = fn () => DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $before = $snapshot();
        foreach ([['start' => '2026-09-05 08:00:00', 'duration' => 0], ['duration' => 60]] as $payload) {
            $this->postJson(route('backend.individual-schedule.reset', $draw), $payload)->assertUnprocessable();
            $this->assertSame($before, $snapshot());
            $this->assertEquals(4, $draw->drawFixtures()->where('scheduled', 1)->count());
        }
        $draw->update(['published' => true]);
        $this->postJson(route('backend.individual-schedule.reset', $draw), ['start' => '2026-09-05 12:00:00'])->assertUnprocessable();
        $this->assertSame($before, $snapshot());
        $draw->update(['published' => false]);
        app(FlexibleMonradService::class)->score($draw, $record->fixture_map['main_a'], [[6, 2]], $record->revision, false);
        $this->postJson(route('backend.individual-schedule.reset', $draw), ['start' => '2026-09-05 12:00:00', 'duration' => 60])->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $record->fixture_map['main_a'], 'time' => '2026-09-05 08:00:00']);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $record->fixture_map['main_b'], 'time' => '2026-09-05 12:00:00']);
        $this->assertSame(4, DB::table('order_of_plays')->where('draw_id', $draw->id)->count());
    }

    public function test_monrad_shared_court_bookings_are_respected_by_auto_and_manual_scheduling(): void
    {
        [$draw, $record, $venue] = $this->setupScheduledDraw();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        foreach (['2026-09-05 08:00:00', '2026-09-05 10:30:00'] as $time) {
            $fixture = Fixture::factory()->create(['draw_id' => $other->id]);
            \App\Models\OrderOfPlay::create(['draw_id' => $other->id, 'fixture_id' => $fixture->id,
                'venue_id' => $venue, 'court' => 1, 'time' => $time, 'duration_minutes' => 60]);
        }
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00', 'duration' => 60])->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $record->fixture_map['main_a'], 'time' => '2026-09-05 09:00:00']);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $record->fixture_map['main_b'], 'time' => '2026-09-05 11:30:00']);
        $this->postJson(route('backend.individual-schedule.save', $draw), [
            'fixture_id' => $record->fixture_map['main_a'], 'scheduled_at' => '2026-09-05 08:30:00',
            'venue_id' => $venue, 'court_label' => 1, 'duration_minutes' => 60,
        ])->assertUnprocessable();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $record->fixture_map['main_a'], 'time' => '2026-09-05 09:00:00']);
        $this->assertSame(2, DB::table('order_of_plays')->where('draw_id', $other->id)->count());
    }

    public function test_legacy_scheduling_respects_existing_monrad_courts_and_saves_duration(): void
    {
        [$draw, $record, $venue] = $this->setupScheduledDraw();
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $other->venues()->attach($venue, ['num_courts' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $other->id, 'round' => 1]);
        $auto = fn () => $this->postJson(route('backend.individual-schedule.auto', $other), [
            'start' => '2026-09-05 08:00:00', 'duration' => 60,
        ]);
        $auto()->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixture->id, 'time' => '2026-09-05 12:00:00', 'duration_minutes' => 60]);
        $auto()->assertOk();
        $this->assertSame(1, DB::table('order_of_plays')->where('draw_id', $other->id)->count());
        app(\App\Services\ScheduleEngine::class)->scheduleRound($other->id, 1, $venue, '2026-09-05 08:00:00', '1', 60);
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixture->id, 'time' => '2026-09-05 12:00:00']);
        $this->postJson(route('backend.individual-schedule.save', $other), [
            'fixture_id' => $fixture->id, 'scheduled_at' => '2026-09-05 08:30:00',
            'venue_id' => $venue, 'court_label' => 1, 'duration_minutes' => 60,
        ])->assertUnprocessable();
        $this->assertSame(4, DB::table('order_of_plays')->where('draw_id', $draw->id)->count());
    }

    public function test_monrad_checks_player_bookings_across_venues_and_different_registrations(): void
    {
        [$draw, $record, $venue] = $this->setupScheduledDraw();
        $a = Fixture::findOrFail($record->fixture_map['main_a']);
        $player = \App\Models\Player::factory()->create();
        Registration::findOrFail($a->registration1_id)->players()->attach($player->id);
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $otherVenue = DB::table('venues')->insertGetId(['name' => 'Second venue', 'event_id' => $draw->event_id]);
        $otherRegistration = Registration::factory()->create();
        $otherRegistration->players()->attach($player->id);
        $fixture = Fixture::factory()->create(['draw_id' => $other->id, 'registration1_id' => $a->registration1_id]);
        \App\Models\OrderOfPlay::create(['draw_id' => $other->id, 'fixture_id' => $fixture->id,
            'venue_id' => $otherVenue, 'court' => 1, 'time' => '2026-09-05 07:00:00', 'duration_minutes' => 120]);
        foreach ([$a->registration1_id, $otherRegistration->id] as $registration) {
            $fixture->update(['registration1_id' => $registration]);
            $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00', 'duration' => 60])->assertOk();
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $a->id, 'time' => '2026-09-05 09:00:00']);
            $this->postJson(route('backend.individual-schedule.save', $draw), [
                'fixture_id' => $a->id, 'scheduled_at' => '2026-09-05 07:00:00',
                'venue_id' => $venue, 'court_label' => 1, 'duration_minutes' => 60,
            ])->assertUnprocessable();
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $a->id, 'time' => '2026-09-05 09:00:00']);
        }
    }

    public function test_other_draws_respect_possible_players_in_unresolved_monrad_matches(): void
    {
        [$draw, $record] = $this->setupScheduledDraw();
        $registration = Fixture::findOrFail($record->fixture_map['main_a'])->registration1_id;
        $this->assertNull(Fixture::findOrFail($record->fixture_map['main_final'])->registration1_id);
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $venue = DB::table('venues')->insertGetId(['name' => 'Second venue', 'event_id' => $draw->event_id]);
        $other->venues()->attach($venue, ['num_courts' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $other->id, 'registration1_id' => $registration]);
        $this->postJson(route('backend.individual-schedule.auto', $other), ['start' => '2026-09-05 10:00:00', 'duration' => 60])->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixture->id, 'time' => '2026-09-05 12:00:00']);
        $this->postJson(route('backend.individual-schedule.save', $other), [
            'fixture_id' => $fixture->id, 'scheduled_at' => '2026-09-05 10:00:00',
            'venue_id' => $venue, 'court_label' => 1, 'duration_minutes' => 60,
        ])->assertUnprocessable();
    }

    public function test_legacy_brackets_wait_for_delayed_winner_and_loser_feeders(): void
    {
        [$draw, , $players] = $this->setupDraw();
        $venue = DB::table('venues')->insertGetId(['name' => 'Bracket courts', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $final = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 2, 'match_nr' => 3]);
        $placement = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'match_nr' => 0]);
        $a = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'match_nr' => 1,
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id,
            'parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $placement->id]);
        $b = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'match_nr' => 2,
            'registration1_id' => $players[2]->id, 'registration2_id' => $players[3]->id,
            'parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $placement->id]);
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $otherVenue = DB::table('venues')->insertGetId(['name' => 'Other courts', 'event_id' => $draw->event_id]);
        $busy = Fixture::factory()->create(['draw_id' => $other->id, 'registration1_id' => $players[0]->id]);
        \App\Models\OrderOfPlay::create(['draw_id' => $other->id, 'fixture_id' => $busy->id, 'venue_id' => $otherVenue,
            'court' => 1, 'time' => '2026-09-05 08:00:00', 'duration_minutes' => 120]);
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00', 'duration' => 60])->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $a->id, 'time' => '2026-09-05 10:00:00']);
        foreach ([$final, $placement] as $target) {
            foreach ([$a, $b] as $feeder) {
                $this->assertTrue(\Carbon\Carbon::parse($target->orderOfPlay->time)->gte(\Carbon\Carbon::parse($feeder->orderOfPlay->time)->addMinutes(60)));
            }
        }
        $this->postJson(route('backend.individual-schedule.save', $draw), ['fixture_id' => $final->id,
            'scheduled_at' => '2026-09-05 09:00:00', 'venue_id' => $venue, 'court_label' => 2, 'duration_minutes' => 60])->assertUnprocessable();
        $this->postJson(route('backend.individual-schedule.save', $draw), ['fixture_id' => $a->id, 'scheduled_at' => null])->assertUnprocessable();
        $before = DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $this->postJson(route('backend.trials.auto', $draw), ['start' => '2026-09-05 12:00:00', 'duration' => 60, 'rounds' => [1]])->assertUnprocessable();
        $this->assertSame($before, DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
    }

    public function test_trials_uses_shared_bookings_preserves_filters_byes_gap_and_idempotency(): void
    {
        [$monrad, , $venue] = $this->setupScheduledDraw();
        $draw = Draw::factory()->create(['event_id' => $monrad->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 1]);
        $a = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 2, 'round' => 1, 'match_nr' => 1]);
        $b = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 2, 'round' => 1, 'match_nr' => 2]);
        $bye = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 1, 'round' => 1, 'scheduled' => 1]);
        $outside = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 3, 'round' => 1, 'scheduled' => 1]);
        foreach ([$bye, $outside] as $fixture) \App\Models\OrderOfPlay::create(['fixture_id' => $fixture->id,
            'draw_id' => $draw->id, 'venue_id' => $venue, 'court' => 1, 'time' => '2026-09-05 18:00:00', 'duration_minutes' => 60]);
        $payload = ['start' => '2026-09-05 08:00:00', 'duration' => 60, 'gap' => 10, 'brackets' => [1, 2], 'rounds' => [1]];
        for ($run = 0; $run < 2; $run++) {
            $this->postJson(route('backend.trials.auto', $draw), $payload)->assertOk();
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $a->id, 'draw_id' => $draw->id, 'time' => '2026-09-05 12:00:00', 'duration_minutes' => 60]);
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $b->id, 'time' => '2026-09-05 13:10:00']);
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $outside->id, 'time' => '2026-09-05 18:00:00']);
            $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $bye->id]);
            $this->assertEquals(0, $bye->fresh()->scheduled);
            $this->assertSame(3, DB::table('order_of_plays')->where('draw_id', $draw->id)->count());
        }
        $before = DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $this->postJson(route('backend.trials.auto', $draw), array_replace($payload, ['duration' => 0]))->assertUnprocessable();
        $draw->update(['published' => true]);
        $this->postJson(route('backend.trials.auto', $draw), $payload)->assertUnprocessable();
        $this->assertSame($before, DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $this->actingAs(User::factory()->create()->assignRole('admin'))->postJson(route('backend.trials.auto', $draw), $payload)->assertForbidden();
    }

    public function test_pending_winner_and_loser_qualifiers_are_not_first_round_byes(): void
    {
        foreach (['parent_fixture_id', 'loser_parent_fixture_id'] as $link) {
            [$draw, , $players] = $this->setupDraw();
            $venue = DB::table('venues')->insertGetId(['name' => 'Qualifier courts', 'event_id' => $draw->event_id]);
            $draw->venues()->attach($venue, ['num_courts' => 2]);
            $final = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 1, 'round' => 2, 'match_nr' => 3]);
            $waiting = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 1, 'round' => 1, 'match_nr' => 2,
                'registration1_id' => $players[0]->id, 'registration2_id' => null, 'parent_fixture_id' => $final->id]);
            $qualifier = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 2, 'round' => 1, 'match_nr' => 1,
                'registration1_id' => $players[1]->id, 'registration2_id' => $players[2]->id, $link => $waiting->id]);
            $this->postJson(route('backend.individual-schedule.auto', $draw), [
                'start' => '2026-09-05 08:00:00', 'duration' => 60,
            ])->assertOk();
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $qualifier->id, 'time' => '2026-09-05 08:00:00']);
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $waiting->id, 'time' => '2026-09-05 09:00:00']);
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $final->id, 'time' => '2026-09-05 10:00:00']);
            $this->assertEquals(1, $waiting->fresh()->scheduled);
            $this->postJson(route('backend.individual-schedule.save', $draw), [
                'fixture_id' => $final->id, 'scheduled_at' => '2026-09-05 09:00:00',
                'venue_id' => $venue, 'court_label' => 2, 'duration_minutes' => 60,
            ])->assertUnprocessable();
            $this->postJson(route('backend.individual-schedule.save', $draw), [
                'fixture_id' => $waiting->id, 'scheduled_at' => null,
            ])->assertUnprocessable();
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $waiting->id]);
        }
    }

    public function test_saved_gaps_survive_partial_trials_and_manual_edits_and_reserve_shared_courts(): void
    {
        [$draw, , $players] = $this->setupDraw();
        $venue = DB::table('venues')->insertGetId(['name' => 'Gap courts', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $b = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 2, 'round' => 2, 'match_nr' => 2]);
        $a = Fixture::factory()->create(['draw_id' => $draw->id, 'bracket_id' => 2, 'round' => 1, 'match_nr' => 1,
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id, 'parent_fixture_id' => $b->id]);
        $payload = ['start' => '2026-09-05 08:00:00', 'duration' => 60, 'gap' => 15];
        $this->postJson(route('backend.trials.auto', $draw), $payload)->assertOk();
        for ($run = 0; $run < 2; $run++) {
            $this->postJson(route('backend.trials.auto', $draw), $payload + ['rounds' => [2]])->assertOk();
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $b->id, 'time' => '2026-09-05 09:15:00', 'gap_minutes' => 15]);
        }
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $a->id, 'time' => '2026-09-05 08:00:00', 'duration_minutes' => 60, 'gap_minutes' => 15]);
        $save = fn ($fixture, $time, $court) => $this->postJson(route('backend.individual-schedule.save', $draw), [
            'fixture_id' => $fixture->id, 'scheduled_at' => $time, 'venue_id' => $venue,
            'court_label' => $court, 'duration_minutes' => 60,
        ]);
        $save($b, '2026-09-05 09:00:00', 2)->assertUnprocessable();
        $save($a, '2026-09-05 08:05:00', 1)->assertUnprocessable();
        $save($b, '2026-09-05 09:15:00', 2)->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $b->id, 'gap_minutes' => 15]);
        $other = Draw::factory()->create(['event_id' => $draw->event_id]);
        $other->venues()->attach($venue, ['num_courts' => 1]);
        $fixture = Fixture::factory()->create(['draw_id' => $other->id]);
        $this->postJson(route('backend.individual-schedule.auto', $other), ['start' => '2026-09-05 09:00:00', 'duration' => 60])->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixture->id, 'time' => '2026-09-05 09:15:00']);
    }

    public function test_screen_saves_preserve_duration_and_gap_unless_duration_is_explicit(): void
    {
        foreach ([true, false] as $monrad) {
            foreach ([60, 90] as $duration) {
                [$draw, $draft, $players] = $this->setupDraw();
                $venue = DB::table('venues')->insertGetId(['name' => 'Manual duration courts', 'event_id' => $draw->event_id]);
                $draw->venues()->attach($venue, ['num_courts' => 1]);
                if ($monrad) {
                    $service = app(FlexibleMonradService::class);
                    $service->save($draw, $draft, 0);
                    $record = $service->generate($draw, 1);
                    $fixtureId = $record->fixture_map['main_a'];
                } else {
                    $fixtureId = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'bracket_id' => 2,
                        'match_nr' => 1, 'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id])->id;
                    Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'bracket_id' => 2,
                        'match_nr' => 2, 'registration1_id' => $players[2]->id, 'registration2_id' => $players[3]->id]);
                }
                $this->postJson(route($monrad ? 'backend.individual-schedule.auto' : 'backend.trials.auto', $draw), [
                    'start' => '2026-09-05 08:00:00', 'duration' => $duration, 'gap' => 15,
                ])->assertOk();
                $payload = ['fixture_id' => $fixtureId, 'scheduled_at' => '2026-09-05 08:00:00',
                    'venue_id' => $venue, 'court_label' => 1];
                $revision = $monrad ? $record->fresh()->revision : null;
                foreach ([$payload, $payload + ['duration_minutes' => null]] as $save) {
                    $this->postJson(route('backend.individual-schedule.save', $draw), $save)->assertOk();
                    $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixtureId,
                        'duration_minutes' => $duration, 'gap_minutes' => $monrad ? 0 : 15]);
                    if ($monrad) $this->assertSame($revision, $record->fresh()->revision);
                }
                $this->postJson(route('backend.individual-schedule.save', $draw), $payload + ['duration_minutes' => 0])->assertUnprocessable();
                $this->postJson(route('backend.individual-schedule.save', $draw), $payload + ['duration_minutes' => 45])->assertOk();
                $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixtureId,
                    'duration_minutes' => 45, 'gap_minutes' => $monrad ? 0 : 15]);
            }
        }
    }

    public function test_monrad_reserves_unresolved_legacy_winner_and_loser_paths_across_registrations(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0);
        $record = $service->generate($draw, 1);
        $venue = DB::table('venues')->insertGetId(['name' => 'Monrad venue', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $legacy = Draw::factory()->create(['event_id' => $draw->event_id]);
        $otherVenue = DB::table('venues')->insertGetId(['name' => 'Legacy venue', 'event_id' => $draw->event_id]);
        $legacy->venues()->attach($otherVenue, ['num_courts' => 2]);
        $aliases = Registration::factory()->count(4)->create();
        foreach ($players as $i => $registration) {
            $player = \App\Models\Player::factory()->create();
            $registration->players()->attach($player->id);
            $aliases[$i]->players()->attach($player->id);
        }
        $final = Fixture::factory()->create(['draw_id' => $legacy->id, 'round' => 2, 'bracket_id' => 2]);
        $plate = Fixture::factory()->create(['draw_id' => $legacy->id, 'round' => 2, 'bracket_id' => 3]);
        foreach ([0, 2] as $i) Fixture::factory()->create(['draw_id' => $legacy->id, 'round' => 1, 'bracket_id' => 2,
            'registration1_id' => $aliases[$i]->id, 'registration2_id' => $aliases[$i + 1]->id,
            'parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $plate->id]);
        $this->postJson(route('backend.trials.auto', $legacy), ['start' => '2026-09-05 08:00:00', 'duration' => 60, 'gap' => 15])->assertOk();
        foreach ([$final, $plate] as $match) {
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $match->id, 'time' => '2026-09-05 09:15:00']);
        }
        $before = DB::table('order_of_plays')->where('draw_id', $legacy->id)->orderBy('id')->get()->toJson();
        for ($run = 0; $run < 2; $run++) {
            $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 09:15:00', 'duration' => 60])->assertOk();
            foreach (['main_a', 'main_b'] as $key) {
                $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $record->fixture_map[$key], 'time' => '2026-09-05 10:30:00']);
            }
        }
        $this->postJson(route('backend.individual-schedule.save', $draw), ['fixture_id' => $record->fixture_map['main_a'],
            'scheduled_at' => '2026-09-05 09:15:00', 'venue_id' => $venue, 'court_label' => 1])->assertUnprocessable();
        $this->assertSame($before, DB::table('order_of_plays')->where('draw_id', $legacy->id)->orderBy('id')->get()->toJson());
    }

    private function setupReverseSchedulingDraws(): array
    {
        [$monrad, $draft, $players] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($monrad, $draft, 0);
        $record = $service->generate($monrad, 1);
        $monradVenue = DB::table('venues')->insertGetId(['name' => 'Reverse Monrad venue', 'event_id' => $monrad->event_id]);
        $monrad->venues()->attach($monradVenue, ['num_courts' => 2]);
        $legacy = Draw::factory()->create(['event_id' => $monrad->event_id]);
        $venue = DB::table('venues')->insertGetId(['name' => 'Reverse legacy venue', 'event_id' => $monrad->event_id]);
        $legacy->venues()->attach($venue, ['num_courts' => 2]);
        $aliases = Registration::factory()->count(4)->create();
        foreach ($players as $i => $registration) {
            $player = \App\Models\Player::factory()->create();
            $registration->players()->attach($player->id);
            $aliases[$i]->players()->attach($player->id);
        }
        $final = Fixture::factory()->create(['draw_id' => $legacy->id, 'round' => 2, 'bracket_id' => 2, 'match_nr' => 3]);
        $plate = Fixture::factory()->create(['draw_id' => $legacy->id, 'round' => 2, 'bracket_id' => 3, 'match_nr' => 4]);
        foreach ([0, 2] as $i) Fixture::factory()->create(['draw_id' => $legacy->id, 'round' => 1, 'bracket_id' => 2,
            'match_nr' => $i / 2 + 1, 'registration1_id' => $aliases[$i]->id, 'registration2_id' => $aliases[$i + 1]->id,
            'parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $plate->id]);
        $this->postJson(route('backend.individual-schedule.auto', $legacy), ['start' => '2026-09-05 08:00:00', 'duration' => 60])->assertOk();
        foreach ([$final, $plate] as $i => $fixture) {
            $this->postJson(route('backend.individual-schedule.save', $legacy), ['fixture_id' => $fixture->id,
                'scheduled_at' => '2026-09-05 12:00:00', 'venue_id' => $venue, 'court_label' => $i + 1])->assertOk();
        }
        $this->postJson(route('backend.individual-schedule.auto', $monrad), ['start' => '2026-09-05 09:00:00', 'duration' => 60])->assertOk();
        foreach (['main_a', 'main_b'] as $key) $this->assertDatabaseHas('order_of_plays', [
            'fixture_id' => $record->fixture_map[$key], 'time' => '2026-09-05 09:00:00']);
        return [$legacy, $final, $plate, $venue, $monrad, $record, $monradVenue];
    }

    public function test_all_legacy_scheduling_paths_respect_monrad_without_serializing_winner_and_loser_matches(): void
    {
        foreach (['manual', 'full', 'trials', 'filtered', 'round'] as $mode) {
            [$legacy, $final, $plate, $venue, $monrad] = $this->setupReverseSchedulingDraws();
            $before = DB::table('order_of_plays')->where('draw_id', $monrad->id)->orderBy('id')->get()->toJson();
            $payload = ['start' => '2026-09-05 08:00:00', 'duration' => 60];
            if ($mode === 'manual') {
                foreach ([$final, $plate] as $i => $fixture) {
                    $save = ['fixture_id' => $fixture->id, 'venue_id' => $venue, 'court_label' => $i + 1];
                    $this->postJson(route('backend.individual-schedule.save', $legacy), $save + ['scheduled_at' => '2026-09-05 09:00:00'])->assertUnprocessable();
                    $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixture->id, 'time' => '2026-09-05 12:00:00']);
                    $this->postJson(route('backend.individual-schedule.save', $legacy), $save + ['scheduled_at' => '2026-09-05 11:00:00'])->assertOk();
                }
            } elseif ($mode === 'round') {
                app(\App\Services\ScheduleEngine::class)->scheduleRound($legacy->id, 2, $venue, $payload['start'], '1', 60);
            } else {
                if ($mode === 'filtered') $payload['rounds'] = [2];
                $this->postJson(route($mode === 'full' ? 'backend.individual-schedule.auto' : 'backend.trials.auto', $legacy), $payload)->assertOk();
            }
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $final->id, 'time' => '2026-09-05 11:00:00']);
            $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $plate->id,
                'time' => $mode === 'round' ? '2026-09-05 12:00:00' : '2026-09-05 11:00:00']);
            if ($mode !== 'round') $this->assertNotEquals($final->fresh()->orderOfPlay->court, $plate->fresh()->orderOfPlay->court);
            $this->assertSame($before, DB::table('order_of_plays')->where('draw_id', $monrad->id)->orderBy('id')->get()->toJson());
            $this->assertSame(4, $legacy->drawFixtures()->where('scheduled', 1)->count());
        }
    }

    public function test_historical_and_partially_updated_trials_bookings_reserve_possible_players(): void
    {
        [$legacy, $final, $plate, $venue, $monrad, $record, $monradVenue] = $this->setupReverseSchedulingDraws();
        $ids = $legacy->drawFixtures()->pluck('id');
        foreach (['current', 'historical', 'mixed'] as $version) {
            if ($version === 'historical') DB::table('order_of_plays')->whereIn('fixture_id', $ids)->update(['draw_id' => null]);
            if ($version === 'mixed') DB::table('order_of_plays')->where('fixture_id', $plate->id)->update(['draw_id' => $legacy->id]);
            $before = DB::table('order_of_plays')->whereIn('fixture_id', $ids)->orderBy('id')->get()->toJson();
            $this->postJson(route('backend.individual-schedule.auto', $monrad), ['start' => '2026-09-05 12:00:00', 'duration' => 60])->assertOk();
            foreach (['main_a', 'main_b'] as $key) $this->assertDatabaseHas('order_of_plays', [
                'fixture_id' => $record->fixture_map[$key], 'time' => '2026-09-05 13:00:00']);
            $this->postJson(route('backend.individual-schedule.save', $monrad), ['fixture_id' => $record->fixture_map['main_a'],
                'scheduled_at' => '2026-09-05 12:00:00', 'venue_id' => $monradVenue, 'court_label' => 1])->assertUnprocessable();
            $this->assertSame($before, DB::table('order_of_plays')->whereIn('fixture_id', $ids)->orderBy('id')->get()->toJson());
        }
    }

    public function test_legacy_reservations_follow_chains_and_release_players_after_results(): void
    {
        [$draw, , $players] = $this->setupDraw();
        $venue = DB::table('venues')->insertGetId(['name' => 'Legacy paths', 'event_id' => $draw->event_id]);
        $otherVenue = DB::table('venues')->insertGetId(['name' => 'Other venue', 'event_id' => $draw->event_id]);
        $final = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 3]);
        $plate = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 2]);
        $semi = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 2,
            'registration1_id' => $players[2]->id, 'parent_fixture_id' => $final->id]);
        $qualifier = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1,
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id,
            'parent_fixture_id' => $semi->id, 'loser_parent_fixture_id' => $plate->id]);
        foreach ([[$final, '2026-09-05 10:00:00'], [$plate, '2026-09-05 09:00:00']] as [$fixture, $time]) {
            \App\Models\OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id,
                'venue_id' => $venue, 'court' => 1, 'time' => $time, 'duration_minutes' => 60]);
        }
        $at = function ($registration, $time) use ($otherVenue) {
            return \App\Domain\Draws\Services\ScheduleAvailability::load([$otherVenue], [$registration->id])
                ->nextAvailable(\Carbon\Carbon::parse($time), 60, $otherVenue, '1', [$registration->id])->format('H:i');
        };
        $this->assertSame('11:00', $at($players[0], '2026-09-05 09:00:00'));
        $this->assertSame('11:00', $at($players[1], '2026-09-05 09:00:00'));
        $qualifier->update(['winner_registration' => $players[0]->id]);
        $this->assertSame('09:00', $at($players[0], '2026-09-05 09:00:00'));
        $this->assertSame('10:00', $at($players[1], '2026-09-05 09:00:00'));
        $this->assertSame('11:00', $at($players[0], '2026-09-05 10:00:00'));
        $this->assertSame('10:00', $at($players[1], '2026-09-05 10:00:00'));
        $this->assertSame('09:00', $at($players[3], '2026-09-05 09:00:00'));
    }

    public function test_partial_schedule_with_missing_feeders_or_cycles_does_not_write(): void
    {
        [$draw] = $this->setupDraw();
        $venue = DB::table('venues')->insertGetId(['name' => 'Trial courts', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 1]);
        $final = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 2]);
        $feeder = Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'parent_fixture_id' => $final->id]);
        $this->postJson(route('backend.trials.auto', $draw), ['start' => '2026-09-05 08:00:00', 'rounds' => [2]])->assertUnprocessable();
        $this->assertSame(0, DB::table('order_of_plays')->where('draw_id', $draw->id)->count());
        $final->update(['parent_fixture_id' => $feeder->id]);
        Fixture::factory()->create(['draw_id' => $draw->id, 'round' => 1, 'match_nr' => 0]);
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00'])->assertUnprocessable();
        $this->assertSame(0, DB::table('order_of_plays')->where('draw_id', $draw->id)->count());
        $this->assertSame(0, $draw->drawFixtures()->where('scheduled', 1)->count());
    }

    public function test_feeder_time_removal_requires_clearing_all_dependent_paths_first(): void
    {
        [$draw, $record] = $this->setupScheduledDraw();
        $remove = fn ($id) => $this->postJson(route('backend.individual-schedule.save', $draw), ['fixture_id' => $id, 'scheduled_at' => null]);
        $a = $record->fixture_map['main_a'];
        $remove($a)->assertUnprocessable();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $a]);
        $remove($record->fixture_map['main_final'])->assertOk();
        $remove($a)->assertUnprocessable(); // The loser placement path still depends on it.
        foreach ($record->fixture_map as $key => $id) {
            if (! in_array($key, ['main_a', 'main_b', 'main_final'])) $remove($id)->assertOk();
        }
        $remove($a)->assertOk();
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $a]);
        $this->assertEquals(0, Fixture::findOrFail($a)->scheduled);
        $revision = $record->fresh()->revision;
        $remove($a)->assertOk();
        $this->assertSame($revision, $record->fresh()->revision);
    }

    public function test_monrad_scoring_requires_completed_sets_and_configured_match_length(): void
    {
        [$draw, $draft] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $url = route('flexible-monrad.score', [$draw, $record->fixture_map['main_a']]);
        foreach ([[[1, 0]], [[6, 5]], [[7, 4]], [[8, 6]], [[6, 4], [6, 2]]] as $sets) {
            $this->putJson($url, ['revision' => 2, 'sets' => $sets])->assertUnprocessable();
            $this->assertSame(2, $record->fresh()->revision);
            $this->assertNull(Fixture::findOrFail($record->fixture_map['main_a'])->winner_registration);
        }
        $draw->settings()->update(['num_sets' => 3]);
        $this->putJson($url, ['revision' => 2, 'sets' => [[6, 4]]])->assertUnprocessable();
        $this->putJson($url, ['revision' => 2, 'sets' => [[6, 4], [6, 2], [2, 6]]])->assertUnprocessable();
        $this->putJson($url, ['revision' => 2, 'sets' => [[7, 6], [5, 7], [6, 4]]])->assertOk();
        $this->assertSame(3, Fixture::findOrFail($record->fixture_map['main_a'])->fixtureResults()->count());
    }

    public function test_auto_schedule_respects_every_winner_and_loser_dependency_on_one_and_multiple_courts(): void
    {
        [$draw, $record] = $this->setupMixedMonrad();
        $venue = DB::table('venues')->insertGetId(['name' => 'Schedule courts', 'event_id' => $draw->event_id]);
        foreach ([1, 3] as $courts) {
            $draw->venues()->sync([$venue => ['num_courts' => $courts]]);
            $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00', 'duration' => 60])->assertOk();
            $slots = \App\Models\OrderOfPlay::where('draw_id', $draw->id)->get()->keyBy('fixture_id');
            $this->assertCount(45, $slots);
            foreach ($record->graph['nodes'] as $key => $node) {
                $slot = $slots[$record->fixture_map[$key]];
                $this->assertEquals(60, $slot->duration_minutes);
                foreach ($node['sources'] as $source) {
                    if (! isset($source['match'])) continue;
                    $feeder = $slots[$record->fixture_map[$source['match']]];
                    $this->assertTrue(\Carbon\Carbon::parse($slot->time)->gte(\Carbon\Carbon::parse($feeder->time)->addMinutes(60)), $key.' precedes '.$source['match']);
                }
            }
            foreach ($slots->groupBy('court') as $courtSlots) {
                $end = null;
                foreach ($courtSlots->sortBy('time') as $slot) {
                    $time = \Carbon\Carbon::parse($slot->time);
                    if ($end) $this->assertTrue($time->gte($end));
                    $end = $time->addMinutes(60);
                }
            }
        }
        $revision = $record->fresh()->revision;
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00', 'duration' => 60])->assertOk();
        $this->assertSame($revision, $record->fresh()->revision);
        $draw->update(['locked' => true]);
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00'])->assertForbidden();
    }

    public function test_scheduler_skips_walkovers_and_manual_changes_cannot_break_dependencies(): void
    {
        [$draw, $draft, $players] = $this->setupDraw();
        $service = app(FlexibleMonradService::class);
        $service->save($draw, $draft, 0); $record = $service->generate($draw, 1);
        $venue = DB::table('venues')->insertGetId(['name' => 'Schedule court', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $this->withdrawRegistration($draw, $players[0]->id);
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00', 'duration' => 60])->assertOk();
        $a = $record->fixture_map['main_a']; $b = $record->fixture_map['main_b']; $final = $record->fixture_map['main_final'];
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $a]);
        $this->assertSame(0, Fixture::findOrFail($a)->scheduled);
        $save = fn ($id, $time) => $this->postJson(route('backend.individual-schedule.save', $draw), [
            'fixture_id' => $id, 'scheduled_at' => $time, 'venue_id' => $venue, 'court_label' => 2, 'duration_minutes' => 60,
        ]);
        $save($a, '2026-09-05 07:00:00')->assertUnprocessable();
        $save($final, '2026-09-05 07:00:00')->assertUnprocessable();
        $save($b, '2026-09-05 10:00:00')->assertUnprocessable();
        $save($final, '2026-09-05 10:00:00')->assertOk();
        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $final, 'duration_minutes' => 60]);
        $this->actingAs(User::factory()->create()->assignRole('admin'))
            ->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-09-05 08:00:00'])->assertForbidden();
    }
}
