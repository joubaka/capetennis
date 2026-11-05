<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, Team, TeamFixture, TeamTie, User};
use App\Services\Draw\BulkDrawDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkDrawDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Event $event): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        return $actor;
    }

    private function preview(Event $event, array $ids)
    {
        return $this->postJson(route('backend.event-draws.delete-preview', $event), ['draw_ids' => $ids]);
    }

    private function applySelection(Event $event, array $ids, string $revision)
    {
        return $this->deleteJson(route('backend.event-draws.bulk-delete', $event), ['draw_ids' => $ids, 'revision' => $revision]);
    }

    public function test_preview_then_atomic_delete_removes_only_selected_graphs_and_retains_rosters_registrations_payments_and_audit(): void
    {
        $event = Event::factory()->create(); $this->actingAs($this->manager($event));
        $draws = Draw::factory()->count(3)->create(['event_id' => $event->id, 'locked' => false, 'published' => false, 'oop_published' => false]);
        $individual = Fixture::factory()->create(['draw_id' => $draws[0]->id, 'match_status' => 0, 'winner_registration' => null]);
        $teamFixture = TeamFixture::create(['draw_id' => $draws[1]->id, 'match_nr' => 1, 'match_status' => 0]);
        $teamFixture->fixturePlayers()->create(['slot_no' => 1]);
        $team = Team::factory()->create(); $draws[1]->teams_in_draw()->attach($team->id);
        \App\Models\Registration::factory()->create();
        \App\Models\TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $team->id,
            'user_id' => auth()->id(), 'player_id' => \App\Models\Player::factory()->create()->id, 'total_amount' => 285,
            'wallet_reserved' => 0, 'payfast_amount_due' => 285, 'pay_status' => true]);
        $ids = [$draws[0]->id, $draws[1]->id];
        $counts = collect(['teams', 'team_players', 'no_profile_team_players', 'registrations', 'team_payment_orders', 'wallet_transactions'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
        $preview = $this->preview($event, $ids)->assertOk()->assertJsonPath('deleted', false)->assertJsonCount(2, 'draws');
        $this->assertDatabaseCount('draws', 3);
        $this->applySelection($event, $ids, $preview->json('revision'))->assertOk()->assertJsonPath('deleted', true);
        $this->assertDatabaseHas('draws', ['id' => $draws[2]->id]);
        $this->assertDatabaseMissing('fixtures', ['id' => $individual->id]);
        $this->assertDatabaseMissing('team_fixtures', ['id' => $teamFixture->id]);
        $this->assertDatabaseCount('team_fixture_players', 0);
        foreach ($counts as $table => $count) $this->assertDatabaseCount($table, $count);
        foreach ($ids as $id) $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $id, 'action' => 'deleted']);
    }

    public function test_unauthorized_foreign_duplicate_and_missing_selection_cannot_preview_or_delete(): void
    {
        $event = Event::factory()->create(); $draw = Draw::factory()->create(['event_id' => $event->id]);
        $this->actingAs(User::factory()->create()); $this->preview($event, [$draw->id])->assertForbidden();
        $this->actingAs($this->manager($event));
        $foreign = Draw::factory()->create();
        foreach ([[], [$draw->id, $draw->id], [$foreign->id], [$draw->id, 99999999]] as $ids) $this->preview($event, $ids)->assertUnprocessable();
        $this->deleteJson(route('backend.event-draws.bulk-delete', $event), ['draw_ids' => [$draw->id]])->assertUnprocessable();
        $this->assertDatabaseCount('draws', 2);
    }

    public function test_super_user_cannot_bulk_delete_protected_or_scored_draw_even_in_mixed_selection(): void
    {
        $event = Event::factory()->create();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $safe = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        foreach ([['locked' => true], ['published' => true], ['oop_published' => true]] as $state) {
            $protected = Draw::factory()->create(['event_id' => $event->id] + $state);
            $this->preview($event, [$safe->id, $protected->id])->assertUnprocessable();
            $this->assertDatabaseHas('draws', ['id' => $safe->id]);
        }
        $fixture = TeamFixture::create(['draw_id' => $safe->id, 'match_nr' => 1, 'match_status' => 0]);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        $this->preview($event, [$safe->id])->assertUnprocessable();
        $this->assertDatabaseHas('team_fixture_results', ['team_fixture_id' => $fixture->id]);
    }

    public function test_lifecycle_feedback_names_each_blocking_draw_and_separates_the_evidence(): void
    {
        $event = Event::factory()->create();
        $this->actingAs($this->manager($event));
        [$enabled, $started, $scored, $advanced, $safe] = collect(['Singles', 'Doubles', 'Mixed', 'Playoffs', 'Draft'])
            ->map(fn ($name) => Draw::factory()->create(['event_id' => $event->id, 'drawName' => $name, 'published' => false]))
            ->all();
        $ids = [$enabled->id, $started->id, $scored->id, $advanced->id, $safe->id];
        $revision = $this->preview($event, $ids)->assertOk()->json('revision');

        TeamTie::factory()->published()->create(['draw_id' => $enabled->id]);
        TeamFixture::create(['draw_id' => $started->id, 'match_nr' => 1, 'match_status' => 2]);
        $fixture = TeamFixture::create(['draw_id' => $scored->id, 'match_nr' => 1, 'match_status' => 0]);
        $fixture->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        Fixture::factory()->create(['draw_id' => $advanced->id, 'match_status' => 3]);

        $expected = [
            "Singles (#{$enabled->id}): Scoring enabled for team ties.",
            "Doubles (#{$started->id}): Play started.",
            "Mixed (#{$scored->id}): Scores recorded.",
            "Playoffs (#{$advanced->id}): Match status or outcome recorded.",
        ];
        $this->assertSame($expected, $this->preview($event, $ids)->assertUnprocessable()->json('errors.draw_ids'));
        $this->assertSame($expected, $this->applySelection($event, $ids, $revision)->assertUnprocessable()->json('errors.draw_ids'));
        $this->assertDatabaseCount('draws', 5);
        $this->assertDatabaseCount('draw_audit_logs', 0);
    }

    public function test_changed_selection_setup_fixtures_or_actor_invalidate_confirmation_without_partial_deletion(): void
    {
        $event = Event::factory()->create(); $actor = $this->manager($event); $this->actingAs($actor);
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $ids = $draws->modelKeys(); $token = $this->preview($event, $ids)->assertOk()->json('revision');
        $this->applySelection($event, [$ids[0]], $token)->assertUnprocessable();
        $draws[0]->teams_in_draw()->attach(Team::factory()->create()->id);
        $this->applySelection($event, $ids, $token)->assertUnprocessable();
        $token = $this->preview($event, $ids)->assertOk()->json('revision');
        TeamFixture::create(['draw_id' => $ids[0], 'match_nr' => 1, 'match_status' => 0]);
        $this->applySelection($event, $ids, $token)->assertUnprocessable();
        $token = $this->preview($event, $ids)->assertOk()->json('revision');
        $this->actingAs($this->manager($event)); $this->applySelection($event, $ids, $token)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 2);
    }

    public function test_placement_links_and_foreign_feeder_references_block_bulk_delete(): void
    {
        $event = Event::factory()->create(); $this->actingAs($this->manager($event));
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'match_status' => 0, 'winner_registration' => null]);
        $other = Draw::factory()->create(['event_id' => $event->id, 'team_draw_selection' => ['competition' => ['source_draw_id' => $draw->id]]]);
        $this->preview($event, [$draw->id])->assertUnprocessable();
        $other->update(['team_draw_selection' => null]);
        Fixture::factory()->create(['draw_id' => $other->id, 'parent_fixture_id' => $fixture->id]);
        $this->preview($event, [$draw->id])->assertUnprocessable();
    }

    public function test_deletion_failure_rolls_back_prior_draw_cleanup_and_audits(): void
    {
        $event = Event::factory()->create(); $this->actingAs($this->manager($event));
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $service = app(BulkDrawDeletionService::class);
        $revision = $service->review($event, auth()->user(), $draws->modelKeys())['revision'];
        $dispatcher = Draw::getEventDispatcher();
        Draw::setEventDispatcher(clone $dispatcher);
        Draw::deleting(function ($draw) use ($draws) { if ($draw->id === $draws[1]->id) throw new \RuntimeException('Injected deletion failure'); });
        try { $service->review($event, auth()->user(), $draws->modelKeys(), $revision); $this->fail('Failure was not propagated.'); }
        catch (\RuntimeException $error) { $this->assertSame('Injected deletion failure', $error->getMessage()); }
        finally { Draw::setEventDispatcher($dispatcher); }
        $this->assertDatabaseCount('draws', 2);
        $this->assertSame(0, DB::table('draw_audit_logs')->where('action', 'deleted')->count());
    }

    public function test_foreign_event_sharing_or_incoming_programme_references_block_deletion(): void
    {
        $event = Event::factory()->create(); $this->actingAs($this->manager($event));
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $foreignEvent = Event::factory()->create();
        DB::table('draw_events')->insert(['draw_id' => $draw->id, 'event_id' => $foreignEvent->id]);
        $this->preview($event, [$draw->id])->assertUnprocessable();
        DB::table('draw_events')->where('draw_id', $draw->id)->delete();
        Draw::factory()->create(['event_id' => $foreignEvent->id, 'team_draw_selection' => ['competition' => ['source_draw_id' => $draw->id]]]);
        $this->preview($event, [$draw->id])->assertUnprocessable();
        $this->assertDatabaseHas('draws', ['id' => $draw->id]);
    }

    public function test_deletion_prunes_only_selected_draw_references_from_saved_schedule_preferences(): void
    {
        $event = Event::factory()->create(); $this->actingAs($this->manager($event));
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id]);
        $fixture = TeamFixture::create(['draw_id' => $draws[0]->id, 'match_nr' => 1, 'match_status' => 0]);
        $otherFixture = TeamFixture::create(['draw_id' => $draws[1]->id, 'match_nr' => 1, 'match_status' => 0]);
        $deletedKey = 'team:'.$fixture->id; $retainedKey = 'team:'.$otherFixture->id;
        $options = ['duration' => 90, 'draw_ids' => $draws->modelKeys(), 'draw_rounds' => [
            ['draw_id' => $draws[0]->id, 'rounds' => [1]], ['draw_id' => $draws[1]->id, 'rounds' => [2]]],
            'programme' => ['rounds' => [['drawId' => $draws[0]->id, 'round' => 1], ['drawId' => $draws[1]->id, 'round' => 2]]],
            'selected_fixture_keys' => [$deletedKey, $retainedKey, 'individual:'.$fixture->id],
            'preview_moves' => [['fixture_key' => $deletedKey, 'court' => '1'], ['fixture_key' => $retainedKey, 'court' => '2']],
            'match_timing' => [$deletedKey => ['duration' => 60], $retainedKey => ['duration' => 90]]];
        DB::table('event_venue_schedule_drafts')->insert(['event_id' => $event->id, 'options' => json_encode($options), 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $token = $this->preview($event, [$draws[0]->id])->assertOk()->json('revision');
        $this->applySelection($event, [$draws[0]->id], $token)->assertOk();
        $saved = json_decode(DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true);
        $this->assertSame(90, $saved['duration']);
        $this->assertSame([$draws[1]->id], $saved['draw_ids']);
        $this->assertCount(1, $saved['draw_rounds']);
        $this->assertSame($draws[1]->id, $saved['draw_rounds'][0]['draw_id']);
        $this->assertSame([2], $saved['draw_rounds'][0]['rounds']);
        $this->assertCount(1, $saved['programme']['rounds']);
        $this->assertSame($draws[1]->id, $saved['programme']['rounds'][0]['drawId']);
        $this->assertSame(2, $saved['programme']['rounds'][0]['round']);
        $this->assertSame([$retainedKey, 'individual:'.$fixture->id], $saved['selected_fixture_keys']);
        $this->assertCount(1, $saved['preview_moves']);
        $this->assertSame($retainedKey, $saved['preview_moves'][0]['fixture_key']);
        $this->assertSame('2', $saved['preview_moves'][0]['court']);
        $this->assertSame([$retainedKey => ['duration' => 90]], $saved['match_timing']);
    }

    public function test_malformed_foreign_schedule_references_block_cleanup_in_both_directions(): void
    {
        $event = Event::factory()->create(); $this->actingAs($this->manager($event));
        $draw = Draw::factory()->create(['event_id' => $event->id]); $foreign = Draw::factory()->create();
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'match_status' => 0]);
        $foreignFixture = Fixture::factory()->create(['draw_id' => $foreign->id, 'match_status' => 0]);
        $venue = \App\Models\Venue::forceCreate(['name' => 'Shared courts']);
        foreach ([[$draw->id, $foreignFixture->id], [$foreign->id, $fixture->id]] as [$drawId, $fixtureId]) {
            $id = DB::table('schedules')->insertGetId(['draw_id' => $drawId, 'fixture_id' => $fixtureId,
                'venue_id' => $venue->id, 'time' => now()->addDay()]);
            $this->preview($event, [$draw->id])->assertUnprocessable();
            $this->assertDatabaseHas('schedules', ['id' => $id]);
            $this->assertDatabaseHas('draws', ['id' => $draw->id]);
            DB::table('schedules')->where('id', $id)->delete();
        }
    }

    public function test_event_manager_page_renders_scoped_selection_controls_and_unrelated_user_cannot_read_it(): void
    {
        $type = \App\Models\EventType::forceCreate(['name' => 'Team', 'type' => \App\Models\EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type->id]);
        $actor = $this->manager($event); $this->actingAs($actor);
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $drawType = DB::table('draw_types')->insertGetId(['drawTypeName' => 'Team - Singles', 'btn_color' => 'primary', 'type' => 'team']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawType_id' => $drawType, 'drawName' => 'Synthetic regional singles']);
        $url = route('headOffice.show', $event);
        $response = $this->get($url)->assertOk()->assertSee('data-draw-delete-toolbar', false)
            ->assertSee('data-delete-draw value="'.$draw->id.'"', false)->assertSee('Delete selected draws')->assertSee('Select all draws')
            ->assertSee('js/bulk-draw-deletion.js', false)->assertSee(route('backend.event-draws.delete-preview', $event), false);
        if ($path = getenv('BULK_DRAW_DELETE_SNAPSHOT')) file_put_contents($path, $response->getContent());
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_cross_draw_team_tie_references_block_deletion_in_both_directions(): void
    {
        $event = Event::factory()->create(); $this->actingAs($this->manager($event));
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $foreign = Draw::factory()->create();
        $tie = \App\Models\TeamTie::factory()->create(['draw_id' => $draw->id]);
        $foreignTie = \App\Models\TeamTie::factory()->create(['draw_id' => $foreign->id]);
        $revision = $this->preview($event, [$draw->id])->assertOk()->json('revision');
        foreach ([[$foreign->id, $tie->id], [$draw->id, $foreignTie->id]] as [$drawId, $tieId]) {
            $fixture = TeamFixture::create(['draw_id' => $drawId, 'team_tie_id' => $tieId, 'match_nr' => 1, 'match_status' => 0]);
            $this->preview($event, [$draw->id])->assertUnprocessable()->assertSee('inconsistent team tie references');
            $this->applySelection($event, [$draw->id], $revision)->assertUnprocessable();
            $this->assertDatabaseHas('draws', ['id' => $draw->id]);
            $this->assertDatabaseHas('team_ties', ['id' => $tie->id]);
            $this->assertDatabaseHas('team_ties', ['id' => $foreignTie->id]);
            $this->assertDatabaseHas('team_fixtures', ['id' => $fixture->id]);
            $fixture->delete();
        }
    }
}
