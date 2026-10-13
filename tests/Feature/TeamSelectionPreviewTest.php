<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, TeamResultSelectionDraft, TeamSubstitution, User};
use App\Services\TeamSelectionPreviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSelectionPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_private_event_scoped_and_includes_unselected_rosters_without_results(): void
    {
        [$event, $team, $region] = $this->scenario();
        $players = collect(range(1, 12))->map(function ($rank) use ($team) {
            $player = Player::factory()->create(['name' => 'Player'.$rank, 'surname' => 'Audit']);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank]);
            return $player;
        });
        $snapshot = $players->map(fn ($player, $index) => ['id' => $player->id, 'name' => $player->name.' Audit', 'teams' => [$team->name],
            'source_team_ids' => [$team->id], 'draft_position' => 12 - $index])->all();
        $this->draft($event, $region, $players->pluck('id')->map('strval')->all(), $snapshot);
        $noResults = Team::factory()->create(['name' => 'No results Boys team', 'category_event_id' => $team->category_event_id, 'region_id' => $region->id]);
        $unselected = Player::factory()->create(['name' => 'Unselected', 'surname' => 'Audit']);
        TeamPlayer::create(['team_id' => $noResults->id, 'player_id' => $unselected->id, 'rank' => 1]);
        $excludedRegion = TeamRegion::create(['region_name' => 'Not a candidate']);
        $event->regions()->attach($excludedRegion);
        Team::factory()->create(['name' => 'Excluded source team', 'category_event_id' => $team->category_event_id, 'region_id' => $excludedRegion->id]);
        $foreignCategory = CategoryEvent::factory()->create();
        $foreignCategory->category->update(['name' => 'u/10 Boys']);
        Team::factory()->create(['name' => 'Foreign event team', 'category_event_id' => $foreignCategory->id, 'region_id' => $region->id]);
        $preview = app(TeamSelectionPreviewService::class)->preview($event->fresh(), 10);
        $group = $preview['groups']->first();
        $this->assertSame(12, $group['selected']->count());
        $this->assertSame($players->last()->id, $group['selected']->first()['id']);
        $this->assertCount(2, $group['teams']);
        $this->assertFalse($group['teams']->firstWhere('name', 'No results Boys team')['rows']->first()['players']->first()['selected']);
        $this->assertCount(12, $group['teams']->firstWhere('name', $team->name)['rows']);
        $url = route('backend.selection-preview.show', $event);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('12 selected')->assertSee('No results Boys team')->assertSee('Unselected Audit')->assertSee('class="text-success"', false)
            ->assertDontSee('Excluded source team')->assertDontSee('Foreign event team');
        $this->get($url.'?age=11')->assertNotFound();
        $this->assertDatabaseCount('team_result_selection_revisions', 0);
        $manager = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        $manager->adminEvents()->attach($event);
        $this->actingAs($manager)->get($url)->assertOk();
        $this->get(route('backend.selection-preview.show', $foreignCategory->event_id))->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_linked_imported_identity_is_exact_and_legacy_substitution_does_not_inherit_green(): void
    {
        [$event, $team, $region] = $this->scenario();
        $profile = Player::factory()->create();
        $imported = NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => 1, 'pay_status' => 0, 'name' => 'Imported', 'surname' => 'Original', 'player_profile' => $profile->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $profile->id, 'rank' => 1]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => 0, 'rank' => 2]);
        $key = 'imported:'.$team->id.':'.$imported->id;
        $draft = $this->draft($event, $region, [$key], [['id' => $key, 'name' => 'Imported Original', 'teams' => [$team->name], 'source_team_ids' => [$team->id], 'draft_position' => 1]]);
        $rows = app(TeamSelectionPreviewService::class)->preview($event->fresh(), 10)['groups']->first()['teams']->first()['rows'];
        $this->assertCount(1, $rows->first()['players']);
        $this->assertTrue($rows->first()['players']->first()['selected']);
        $this->assertCount(0, $rows[1]['players']);
        $replacement = NoProfileTeamPlayer::create(['name' => 'Different', 'surname' => 'Person', 'rank' => 1, 'pay_status' => 0]);
        TeamSubstitution::create(['event_id' => $event->id, 'team_id' => $team->id, 'actor_id' => auth()->id(), 'request_key' => 'preview-substitution', 'fingerprint' => str_repeat('a', 64), 'created_at' => now(),
            'details' => ['scope' => 'next', 'source_rank' => 1, 'new_type' => 'imported', 'new_identity_id' => $replacement->id, 'anchor_type' => 'imported', 'anchor_id' => $imported->id]]);
        $group = app(TeamSelectionPreviewService::class)->preview($event->fresh(), 10)['groups']->first();
        $this->assertFalse($group['teams']->first()['rows']->first()['players']->first()['selected']);
        $this->assertCount(1, $group['missing']);
        $snapshot = $draft->snapshot;
        $snapshot[0]['source_participants'] = [['team_id' => $team->id, 'type' => 'imported', 'id' => $replacement->id]];
        $draft->update(['snapshot' => $snapshot]);
        $group = app(TeamSelectionPreviewService::class)->preview($event->fresh(), 10)['groups']->first();
        $this->assertTrue($group['teams']->first()['rows']->first()['players']->first()['selected']);
        $this->assertCount(0, $group['missing']);
    }

    public function test_undrafted_groups_use_event_settings_and_have_no_selected_players(): void
    {
        [$event, $team, $region] = $this->scenario();
        $event->team_result_settings = ['region_ids' => [], 'excluded_result_region_ids' => [], 'formats' => []];
        $event->save();
        $this->assertCount(0, app(TeamSelectionPreviewService::class)->preview($event, 10)['groups']);
        $event->team_result_settings = ['region_ids' => [$region->id]];
        $event->save();
        $group = app(TeamSelectionPreviewService::class)->preview($event, 10)['groups']->first();
        $this->assertNull($group['draft']);
        $this->assertCount(0, $group['selected']);
        $this->assertCount(10, $group['teams']->first()['rows']);
    }

    private function scenario(): array
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $category->category->update(['name' => 'u/10']);
        $region = TeamRegion::create(['region_name' => 'Candidate region']);
        $event->regions()->attach($region);
        $team = Team::factory()->create(['name' => 'Source Boys', 'category_event_id' => $category->id, 'region_id' => $region->id]);
        return [$event, $team, $region];
    }

    private function draft(Event $event, TeamRegion $region, array $selected, array $snapshot): TeamResultSelectionDraft
    {
        return TeamResultSelectionDraft::create(['event_id' => $event->id, 'group_key' => '10-boys', 'region_ids' => [$region->id], 'formats' => ['singles'],
            'excluded_result_region_ids' => [], 'selected_keys' => $selected, 'reasons' => [], 'version' => 1, 'snapshot' => $snapshot, 'updated_by' => auth()->id()]);
    }
}
