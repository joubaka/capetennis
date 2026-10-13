<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, Player, SelectionPreviewComment, SelectionPreviewGrant, Team, TeamPlayer, TeamRegion, TeamResultSelectionDraft, User};
use App\Services\TeamSelectionPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SelectionPreviewDiscussionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_conveners_read_and_append_notes_without_changing_saved_selection(): void
    {
        [$event, $draft, $actor, $players] = $this->scenario();
        $viewer = User::factory()->create();
        $grant = SelectionPreviewGrant::create(['event_id' => $event->id, 'user_id' => $viewer->id, 'granted_by' => $actor->id]);
        $url = route('backend.selection-preview-comments.show', $event);
        $target = ['group_key' => '10-boys', 'player_key' => (string) $players[0]->id];
        $before = $draft->fresh()->getRawOriginal();
        $this->actingAs($viewer)->getJson($url.'?'.http_build_query($target))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('original_note', '<script>original</script>')->assertJsonCount(0, 'comments');
        $this->postJson($url, $target + ['draft_version' => 1, 'body' => '<img src=x onerror=alert(1)> My observation'])->assertCreated();
        $this->getJson($url.'?'.http_build_query($target))->assertOk()->assertJsonPath('comments.0.author', $viewer->name)
            ->assertJsonPath('comments.0.body', '<img src=x onerror=alert(1)> My observation')->assertJsonPath('comments.0.draft_version', 1)
            ->assertDontSee($viewer->email);
        $this->assertSame($before, $draft->fresh()->getRawOriginal());
        $this->assertDatabaseCount('team_result_selection_revisions', 0);
        $this->putJson(route('backend.team-result-selection.store', $event), [])->assertForbidden();
        $this->postJson($url, $target + ['draft_version' => 2, 'body' => 'Stale'])->assertConflict();
        $this->postJson($url, array_replace($target, ['player_key' => 'foreign']) + ['draft_version' => 1, 'body' => 'Foreign'])->assertNotFound();
        $other = Event::factory()->create();
        $this->getJson(route('backend.selection-preview-comments.show', $other).'?'.http_build_query($target))->assertForbidden();
        $this->postJson($url, $target + ['draft_version' => 1, 'body' => str_repeat('a', 2001)])->assertUnprocessable();
        $grant->update(['revoked_at' => now()]);
        $this->getJson($url.'?'.http_build_query($target))->assertForbidden();
        $this->postJson($url, $target + ['draft_version' => 1, 'body' => 'Revoked'])->assertForbidden();
        $this->assertDatabaseCount('selection_preview_comments', 1);
    }

    public function test_notes_badges_are_escaped_and_discussion_reads_are_paginated(): void
    {
        [$event, $draft, $actor, $players] = $this->scenario();
        foreach (range(1, 21) as $number) SelectionPreviewComment::create(['event_id' => $event->id, 'group_key' => '10-boys', 'player_key' => (string) $players[0]->id,
            'author_id' => $actor->id, 'draft_version' => 1, 'body' => 'Discussion '.$number, 'created_at' => now()]);
        $url = route('backend.selection-preview-comments.show', $event).'?group_key=10-boys&player_key='.$players[0]->id;
        $this->getJson($url)->assertOk()->assertJsonCount(20, 'comments')->assertJsonPath('comments.0.body', 'Discussion 21');
        $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(1, 'comments');
        $this->get(route('backend.selection-preview.show', $event))->assertOk()->assertSee('data-preview-note', false)
            ->assertDontSee('<script>original</script>', false)->assertSee('selection-note-modal')->assertSee('preview-selected-team');
    }

    public function test_cross_band_review_flags_only_unambiguous_same_team_order_inversions(): void
    {
        [$event, $draft, , $players, $team] = $this->scenario();
        $preview = app(TeamSelectionPreviewService::class)->preview($event, 10, 'boys');
        $selected = $preview['groups']->first()['selected'];
        $this->assertSame('above', $selected[0]['review']['kind']);
        $this->assertSame('below', $selected[1]['review']['kind']);
        $rows = $preview['groups']->first()['teams']->first()['rows'];
        $this->assertSame('below', $rows[0]['players']->first()['review']['kind']);
        $this->assertSame('above', $rows[4]['players']->first()['review']['kind']);
        $snapshot = $draft->snapshot;
        $snapshot[1]['rank'] = 2; $snapshot[1]['ranks'] = [2];
        $draft->update(['snapshot' => $snapshot]);
        $this->assertNull(app(TeamSelectionPreviewService::class)->preview($event, 10)['groups']->first()['selected'][0]['review']);
        $snapshot[1]['rank'] = 5; $snapshot[1]['ranks'] = [5]; $snapshot[1]['source_team_ids'] = [$team->id + 100];
        $draft->update(['snapshot' => $snapshot]);
        $this->assertNull(app(TeamSelectionPreviewService::class)->preview($event, 10)['groups']->first()['selected'][0]['review']);
        $snapshot[1]['source_team_ids'] = [$team->id]; $snapshot[1]['ranks'] = [3, 5];
        $draft->update(['snapshot' => $snapshot]);
        $this->assertNull(app(TeamSelectionPreviewService::class)->preview($event, 10)['groups']->first()['selected'][0]['review']);
    }

    private function scenario(): array
    {
        Role::findOrCreate('super-user', 'web');
        $actor = User::factory()->create()->assignRole('super-user'); $this->actingAs($actor);
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]); $category->category->update(['name' => 'u/10 Boys']);
        $region = TeamRegion::create(['region_name' => 'Source region']); $event->regions()->attach($region);
        $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id, 'name' => 'Source Boys']);
        $players = Player::factory()->count(2)->create();
        $snapshot = $players->map(function ($player, $index) use ($team) {
            $rank = $index ? 5 : 1;
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank]);
            return ['id' => $player->id, 'name' => $player->name.' '.$player->surname, 'teams' => [$team->name], 'source_team_ids' => [$team->id], 'rank' => $rank,
                'ranks' => [$rank], 'draft_position' => $index ? 1 : 2, 'source_participants' => [['team_id' => $team->id, 'type' => 'profile', 'id' => $player->id]]];
        })->all();
        $draft = TeamResultSelectionDraft::create(['event_id' => $event->id, 'group_key' => '10-boys', 'region_ids' => [$region->id], 'formats' => ['singles'],
            'excluded_result_region_ids' => [], 'selected_keys' => $players->pluck('id')->map('strval')->all(), 'reasons' => [(string) $players[0]->id => '<script>original</script>'],
            'version' => 1, 'snapshot' => $snapshot, 'updated_by' => $actor->id]);
        return [$event, $draft, $actor, $players, $team];
    }
}
