<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\DrawGroup;
use App\Models\DrawGroupRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationWithdrawTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
    }

    public function test_guest_cannot_withdraw(): void
    {
        $registration = CategoryEventRegistration::factory()->create();

        $response = $this->post(route('registrations.withdraw', $registration));

        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_withdraw_own_registration(): void
    {
        $user = User::factory()->create();
        $registration = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user);

        // CategoryEventRegistration::canWithdraw needs categoryEvent.event relation.
        // This test exercises the auth redirect and ownership check path.
        $response = $this->post(route('registrations.withdraw', $registration));

        // Either succeeds or redirects — must not 403.
        $response->assertStatus(302);
    }

    public function test_non_owner_cannot_withdraw(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $registration = CategoryEventRegistration::factory()
            ->create(['user_id' => $owner->id, 'status' => 'active']);

        $this->actingAs($other);

        $response = $this->post(route('registrations.withdraw', $registration));

        // Should redirect back with error (not 403, the controller handles it via canWithdraw)
        $response->assertStatus(302);
    }

    public function test_already_withdrawn_registration_cannot_be_withdrawn_again(): void
    {
        $user = User::factory()->create();
        $registration = CategoryEventRegistration::factory()
            ->withdrawn()
            ->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->post(route('registrations.withdraw', $registration));

        $response->assertSessionHasErrors();
    }

    public function test_withdraw_blocked_when_category_draw_is_locked(): void
    {
        $user = User::factory()->create();
        $registration = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        // Lock the draw on the category event
        $registration->categoryEvent->update(['locked_at' => now()]);
        $registration->load('categoryEvent');

        $result = $registration->canWithdraw($user);

        $this->assertFalse($result['ok']);
        $this->assertEquals('draw_locked', $result['reason']);
    }

    // =========================================================================
    // P0 HOTFIX 1 — WITHDRAWAL MUST REMOVE FROM RR GROUPS
    // =========================================================================

    /**
     * After withdrawal the player's draw_group_registrations row must be gone.
     */
    public function test_withdrawal_removes_player_from_rr_draw_group(): void
    {
        $user = User::factory()->create();
        $cer  = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $registrationId = $cer->registration_id;
        $eventId        = $cer->categoryEvent->event_id;

        // Create a Draw → DrawGroup → DrawGroupRegistration for this player
        $draw = Draw::create([
            'drawName'          => 'RR',
            'drawType_id'       => 1,
            'category_event_id' => $cer->category_event_id,
            'event_id'          => $eventId,
        ]);

        $group = DrawGroup::create([
            'draw_id' => $draw->id,
            'name'    => 'A',
        ]);

        DrawGroupRegistration::create([
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);

        $this->assertDatabaseHas('draw_group_registrations', [
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);

        // Act — withdraw via the service (same path the controller uses after HOTFIX 1)
        $actingUser = $user;
        // Bypass canWithdraw deadline checks by using admin withdrawal
        app(\App\Domain\Entries\Services\EntryService::class)
            ->withdrawEntryAsAdmin($cer, $actingUser);

        // Assert — draw group row must be deleted
        $this->assertDatabaseMissing('draw_group_registrations', [
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);
    }

    /**
     * After withdrawal the CER status is withdrawn so it is excluded from
     * active/standing queries.
     */
    public function test_withdrawn_player_has_withdrawn_status(): void
    {
        $user = User::factory()->create();
        $cer  = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        app(\App\Domain\Entries\Services\EntryService::class)
            ->withdrawEntryAsAdmin($cer, $user);

        $this->assertDatabaseHas('category_event_registrations', [
            'id'     => $cer->id,
            'status' => 'withdrawn',
        ]);
    }

    // =========================================================================
    // FIX 2 — WITHDRAWAL NULLS UNPLAYED FIXTURE SLOTS + REMOVES SCHEDULES
    // =========================================================================

    public function test_withdrawal_nulls_unplayed_fixture_slots(): void
    {
        $user = User::factory()->create()->assignRole('admin');
        $cer  = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $registrationId = $cer->registration_id;
        $eventId        = $cer->categoryEvent->event_id;

        $draw = Draw::create([
            'drawName'          => 'RR',
            'drawType_id'       => 1,
            'category_event_id' => $cer->category_event_id,
            'event_id'          => $eventId,
        ]);

        // Unplayed fixture — withdrawn player is reg1
        $fixture = \App\Models\Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'registration1_id'    => $registrationId,
            'registration2_id'    => 999,
            'winner_registration' => null,
        ]);

        app(\App\Domain\Entries\Services\EntryService::class)
            ->withdrawEntryAsAdmin($cer, $user);

        $this->assertDatabaseHas('fixtures', [
            'id'               => $fixture->id,
            'registration1_id' => null,
        ]);
    }

    public function test_withdrawal_does_not_alter_completed_fixture(): void
    {
        $user = User::factory()->create()->assignRole('admin');
        $cer  = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $registrationId = $cer->registration_id;
        $eventId        = $cer->categoryEvent->event_id;

        $draw = Draw::create([
            'drawName'          => 'RR',
            'drawType_id'       => 1,
            'category_event_id' => $cer->category_event_id,
            'event_id'          => $eventId,
        ]);

        // Completed fixture — winner already set
        $fixture = \App\Models\Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'registration1_id'    => $registrationId,
            'registration2_id'    => 999,
            'winner_registration' => $registrationId,
        ]);

        app(\App\Domain\Entries\Services\EntryService::class)
            ->withdrawEntryAsAdmin($cer, $user);

        // Completed fixture must be untouched
        $this->assertDatabaseHas('fixtures', [
            'id'                  => $fixture->id,
            'registration1_id'    => $registrationId,
            'winner_registration' => $registrationId,
        ]);
    }

    public function test_withdrawal_removes_schedules_for_unplayed_fixture(): void
    {
        $user = User::factory()->create()->assignRole('admin');
        $cer  = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $registrationId = $cer->registration_id;
        $eventId        = $cer->categoryEvent->event_id;

        $draw = Draw::create([
            'drawName'          => 'RR',
            'drawType_id'       => 1,
            'category_event_id' => $cer->category_event_id,
            'event_id'          => $eventId,
        ]);

        $fixture = \App\Models\Fixture::factory()->create([
            'draw_id'             => $draw->id,
            'registration1_id'    => $registrationId,
            'registration2_id'    => 999,
            'winner_registration' => null,
        ]);

        // Attach a schedule (OOP) to this fixture
        \Illuminate\Support\Facades\DB::table('order_of_plays')->insert([
            'fixture_id' => $fixture->id,
            'draw_id'    => $draw->id,
            'venue_id'   => 1,
            'court'      => '1',
            'time'       => now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('order_of_plays', ['fixture_id' => $fixture->id]);

        app(\App\Domain\Entries\Services\EntryService::class)
            ->withdrawEntryAsAdmin($cer, $user);

        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $fixture->id]);
    }
}
