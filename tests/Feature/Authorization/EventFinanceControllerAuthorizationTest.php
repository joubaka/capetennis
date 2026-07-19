<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\ExpenseType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected Event $event;
    protected Event $otherEvent;
    protected User $admin;
    protected User $otherAdmin;
    protected User $convenor;
    protected User $ordinaryUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor', 'guard_name' => 'web']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->otherAdmin = User::factory()->create();
        $this->otherAdmin->assignRole('admin');

        $this->convenor = User::factory()->create();
        $this->convenor->assignRole('convenor');

        $this->ordinaryUser = User::factory()->create();

        // Create events
        $this->event = Event::factory()->create(['name' => 'Event A']);
        $this->otherEvent = Event::factory()->create(['name' => 'Event B']);

        // Make admin an event admin for $this->event
        EventAdmin::create([
            'user_id' => $this->admin->id,
            'event_id' => $this->event->id,
        ]);

        // Make otherAdmin an event admin for $this->otherEvent
        EventAdmin::create([
            'user_id' => $this->otherAdmin->id,
            'event_id' => $this->otherEvent->id,
        ]);

        // Make convenor an event convenor for $this->event
        EventConvenor::create([
            'user_id' => $this->convenor->id,
            'event_id' => $this->event->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: Guest Rejected
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function guest_redirected_on_finance_view()
    {
        $this->get(route('finance.index', $this->event->id))
            ->assertRedirect('login');
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: Permitted Admin/Convenor Allowed Within Scope
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_view_finance_for_authorized_event()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('finance.index', $this->event->id));

        $response->assertSuccessful();
    }

    /** @test */
    public function convenor_can_view_finance_for_authorized_event()
    {
        $response = $this->actingAs($this->convenor)
            ->get(route('finance.index', $this->event->id));

        $response->assertSuccessful();
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: Ordinary User Receives 403
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function ordinary_user_receives_403_on_finance_view()
    {
        $response = $this->actingAs($this->ordinaryUser)
            ->get(route('finance.index', $this->event->id));

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: Cross-Event Isolation (Admin A cannot view Finance for Event B)
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_cannot_view_finance_for_different_event()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('finance.index', $this->otherEvent->id));

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: Expense Management Scope Checks
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_store_expense_in_authorized_event()
    {
        // Ensure expense type exists
        ExpenseType::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('finance.expense.store', $this->event->id), [
                'expense_type' => 'Equipment',
                'description' => 'Tennis rackets',
                'amount' => 500,
            ]);

        // Expect success or a validation error (not 403)
        $this->assertTrue($response->isSuccessful() || $response->status() === 422);
    }

    /** @test */
    public function admin_cannot_store_expense_in_different_event()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('finance.expense.store', $this->otherEvent->id), [
                'expense_type' => 'Equipment',
                'description' => 'Tennis rackets',
                'amount' => 500,
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function ordinary_user_receives_403_on_store_expense()
    {
        $response = $this->actingAs($this->ordinaryUser)
            ->post(route('finance.expense.store', $this->event->id), [
                'expense_type' => 'Equipment',
                'amount' => 500,
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: Income Item Management
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_store_income_item_in_authorized_event()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('finance.income.store', $this->event->id), [
                'label' => 'Sponsorship',
                'quantity' => 1,
                'amount' => 1000,
            ]);

        $this->assertTrue($response->isSuccessful() || $response->status() === 422);
    }

    /** @test */
    public function admin_cannot_store_income_item_in_different_event()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('finance.income.store', $this->otherEvent->id), [
                'label' => 'Sponsorship',
                'amount' => 1000,
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: Convenor Management
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function admin_can_store_convenor_in_authorized_event()
    {
        $newUser = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('finance.convenor.store', $this->event->id), [
                'user_ids' => [$newUser->id],
                'role' => 'Treasurer',
            ]);

        $this->assertTrue($response->isSuccessful() || $response->status() === 422);
    }

    /** @test */
    public function admin_cannot_store_convenor_in_different_event()
    {
        $newUser = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('finance.convenor.store', $this->otherEvent->id), [
                'user_ids' => [$newUser->id],
                'role' => 'Treasurer',
            ]);

        $response->assertForbidden();
    }

    // ──────────────────────────────────────────────────────────────────
    // Test: No Database Changes on Rejected Requests
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function rejected_expense_store_makes_no_changes()
    {
        $initialCount = EventExpense::count();

        $this->actingAs($this->ordinaryUser)
            ->post(route('finance.expense.store', $this->event->id), [
                'expense_type' => 'Equipment',
                'amount' => 500,
            ])
            ->assertForbidden();

        $this->assertEquals($initialCount, EventExpense::count());
    }

    /** @test */
    public function rejected_income_store_makes_no_changes()
    {
        $initialCount = EventIncomeItem::count();

        $this->actingAs($this->ordinaryUser)
            ->post(route('finance.income.store', $this->event->id), [
                'label' => 'Sponsorship',
                'amount' => 1000,
            ])
            ->assertForbidden();

        $this->assertEquals($initialCount, EventIncomeItem::count());
    }
}
