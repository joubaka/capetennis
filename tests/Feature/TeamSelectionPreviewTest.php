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
        $this->assertSame(2, $group['selected']->where('reserve', true)->count());
        $sourceRows = $group['teams']->firstWhere('name', $team->name)['rows'];
        $this->assertTrue($sourceRows[0]['players']->first()['reserve']); // Draft order, not source roster rank.
        $this->assertFalse($sourceRows[10]['players']->first()['reserve']);
        $this->assertCount(2, $group['teams']);
        $this->assertFalse($group['teams']->firstWhere('name', 'No results Boys team')['rows']->first()['players']->first()['selected']);
        $this->assertFalse($group['teams']->firstWhere('name', 'No results Boys team')['rows']->first()['players']->first()['reserve']);
        $this->assertCount(12, $group['teams']->firstWhere('name', $team->name)['rows']);
        $url = route('backend.selection-preview.show', $event);
        $response = $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('12 selected')->assertSee('No results Boys team')->assertSee('Unselected Audit')->assertSee('class="text-success"', false)
            ->assertDontSee('Excluded source team')->assertDontSee('Foreign event team');
        $this->assertSame(4, substr_count($response->getContent(), 'class="badge bg-danger">Reserve</span>'));
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

    public function test_main_and_reserve_setup_is_event_scoped_and_preserves_drafts_and_other_settings(): void
    {
        [$event, $team, $region] = $this->scenario();
        $this->setupFixture($event, $team);
        $original = ['region_ids' => [$region->id], 'excluded_result_region_ids' => [$region->id], 'formats' => ['singles']];
        $event->team_result_settings = $original;
        $event->save();
        $snapshot = collect(range(1, 12))->map(fn ($id) => ['id' => $id, 'name' => 'Saved '.$id, 'teams' => [$team->name], 'source_team_ids' => [$team->id], 'draft_position' => $id])->all();
        $draft = $this->draft($event, $region, array_map('strval', range(1, 12)), $snapshot);
        $before = $draft->fresh()->getRawOriginal();
        $other = Event::factory()->create();
        $url = route('backend.team-selection-size.store', $event);
        $this->putJson($url, ['team_size' => 8, 'reserve_count' => 2])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertEquals($original + ['team_size' => 8, 'reserve_count' => 2], $event->fresh()->team_result_settings);
        $this->assertSame($before, $draft->fresh()->getRawOriginal());
        $this->assertNull($other->fresh()->team_result_settings);
        $group = app(TeamSelectionPreviewService::class)->preview($event->fresh(), 10)['groups']->first();
        $this->assertCount(12, $group['selected']);
        $this->assertSame([9, 10], $group['selected']->where('reserve', true)->pluck('id')->all());
        $this->assertSame([11, 12], $group['over_capacity']->pluck('id')->all());
        $this->get(route('backend.selection-preview.show', $event))->assertOk()->assertSee('Additional selections were kept');
        foreach ([['team_size' => 0, 'reserve_count' => 2], ['team_size' => 11, 'reserve_count' => 2], ['team_size' => 10, 'reserve_count' => -1]] as $invalid) {
            $this->putJson($url, $invalid)->assertUnprocessable();
        }
        $this->putJson(route('backend.team-result-settings.store', $event), $original)->assertOk();
        $this->assertSame(8, $event->fresh()->team_result_settings['team_size']);
        $this->assertSame(2, $event->fresh()->team_result_settings['reserve_count']);
        $this->put($url, ['team_size' => 12, 'reserve_count' => 0, 'age' => 10])->assertRedirect(route('backend.selection-preview.show', ['event' => $event, 'age' => 10]));
        $group = app(TeamSelectionPreviewService::class)->preview($event->fresh(), 10)['groups']->first();
        $this->assertSame(0, $group['selected']->where('reserve', true)->count());
        $this->assertSame(0, $group['over_capacity']->count());
        $manager = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        $manager->adminEvents()->attach($event);
        $this->actingAs($manager)->putJson($url, ['team_size' => 10, 'reserve_count' => 2])->assertOk();
        $this->putJson(route('backend.team-selection-size.store', $other), ['team_size' => 10, 'reserve_count' => 2])->assertForbidden();
        $this->actingAs(User::factory()->create())->putJson($url, ['team_size' => 10, 'reserve_count' => 2])->assertForbidden();
        $this->assertSame($before, $draft->fresh()->getRawOriginal());
        $this->assertDatabaseCount('team_result_selection_revisions', 0);
    }

    public function test_ten_or_fewer_selected_players_have_no_reserve_labels(): void
    {
        [$event, $team, $region] = $this->scenario();
        $snapshot = collect(range(1, 10))->map(fn ($id) => ['id' => $id, 'name' => 'Saved '.$id, 'teams' => [$team->name], 'source_team_ids' => [$team->id], 'draft_position' => $id])->all();
        $draft = $this->draft($event, $region, array_map('strval', range(1, 10)), $snapshot);
        $this->get(route('backend.selection-preview.show', $event))->assertOk()->assertDontSee('class="badge bg-danger">Reserve</span>', false);
        $draft->update(['selected_keys' => []]);
        $this->get(route('backend.selection-preview.show', $event))->assertOk()->assertDontSee('class="badge bg-danger">Reserve</span>', false);
    }

    public function test_saving_capacity_first_keeps_default_regions_and_formats_checked(): void
    {
        [$event, $team, $region] = $this->scenario();
        $this->setupFixture($event, $team);
        $this->putJson(route('backend.team-selection-size.store', $event), ['team_size' => 10, 'reserve_count' => 2])->assertOk();
        $this->assertEquals(['team_size' => 10, 'reserve_count' => 2], $event->fresh()->team_result_settings);
        $html = view('backend.adminPage.admin_show._partials.result-ranks', ['event' => $event->fresh()])->render();
        $this->assertMatchesRegularExpression('/data-result-region value="'.$region->id.'"\s+checked/', $html);
        $this->assertMatchesRegularExpression('/data-result-format value="singles"\s+checked/', $html);
        $this->putJson(route('backend.team-result-settings.store', $event), ['region_ids' => [], 'excluded_result_region_ids' => [], 'formats' => []])->assertOk();
        $html = view('backend.adminPage.admin_show._partials.result-ranks', ['event' => $event->fresh()])->render();
        $this->assertDoesNotMatchRegularExpression('/data-result-region value="'.$region->id.'"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-result-format value="singles"\s+checked/', $html);
    }

    public function test_navigation_chooses_one_available_age_and_gender_in_numeric_order(): void
    {
        [$event, $boys, $region] = $this->scenario();
        $girls = Team::factory()->create(['category_event_id' => $boys->category_event_id, 'region_id' => $region->id, 'name' => 'Girls only team']);
        TeamPlayer::create(['team_id' => $girls->id, 'player_id' => Player::factory()->create(['name' => 'GirlOnly', 'surname' => 'Player'])->id, 'rank' => 1]);
        TeamResultSelectionDraft::create(['event_id' => $event->id, 'group_key' => '11-girls', 'region_ids' => [$region->id], 'formats' => [], 'excluded_result_region_ids' => [],
            'selected_keys' => [], 'reasons' => [], 'version' => 1, 'snapshot' => [], 'updated_by' => auth()->id()]);
        $service = app(TeamSelectionPreviewService::class);
        $preview = $service->preview($event, 10, 'girls');
        $this->assertSame(['10-boys', '10-girls', '11-girls'], $preview['navigationGroups']->pluck('key')->all());
        $this->assertCount(1, $preview['groups']);
        $this->assertSame('u/10 Girls', $preview['groups']->first()['name']);
        $url = route('backend.selection-preview.show', $event);
        $this->get($url.'?age=10')->assertOk()->assertSee('Source Boys')->assertDontSee('GirlOnly Player');
        $this->get($url.'?age=10&gender=girls')->assertOk()->assertSee('GirlOnly Player')->assertDontSee('Source Boys')
            ->assertSee('roster_result_category=10-girls', false);
        $this->get($url.'?age=11&gender=girls')->assertOk()->assertSee('No saved selections.');
        $this->get($url.'?age=11&gender=boys')->assertNotFound();
        $this->getJson($url.'?age=10&gender=mixed')->assertUnprocessable();
        $this->put(route('backend.team-selection-size.store', $event), ['team_size' => 10, 'reserve_count' => 2, 'age' => 10, 'gender' => 'girls'])
            ->assertRedirect($url.'?age=10&gender=girls');
    }

    public function test_selected_names_use_saved_region_and_original_roster_position_with_ambiguity_visible(): void
    {
        [$event, $team, $region] = $this->scenario();
        $players = Player::factory()->count(2)->create();
        $snapshot = $players->map(fn ($player, $index) => ['id' => $player->id, 'name' => $player->name, 'region' => 'OVER', 'rank' => $index ? 5 : 1,
            'ranks' => [$index ? 5 : 1], 'source_team_ids' => [$team->id], 'teams' => [$team->name], 'draft_position' => $index ? 1 : 2])->all();
        $draft = $this->draft($event, $region, $players->pluck('id')->map('strval')->all(), $snapshot);
        $selected = app(TeamSelectionPreviewService::class)->preview($event, 10)['groups']->first()['selected'];
        $this->assertSame('OVER - 5', $selected[0]['origin_label']);
        $this->assertSame('OVER - 1', $selected[1]['origin_label']);
        $this->get(route('backend.selection-preview.show', $event))->assertOk()->assertSee('(OVER - 5)')->assertSee('(OVER - 1)')->assertSee($team->name);
        $snapshot[0]['ranks'] = [1, 3]; $snapshot[0]['source_team_ids'] = [$team->id, $team->id + 1];
        $draft->update(['snapshot' => $snapshot]);
        $this->assertSame('OVER - position unclear', app(TeamSelectionPreviewService::class)->preview($event, 10)['groups']->first()['selected'][1]['origin_label']);
    }

    private function setupFixture(Event $event, Team $team): void
    {
        $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $team->category_event_id]);
        \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'age' => 'u/10 Boys', 'fixture_type' => 1, 'numSets' => 3, 'round_nr' => 1, 'match_nr' => 1]);
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
