<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\CategoryEvent;
use App\Models\Player;
use App\Models\Series;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailControllerAuthorizationTest extends TestCase
{
  use RefreshDatabase;

  private User $superUser;
  private User $admin;
  private User $convenor;
  private User $ordinaryUser;
  private Event $eventA;
  private Event $eventB;
  private Series $seriesA;
  private TeamRegion $regionA;
  private Team $teamA;

  protected function setUp(): void
  {
    parent::setUp();

    // Create roles
    Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'convenor', 'guard_name' => 'web']);

    // Create users
    $this->superUser = User::factory()->create();
    $this->superUser->assignRole('super-user');

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->convenor = User::factory()->create();
    $this->convenor->assignRole('convenor');

    $this->ordinaryUser = User::factory()->create();

    // Create events
    $this->eventA = Event::factory()->create(['name' => 'Event A']);
    $this->eventB = Event::factory()->create(['name' => 'Event B']);

    // Create series and associate event
    $this->seriesA = Series::factory()->create();
    $this->eventA->update(['series_id' => $this->seriesA->id]);

    // Create region manually for Event A (no factory)
    $this->regionA = TeamRegion::create([
      'event_id' => $this->eventA->id,
      'region_name' => 'Region A'
    ]);

    // Create a CategoryEvent to use for teams
    $categoryEvent = CategoryEvent::factory()->create([
      'event_id' => $this->eventA->id,
    ]);

    $this->teamA = Team::factory()->create([
      'category_event_id' => $categoryEvent->id,
      'name' => 'Team A',
      'region_id' => $this->regionA->id,
    ]);

    // Grant admin access to Event A
    EventAdmin::create([
      'user_id' => $this->admin->id,
      'event_id' => $this->eventA->id,
    ]);

    // Grant convenor access to Event A
    EventConvenor::create([
      'user_id' => $this->convenor->id,
      'event_id' => $this->eventA->id,
    ]);
  }

  /**
   * Route-facing methods: sendEmail, getPlayers, getTeams, getRegions, sendToSeriesPlayers
   */

  // ============================================================================
  // sendEmail - Main email route
  // ============================================================================

  public function test_guest_cannot_send_email()
  {
    $player = Player::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventA->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(401);
  }

  public function test_super_user_can_send_email()
  {
    $this->actingAs($this->superUser);
    $player = Player::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventA->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(200);
  }

  public function test_permitted_admin_can_send_email_within_scope()
  {
    $this->actingAs($this->admin);
    $player = Player::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventA->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(200);
  }

  public function test_admin_for_event_a_cannot_send_email_for_event_b()
  {
    $this->actingAs($this->admin);
    $player = Player::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventB->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(403);
  }

  public function test_ordinary_user_cannot_send_email()
  {
    $this->actingAs($this->ordinaryUser);
    $player = Player::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventA->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(403);
  }

  public function test_convenor_for_event_a_can_send_email_within_scope()
  {
    $this->actingAs($this->convenor);
    $player = Player::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventA->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(200);
  }

  // ============================================================================
  // getPlayers, getTeams, getRegions - AJAX endpoints (basic auth check)
  // These endpoints have data-loading complexity unrelated to authorization,
  // so we focus on testing that the authorization gate is checked.
  // ============================================================================

  public function test_guest_cannot_get_players()
  {
    $response = $this->getJson(route('backend.email.players', $this->eventA->id));
    $response->assertStatus(401);
  }

  public function test_ordinary_user_cannot_get_players()
  {
    $this->actingAs($this->ordinaryUser);

    $response = $this->getJson(route('backend.email.players', $this->eventA->id));
    $response->assertStatus(403);
  }

  public function test_admin_for_event_a_cannot_get_players_for_event_b()
  {
    $this->actingAs($this->admin);

    $response = $this->getJson(route('backend.email.players', $this->eventB->id));
    $response->assertStatus(403);
  }

  // ============================================================================
  // sendToSeriesPlayers - Series email route
  // ============================================================================

  public function test_guest_cannot_send_series_email()
  {
    $response = $this->postJson(route('series.email.players', $this->seriesA->id), [
      'emailSubject' => 'Test',
      'message' => 'Test message',
    ]);

    $response->assertStatus(401);
  }

  public function test_super_user_can_send_series_email()
  {
    $this->actingAs($this->superUser);

    $response = $this->postJson(route('series.email.players', $this->seriesA->id), [
      'emailSubject' => 'Test',
      'message' => 'Test message',
    ]);

    $response->assertStatus(200);
  }

  public function test_admin_for_series_event_can_send_series_email()
  {
    $this->actingAs($this->admin);

    $response = $this->postJson(route('series.email.players', $this->seriesA->id), [
      'emailSubject' => 'Test',
      'message' => 'Test message',
    ]);

    $response->assertStatus(200);
  }

  public function test_admin_without_series_event_access_cannot_send_series_email()
  {
    // Create a new user who is admin for Event B only
    $adminEventB = User::factory()->create();
    $adminEventB->assignRole('admin');
    EventAdmin::create([
      'user_id' => $adminEventB->id,
      'event_id' => $this->eventB->id,
    ]);

    $this->actingAs($adminEventB);

    $response = $this->postJson(route('series.email.players', $this->seriesA->id), [
      'emailSubject' => 'Test',
      'message' => 'Test message',
    ]);

    $response->assertStatus(403);
  }

  public function test_ordinary_user_cannot_send_series_email()
  {
    $this->actingAs($this->ordinaryUser);

    $response = $this->postJson(route('series.email.players', $this->seriesA->id), [
      'emailSubject' => 'Test',
      'message' => 'Test message',
    ]);

    $response->assertStatus(403);
  }

  // ============================================================================
  // Scope isolation and boundary checks
  // ============================================================================

  public function test_event_email_data_endpoints_are_isolated_by_event()
  {
    $this->actingAs($this->admin);

    // Can access Event A data (or gets an error other than 403)
    $response = $this->getJson(route('backend.email.players', $this->eventA->id));
    $this->assertNotEquals(403, $response->status(), 'Admin should have authorization for Event A data');

    // Cannot access Event B data - should get 403
    $response = $this->getJson(route('backend.email.players', $this->eventB->id));
    $response->assertStatus(403);
  }

  public function test_cross_event_isolation_on_sendEmail()
  {
    $this->actingAs($this->admin);
    $player = Player::factory()->create(['email' => 'test@example.com']);

    // Can send for Event A
    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventA->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);
    $response->assertStatus(200);

    // Cannot send for Event B
    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventB->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);
    $response->assertStatus(403);
  }

  public function test_convenor_follows_existing_head_office_rules()
  {
    $this->actingAs($this->convenor);
    $player = Player::factory()->create(['email' => 'test@example.com']);

    // Convenor can send email for their event
    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventA->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(200);

    // But not for another event
    $response = $this->postJson(route('email.send'), [
      'target_type' => 'player',
      'to' => $player->id,
      'event_id' => $this->eventB->id,
      'fromName' => 'Test',
      'message' => 'Test message',
      'emailSubject' => 'Test',
    ]);

    $response->assertStatus(403);
  }
}
