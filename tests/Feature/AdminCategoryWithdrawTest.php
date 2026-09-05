<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Registration;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for CategoryEventController::withdraw() (admin path).
 *
 * Route: POST /backend/admin/category-registration/{registration}/withdraw
 *        named admin.category.registration.withdraw
 *        Access: authenticated admin or super-user with event management scope
 */
class AdminCategoryWithdrawTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Create a basic active registration with its required relations. */
    private function activeRegistration(array $overrides = []): CategoryEventRegistration
    {
        return CategoryEventRegistration::factory()->create(array_merge([
            'status' => 'active',
        ], $overrides));
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    private function actingAsEventAdmin(User $user, CategoryEventRegistration $registration): static
    {
        DB::table('event_admins')->insert([
            'event_id' => $registration->categoryEvent->event_id,
            'user_id' => $user->id,
        ]);

        return $this->actingAs($user);
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $reg = $this->activeRegistration();

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_cannot_withdraw_a_registration_from_another_event(): void
    {
        $admin = $this->adminUser();
        $ownEvent = Event::factory()->create();
        $registration = $this->activeRegistration();

        DB::table('event_admins')->insert([
            'event_id' => $ownEvent->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.category.registration.withdraw', $registration))
            ->assertForbidden();

        $this->assertDatabaseHas('category_event_registrations', [
            'id' => $registration->id,
            'status' => 'active',
            'withdrawn_at' => null,
        ]);
    }

    // -----------------------------------------------------------------------
    // Already-withdrawn guard
    // -----------------------------------------------------------------------

    public function test_already_withdrawn_registration_returns_error(): void
    {
        $admin = $this->adminUser();
        $reg   = CategoryEventRegistration::factory()->withdrawn()->create();

        $this->actingAsEventAdmin($admin, $reg);

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // Successful withdrawal — unpaid registration
    // -----------------------------------------------------------------------

    public function test_active_unpaid_registration_is_withdrawn_and_stays_on_page(): void
    {
        $admin = $this->adminUser();
        $reg   = $this->activeRegistration(['pf_transaction_id' => null]);

        $this->actingAsEventAdmin($admin, $reg);

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        // Redirects back with success (no refund needed)
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('category_event_registrations', [
            'id'            => $reg->id,
            'status'        => 'withdrawn',
            'refund_status' => 'not_refunded',
        ]);
    }

    // -----------------------------------------------------------------------
    // Successful withdrawal — paid registration → redirect to refund chooser
    // -----------------------------------------------------------------------

    public function test_paid_registration_redirects_to_admin_refund_chooser(): void
    {
        $admin = $this->adminUser();

        // Create a paid registration that has a pf_transaction_id
        $reg = CategoryEventRegistration::factory()
            ->paid()
            ->create(['status' => 'active']);

        $this->actingAsEventAdmin($admin, $reg);

        // We expect a redirect to the admin refund chooser page
        // The controller does: redirect()->route('admin.registration.refund.choose', [$event, $registration])
        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Registration must be marked withdrawn in DB
        $this->assertDatabaseHas('category_event_registrations', [
            'id'     => $reg->id,
            'status' => 'withdrawn',
        ]);
    }

    // -----------------------------------------------------------------------
    // Withdrawal sets correct DB state
    // -----------------------------------------------------------------------

    public function test_withdrawal_sets_not_refunded_status(): void
    {
        $admin = $this->adminUser();
        $reg   = $this->activeRegistration();

        $this->actingAsEventAdmin($admin, $reg);

        $this->post(route('admin.category.registration.withdraw', $reg));

        $reg->refresh();
        $this->assertEquals('withdrawn', $reg->status);
        $this->assertEquals('not_refunded', $reg->refund_status);
        $this->assertNotNull($reg->withdrawn_at);
    }

    public function test_withdrawal_clears_previous_refund_amounts(): void
    {
        $admin = $this->adminUser();
        $reg   = $this->activeRegistration([
            'refund_gross' => 100.00,
            'refund_fee'   => 10.00,
            'refund_net'   => 90.00,
        ]);

        $this->actingAsEventAdmin($admin, $reg);

        $this->post(route('admin.category.registration.withdraw', $reg));

        $reg->refresh();
        $this->assertEquals(0, $reg->refund_gross);
        $this->assertEquals(0, $reg->refund_fee);
        $this->assertEquals(0, $reg->refund_net);
        $this->assertNull($reg->refund_method);
    }

    public function test_ajax_withdrawal_warns_when_the_entry_was_in_a_scheduled_draw(): void
    {
        $admin = $this->adminUser();
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->for($event)->create();
        $reg = $this->activeRegistration([
            'category_event_id' => $categoryEvent->id,
            'pf_transaction_id' => null,
        ]);

        $draw = Draw::create([
            'drawName' => 'Girls under 13',
            'drawType_id' => 1,
            'category_event_id' => $categoryEvent->id,
            'event_id' => $event->id,
        ]);
        $draw->registrations()->attach($reg->registration_id);

        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'registration1_id' => $reg->registration_id,
        ]);
        $venue = new Venue();
        $venue->forceFill(['name' => 'Centre Court', 'event_id' => $event->id])->save();
        OrderOfPlay::create([
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'venue_id' => $venue->id,
            'court' => '1',
            'time' => now()->addDay(),
        ]);

        $this->actingAsEventAdmin($admin, $reg);

        $response = $this->postJson(route('admin.category.registration.withdraw', $reg));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('draw_impact.requires_attention', true)
            ->assertJsonPath('draw_impact.draws.0.id', $draw->id)
            ->assertJsonPath('draw_impact.draws.0.name', 'Girls under 13')
            ->assertJsonPath('draw_impact.draws.0.has_schedule', true)
            ->assertJsonPath('draw_impact.draws.0.scheduled_matches', 1)
            ->assertJsonPath('draw_impact.draws.0.draw_url', route('draws.manage', $draw->id))
            ->assertJsonPath('draw_impact.draws.0.schedule_url', route('backend.event-venue-schedule.index', [
                'event' => $event->id,
                'draw_ids' => [$draw->id],
            ]));

        $this->assertDatabaseHas('category_event_registrations', [
            'id' => $reg->id,
            'status' => 'withdrawn',
        ]);
    }

    public function test_ajax_withdrawal_has_no_draw_warning_for_an_unplaced_entry(): void
    {
        $admin = $this->adminUser();
        $reg = $this->activeRegistration(['pf_transaction_id' => null]);

        $this->actingAsEventAdmin($admin, $reg);

        $this->postJson(route('admin.category.registration.withdraw', $reg))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('draw_impact.requires_attention', false)
            ->assertJsonCount(0, 'draw_impact.draws');
    }

    // -----------------------------------------------------------------------
    // HOTFIX 3 — admin withdrawal routes through EntryService, cleaning draw groups
    // -----------------------------------------------------------------------

    public function test_admin_withdrawal_removes_player_from_draw_group(): void
    {
        $admin = $this->adminUser();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $reg = $this->activeRegistration([
            'category_event_id' => $ce->id,
        ]);
        $registrationId = $reg->registration_id;

        // Place player in a draw group
        $draw = \App\Models\Draw::create([
            'drawName'          => 'RR',
            'drawType_id'       => 1,
            'category_event_id' => $ce->id,
            'event_id'          => $event->id,
        ]);

        $group = \App\Models\DrawGroup::create([
            'draw_id'    => $draw->id,
            'name'       => 'Group A',
            'sort_order' => 1,
        ]);

        \App\Models\DrawGroupRegistration::create([
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);

        $this->assertDatabaseHas('draw_group_registrations', [
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);

        $this->actingAsEventAdmin($admin, $reg);
        $this->post(route('admin.category.registration.withdraw', $reg));

        // HOTFIX 3: draw_group_registrations must be removed
        $this->assertDatabaseMissing('draw_group_registrations', [
            'draw_group_id'   => $group->id,
            'registration_id' => $registrationId,
        ]);
    }

    public function test_admin_withdrawal_sets_withdrawn_status_and_draw_group_is_absent(): void
    {
        $admin = $this->adminUser();
        $event = Event::factory()->create();
        $ce    = CategoryEvent::factory()->for($event)->create();

        $reg = $this->activeRegistration(['category_event_id' => $ce->id]);

        $draw = \App\Models\Draw::create([
            'drawName'          => 'RR',
            'drawType_id'       => 1,
            'category_event_id' => $ce->id,
            'event_id'          => $event->id,
        ]);

        $group = \App\Models\DrawGroup::create([
            'draw_id'    => $draw->id,
            'name'       => 'Group B',
            'sort_order' => 1,
        ]);

        \App\Models\DrawGroupRegistration::create([
            'draw_group_id'   => $group->id,
            'registration_id' => $reg->registration_id,
        ]);

        $this->actingAsEventAdmin($admin, $reg);
        $this->post(route('admin.category.registration.withdraw', $reg));

        $reg->refresh();
        $this->assertEquals('withdrawn', $reg->status);

        $inGroup = \App\Models\DrawGroupRegistration::where('registration_id', $reg->registration_id)->exists();
        $this->assertFalse($inGroup, 'Withdrawn player must not remain in any draw group');
    }

    public function test_non_super_user_admin_withdrawal_does_not_redirect_to_refund_chooser(): void
    {
        // Regular admin (not super-user): withdrawal succeeds but no refund chooser redirect
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $reg = CategoryEventRegistration::factory()
            ->paid()
            ->create(['status' => 'active']);

        $this->actingAsEventAdmin($admin, $reg);

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        // Must NOT redirect to refund chooser
        $location = $response->headers->get('location', '');
        $this->assertStringNotContainsString('refund', $location);
    }

    public function test_super_user_admin_withdrawal_of_paid_registration_redirects_to_refund_chooser(): void
    {
        $superUser = User::factory()->create();
        $superUser->assignRole('super-user');

        $reg = CategoryEventRegistration::factory()
            ->paid()
            ->create(['status' => 'active']);

        $this->actingAs($superUser);

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Super-user must be sent to refund chooser
        $location = $response->headers->get('location', '');
        $this->assertStringContainsString('refund', $location);
    }

    public function test_super_user_admin_withdrawal_after_deadline_cannot_open_refund_flow(): void
    {
        $superUser = User::factory()->create();
        $superUser->assignRole('super-user');

        $reg = CategoryEventRegistration::factory()->paid()->create(['status' => 'active']);
        $reg->categoryEvent->event->update([
            'withdrawal_deadline' => now()->subMinute(),
        ]);

        $this->actingAs($superUser);

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertRedirect();
        $this->assertStringNotContainsString('refund', $response->headers->get('location', ''));
        $response->assertSessionHas('success', 'Registration withdrawn (no refund — deadline passed).');
        $this->assertDatabaseHas('category_event_registrations', [
            'id' => $reg->id,
            'status' => 'withdrawn',
            'refund_status' => 'not_refunded',
        ]);
    }
}
