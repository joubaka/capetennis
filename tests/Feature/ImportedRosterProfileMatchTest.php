<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportedRosterProfileMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;
    private Event $source;
    private Team $team;
    private Team $sourceTeam;
    private NoProfileTeamPlayer $slot;
    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->event = Event::factory()->create(['start_date' => now()->addMonth()]);
        $this->source = Event::factory()->create(['start_date' => now()->subMonth()]);
        foreach ([$this->event, $this->source] as $event) EventAdmin::create(['event_id' => $event->id, 'user_id' => $this->admin->id]);
        $this->team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $this->event->id])->id, 'noProfile' => true]);
        $this->sourceTeam = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $this->source->id])->id]);
        $this->slot = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'name' => 'Daniël', 'surname' => 'van den Burgh', 'rank' => 1, 'pay_status' => 0]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => 0, 'rank' => 1, 'pay_status' => 0]);
        $this->player = Player::factory()->create(['name' => 'Daniël', 'surname' => 'van den Burgh']);
        TeamPlayer::create(['team_id' => $this->sourceTeam->id, 'player_id' => $this->player->id, 'rank' => 1, 'pay_status' => 1]);
        $this->actingAs($this->admin);
    }

    private function url(string $action = 'index'): string
    {
        return route('backend.team-selection.profile-matches.'.$action, [$this->event, $this->team]);
    }

    private function preview(): \Illuminate\Testing\TestResponse
    {
        return $this->get($this->url().'?source_event_id='.$this->source->id)->assertOk();
    }

    private function payload(): array
    {
        return ['source_event_id' => $this->source->id, 'roster_page' => 1, 'fingerprint' => $this->preview()->viewData('fingerprint'),
            'confirm_links' => 1, 'links' => [$this->slot->id => $this->player->id]];
    }

    public function test_preview_is_read_only_and_duplicate_profiles_have_no_preselected_choice(): void
    {
        $duplicate = Player::factory()->create(['name' => 'Daniël', 'surname' => 'van den Burgh']);
        TeamPlayer::create(['team_id' => $this->sourceTeam->id, 'player_id' => $duplicate->id, 'rank' => 2, 'pay_status' => 0]);
        $response = $this->preview()->assertSee('Multiple profiles match')->assertSee($this->player->email)->assertSee($duplicate->email);
        $this->assertCount(2, $response->viewData('preview')['rows']->first()['candidates']);
        $response->assertSee('value="" checked', false)->assertDontSee('value="'.$this->player->id.'" checked', false);
        $this->assertNull($this->slot->fresh()->player_profile);
        $this->assertDatabaseCount('players', 2);
    }

    public function test_confirmation_links_only_selected_profile_and_preserves_roster_source_and_ownership(): void
    {
        $before = DB::table('user_players')->count();
        $this->post($this->url('store'), $this->payload())->assertRedirect();
        $this->assertEquals($this->player->id, $this->slot->fresh()->player_profile);
        $this->assertSame('van den Burgh', $this->slot->fresh()->surname);
        $this->assertDatabaseHas('team_players', ['team_id' => $this->team->id, 'rank' => 1, 'player_id' => $this->player->id, 'pay_status' => 0]);
        $this->assertDatabaseHas('team_players', ['team_id' => $this->sourceTeam->id, 'rank' => 1, 'player_id' => $this->player->id, 'pay_status' => 1]);
        $this->assertTrue($this->team->fresh()->noProfile);
        $this->assertDatabaseCount('user_players', $before);
        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('no_profile_team_players', 1);
    }

    public function test_admin_can_choose_second_duplicate_instead_of_first_candidate(): void
    {
        $duplicate = Player::factory()->create(['name' => 'Daniël', 'surname' => 'van den Burgh']);
        TeamPlayer::create(['team_id' => $this->sourceTeam->id, 'player_id' => $duplicate->id, 'rank' => 2, 'pay_status' => 0]);
        $payload = $this->payload(); $payload['links'][$this->slot->id] = $duplicate->id;
        $this->post($this->url('store'), $payload)->assertRedirect();
        $this->assertEquals($duplicate->id, $this->slot->fresh()->player_profile);
        $this->assertDatabaseMissing('team_players', ['team_id' => $this->team->id, 'player_id' => $this->player->id]);
    }

    public function test_destination_and_later_event_cannot_be_used_as_source(): void
    {
        $this->get($this->url().'?source_event_id='.$this->event->id)->assertUnprocessable();
        $this->source->update(['start_date' => now()->addYear()]);
        $this->get($this->url().'?source_event_id='.$this->source->id)->assertUnprocessable();
        $this->get($this->url())->assertOk()->assertDontSee($this->source->name);
    }

    public function test_unauthorized_source_and_destination_and_cross_event_team_are_rejected(): void
    {
        $payload = $this->payload();
        EventAdmin::where('event_id', $this->source->id)->delete();
        $this->get($this->url().'?source_event_id='.$this->source->id)->assertForbidden()->assertDontSee($this->player->email);
        $this->postJson($this->url('store'), $payload)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($this->url())->assertForbidden();
        $this->actingAs($this->admin)->get(route('backend.team-selection.profile-matches.index', [$this->event, $this->sourceTeam]))->assertNotFound();
    }

    public function test_stale_name_and_replay_are_rejected(): void
    {
        $payload = $this->payload();
        $this->slot->update(['name' => 'Different']);
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->slot->update(['name' => 'Daniël']);
        $payload = $this->payload();
        $this->post($this->url('store'), $payload)->assertRedirect();
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('team_players', 2);
    }

    public function test_nonparticipant_and_wrong_slot_and_missing_confirmation_are_rejected(): void
    {
        $payload = $this->payload();
        $outsider = Player::factory()->create(['name' => 'Daniël', 'surname' => 'van den Burgh']);
        $payload['links'][$this->slot->id] = $outsider->id;
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['links'] = [999999 => $this->player->id];
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $payload = $this->payload(); unset($payload['confirm_links']);
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->assertNull($this->slot->fresh()->player_profile);
    }

    public function test_batch_failure_rolls_back_all_links_and_duplicate_assignments_are_rejected(): void
    {
        $second = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Other', 'surname' => 'Person', 'pay_status' => 1]);
        $other = Player::factory()->create(['name' => 'Other', 'surname' => 'Person']);
        TeamPlayer::create(['team_id' => $this->sourceTeam->id, 'rank' => 2, 'player_id' => $other->id, 'pay_status' => 0]);
        $payload = $this->payload(); $payload['links'][$second->id] = $other->id;
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->assertNull($this->slot->fresh()->player_profile);
        $this->assertNull($second->fresh()->player_profile);
        $payload['links'][$second->id] = $this->player->id;
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('team_players', 3);
    }

    public function test_source_only_imported_link_is_found_with_team_context_and_normalized_compound_name(): void
    {
        TeamPlayer::where('team_id', $this->sourceTeam->id)->delete();
        $this->player->update(['name' => 'DANIËL', 'surname' => 'van  den Burgh']);
        NoProfileTeamPlayer::create(['team_id' => $this->sourceTeam->id, 'rank' => 1, 'name' => 'Old import', 'surname' => 'Name', 'player_profile' => $this->player->id, 'pay_status' => 0]);
        $row = $this->preview()->viewData('preview')['rows']->first();
        $this->assertEquals([$this->player->id], $row['candidates']->pluck('id')->all());
        $this->assertStringContainsString($this->sourceTeam->name, $row['candidates']->first()->source_teams[0]);
    }

    public function test_individual_participants_are_found_but_pending_checkout_is_not_participation(): void
    {
        TeamPlayer::where('team_id', $this->sourceTeam->id)->delete();
        $registration = \App\Models\Registration::create([]);
        $registration->players()->attach($this->player);
        $entry = \App\Models\CategoryEventRegistration::create([
            'category_event_id' => $this->sourceTeam->category_event_id, 'registration_id' => $registration->id,
            'status' => 'pending_checkout', 'payment_status_id' => 0,
        ]);
        $this->assertCount(0, $this->preview()->viewData('preview')['rows']->first()['candidates']);
        $entry->update(['status' => 'active']);
        $this->assertCount(1, $this->preview()->viewData('preview')['rows']->first()['candidates']);
        $entry->update(['withdrawn_at' => now(), 'status' => 'withdrawn']);
        $this->assertCount(0, $this->preview()->viewData('preview')['rows']->first()['candidates']);
    }

    public function test_changed_identity_or_removed_source_participation_invalidates_preview(): void
    {
        $payload = $this->payload();
        $this->player->update(['email' => 'changed@example.test']);
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $payload = $this->payload();
        TeamPlayer::where('team_id', $this->sourceTeam->id)->delete();
        $this->postJson($this->url('store'), $payload)->assertUnprocessable();
        $this->assertNull($this->slot->fresh()->player_profile);
    }

    public function test_skip_values_and_already_linked_rows_are_preserved(): void
    {
        $skipped = NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'rank' => 2, 'name' => 'Unknown', 'surname' => 'Name', 'pay_status' => 0]);
        $payload = $this->payload(); $payload['links'][$skipped->id] = '';
        $this->post($this->url('store'), $payload)->assertRedirect();
        $this->assertNull($skipped->fresh()->player_profile);
        $row = $this->preview()->viewData('preview')['rows']->first();
        $this->assertCount(0, $row['candidates']);
        $this->assertEquals($this->player->id, $row['slot']->player_profile);
    }
}
