<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventOperationsPendingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_counts_only_active_paid_entries_and_separates_active_pending_checkouts(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->for($event)->create();
        $otherCategory = CategoryEvent::factory()->create();

        $activePaid = $this->entry($category, 1, 'active');
        $pending = $this->entry($category, null, 'active', 'unpaid');
        $this->entry($category, 1, 'withdrawn');
        $this->entry($category, null, 'withdrawn');
        $deleted = $this->entry($category, null, 'active');
        $deleted->delete();
        $this->entry($otherCategory, null, 'active');

        $operations = app(EventOperationsService::class)->for($event->fresh());

        $this->assertSame(1, $operations['counts']['entries']);
        $this->assertSame(1, $operations['counts']['paid']);
        $this->assertSame(1, $operations['counts']['pending_checkouts']);
        $this->assertSame(2, $operations['counts']['withdrawals']);
        $this->assertArrayNotHasKey('unpaid', $operations['counts']);
        $warning = $operations['warnings']->firstWhere('key', 'pending_checkouts');
        $this->assertSame('Pending checkouts', $warning['reason']);
        $this->assertSame(1, $warning['count']);
        $this->assertSame(route('admin.events.entries.new', $event), $warning['action']);
        $this->assertSame('unpaid', $pending->admin_payment_status);
        $this->assertNull($pending->payment_status_id);
        $this->assertSame(1, $activePaid->payment_status_id);
    }

    public function test_super_user_entries_page_separates_pending_checkouts_from_confirmed_entries(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $superUser = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->for($event)->create();
        $paid = $this->entry($category, 1, 'active', playerName: 'Confirmed');
        $pending = $this->entry($category, 0, 'active', 'unpaid', 'Pending');
        $withdrawn = $this->entry($category, 1, 'withdrawn', playerName: 'Withdrawn');
        $otherPending = $this->entry(CategoryEvent::factory()->create(), 0, 'active', playerName: 'OtherEvent');

        $response = $this->actingAs($superUser)->get(route('admin.events.entries.new', $event));

        $response->assertOk()
            ->assertSee('Pending checkouts')
            ->assertSee('Canonical unpaid checkout drafts are shown separately')
            ->assertSee('Pending '.$pending->registration->players->first()->surname)
            ->assertSee('Confirmed '.$paid->registration->players->first()->surname)
            ->assertDontSee('Withdrawn '.$withdrawn->registration->players->first()->surname)
            ->assertDontSee('OtherEvent '.$otherPending->registration->players->first()->surname)
            ->assertSee('1</span> confirmed entries', false);
    }

    public function test_event_admin_cannot_see_pending_checkout_counts_or_rows(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $category = CategoryEvent::factory()->for($event)->create();
        $pending = $this->entry($category, 0, 'active', playerName: 'PrivateDraft');

        $this->actingAs($admin);
        $operations = app(EventOperationsService::class)->for($event->fresh());
        $this->assertArrayNotHasKey('pending_checkouts', $operations['counts']);
        $this->assertNull($operations['warnings']->firstWhere('key', 'pending_checkouts'));

        $this->get(route('admin.events.overview', $event))
            ->assertOk()
            ->assertDontSee('Pending checkouts')
            ->assertDontSee('data-metric="pending_checkouts"', false);

        $this->get(route('admin.events.entries.new', $event))
            ->assertOk()
            ->assertDontSee('Pending checkouts')
            ->assertDontSee('PrivateDraft '.$pending->registration->players->first()->surname)
            ->assertDontSee('data-pending-checkout-id', false);
    }

    private function entry(
        CategoryEvent $category,
        ?int $paymentStatus,
        string $status,
        ?string $adminPaymentStatus = null,
        string $playerName = 'Player',
    ): CategoryEventRegistration {
        $registration = Registration::factory()->create();
        $player = Player::factory()->create([
            'name' => $playerName,
            'surname' => $playerName.'SensitiveSurname',
        ]);
        DB::table('player_registrations')->insert([
            'registration_id' => $registration->id,
            'player_id' => $player->id,
        ]);

        return CategoryEventRegistration::factory()->create([
            'category_event_id' => $category->id,
            'registration_id' => $registration->id,
            'payment_status_id' => $paymentStatus,
            'status' => $status,
            'withdrawn_at' => str_starts_with($status, 'withdrawn') ? now() : null,
            'admin_payment_status' => $adminPaymentStatus,
        ]);
    }
}
