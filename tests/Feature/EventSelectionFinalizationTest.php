<?php

namespace Tests\Feature;

use App\Models\{Event, EventSelectionFinalization, Player, SelectionPreviewGrant, TeamResultSelectionDraft, User};
use App\Services\{TeamResultRankingService, TeamResultSelectionDraftService, TeamSelectionPreviewService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventSelectionFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_saved_groups_finalize_atomically_idempotently_and_read_the_frozen_ranking(): void
    {
        [$event, $actor, $drafts, $input] = $this->scenario(13, 12);
        $url = route('backend.selection-finalization.store', $event);
        $this->postJson($url, $input)->assertOk()->assertJsonCount(2, 'finalized_groups');
        $id = EventSelectionFinalization::first()->id;
        $this->postJson($url, $input)->assertOk()->assertJsonPath('finalization_id', $id);
        $this->assertDatabaseCount('event_selection_finalizations', 1);
        $final = EventSelectionFinalization::first();
        $this->assertCount(13, $final->groups['10-boys']['snapshot']); // Unselected candidates are frozen too.
        $this->assertSame('main', $final->groups['10-boys']['selection_statuses'][(string) $drafts[0]->snapshot[0]['id']]['status']);
        $this->assertSame(2, collect($final->groups['10-boys']['selection_statuses'])->where('status', 'reserve')->count());
        $name = $final->groups['10-boys']['snapshot'][0]['name'];
        $changed = $drafts[0]->snapshot; $changed[0]['name'] = 'Later draft name';
        $drafts[0]->update(['snapshot' => $changed, 'version' => 2, 'reasons' => []]);
        $event->team_result_settings = ['team_size' => 1, 'reserve_count' => 0]; $event->save();
        $preview = app(TeamSelectionPreviewService::class)->preview($event, 10, 'boys');
        $this->assertSame(10, $preview['teamSize']);
        $this->assertSame($name, $preview['groups']->first()['selected']->first()['name']);
        $this->get(route('backend.selection-preview.show', ['event' => $event, 'age' => 10, 'gender' => 'boys']))->assertOk()->assertSee('Finalized ranking')
            ->assertDontSee('Later draft name')->assertDontSee('id="preview-team-setup"', false);
        $this->putJson(route('backend.team-selection-size.store', $event), ['team_size' => 10, 'reserve_count' => 2])->assertConflict();
        $noteUrl = route('backend.selection-preview-comments.show', $event).'?group_key=10-boys&player_key='.$drafts[0]->snapshot[0]['id'];
        $this->getJson($noteUrl)->assertOk()->assertJsonPath('original_note', 'Original decision')->assertJsonPath('draft_version', 1);
        $this->assertSame($actor->id, $final->finalized_by);
        $this->assertFalse($event->fresh()->results_published);
        $this->assertDatabaseCount('team_result_selection_revisions', 0);
    }

    public function test_missing_empty_over_capacity_or_changed_drafts_and_settings_never_partially_finalize(): void
    {
        [$event, , $drafts, $input] = $this->scenario();
        $url = route('backend.selection-finalization.store', $event);
        $this->postJson($url, array_replace($input, ['versions' => ['10-boys' => 1]]))->assertConflict();
        $this->postJson($url, array_replace($input, ['versions' => ['10-boys' => 2, '10-girls' => 1]]))->assertConflict();
        $selected = $drafts[1]->selected_keys;
        $drafts[1]->update(['selected_keys' => []]);
        $this->postJson($url, $input)->assertUnprocessable();
        $drafts[1]->update(['selected_keys' => $selected]);
        $event->team_result_settings = ['team_size' => 8, 'reserve_count' => 4]; $event->save();
        $this->postJson($url, $input)->assertConflict(); // Reviewed counts changed without changing draft versions.
        $event->team_result_settings = ['team_size' => 1, 'reserve_count' => 0]; $event->save();
        $this->postJson($url, array_replace($input, ['team_size' => 1, 'reserve_count' => 0]))->assertUnprocessable();
        $this->assertDatabaseCount('event_selection_finalizations', 0);
    }

    public function test_finalized_event_blocks_canonical_draft_writes_and_basic_grantees_cannot_finalize(): void
    {
        [$event, $actor, $drafts, $input] = $this->scenario(13, 12);
        $viewer = User::factory()->create();
        SelectionPreviewGrant::create(['event_id' => $event->id, 'user_id' => $viewer->id, 'granted_by' => $actor->id]);
        $this->actingAs($viewer)->postJson(route('backend.selection-finalization.store', $event), $input)->assertForbidden();
        $other = Event::factory()->create();
        $this->actingAs($actor)->postJson(route('backend.selection-finalization.store', $other), $input)->assertForbidden();
        $this->postJson(route('backend.selection-finalization.store', $event), $input)->assertOk();
        $ranking = Mockery::mock(TeamResultRankingService::class);
        $ranking->shouldReceive('setup')->andReturn(['groups' => collect([['key' => '10-boys'], ['key' => '11-boys']]), 'regions' => collect(), 'formats' => collect()]);
        $ranking->shouldReceive('ranking')->andReturn(collect($drafts[0]->snapshot));
        $this->app->instance(TeamResultRankingService::class, $ranking);
        foreach (['10-boys', '11-boys'] as $key) {
            $this->putJson(route('backend.team-result-selection.store', $event), ['group_key' => $key, 'version' => 1, 'region_ids' => [], 'formats' => [], 'selected_keys' => $drafts[0]->selected_keys, 'reasons' => []])->assertConflict();
        }
        $this->assertDatabaseCount('team_result_selection_drafts', 2);
        $this->assertDatabaseCount('team_result_selection_revisions', 0);
    }

    public function test_unlock_preserves_history_restores_frozen_selections_and_allows_new_finalization(): void
    {
        [$event, $actor, $drafts, $input] = $this->scenario(13, 12);
        $this->postJson(route('backend.selection-finalization.store', $event), $input)->assertOk();
        $final = EventSelectionFinalization::first();
        $groups = $final->groups;
        $drafts[0]->update(['selected_keys' => [], 'version' => 4]);
        $unlock = ['finalization_id' => $final->id, 'reason' => 'Correct a selection', 'confirm_unlock' => 1];
        $url = route('backend.selection-finalization.reopen', $event);
        $this->postJson($url, $unlock)->assertOk()->assertJsonPath('unlocked', true);
        $this->postJson($url, $unlock)->assertOk();
        $this->assertDatabaseCount('event_selection_reopenings', 1);
        $this->assertDatabaseCount('team_result_selection_revisions', 2);
        $this->assertSame($groups, $final->fresh()->groups);
        $this->assertFalse(EventSelectionFinalization::active()->where('event_id', $event->id)->exists());
        $this->assertSame($groups['10-boys']['selected_keys'], $drafts[0]->fresh()->selected_keys);
        $this->assertSame(5, $drafts[0]->fresh()->version);
        $this->get(route('backend.selection-preview.show', ['event' => $event, 'age' => 10, 'gender' => 'boys']))->assertOk()->assertSee('Finalize all saved teams');
        $input['versions'] = ['10-boys' => 5, '10-girls' => 2];
        $this->postJson(route('backend.selection-finalization.store', $event), $input)->assertOk();
        $this->assertDatabaseCount('event_selection_finalizations', 2);
        $this->postJson($url, $unlock)->assertConflict();
        $this->assertFalse($event->fresh()->results_published);
        $this->assertDatabaseHas('event_selection_reopenings', ['finalization_id' => $final->id, 'reopened_by' => $actor->id, 'reason' => 'Correct a selection']);
    }

    public function test_unlock_requires_manager_review_and_event_scoped_finalization(): void
    {
        [$event, $actor, , $input] = $this->scenario();
        $this->postJson(route('backend.selection-finalization.store', $event), $input)->assertOk();
        $final = EventSelectionFinalization::first();
        $url = route('backend.selection-finalization.reopen', $event);
        $unlock = ['finalization_id' => $final->id, 'reason' => 'Correction', 'confirm_unlock' => 1];
        $this->postJson($url, array_replace($unlock, ['confirm_unlock' => 0]))->assertUnprocessable();
        $this->postJson($url, array_replace($unlock, ['reason' => ' ']))->assertUnprocessable();
        $viewer = User::factory()->create();
        SelectionPreviewGrant::create(['event_id' => $event->id, 'user_id' => $viewer->id, 'granted_by' => $actor->id]);
        $this->actingAs($viewer)->postJson($url, $unlock)->assertForbidden();
        $this->get(route('backend.selection-preview.show', $event))->assertOk()->assertDontSee('Unlock all saved teams');
        $other = Event::factory()->create(); $actor->adminEvents()->attach($other);
        $this->actingAs($actor)->postJson(route('backend.selection-finalization.reopen', $other), $unlock)->assertNotFound();
        $this->assertDatabaseCount('event_selection_reopenings', 0);
        $this->assertTrue(EventSelectionFinalization::active()->where('event_id', $event->id)->exists());
    }

    private function scenario(int $playerCount = 3, int $selectedCount = 2): array
    {
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['results_published' => false]); $actor->adminEvents()->attach($event); $this->actingAs($actor);
        $drafts = collect(['10-boys', '10-girls'])->map(function ($key) use ($event, $actor, $playerCount, $selectedCount) {
            $players = Player::factory()->count($playerCount)->create();
            $snapshot = $players->map(fn ($player, $index) => ['id' => $player->id, 'name' => $player->name.' '.$player->surname, 'teams' => ['Source team'],
                'source_team_ids' => [1], 'rank' => $index + 1, 'ranks' => [$index + 1], 'draft_position' => $index + 1])->all();
            return TeamResultSelectionDraft::create(['event_id' => $event->id, 'group_key' => $key, 'version' => 1, 'snapshot' => $snapshot,
                'region_ids' => [], 'excluded_result_region_ids' => [], 'formats' => [], 'selected_keys' => $players->take($selectedCount)->pluck('id')->map('strval')->all(),
                'reasons' => [(string) $players[0]->id => 'Original decision'], 'updated_by' => $actor->id]);
        });
        return [$event, $actor, $drafts, ['versions' => ['10-boys' => 1, '10-girls' => 1], 'team_size' => 10, 'reserve_count' => 2]];
    }
}
