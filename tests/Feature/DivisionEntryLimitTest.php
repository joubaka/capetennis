<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventType, Registration, RegistrationOrder, RegistrationOrderItems, User};
use App\Services\DivisionCapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DivisionEntryLimitTest extends TestCase
{
    use RefreshDatabase;

    private function division(?int $cap = 2): CategoryEvent
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Capped individual event', 'type' => EventType::INDIVIDUAL]);
        return CategoryEvent::factory()->create(['event_id' => Event::factory()->create(['eventType' => $type])->id, 'entry_limit' => $cap]);
    }

    private function hold(CategoryEvent $category): array
    {
        $registration = Registration::create([]);
        $order = RegistrationOrder::create(['user_id' => User::factory()->create()->id, 'status' => 'pending', 'pay_status' => 0, 'payfast_paid' => false]);
        $item = new RegistrationOrderItems();
        $item->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id, 'category_event_id' => $category->id, 'player_id' => \App\Models\Player::factory()->create()->id, 'user_id' => $order->user_id, 'item_price' => 100])->save();
        return [$registration, $order];
    }

    public function test_cap_counts_unresolved_orders_once_and_cancellation_releases_place(): void
    {
        $category = $this->division();
        [$registration, $order] = $this->hold($category);
        CategoryEventRegistration::create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 0, 'status' => 'pending_checkout']);
        $capacity = app(DivisionCapacityService::class);
        $this->assertSame(1, $capacity->remaining($category));
        // Age alone does not release protected provider/EFT attempts.
        DB::table('registration_orders')->where('id', $order->id)->update(['created_at' => now()->subDay()]);
        $this->assertSame(1, $capacity->remaining($category));
        DB::table('registration_orders')->where('id', $order->id)->update(['status' => 'cancelled']);
        $this->assertSame(2, $capacity->remaining($category));
    }

    public function test_paid_entries_withdrawals_cross_event_and_full_guard(): void
    {
        $category = $this->division(1);
        [$registration, $order] = $this->hold($category);
        DB::table('registration_orders')->where('id', $order->id)->update(['pay_status' => 1]);
        $entry = CategoryEventRegistration::create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1, 'status' => 'active']);
        $capacity = app(DivisionCapacityService::class);
        $this->assertSame(0, $capacity->remaining($category));
        $this->assertSame(1, $capacity->remaining($this->division(1)));
        try { $capacity->assertAvailable($category); $this->fail('Expected full division rejection'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('division', $exception->errors()); }
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->assertSame(1, $capacity->remaining($category));
    }

    public function test_uncapped_division_and_paid_orphan_commitment(): void
    {
        $category = $this->division(null);
        [$registration, $order] = $this->hold($category);
        DB::table('registration_orders')->where('id', $order->id)->update(['pay_status' => 1]);
        $this->assertNull(app(DivisionCapacityService::class)->remaining($category));
        $this->assertSame(1, app(DivisionCapacityService::class)->occupied($category));
    }

    public function test_admin_add_at_full_limit_has_no_registration_or_financial_side_effects(): void
    {
        $category = $this->division(1);
        $this->hold($category);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('super-user');
        $player = \App\Models\Player::factory()->create();
        $before = [Registration::count(), CategoryEventRegistration::count(), DB::table('transactions_pf')->count()];
        try {
            app(\App\Domain\Entries\Services\EntryService::class)->addPlayerAsAdmin($category, $player->id, $admin);
            $this->fail('Full division accepted an administrator entry.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('division', $exception->errors());
        }
        $this->assertSame($before, [Registration::count(), CategoryEventRegistration::count(), DB::table('transactions_pf')->count()]);
    }

    public function test_limit_setting_is_event_scoped_optional_validated_and_preserves_entries(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $category = $this->division();
        DB::table('event_admins')->insert(['event_id' => $category->event_id, 'user_id' => $admin->id]);
        $url = route('admin.events.category-limit.update', $category);
        $this->actingAs($admin)->patch($url, ['entry_limit' => 16])->assertRedirect();
        $this->assertSame(16, (int) $category->fresh()->entry_limit);
        $this->patch($url, ['entry_limit' => 0])->assertSessionHasErrors('entry_limit');
        $this->patch($url, ['entry_limit' => null])->assertRedirect();
        $this->assertNull($category->fresh()->entry_limit);
        $this->patchJson($url, ['entry_limit' => 32])->assertOk()->assertExactJson(['success' => true, 'entry_limit' => 32]);
        $this->patchJson($url, ['entry_limit' => 0])->assertUnprocessable()->assertJsonValidationErrors('entry_limit');
        $this->assertSame(32, (int) $category->fresh()->entry_limit);
        $this->patchJson($url, ['entry_limit' => null])->assertOk()->assertExactJson(['success' => true, 'entry_limit' => null]);
        $other = $this->division();
        $this->patch(route('admin.events.category-limit.update', $other), ['entry_limit' => 8])->assertForbidden();
        $this->assertSame(2, (int) $other->fresh()->entry_limit);
    }
}
