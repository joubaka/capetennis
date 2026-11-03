<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Finance\Services\EventDirectorNetSettlementService;
use App\Domain\Finance\Services\EventDirectorReconciliation;
use App\Domain\Finance\Services\EventExpenseReimbursementService;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventPayout;
use App\Models\RegistrationManualReceipt;
use App\Models\RegistrationOrder;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminPayoutIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;
    private EventConvenor $convenor;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('super-user');
        $this->event = Event::factory()->create();
        $this->convenor = EventConvenor::create([
            'event_id' => $this->event->id, 'user_id' => User::factory()->create()->id, 'role' => 'hoof',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'idempotency_key' => (string) Str::uuid(), 'convenor_id' => $this->convenor->id,
            'amount' => '123.45', 'payment_method' => 'bank_transfer',
            'paid_at' => now()->toDateString(), 'reference' => 'EFT evidence', 'description' => 'Entry fees',
        ], $overrides);
    }

    private function createPayout(array $payload = []): EventPayout
    {
        $this->actingAs($this->admin)->post(route('superadmin.finances.payout.store', $this->event), $payload ?: $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();
        return EventPayout::where('event_id', $this->event->id)->firstOrFail();
    }

    public function test_payout_retry_records_one_exact_amount_and_one_actor_bound_audit(): void
    {
        $payload = $this->payload();
        $payout = $this->createPayout($payload);
        $this->actingAs($this->admin)->post(route('superadmin.finances.payout.store', $this->event), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('event_payouts', 1);
        $this->assertSame('123.45', $payout->amount);
        $this->assertSame($this->convenor->user->name, $payout->recipient_name);
        $audit = Activity::where('log_name', 'payout')->sole();
        $this->assertSame($this->admin->id, $audit->causer_id);
        $this->assertSame('recorded', $audit->properties['action']);
        $this->assertSame($this->event->id, $audit->properties['event_id']);
        $businessAudit = DB::table('audit_events')->where('action', 'registration_payout.recorded')->sole();
        $this->assertSame($this->admin->id, $businessAudit->actor_id);
        $this->assertSame($this->event->id, $businessAudit->event_id);
    }

    public function test_retry_rejects_changed_amount_reference_actor_and_event_without_new_rows(): void
    {
        $payload = $this->payload();
        $this->createPayout($payload);
        foreach (['amount' => '123.46', 'reference' => 'Changed evidence'] as $field => $value) {
            $this->actingAs($this->admin)->post(route('superadmin.finances.payout.store', $this->event), array_replace($payload, [$field => $value]))
                ->assertSessionHasErrors('idempotency_key');
        }
        $otherAdmin = User::factory()->create()->assignRole('super-user');
        $this->actingAs($otherAdmin)->post(route('superadmin.finances.payout.store', $this->event), $payload)
            ->assertSessionHasErrors('idempotency_key');
        $otherEvent = Event::factory()->create();
        $otherConvenor = EventConvenor::create(['event_id' => $otherEvent->id, 'user_id' => User::factory()->create()->id, 'role' => 'hoof']);
        $this->actingAs($this->admin)->post(route('superadmin.finances.payout.store', $otherEvent), array_replace($payload, ['convenor_id' => $otherConvenor->id]))
            ->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('event_payouts', 1);
        $this->assertSame(1, Activity::where('log_name', 'payout')->count());
    }

    public function test_server_rejects_foreign_recipient_and_inexact_or_out_of_range_amounts(): void
    {
        $otherEvent = Event::factory()->create();
        $foreignConvenor = EventConvenor::create(['event_id' => $otherEvent->id, 'user_id' => User::factory()->create()->id, 'role' => 'hoof']);
        $this->actingAs($this->admin)->post(route('superadmin.finances.payout.store', $this->event), $this->payload(['convenor_id' => $foreignConvenor->id]))
            ->assertSessionHasErrors('convenor_id');
        $this->post(route('superadmin.finances.payout.store', $this->event), $this->payload(['convenor_id' => null, 'recipient_user_id' => User::factory()->create()->id]))
            ->assertSessionHasErrors('recipient_user_id');
        foreach (['1.001', '1e2', '0.00', '-1', '100000000.00'] as $amount) {
            $this->post(route('superadmin.finances.payout.store', $this->event), $this->payload(['amount' => $amount]))
                ->assertSessionHasErrors('amount');
        }
        $this->post(route('superadmin.finances.payout.store', $this->event), $this->payload(['paid_at' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('paid_at');
        $this->assertDatabaseCount('event_payouts', 0);
    }

    public function test_ordinary_user_cannot_record_or_correct_a_payout(): void
    {
        $payout = $this->createPayout();
        $this->actingAs(User::factory()->create())
            ->post(route('superadmin.finances.payout.store', $this->event), $this->payload())->assertForbidden();
        $this->delete(route('superadmin.finances.payout.destroy', $payout), ['reason' => 'Incorrect evidence', 'confirm' => '1'])->assertForbidden();
        $this->assertDatabaseCount('event_payouts', 1);
    }

    public function test_accounting_correction_retains_original_and_restores_only_its_event_balance_once(): void
    {
        $payout = $this->createPayout();
        $original = $payout->getAttributes();
        $otherEvent = Event::factory()->create();
        $ledger = app(FinancialLedgerService::class);
        $otherBalance = $ledger->buildForEvent($otherEvent)['totals']['registration_balance'];
        $before = $ledger->buildForEvent($this->event)['totals']['registration_balance'];
        $data = ['reason' => 'This was duplicate imported evidence.', 'confirm' => '1', 'event_id' => $otherEvent->id, 'amount' => '99999999.99'];
        foreach ([1, 2] as $attempt) {
            $this->actingAs($this->admin)->delete(route('superadmin.finances.payout.destroy', $payout), $data)
                ->assertRedirect(route('superadmin.finances.event', $this->event))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('event_payouts', 2);
        $this->assertSame($original, $payout->fresh()->getAttributes());
        $reversal = EventPayout::where('reversal_of_id', $payout->id)->sole();
        $this->assertSame('-123.45', $reversal->amount);
        $this->assertSame($this->event->id, $reversal->event_id);
        $this->assertSame($this->admin->id, $reversal->paid_by);
        $this->assertSame($data['reason'], $reversal->reversal_reason);
        $totals = $ledger->buildForEvent($this->event)['totals'];
        $this->assertEqualsWithDelta($before + 123.45, $totals['registration_balance'], 0.001);
        $this->assertEquals(0, $totals['total_paid_out']);
        $this->assertEquals($totals['total_paid_out'], $ledger->buildFySummaryRow($this->event)['registration_paid_out']);
        $this->assertEquals($otherBalance, $ledger->buildForEvent($otherEvent)['totals']['registration_balance']);
        $this->assertSame(2, Activity::where('log_name', 'payout')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'registration_payout.corrected')->count());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_correction_requires_reason_confirmation_and_rejects_changed_retry_or_linked_settlement(): void
    {
        $payout = $this->createPayout();
        $route = route('superadmin.finances.payout.destroy', $payout);
        $this->delete($route, [])->assertSessionHasErrors(['reason', 'confirm']);
        $this->delete($route, ['reason' => 'Wrong record', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->delete($route, ['reason' => 'Changed reason', 'confirm' => '1'])->assertSessionHasErrors('payout');
        $directorPayment = EventPayout::create(['event_id' => $this->event->id, 'amount' => '25.00', 'purpose' => 'director_payment', 'paid_by' => $this->admin->id]);
        $this->delete(route('superadmin.finances.payout.destroy', $directorPayment), ['reason' => 'Wrong record', 'confirm' => '1'])->assertSessionHasErrors('payout');
        $reversal = EventPayout::where('reversal_of_id', $payout->id)->sole();
        $this->delete(route('superadmin.finances.payout.destroy', $reversal), ['reason' => 'Reverse correction', 'confirm' => '1'])->assertSessionHasErrors('payout');
        $this->assertDatabaseCount('event_payouts', 3);
    }

    public function test_original_legacy_and_correction_records_cannot_be_updated_or_deleted(): void
    {
        $payout = $this->createPayout();
        foreach (['update', 'delete'] as $action) {
            try {
                $action === 'update' ? $payout->update(['amount' => '1.00']) : $payout->delete();
                $this->fail('Financial evidence must be immutable.');
            } catch (ValidationException) {
                $this->assertSame('123.45', $payout->fresh()->amount);
            }
        }
    }

    public function test_audit_failure_rolls_back_the_payout_record(): void
    {
        $dispatcher = Activity::getEventDispatcher();
        Activity::setEventDispatcher(clone $dispatcher);
        Activity::creating(function () { throw new \RuntimeException('Simulated audit failure'); });
        try {
            app(FinancialLedgerService::class)->recordRegistrationPayout($this->event, $this->admin, $this->payload());
            $this->fail('Expected audit failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure', $exception->getMessage());
        } finally {
            Activity::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertSame(0, Activity::where('log_name', 'payout')->count());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'registration_payout.recorded')->count());
    }

    public function test_disabled_auditing_cannot_silently_allow_a_payout_record(): void
    {
        config(['audit.enabled' => false, 'audit.fail_closed' => false]);
        try {
            app(FinancialLedgerService::class)->recordRegistrationPayout($this->event, $this->admin, $this->payload());
            $this->fail('A payout requires canonical audit evidence.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('required audit evidence', $exception->getMessage());
        }
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertSame(0, Activity::where('log_name', 'payout')->count());
    }

    public function test_null_canonical_audit_result_rolls_back_correction_and_leaves_original_unchanged(): void
    {
        $payout = $this->createPayout();
        $original = $payout->getAttributes();
        $this->mock(AuditWriter::class)->shouldReceive('record')->andReturnNull();
        try {
            app(FinancialLedgerService::class)->reverseRegistrationPayout($payout, $this->admin, [
                'reason' => 'Incorrect duplicate record', 'confirm' => '1',
            ]);
            $this->fail('A correction requires canonical audit evidence.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('required audit evidence', $exception->getMessage());
        }
        $this->assertSame($original, $payout->fresh()->getAttributes());
        $this->assertDatabaseCount('event_payouts', 1);
        $this->assertSame(1, Activity::where('log_name', 'payout')->count());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'registration_payout.corrected')->count());
    }

    private function seedDirectorIncome(): void
    {
        $this->event->update(['cape_tennis_fee' => 0]);
        $this->convenor->update(['profit_share_pct' => 100]);
        $order = RegistrationOrder::create(['user_id' => $this->admin->id, 'total_fee' => '1000.00', 'pay_status' => true]);
        RegistrationManualReceipt::create(['order_id' => $order->id, 'event_id' => $this->event->id,
            'amount' => '1000.00', 'method' => 'eft', 'reference' => 'SYNTHETIC-PAYOUT-INCOME',
            'verified_by_user_id' => $this->admin->id, 'paid_at' => now()->subDay()]);
    }

    public function test_generic_payout_reduces_general_and_full_net_director_balances_and_correction_restores_exact_cents(): void
    {
        $this->seedDirectorIncome();
        $previews = app(EventDirectorNetSettlementService::class);
        $before = $previews->previews($this->event)[$this->convenor->id];
        $payout = $this->createPayout();
        $after = $previews->previews($this->event)[$this->convenor->id];
        $this->assertSame($before['registration_cents'] - 12345, $after['registration_cents']);
        $this->assertSame($before['cash_cents'] - 12345, $after['cash_cents']);
        $this->assertSame(12345, EventExpenseReimbursementService::recordedCents($this->event->id, $this->convenor->id));
        $rows = EventDirectorReconciliation::build($this->event->convenors()->get(), collect(), EventDirectorReconciliation::paymentsForEvent($this->event), 1000);
        $this->assertSame(123.45, $rows->first()['director_paid']);
        $this->assertSame(876.55, $rows->first()['final_payout']);
        $this->delete(route('superadmin.finances.payout.destroy', $payout), ['reason' => 'Duplicated imported payout record', 'confirm' => '1'])
            ->assertSessionHasNoErrors();
        $restored = $previews->previews($this->event)[$this->convenor->id];
        $this->assertSame($before['registration_cents'], $restored['registration_cents']);
        $this->assertSame($before['cash_cents'], $restored['cash_cents']);
        $this->assertSame(0, EventExpenseReimbursementService::recordedCents($this->event->id, $this->convenor->id));
        $this->assertDatabaseCount('regional_clothing_settlements', 0);
    }

    public function test_unidentified_legacy_payout_blocks_new_director_payments_without_guessing_from_name(): void
    {
        $this->seedDirectorIncome();
        EventPayout::create(['event_id' => $this->event->id, 'recipient_name' => $this->convenor->user->name,
            'amount' => '20.45', 'paid_by' => $this->admin->id, 'paid_at' => now()]);
        $preview = app(EventDirectorNetSettlementService::class)->previews($this->event)[$this->convenor->id];
        $this->assertFalse($preview['available']);
        $this->assertStringContainsString('unidentified or ambiguous', $preview['unavailable_reason']);
        try {
            app(FinancialLedgerService::class)->recordExpenseReimbursement($this->event, $this->admin,
                $this->payload(['amount' => '10.00']));
            $this->fail('Unidentified payment must be reconciled first.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertDatabaseCount('event_payouts', 1);
    }

    public function test_recorded_convenor_identity_cannot_be_reassigned_or_deleted(): void
    {
        $this->createPayout();
        foreach (['update', 'delete'] as $action) {
            try {
                $action === 'update' ? $this->convenor->update(['user_id' => User::factory()->create()->id]) : $this->convenor->delete();
                $this->fail('Recorded payout identity must remain attached.');
            } catch (ValidationException) {
                $this->assertNotNull($this->convenor->fresh());
            }
        }
    }

    public function test_known_admin_recipient_without_director_assignment_blocks_settlement_until_accounting_correction(): void
    {
        $this->seedDirectorIncome();
        $recipient = User::factory()->create();
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $recipient->id]);
        $payout = $this->createPayout($this->payload(['convenor_id' => null, 'recipient_user_id' => $recipient->id]));
        $this->assertNull($payout->convenor_id);
        $service = app(EventDirectorNetSettlementService::class);
        $this->assertFalse($service->previews($this->event)[$this->convenor->id]['available']);
        try {
            app(FinancialLedgerService::class)->recordExpenseReimbursement($this->event, $this->admin, $this->payload(['amount' => '10.00']));
            $this->fail('A known recipient is not proof of a director allocation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->delete(route('superadmin.finances.payout.destroy', $payout), ['reason' => 'Incorrect duplicate imported record', 'confirm' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($service->previews($this->event)[$this->convenor->id]['available']);
        $this->assertDatabaseCount('event_payouts', 2);
    }

    public function test_legacy_convenor_reference_from_another_event_blocks_settlement(): void
    {
        $this->seedDirectorIncome();
        $otherEvent = Event::factory()->create();
        $foreignConvenor = EventConvenor::create(['event_id' => $otherEvent->id, 'user_id' => User::factory()->create()->id, 'role' => 'hoof']);
        EventPayout::create(['event_id' => $this->event->id, 'convenor_id' => $foreignConvenor->id,
            'amount' => '20.45', 'paid_by' => $this->admin->id]);
        $preview = app(EventDirectorNetSettlementService::class)->previews($this->event)[$this->convenor->id];
        $this->assertFalse($preview['available']);
        $this->assertStringContainsString('unidentified or ambiguous', $preview['unavailable_reason']);
        $this->assertDatabaseCount('event_payouts', 1);
    }

    public function test_payout_forms_render_event_bound_recipient_evidence_and_confirmed_accounting_correction(): void
    {
        $export = getenv('CT_DASHBOARD_PAYOUT_QA_HTML') === '1';
        $capturePath = storage_path('framework/testing/dashboard-payout-form.html');
        if ($export && is_file($capturePath)) unlink($capturePath);
        $payout = $this->createPayout();
        $response = $this->get(route('superadmin.finances.event', $this->event));
        $response->assertOk()
            ->assertSee('action="'.route('superadmin.finances.payout.store', $this->event).'"', false)
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('id="payoutRecipientUserId" name="recipient_user_id"', false)
            ->assertSee('data-convenor-id="'.$this->convenor->id.'"', false)
            ->assertSee('action="'.route('superadmin.finances.payout.destroy', $payout).'"', false)
            ->assertSee('id="payout-reason-'.$payout->id.'" name="reason"', false)
            ->assertSee('name="confirm" value="1" required', false)
            ->assertSee('Record accounting correction')
            ->assertSee('It does not return money or reverse a bank transfer.')
            ->assertDontSee('Delete this payout?', false);
        if ($export) {
            if (! is_dir(dirname($capturePath))) mkdir(dirname($capturePath), 0777, true);
            $this->assertNotFalse(file_put_contents($capturePath, $response->getContent()));
        }
    }

    public function test_legacy_convenor_and_recipient_identity_conflict_blocks_both_directors(): void
    {
        $this->seedDirectorIncome();
        $this->convenor->update(['profit_share_pct' => 50]);
        $secondDirector = EventConvenor::create(['event_id' => $this->event->id,
            'user_id' => User::factory()->create()->id, 'role' => 'hulp', 'profit_share_pct' => 50]);
        EventPayout::create(['event_id' => $this->event->id, 'convenor_id' => $this->convenor->id,
            'recipient_user_id' => $secondDirector->user_id, 'recipient_name' => $secondDirector->user->name,
            'amount' => '20.45', 'paid_by' => $this->admin->id]);
        $previews = app(EventDirectorNetSettlementService::class)->previews($this->event);
        foreach ([$this->convenor, $secondDirector] as $director) {
            $this->assertFalse($previews[$director->id]['available']);
            try {
                app(FinancialLedgerService::class)->recordExpenseReimbursement($this->event, $this->admin,
                    $this->payload(['convenor_id' => $director->id, 'amount' => '10.00']));
                $this->fail('Conflicting recorded recipient identities require reconciliation for both directors.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
        }
        $this->assertDatabaseCount('event_payouts', 1);
    }
}
