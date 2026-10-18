<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventAdmin, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportedRegionProfileMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;
    private Event $source;
    private TeamRegion $region;
    private Team $team;
    private Team $secondTeam;
    private Team $sourceTeam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->event = Event::factory()->create(['start_date' => now()->addMonth()]);
        $this->source = Event::factory()->create(['start_date' => now()->subMonth()]);
        foreach ([$this->event, $this->source] as $event) EventAdmin::create(['event_id' => $event->id, 'user_id' => $this->admin->id]);
        $this->region = TeamRegion::create(['region_name' => 'Test region']);
        DB::table('event_regions')->insert(['event_id' => $this->event->id, 'region_id' => $this->region->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->team = Team::factory()->create(['region_id' => $this->region->id, 'category_event_id' => $category->id, 'noProfile' => true]);
        $this->secondTeam = Team::factory()->create(['region_id' => $this->region->id, 'category_event_id' => $category->id, 'noProfile' => true]);
        $this->sourceTeam = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $this->source->id])->id]);
        $this->actingAs($this->admin);
    }

    private function slot(Team $team, string $name = 'Joshua', int $rank = 1): NoProfileTeamPlayer
    {
        return NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $name, 'surname' => 'van Wyk', 'rank' => $rank, 'pay_status' => 0]);
    }

    private function candidate(string $name = 'Joshua', int $rank = 1): Player
    {
        $player = Player::factory()->male()->create(['name' => $name, 'surname' => 'van Wyk']);
        TeamPlayer::create(['team_id' => $this->sourceTeam->id, 'player_id' => $player->id, 'rank' => $rank, 'pay_status' => 1]);
        return $player;
    }

    private function url(string $action = 'index', ?TeamRegion $region = null): string
    {
        return route('backend.team-selection.region-profile-matches.'.$action, [$this->event, $region ?? $this->region]);
    }

    private function preview(): \Illuminate\Testing\TestResponse
    {
        return $this->get($this->url().'?source_event_id='.$this->source->id)->assertOk();
    }

    private function payload(array $links): array
    {
        return ['source_event_id' => $this->source->id, 'roster_page' => 1, 'fingerprint' => $this->preview()->viewData('fingerprint'), 'confirm_links' => 1, 'links' => $links];
    }

    public function test_region_scans_all_imported_teams_preselects_unique_matches_and_confirms_one_batch(): void
    {
        $one = $this->slot($this->team);
        $two = $this->slot($this->secondTeam, 'Dirk');
        $playerOne = $this->candidate(); $playerTwo = $this->candidate('Dirk', 2);
        $otherRegionTeam = Team::factory()->create(['category_event_id' => $this->team->category_event_id, 'noProfile' => true]);
        $outside = $this->slot($otherRegionTeam, 'Outside');
        $otherEventTeam = Team::factory()->create(['region_id' => $this->region->id, 'category_event_id' => $this->sourceTeam->category_event_id, 'noProfile' => true]);
        $foreign = $this->slot($otherEventTeam, 'Foreign');
        $response = $this->preview()->assertSee($this->team->name)->assertSee($this->secondTeam->name)->assertDontSee('Outside van Wyk')->assertDontSee('Foreign van Wyk');
        $rows = $response->viewData('preview')['rows'];
        $this->assertCount(2, $rows);
        $this->assertEquals([$playerOne->id, $playerTwo->id], $rows->pluck('selectedPlayerId')->all());
        $response->assertSee('value="'.$playerOne->id.'" checked', false);
        $this->assertNull($one->fresh()->player_profile);
        $beforeLinks = DB::table('user_players')->count();
        $this->post($this->url('store'), $this->payload([$one->id => $playerOne->id, $two->id => $playerTwo->id]))->assertRedirect();
        $this->assertEquals($playerOne->id, $one->fresh()->player_profile);
        $this->assertEquals($playerTwo->id, $two->fresh()->player_profile);
        $this->assertNull($outside->fresh()->player_profile); $this->assertNull($foreign->fresh()->player_profile);
        $this->assertDatabaseCount('user_players', $beforeLinks);
        $this->assertDatabaseCount('players', 2);
        $this->assertDatabaseCount('team_payment_orders', 0);
    }

    public function test_duplicate_candidates_and_identity_conflicts_are_flagged_without_default_selection(): void
    {
        $duplicate = $this->slot($this->team);
        $this->candidate(); $this->candidate('Joshua', 2);
        $conflict = $this->slot($this->secondTeam, 'Dirk');
        $conflict->update(['date_of_birth' => '2014-01-02', 'email' => 'imported@example.test']);
        $player = $this->candidate('Dirk', 3); $player->update(['dateOfBirth' => '2013-01-02']);
        $this->slot($this->secondTeam, 'NoMatch', 2);
        $safe = $this->slot($this->secondTeam, 'Safe', 3); $this->candidate('Safe', 4);
        $response = $this->preview()->assertSee('Multiple profiles match')->assertSee('Date of birth differs')->assertSee('Email differs');
        if (getenv('CT_PROFILE_MATCH_QA') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/profile-match-qa'));
            file_put_contents(storage_path('app/profile-match-qa/region-preview.html'), $response->getContent());
        }
        $this->assertNull($response->viewData('preview')['rows']->first()['selectedPlayerId']);
        $this->assertNull($response->viewData('preview')['rows']->firstWhere('slot.id', $conflict->id)['selectedPlayerId']);
        // Review warnings allow an explicit administrator choice; duplicate profiles never receive an implicit choice.
        $this->post($this->url('store'), $this->payload([$conflict->id => $player->id]))->assertRedirect();
        $this->assertEquals($player->id, $conflict->fresh()->player_profile);
        $this->assertNull($duplicate->fresh()->player_profile);
    }

    public function test_duplicate_roster_identity_on_later_page_prevents_auto_selection_and_limits_region_to_100_rows(): void
    {
        $slot = $this->slot($this->team); $this->candidate();
        for ($rank = 2; $rank <= 100; $rank++) $this->slot($this->team, 'Unmatched'.$rank, $rank);
        $this->slot($this->secondTeam, ' Joshua ');
        $response = $this->preview()->assertSee('occurs more than once');
        $preview = $response->viewData('preview');
        $this->assertSame(101, $preview['slots']->total());
        $this->assertCount(100, $preview['rows']);
        $this->assertNull($preview['rows']->first()['selectedPlayerId']);
        $this->assertSame(2, $preview['rows']->first()['duplicateRosterCount']);
        $this->assertEquals($slot->id, $preview['rows']->first()['slot']->id);
    }

    public function test_existing_assignments_payment_and_inconsistent_rosters_are_blocked_server_side(): void
    {
        $slot = $this->slot($this->team); $player = $this->candidate();
        TeamPlayer::create(['team_id' => $this->secondTeam->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $response = $this->preview()->assertSee('already assigned');
        $this->assertNull($response->viewData('preview')['rows']->first()['selectedPlayerId']);
        $this->postJson($this->url('store'), $this->payload([$slot->id => $player->id]))->assertUnprocessable();
        TeamPlayer::where('team_id', $this->secondTeam->id)->delete();
        $slot->update(['pay_status' => 1]);
        $this->preview()->assertSee('payment history');
        $this->postJson($this->url('store'), $this->payload([$slot->id => $player->id]))->assertUnprocessable();
        $this->assertNull($slot->fresh()->player_profile);
    }

    public function test_scope_authorization_stale_imported_details_and_duplicate_assignment_are_rejected(): void
    {
        $one = $this->slot($this->team); $two = $this->slot($this->secondTeam); $player = $this->candidate();
        $payload = $this->payload([$one->id => $player->id]);
        $one->update(['email' => 'changed@example.test']);
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->postJson($this->url('store'), $this->payload([$one->id => $player->id, $two->id => $player->id]))->assertUnprocessable();
        $this->get($this->url('index', TeamRegion::create(['region_name' => 'Test region'])))->assertNotFound();
        $this->actingAs(User::factory()->create())->get($this->url())->assertForbidden();
        $this->assertNull($one->fresh()->player_profile); $this->assertNull($two->fresh()->player_profile);
    }

    public function test_blocked_second_selection_rolls_back_the_whole_region_batch(): void
    {
        $one = $this->slot($this->team); $two = $this->slot($this->secondTeam, 'Dirk');
        $playerOne = $this->candidate(); $playerTwo = $this->candidate('Dirk', 2);
        $two->update(['pay_status' => 1]);
        $this->postJson($this->url('store'), $this->payload([$one->id => $playerOne->id, $two->id => $playerTwo->id]))->assertUnprocessable();
        $this->assertNull($one->fresh()->player_profile); $this->assertNull($two->fresh()->player_profile);
        $this->assertDatabaseCount('team_players', 2);
    }

    public function test_missing_historical_name_is_flagged_without_breaking_other_rows(): void
    {
        $slot = $this->slot($this->team); $player = $this->candidate();
        $blank = NoProfileTeamPlayer::create(['team_id' => $this->secondTeam->id, 'name' => null, 'surname' => null, 'rank' => 1, 'pay_status' => 0]);
        $response = $this->preview()->assertSee('first name or surname is missing');
        $rows = $response->viewData('preview')['rows'];
        $this->assertEquals($player->id, $rows->first()['selectedPlayerId']);
        $this->assertNull($rows->last()['selectedPlayerId']);
        $this->post($this->url('store'), $this->payload([$slot->id => $player->id]))->assertRedirect();
        $this->assertNull($blank->fresh()->player_profile);
    }
}
