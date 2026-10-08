<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private TeamRegion $region;
    private Team $team;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actor = User::factory()->create()->assignRole('super-user');
        $this->actingAs($this->actor);
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => 'team']);
        $this->event = Event::factory()->create(['eventType' => 3]);
        $this->region = TeamRegion::create(['region_name' => 'Test region', 'short_name' => 'TR']);
        $this->event->regions()->attach($this->region->id);
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->team = Team::factory()->create(['region_id' => $this->region->id, 'category_event_id' => $category->id, 'num_team_members' => 2]);
    }

    public function test_workspace_initial_render_does_not_load_global_players_or_private_roster_contacts(): void
    {
        $player = Player::factory()->create(['email' => 'private-roster@example.test']);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        $this->get(route('admin.events.teams', $this->event))
            ->assertOk()->assertSee('Find a player or team')->assertSee('data-roster-panel', false)
            ->assertDontSee('private-roster@example.test')->assertDontSee('Preserve payment status');
        $this->assertDatabaseCount('team_players', 1);
    }

    public function test_players_page_keeps_communication_and_one_regional_clothing_menu(): void
    {
        $player = Player::factory()->create(['name' => 'Synthetic', 'surname' => 'Player', 'email' => 'synthetic@example.test', 'cellNr' => '0123456789']);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        Team::factory()->create(['region_id' => $this->region->id, 'category_event_id' => $this->team->category_event_id]);
        $page = $this->get(route('admin.events.teams', $this->event).'?selected_region='.$this->region->id)->assertOk();
        $page->assertSee('More filters')->assertSee('Tools')->assertSee('Email history')
            ->assertSee('Sender details')->assertSee('Roster display filters do not change')
            ->assertDontSee('Selection &amp; reserves', false);
        $response = $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->region->id)->assertOk();
        $response->assertSee('Send team email')->assertSee('Clothing setup')
            ->assertDontSee('Team details')->assertDontSee('Manage selection &amp; reserves', false)->assertDontSee('Replace a player');
        $this->assertSame(1, substr_count($response->getContent(), '>Clothing setup</a>'));
        $this->assertDatabaseCount('wallet_transactions', 0);
        if (getenv('CT_BATCHES_QA') === '1') {
            $directory = storage_path('app/batches345-qa');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
            file_put_contents($directory.'/roster.html', str_replace('http://localhost', 'http://127.0.0.1:8775/ct/public', $page->getContent()));
            file_put_contents($directory.'/players-'.$this->region->id.'.html', $response->getContent());
            file_put_contents($directory.'/meta.json', json_encode(['region' => $this->region->id, 'path' => parse_url(route('admin.events.teams', $this->event), PHP_URL_PATH), 'category' => $this->team->category_event_id]));
        }
    }

    public function test_historical_rosters_load_both_panels_without_exposing_shared_region_rosters(): void
    {
        $this->team->update(['category_event_id' => null, 'name' => 'Historical u/10 Boys']);
        $player = Player::factory()->create(['name' => 'HistoricalPlayer']);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $player->id, 'rank' => 1]);
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'name' => 'HistoricalImport', 'surname' => 'Only', 'rank' => 2, 'pay_status' => 1]);
        $shared = TeamRegion::create(['region_name' => 'Shared region']);
        $this->event->regions()->attach($shared);
        Event::factory()->create()->regions()->attach($shared);
        $foreign = Team::factory()->create(['region_id' => $shared->id, 'name' => 'Foreign legacy roster']);
        NoProfileTeamPlayer::create(['team_id' => $foreign->id, 'name' => 'ForeignImport', 'surname' => 'Only', 'rank' => 1, 'pay_status' => 1]);
        $url = route('admin.events.teams', $this->event);
        $this->get($url)->assertOk()->assertSee('Historical u/10 Boys')->assertDontSee('Foreign legacy roster');
        foreach (['players', 'order'] as $panel) {
            $this->get($url.'?roster_region='.$this->region->id.'&panel='.$panel)->assertOk()->assertSee('HistoricalPlayer')->assertSee('HistoricalImport');
            $this->get($url.'?roster_region='.$shared->id.'&panel='.$panel)->assertOk()->assertDontSee('ForeignImport')->assertDontSee('Foreign legacy roster');
        }
        $this->assertDatabaseCount('team_players', 1);
        $this->assertDatabaseCount('no_profile_team_players', 2);
    }

    public function test_lazy_roster_is_event_scoped_and_maps_imported_contacts_by_team_and_rank(): void
    {
        foreach ([$this->team, Team::factory()->create(['region_id' => $this->region->id, 'category_event_id' => $this->team->category_event_id])] as $index => $team) {
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => 0, 'rank' => 1, 'pay_status' => 0]);
            NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => 1, 'name' => 'Imported', 'surname' => (string) $index, 'email' => "imported{$index}@example.test", 'cell_nr' => '0123456789', 'pay_status' => 0]);
        }
        $otherEvent = Event::factory()->create(['eventType' => 3]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        Team::factory()->create(['region_id' => $this->region->id, 'category_event_id' => $otherCategory->id, 'name' => 'Other event private team']);
        $response = $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->region->id);
        $response->assertOk()->assertSee('imported0@example.test')->assertSee('imported1@example.test')
            ->assertSee('0123456789')->assertDontSee('Other event private team')->assertDontSee('Change Pay Status');
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_lazy_panel_denies_foreign_region_and_unauthorized_actor(): void
    {
        $foreignRegion = TeamRegion::create(['region_name' => 'Foreign region']);
        $url = route('admin.events.teams', $this->event);
        $this->get($url.'?roster_region='.$foreignRegion->id)->assertNotFound();
        $this->actingAs(User::factory()->create())->get($url.'?roster_region='.$this->region->id)->assertForbidden();
    }

    public function test_empty_region_and_order_panel_are_renderable(): void
    {
        $empty = TeamRegion::create(['region_name' => 'Empty region']);
        $this->event->regions()->attach($empty->id);
        $this->get(route('admin.events.teams', $this->event).'?roster_region='.$empty->id)->assertOk()->assertSee('No teams in this region');
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->region->id.'&panel=order')
            ->assertOk()->assertSee('data-order-move', false)->assertSee($player->name);
    }

    public function test_imported_roster_without_mirror_is_visible_and_results_reject_foreign_category(): void
    {
        NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 1, 'name' => 'Imported', 'surname' => 'Only', 'email' => 'only@example.test', 'pay_status' => 0]);
        $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->region->id)->assertOk()->assertSee('only@example.test')->assertSee('1 occupied places');
        $this->postJson(route('get.event.category.data'), ['event_id' => $this->event->id, 'categoryEvent' => $this->team->category_event_id])->assertOk()->assertSee('No results recorded');
        $foreign = CategoryEvent::factory()->create();
        $this->postJson(route('get.event.category.data'), ['event_id' => $this->event->id, 'categoryEvent' => $foreign->id])->assertNotFound();
        $this->assertDatabaseCount('team_players', 0);
    }

    public function test_roster_fixture_has_distinct_payment_and_vacant_states(): void
    {
        foreach ([['Alice', 'Able', 1], ['Ben', 'Baker', 0]] as $index => [$name, $surname, $paid]) {
            $player = Player::factory()->create(['name' => $name, 'surname' => $surname, 'email' => strtolower($name).'@example.test', 'cellNr' => '0123456789']);
            TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $player->id, 'rank' => $index + 1, 'pay_status' => $paid]);
        }
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => 0, 'rank' => 3, 'pay_status' => 0]);
        $this->team->update(['name' => 'Primary schools U10 Boys', 'num_team_members' => 3]);
        $empty = TeamRegion::create(['region_name' => 'Second region']);
        $this->event->regions()->attach($empty->id);
        $url = route('admin.events.teams', $this->event);
        $panel = $this->get($url.'?roster_region='.$this->region->id)->assertOk()->assertSee('data-payment="vacant"', false)->assertSee('data-payment="paid"', false)->assertSee('data-payment="unpaid"', false);
        if (getenv('CT_WORKSPACE_QA') === '1') {
            $directory = storage_path('app/team-workspace-qa');
            \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
            $pages = ['index' => $this->get($url)->getContent(), 'workspace' => $this->get($url.'?workspace=1')->getContent(),
                'players-'.$this->region->id => $panel->getContent(), 'order-'.$this->region->id => $this->get($url.'?roster_region='.$this->region->id.'&panel=order')->getContent(),
                'players-'.$empty->id => $this->get($url.'?roster_region='.$empty->id)->getContent(), 'order-'.$empty->id => $this->get($url.'?roster_region='.$empty->id.'&panel=order')->getContent()];
            foreach ($pages as $name => $html) file_put_contents($directory.'/'.$name.'.html', str_replace('http://localhost', 'http://127.0.0.1:8767/ct/public', $html));
            file_put_contents($directory.'/meta.json', json_encode(['path' => parse_url($url, PHP_URL_PATH), 'event' => $this->event->id, 'region' => $this->region->id]));
        }
    }

    public function test_ajax_rename_returns_json_and_fragment_contains_authoritative_name(): void
    {
        $this->patchJson(route('backend.team.name.update', [$this->event, $this->team]), ['name' => 'Updated team'])->assertOk()->assertJsonPath('success', true);
        $this->get(route('admin.events.teams', $this->event).'?workspace=1')->assertOk()->assertSee('Updated team')->assertDontSee('<!DOCTYPE', false);
        $this->assertDatabaseHas('teams', ['id' => $this->team->id, 'name' => 'Updated team']);
    }

    public function test_order_panel_keeps_vacancy_slot_identifiers_and_payment_history(): void
    {
        $player = Player::factory()->create();
        $occupied = TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        $vacant = TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => 0, 'rank' => 2, 'pay_status' => 0]);
        $this->get(route('admin.events.teams', $this->event).'?roster_region='.$this->region->id.'&panel=order')
            ->assertOk()->assertSee('data-playerteamid="'.$vacant->id.'"', false)->assertSee('Vacant');
        $this->postJson(url('/backend/team/orderPlayerList'), ['team_id' => $this->team->id, 'order' => [
            ['id' => $vacant->id, 'team_player_id' => $vacant->id, 'type' => 'profile', 'position' => 1],
            ['id' => $occupied->id, 'team_player_id' => $occupied->id, 'type' => 'profile', 'position' => 2],
        ]])->assertOk();
        $this->assertDatabaseHas('team_players', ['id' => $occupied->id, 'rank' => 2, 'player_id' => $player->id, 'pay_status' => 1]);
        $this->assertDatabaseHas('team_players', ['id' => $vacant->id, 'rank' => 1, 'player_id' => 0, 'pay_status' => 0]);
        $this->assertDatabaseCount('team_players', 2);
    }
}
