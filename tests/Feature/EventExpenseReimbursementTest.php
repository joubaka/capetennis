<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\EventExpenseBatchService;
use App\Domain\Finance\Services\EventProfitAllocation;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventExpenseReimbursementTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;
    private EventConvenor $director;
    private EventExpense $expense;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $this->director = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => User::factory()->create()->id, 'role' => 'hulp', 'profit_share_pct' => 50]);
        $this->expense = EventExpense::create(['event_id' => $this->event->id, 'paid_by_convenor_id' => $this->director->id, 'expense_type' => 'balls', 'amount' => '63630.00', 'date' => '2026-10-20']);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['convenor_id' => $this->director->id, 'amount' => '60000.00', 'idempotency_key' => (string) Str::uuid(), 'description' => 'Already paid from entries'], $changes);
    }

    private function record(array $data)
    {
        return $this->actingAs($this->admin)->post(route('admin.events.expense-reimbursements.store', $this->event), $data);
    }

    public function test_submitted_expense_can_be_reimbursed_without_approval_and_retries_are_harmless(): void
    {
        $url = route('admin.events.finances.expense.reimburse', $this->expense);
        $this->post($url)->assertRedirect('login');
        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
        $this->actingAs($this->admin)->post($url)->assertRedirect();
        $completedAt = $this->expense->fresh()->reimbursed_at;
        $this->assertNotNull($completedAt);
        $this->assertNull($this->expense->fresh()->approved_at);
        $this->assertSame($this->admin->id, $this->expense->fresh()->reimbursed_by);
        $this->post($url)->assertRedirect();
        $this->assertTrue($completedAt->equalTo($this->expense->fresh()->reimbursed_at));
        $this->assertDatabaseCount('event_expenses', 1);
        $this->assertDatabaseCount('event_payouts', 0);
    }

    public function test_partial_reimbursement_exact_amount_is_audited_and_idempotent(): void
    {
        $data = $this->data();
        $this->record($data)->assertRedirect(route('admin.events.finances', $this->event));
        $this->record($data)->assertRedirect();
        $this->assertDatabaseCount('event_payouts', 1);
        $this->assertDatabaseHas('event_payouts', ['amount' => '60000.00', 'purpose' => 'expense_reimbursement', 'funding_source' => 'registration_entries', 'paid_by' => $this->admin->id, 'recorded_by_name' => $this->admin->name, 'recipient_user_id' => $this->director->user_id, 'paid_at' => null]);
        $this->assertNull($this->expense->fresh()->approved_at);
        $this->assertNull($this->expense->fresh()->reimbursed_at);
        $type = new \App\Models\EventType;
        $type->forceFill(['name' => 'Team reimbursement test', 'type' => \App\Models\EventType::TEAM])->save();
        $this->event->update(['eventType' => $type->id]);
        $this->get(route('admin.events.overview', $this->event))->assertOk()->assertSee('Record reimbursement already paid')->assertViewHas('reimbursementPayments');
        $this->record(array_replace($data, ['amount' => '1']))->assertSessionHasErrors('amount');
        $response = $this->get(route('admin.events.finances', $this->event))->assertOk()->assertViewHas('totalExpenses', 63630.0);
        $row = $response->viewData('recon')->first();
        $this->assertSame(3630.0, $row['owed_back'] - $row['reimbursed']);
        $this->assertSame(-63630.0, $response->viewData('netProfit'));
    }

    public function test_dated_retry_preserves_original_actor_and_record_count(): void
    {
        $data = $this->data(['paid_at' => '2026-01-01', 'amount' => '0.01']);
        $this->record($data)->assertRedirect();
        $original = EventPayout::first()->toArray();
        $this->record($data)->assertRedirect();
        $this->assertSame($original, EventPayout::first()->toArray());
        $this->assertDatabaseCount('event_payouts', 1);
        try { $this->director->user->delete(); $this->fail('Must retain director account'); } catch (\Illuminate\Validation\ValidationException) {}
        $this->assertDatabaseHas('event_convenors', ['id' => $this->director->id, 'user_id' => $this->director->user_id]);
    }

    public function test_authorization_cross_event_and_precision_reject_without_records(): void
    {
        $url = route('admin.events.expense-reimbursements.store', $this->event);
        $this->post($url, $this->data())->assertRedirect('login');
        $this->actingAs(User::factory()->create())->post($url, $this->data())->assertForbidden();
        $foreign = EventConvenor::create(['event_id' => Event::factory()->create()->id, 'user_id' => User::factory()->create()->id, 'role' => 'hulp']);
        $this->record($this->data(['convenor_id' => $foreign->id]))->assertSessionHasErrors('convenor_id');
        $this->record($this->data(['amount' => '1.001']))->assertSessionHasErrors('amount');
        $this->record($this->data(['amount' => '63630.01']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payouts', 0);
    }

    public function test_existing_legacy_reimbursements_reduce_available_amount_and_cannot_double_count(): void
    {
        EventExpense::create(['event_id' => $this->event->id, 'paid_by_convenor_id' => $this->director->id, 'expense_type' => 'venue', 'amount' => '100', 'reimbursed_at' => now(), 'approved_at' => now()]);
        $this->record($this->data(['amount' => '63630.01']))->assertSessionHasErrors('amount');
        $this->record($this->data())->assertRedirect();
        $this->expense->update(['approved_at' => now()]);
        $this->actingAs($this->admin)->post(route('admin.events.finances.expense.reimburse', $this->expense))->assertSessionHasErrors('expense');
        $this->assertNull($this->expense->fresh()->reimbursed_at);
        $this->record($this->data(['amount' => '3630.00']))->assertRedirect();
        $this->record($this->data(['amount' => '0.01']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payouts', 2);
    }

    public function test_reimbursement_protects_expense_mutations_director_and_receipt(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('receipts/original.pdf', 'receipt');
        $this->expense->update(['receipt_path' => 'receipts/original.pdf']);
        $this->record($this->data())->assertRedirect();
        $this->actingAs($this->admin)->patch(route('admin.events.finances.expense.update', $this->expense), ['expense_type' => 'balls', 'amount' => '1', 'receipt' => UploadedFile::fake()->create('new.pdf', 5, 'application/pdf')])->assertSessionHasErrors('expense');
        $this->delete(route('admin.events.finances.expense.destroy', $this->expense))->assertSessionHasErrors('expense');
        Storage::disk('public')->assertExists('receipts/original.pdf');
        $this->delete(route('admin.events.finances.convenor.destroy', $this->director))->assertSessionHasErrors('convenor');
        $line = $this->expense->fresh()->toArray() + ['version' => EventExpenseBatchService::version($this->expense->fresh())];
        $line['amount'] = '1';
        $line['date'] = '2026-10-20';
        $this->put(route('admin.events.expenses.update', $this->event), ['form_complete' => 1, 'lines' => [$line]])->assertSessionHasErrors();
        $this->assertSame(63630.0, $this->expense->fresh()->calculatedAmount());
        $payout = EventPayout::first();
        try { $payout->delete(); $this->fail('Must retain reimbursement'); } catch (\Illuminate\Validation\ValidationException) {}
        $this->assertDatabaseCount('event_payouts', 1);
    }

    public function test_cash_balance_deducts_reimbursement_once_and_profit_allocations_keep_the_odd_cent(): void
    {
        $second = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => User::factory()->create()->id, 'role' => 'hulp', 'profit_share_pct' => 50]);
        $shares = EventProfitAllocation::cents(collect([$this->director, $second]), 50065.93);
        $this->assertSame(5006593, array_sum($shares));
        $this->assertSame(2503297, $shares[$this->director->id]);
        $this->assertSame(2503296, $shares[$second->id]);
        $this->record($this->data())->assertRedirect();
        $ledger = app(FinancialLedgerService::class);
        $totals = $ledger->buildTotals(collect([(object) ['type' => 'payment', 'gross' => 122990, 'fee' => -5129.07, 'capeFee' => -3765]]), collect(), $ledger->buildPayoutRows($this->event));
        $this->assertSame(114095.93, $totals['registration_net']);
        $this->assertSame(54095.93, $totals['registration_balance']);
        $this->assertSame(60000.0, $totals['total_paid_out']);
    }
}
