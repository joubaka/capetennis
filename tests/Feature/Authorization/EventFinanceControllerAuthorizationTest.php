<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $adminB;
    private User $user;
    private Event $eventA;
    private Event $eventB;
    private EventExpense $expenseA;
    private EventIncomeItem $incomeA;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->adminB = User::factory()->create();
        $this->adminB->assignRole('admin');
        $this->user = User::factory()->create();
        $this->eventA = Event::factory()->create();
        $this->eventB = Event::factory()->create();
        EventAdmin::create(['user_id' => $this->admin->id, 'event_id' => $this->eventA->id]);
        EventAdmin::create(['user_id' => $this->adminB->id, 'event_id' => $this->eventB->id]);
        $this->expenseA = EventExpense::create(['event_id' => $this->eventA->id, 'expense_type' => 'Equipment', 'amount' => 100]);
        $this->incomeA = EventIncomeItem::create(['event_id' => $this->eventA->id, 'label' => 'Sponsorship', 'quantity' => 1, 'unit_price' => 500]);
    }

    public function test_view_guest_redirect() { $this->get(route('admin.events.finances', $this->eventA->id))->assertRedirect('login'); }

    public function test_view_admin_success() { $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventA->id))->assertSuccessful(); }

    public function test_view_cross_event_forbidden() { $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventB->id))->assertStatus(403); }

    public function test_view_user_forbidden() { $this->actingAs($this->user)->get(route('admin.events.finances', $this->eventA->id))->assertStatus(403); }

    public function test_expense_store_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.store', $this->eventA->id), ['expense_type' => 'Equipment', 'amount' => 100])->assertRedirect(); $this->assertCount(2, EventExpense::where('event_id', $this->eventA->id)->get()); }

    public function test_expense_store_cross_event() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.store', $this->eventB->id), ['expense_type' => 'Equipment', 'amount' => 100])->assertStatus(403); }

    public function test_expense_update_admin() { $this->actingAs($this->admin)->patch(route('admin.events.finances.expense.update', $this->expenseA->id), ['expense_type' => 'Equipment', 'amount' => 250])->assertRedirect(); $this->assertEquals(250, $this->expenseA->refresh()->amount); }

    public function test_expense_update_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->patch(route('admin.events.finances.expense.update', $expenseB->id), ['expense_type' => 'Equipment', 'amount' => 250])->assertStatus(403); }

    public function test_expense_destroy_admin() { $this->actingAs($this->admin)->delete(route('admin.events.finances.expense.destroy', $this->expenseA->id))->assertRedirect(); $this->assertNull(EventExpense::find($this->expenseA->id)); }

    public function test_expense_destroy_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->delete(route('admin.events.finances.expense.destroy', $expenseB->id))->assertStatus(403); $this->assertNotNull(EventExpense::find($expenseB->id)); }

    public function test_expense_approve_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.approve', $this->expenseA->id))->assertRedirect(); }

    public function test_expense_approve_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->post(route('admin.events.finances.expense.approve', $expenseB->id))->assertStatus(403); }

    public function test_expense_reimburse_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.reimburse', $this->expenseA->id))->assertRedirect(); }

    public function test_expense_reimburse_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->post(route('admin.events.finances.expense.reimburse', $expenseB->id))->assertStatus(403); }

    public function test_income_store_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.income.store', $this->eventA->id), ['label' => 'S', 'quantity' => 1, 'unit_price' => 500])->assertRedirect(); $this->assertCount(2, EventIncomeItem::where('event_id', $this->eventA->id)->get()); }

    public function test_income_store_cross_event() { $this->actingAs($this->admin)->post(route('admin.events.finances.income.store', $this->eventB->id), ['label' => 'S', 'quantity' => 1, 'unit_price' => 500])->assertStatus(403); }

    public function test_income_update_admin() { $this->actingAs($this->admin)->patch(route('admin.events.finances.income.update', $this->incomeA->id), ['unit_price' => 750])->assertRedirect(); }

    public function test_income_update_cross_event() { $incomeB = EventIncomeItem::create(['event_id' => $this->eventB->id, 'label' => 'S', 'quantity' => 1, 'unit_price' => 500]); $this->actingAs($this->admin)->patch(route('admin.events.finances.income.update', $incomeB->id), ['unit_price' => 750])->assertStatus(403); }

    public function test_income_destroy_admin() { $this->actingAs($this->admin)->delete(route('admin.events.finances.income.destroy', $this->incomeA->id))->assertRedirect(); $this->assertNull(EventIncomeItem::find($this->incomeA->id)); }

    public function test_income_destroy_cross_event() { $incomeB = EventIncomeItem::create(['event_id' => $this->eventB->id, 'label' => 'S', 'quantity' => 1, 'unit_price' => 500]); $this->actingAs($this->admin)->delete(route('admin.events.finances.income.destroy', $incomeB->id))->assertStatus(403); $this->assertNotNull(EventIncomeItem::find($incomeB->id)); }

    public function test_convenor_store_admin() { $user = User::factory()->create(); $this->actingAs($this->admin)->post(route('admin.events.finances.convenor.store', $this->eventA->id), ['user_ids' => [$user->id]])->assertRedirect(); $this->assertTrue(EventConvenor::where('user_id', $user->id)->where('event_id', $this->eventA->id)->exists()); }

    public function test_convenor_store_cross_event() { $user = User::factory()->create(); $this->actingAs($this->admin)->post(route('admin.events.finances.convenor.store', $this->eventB->id), ['user_ids' => [$user->id]])->assertStatus(403); }

    public function test_convenor_update_admin() { $convenor = EventConvenor::create(['event_id' => $this->eventA->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->patch(route('admin.events.finances.convenor.update', $convenor->id), ['role' => 'hulp'])->assertRedirect(); }

    public function test_convenor_update_cross_event() { $convenor = EventConvenor::create(['event_id' => $this->eventB->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->patch(route('admin.events.finances.convenor.update', $convenor->id), ['role' => 'hulp'])->assertStatus(403); }

    public function test_convenor_destroy_admin() { $convenor = EventConvenor::create(['event_id' => $this->eventA->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->delete(route('admin.events.finances.convenor.destroy', $convenor->id))->assertRedirect(); $this->assertNull(EventConvenor::find($convenor->id)); }

    public function test_convenor_destroy_cross_event() { $convenor = EventConvenor::create(['event_id' => $this->eventB->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->delete(route('admin.events.finances.convenor.destroy', $convenor->id))->assertStatus(403); $this->assertNotNull(EventConvenor::find($convenor->id)); }
}
