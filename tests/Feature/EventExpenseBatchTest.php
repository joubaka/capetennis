<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\EventExpenseAmounts;
use App\Domain\Finance\Services\EventExpenseBatchService;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\ExpenseType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventExpenseBatchTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::factory()->create(['name' => 'Platteland Tennis Tournament Primary Schools 2026']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        ExpenseType::firstOrCreate(['key' => 'balls'], ['label' => 'Balls']);
        ExpenseType::firstOrCreate(['key' => 'venue'], ['label' => 'Court hire']);
    }

    private function row(array $changes = []): array
    {
        return array_replace(['batch_key' => (string) Str::uuid(), 'expense_type' => 'balls', 'description' => 'Tennis balls',
            'quantity' => '0.50', 'unit_price' => '2.01', 'amount' => '999', 'date' => '2026-10-21'], $changes);
    }

    private function existing(EventExpense $expense, array $changes = []): array
    {
        return array_replace($expense->toArray(), ['id' => $expense->id, 'version' => EventExpenseBatchService::version($expense), 'date' => $expense->date->format('Y-m-d')], $changes);
    }

    private function save(array $lines, bool $complete = true)
    {
        return $this->actingAs($this->admin)->put(route('admin.events.expenses.update', $this->event), ['lines' => $lines] + ($complete ? ['form_complete' => 1] : []));
    }

    public function test_multiple_categories_save_exact_cents_and_repeat_without_duplicates(): void
    {
        $rows = [$this->row(), $this->row(['expense_type' => 'venue', 'description' => 'Venue hire', 'recipient_name' => 'Venue convenor'])];
        foreach ([1, 2] as $attempt) {
            $this->save($rows)->assertRedirect(route('admin.events.expenses.edit', ['event' => $this->event, 'page' => 1]));
            $this->assertDatabaseCount('event_expenses', 2);
        }
        $this->assertSame(2.02, EventExpense::all()->sum(fn ($expense) => $expense->calculatedAmount()));
        $this->assertSame(101, EventExpenseAmounts::lineTotal('0.50', '2.01'));
        $this->get(route('admin.events.finances', $this->event))->assertOk()->assertViewHas('totalExpenses', 2.02);
        $this->assertDatabaseHas('event_expenses', ['amount' => '1.01', 'recipient_name' => 'Venue convenor']);
        $this->export('batch-populated', $this->get(route('admin.events.expenses.edit', $this->event))->assertOk()->getContent());
    }

    public function test_updates_preserve_receipts_budget_and_omitted_rows_and_retries_are_safe(): void
    {
        $this->save([$this->row(), $this->row()])->assertRedirect();
        $expense = EventExpense::first();
        $expense->forceFill(['receipt_path' => 'receipts/existing.pdf', 'budget_amount' => '75', 'convenor_name' => 'Original payer', 'description' => null])->save();
        $expense->refresh();
        $row = $this->existing($expense, ['unit_price' => '4.02', 'budget_amount' => 1, 'receipt_path' => 'bad.pdf', 'approved_at' => now()->toDateTimeString()]);
        $this->save([$row])->assertRedirect();
        $this->save([$row])->assertRedirect();
        $this->assertDatabaseCount('event_expenses', 2);
        $this->assertDatabaseHas('event_expenses', ['id' => $expense->id, 'amount' => '2.01', 'budget_amount' => '75', 'receipt_path' => 'receipts/existing.pdf', 'approved_at' => null, 'convenor_name' => 'Original payer', 'description' => null]);
    }

    public function test_unauthorized_users_and_foreign_event_admin_cannot_view_or_save(): void
    {
        $this->get(route('admin.events.expenses.edit', $this->event))->assertRedirect('login');
        $this->put(route('admin.events.expenses.update', $this->event), ['lines' => []])->assertRedirect('login');
        foreach ([User::factory()->create(), $this->admin] as $user) {
            $event = $user->is($this->admin) ? Event::factory()->create() : $this->event;
            $this->actingAs($user)->get(route('admin.events.expenses.edit', $event))->assertForbidden();
            $this->put(route('admin.events.expenses.update', $event), ['form_complete' => 1, 'lines' => [$this->row()]])->assertForbidden();
        }
        $this->assertDatabaseCount('event_expenses', 0);
    }

    public function test_foreign_expense_director_and_venue_ids_reject_the_whole_submission(): void
    {
        $foreignEvent = Event::factory()->create();
        $foreign = EventExpense::create(['event_id' => $foreignEvent->id, 'expense_type' => 'balls', 'amount' => 5, 'date' => '2026-10-21']);
        $director = EventConvenor::create(['event_id' => $foreignEvent->id, 'user_id' => User::factory()->create()->id, 'role' => 'hoof']);
        foreach ([['id' => $foreign->id, 'version' => EventExpenseBatchService::version($foreign)], ['paid_by_convenor_id' => $director->id], ['venue_id' => 999999]] as $invalid) {
            $this->save([$this->row(), $this->row($invalid)])->assertSessionHasErrors();
            $this->assertDatabaseCount('event_expenses', 1);
        }
    }

    public function test_historical_approval_does_not_block_editing_submitted_expenses(): void
    {
        $this->save([$this->row()])->assertRedirect();
        $expense = EventExpense::first();
        $expense->update(['approved_at' => now()]);
        $this->save([$this->existing($expense->fresh(), ['description' => 'Updated submitted expense'])])->assertRedirect();
        $this->assertDatabaseHas('event_expenses', ['id' => $expense->id, 'description' => 'Updated submitted expense']);
        $this->assertNotNull($expense->fresh()->approved_at);
    }

    public function test_reimbursed_and_system_expenses_are_protected_with_atomic_rollback(): void
    {
        $this->save([$this->row(), $this->row()])->assertRedirect();
        $first = EventExpense::first();
        $second = EventExpense::orderByDesc('id')->first();
        foreach ([['reimbursed_at' => now()], ['reimbursed_at' => null, 'expense_type' => 'payfast']] as $state) {
            $second->forceFill($state)->save();
            $this->save([$this->existing($first, ['unit_price' => '9']), $this->existing($second, ['expense_type' => 'balls'])])->assertSessionHasErrors();
            $this->assertDatabaseHas('event_expenses', ['id' => $first->id, 'amount' => '1.01']);
        }
        $this->save([$this->row(['expense_type' => 'payfast'])])->assertSessionHasErrors('lines.0.expense_type');
        $this->assertDatabaseCount('event_expenses', 2);
    }

    public function test_stale_existing_edit_and_new_row_replay_cannot_overwrite_later_changes(): void
    {
        $original = $this->row();
        $this->save([$original])->assertRedirect();
        $expense = EventExpense::first();
        $stale = $this->existing($expense, ['description' => 'Stale edit']);
        $expense->update(['description' => 'Newer description']);
        $this->save([$this->row(), $stale])->assertSessionHasErrors();
        $this->save([$original])->assertSessionHasErrors();
        $this->assertDatabaseCount('event_expenses', 1);
        $this->assertDatabaseHas('event_expenses', ['id' => $expense->id, 'description' => 'Newer description']);
    }

    public function test_zero_values_precision_and_incomplete_forms_are_safe_and_errors_preserve_input(): void
    {
        $this->save([$this->row(['quantity' => 0])])->assertRedirect();
        $this->assertDatabaseHas('event_expenses', ['amount' => 0]);
        $this->save([$this->row()], false)->assertSessionHasErrors('form_complete');
        $this->save([$this->row(['unit_price' => '1.001'])])->assertSessionHasErrors('lines.0.unit_price');
        $response = $this->get(route('admin.events.expenses.edit', $this->event))->assertOk()->assertSee('1.001')->assertSee('Check the expense details.')
            ->assertSee('Expense 1 unit cost')->assertDontSee('lines.0.unit_price');
        $this->export('batch-validation', $response->getContent());
        $this->assertDatabaseCount('event_expenses', 1);
    }

    public function test_empty_editor_and_fixed_amount_expense_are_supported(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.events.expenses.edit', $this->event))->assertOk()->assertSee('No editable expenses');
        $this->export('batch-empty', $response->getContent());
        $this->save([$this->row(['quantity' => null, 'unit_price' => null, 'amount' => '17950.01'])])->assertRedirect();
        $this->assertDatabaseHas('event_expenses', ['amount' => '17950.01', 'quantity' => null, 'unit_price' => null]);
    }

    private function export(string $name, string $html): void
    {
        if (getenv('CT_EVENT_EXPENSE_QA')) {
            $directory = storage_path('app/event-expenses-qa');
            if (! is_dir($directory)) { mkdir($directory, 0755, true); }
            file_put_contents($directory.'/'.$name.'.html', $html);
        }
    }
}
