<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventRegion, EventRegionManager, EventType, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CentralTeamWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;
    private EventRegion $eventRegion;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('super-user');
        $this->actingAs($this->admin);
        EventType::unguarded(fn () => EventType::updateOrCreate(['id' => 3], ['name' => 'Teams', 'type' => EventType::TEAM, 'code' => 'teams']));
        $this->event = Event::factory()->create(['eventType' => 3, 'name' => 'Synthetic Central Teams']);
        TeamRegion::create(['region_name' => 'Unrelated region']);
        $region = TeamRegion::create(['region_name' => 'North region']);
        $this->eventRegion = EventRegion::forceCreate(['event_id' => $this->event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->team = Team::factory()->create(['name' => 'North Primary', 'region_id' => $region->id, 'category_event_id' => $category->id, 'num_team_members' => 3, 'noProfile' => true]);
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Synthetic', 'surname' => 'Player', 'email' => 'confidential-roster@example.test', 'pay_status' => 0]);
        $linked = Player::factory()->create(['name' => 'Linked', 'surname' => 'Synthetic']);
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Original', 'surname' => 'Roster', 'player_profile' => $linked->id, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'player_id' => $linked->id, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 4, 'name' => 'Queued', 'surname' => 'Reserve', 'pay_status' => 0]);
        $profileTeam = Team::factory()->create(['name' => 'North Profiles', 'region_id' => $region->id, 'category_event_id' => $category->id, 'num_team_members' => 3, 'noProfile' => false]);
        TeamPlayer::create(['team_id' => $profileTeam->id, 'rank' => 1, 'player_id' => Player::factory()->create()->id, 'pay_status' => 0]);
        $south = TeamRegion::create(['region_name' => 'South private region']);
        EventRegion::forceCreate(['event_id' => $this->event->id, 'region_id' => $south->id, 'ordering' => 2]);
        Team::factory()->create(['name' => 'South Team', 'region_id' => $south->id, 'category_event_id' => $category->id, 'num_team_members' => 3, 'noProfile' => true]);
    }

    public function test_default_shell_has_one_region_team_context_and_lazy_invitations_without_roster_contacts(): void
    {
        $page = $this->get(route('admin.events.teams', $this->event))->assertOk();
        $page->assertSee('data-team-context', false)->assertSee('data-workspace-team', false)->assertSee('Players &amp; invitations', false)
            ->assertDontSee('data-bs-target="#tab-invitations"', false)->assertSee('Setup &amp; history', false)
            ->assertDontSee('confidential-roster@example.test')->assertDontSee('data-embedded-teams-selection', false)->assertDontSee('<iframe', false);
        $this->assertSame(1, substr_count($page->getContent(), 'id="roster-region"'));
        $this->assertSame(1, preg_match_all('/<select[^>]+data-workspace-team/', $page->getContent()));
        $this->assertSame(0, substr_count($page->getContent(), 'data-order-region'));
        $page->assertSee('value="'.$this->eventRegion->region_id.'" data-event-region-id="'.$this->eventRegion->id.'"', false);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $linkedRaw = NoProfileTeamPlayer::where('team_id', $this->team->id)->whereNotNull('player_profile')->firstOrFail();
        $this->get(route('admin.events.teams', [$this->event, 'roster_region' => $this->eventRegion->region_id]))->assertOk()
            ->assertSee('data-roster-player-id="'.$linkedRaw->player_profile.'" data-roster-imported-id="'.$linkedRaw->id.'"', false);
    }

    public function test_legacy_invitation_url_renders_same_shell_and_preserves_all_real_tools_without_duplicate_ids(): void
    {
        $url = route('backend.team-selection.index', [$this->event, 'selected_region' => $this->eventRegion->region_id, 'selected_team' => $this->team->id]);
        $page = $this->get($url)->assertOk();
        $page->assertSee('data-team-context', false)->assertSee('data-default-mode="invitations"', false)->assertSee('data-embedded-teams-selection', false)
            ->assertSee('Reset profile link')->assertSee('Import &amp; send', false)->assertSee('Help &amp; progress', false)->assertSee('Ranking setup')->assertSee('Link player profile');
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML($page->getContent());
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $ids = [];
        foreach ((new \DOMXPath($document))->query('//*[@id]') as $node) $ids[] = $node->getAttribute('id');
        $duplicates = array_keys(array_filter(array_count_values($ids), fn ($count) => $count > 1));
        $baseline = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $baseline->loadHTML($this->get(route('admin.events.teams', $this->event))->getContent());
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $baselineIds = [];
        foreach ((new \DOMXPath($baseline))->query('//*[@id]') as $node) $baselineIds[] = $node->getAttribute('id');
        $this->assertSame(array_keys(array_filter(array_count_values($baselineIds), fn ($count) => $count > 1)), $duplicates);
        if (getenv('CT_CENTRAL_TEAMS_QA') === '1') {
            file_put_contents(storage_path('framework/testing/qa-central-teams.html'), $page->getContent());
            file_put_contents(storage_path('framework/testing/qa-central-roster.html'), $this->get(route('admin.events.teams', [$this->event, 'roster_region' => $this->eventRegion->region_id]))->getContent());
            file_put_contents(storage_path('framework/testing/qa-central-order.html'), $this->get(route('admin.events.teams', [$this->event, 'roster_region' => $this->eventRegion->region_id, 'panel' => 'order']))->getContent());
        }
    }

    public function test_lazy_invitation_contract_is_private_event_bound_and_does_not_mutate_records(): void
    {
        $url = route('backend.team-selection.index', [$this->event, 'workspace_fragment' => 1, 'selected_region' => $this->eventRegion->region_id, 'selected_team' => $this->team->id]);
        $fragment = $this->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonStructure(['html', 'style', 'script']);
        $this->assertStringContainsString('confidential-roster@example.test', $fragment->json('html'));
        $this->assertStringContainsString('syncTeamSelectionContext', $fragment->json('script'));
        $this->assertStringNotContainsString('id="roster-region"', $fragment->json('html'));
        $this->getJson(route('backend.team-selection.index', [$this->event, 'workspace_fragment' => 1, 'selected_region' => TeamRegion::create(['region_name' => 'Foreign region'])->id]))->assertNotFound();
        $foreignEvent = Event::factory()->create(['eventType' => 3]);
        $foreignTeam = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $foreignEvent->id])->id]);
        $this->getJson(route('backend.team-selection.index', [$this->event, 'workspace_fragment' => 1, 'selected_team' => $foreignTeam->id]))->assertNotFound();
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_regional_actor_retains_minimal_roster_and_cannot_load_hidden_invitation_data(): void
    {
        $manager = User::factory()->create();
        EventRegionManager::create(['event_id' => $this->event->id, 'event_region_id' => $this->eventRegion->id, 'region_id' => $this->eventRegion->region_id, 'user_id' => $manager->id]);
        $this->actingAs($manager);
        $this->get(route('admin.events.teams', $this->event))->assertOk()->assertSee('North region')->assertDontSee('South private region')
            ->assertDontSee('confidential-roster@example.test')->assertDontSee('id="tab-invitations"', false)->assertDontSee('data-embedded-teams-selection', false);
        $this->getJson(route('backend.team-selection.index', [$this->event, 'workspace_fragment' => 1]))->assertForbidden();
    }

    public function test_custom_team_event_type_uses_same_central_shell_and_preserves_validation_input(): void
    {
        $type = EventType::unguarded(fn () => EventType::create(['name' => 'Custom team event', 'type' => EventType::TEAM, 'code' => 'custom-central-team']));
        $this->event->update(['eventType' => $type->id]);
        $this->withSession(['_old_input' => ['settings_team_id' => $this->team->id, 'num_team_members' => 7]])
            ->get(route('admin.events.teams', [$this->event, 'roster_tab' => 'invitations', 'selected_region' => $this->eventRegion->region_id, 'selected_team' => $this->team->id]))
            ->assertOk()->assertDontSee('id="tab-invitations"', false)->assertSee('data-embedded-teams-selection', false)->assertSee('name="num_team_members" value="7"', false);
    }

    public function test_managed_team_preserves_real_invitation_and_selection_order_handlers_in_shared_context(): void
    {
        $series = \App\Models\Series::create(['name' => 'Synthetic ranking series', 'year' => (int) $this->event->start_date->format('Y')]);
        $source = \App\Models\EventRegionRankingSource::create(['event_id' => $this->event->id, 'event_region_id' => $this->eventRegion->id, 'region_id' => $this->eventRegion->region_id, 'series_id' => $series->id, 'reserve_count' => 1]);
        $import = \App\Models\TeamSelectionImport::create(['event_id' => $this->event->id, 'region_id' => $this->eventRegion->region_id, 'source_id' => $source->id, 'series_id' => $series->id, 'ranking_run_id' => 'synthetic-central', 'status' => 'draft']);
        $player = Player::factory()->create();
        \App\Models\TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $this->event->id, 'region_id' => $this->eventRegion->region_id, 'team_id' => $this->team->id, 'player_id' => $player->id, 'ranking_list_id' => 1, 'ranking_position' => 1, 'queue_position' => 1, 'status' => 'reserve']);
        $this->get(route('admin.events.teams', $this->event))->assertOk()->assertSee('value="'.$this->team->id.'" data-region="'.$this->eventRegion->region_id.'" data-managed="1"', false);
        $fragment = $this->getJson(route('backend.team-selection.index', [$this->event, 'workspace_fragment' => 1]))->assertOk();
        $this->assertStringContainsString('data-team-invitations', $fragment->json('html'));
        $this->assertStringContainsString(route('backend.team-selection.players.add', [$this->event, $import, $this->team]), $fragment->json('html'));
        $this->assertStringContainsString('id="team-order-'.$this->team->id.'"', $fragment->json('html'));
        $this->assertDatabaseCount('team_payment_orders', 0);
    }
}
