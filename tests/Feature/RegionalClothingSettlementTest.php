<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Finance\Services\RegionalClothingSettlementService;
use App\Models\CategoryEvent;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\RegionalClothingSettlement;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegionalClothingSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;
    private EventRegion $scope;
    private ClothingOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-21 14:00:00'));
        Http::preventStrayRequests();
        $this->mock(\App\Services\Payfast::class)->shouldNotReceive('refundUsingAvailableMethod');
        Role::findOrCreate('super-user', 'web');
        $this->admin = User::factory()->create()->assignRole('super-user');
        $this->event = Event::factory()->create();
        [$this->scope, $this->order] = $this->region($this->event, 'QA West', '32659.98', '1347.32');
    }

    private function region(Event $event, string $name, string $gross = '400.00', string $fee = '0.00'): array
    {
        $region = TeamRegion::create(['region_name' => $name, 'clothing_order' => false]);
        $id = DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id]);
        $order = ClothingOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'user_id' => User::factory()->create()->id,
            'pay_status' => 1, 'status' => 'completed', 'total' => $gross, 'amount_paid' => $gross,
            'payfast_fee' => $fee, 'paid_at' => '2026-10-01 12:00:00']);
        return [EventRegion::findOrFail($id), $order];
    }

    private function refund(string $status = 'completed', string $amount = '395.00', ?ClothingOrder $order = null): ClothingRefund
    {
        $order ??= $this->order;
        return FinanceMutationScope::run('refund_state_write', function () use ($order, $status, $amount) {
            $refund = ClothingRefund::create([
            'event_id' => $order->event_id, 'clothing_order_id' => $order->id, 'payer_id' => $order->user_id,
            'requested_by' => $this->admin->id, 'requested_by_name' => 'QA Refund Initiator',
            'request_token' => (string) Str::uuid(), 'request_fingerprint' => str_repeat('a', 64),
            'refund_method' => 'payfast', 'refund_status' => $status, 'refund_gross' => $amount, 'refund_fee' => '0.00', 'refund_net' => $amount,
            'reason' => 'Synthetic unreceived clothing item', 'refunded_at' => $status === 'completed' ? '2026-10-15 12:00:00' : null,
            ]);
            $refund->forceFill(['created_at' => '2026-10-10 12:00:00'])->save();
            return $refund;
        });
    }

    private function data(array $changes = []): array
    {
        return array_replace([
            'event_region_id' => $this->scope->id, 'kind' => 'payout', 'amount' => '31312.66',
            'recipient_name' => 'QA Regional Recipient', 'occurred_at' => '2026-10-07 12:00:00',
            'reference' => 'QA-CLOTHING-PAID', 'evidence' => 'Synthetic confirmed payment statement evidence',
            'request_token' => (string) Str::uuid(), 'confirmed' => 1,
        ], $changes);
    }

    private function postRecord(array $data)
    {
        return $this->actingAs($this->admin)->post(route('admin.events.clothing-settlements.store', $this->event), $data);
    }

    private function report(): \Illuminate\Support\Collection
    {
        $ledger = app(FinancialLedgerService::class)->buildForEvent($this->event);
        return app(RegionalClothingSettlementService::class)->report($this->event, $ledger['paymentRows']->merge($ledger['refundRows']))->keyBy(fn ($row) => $row['scope']->id);
    }

    public function test_historical_full_payout_and_later_refund_without_deadline_show_exact_recovery_and_original_actor(): void
    {
        $this->refund();
        $data = $this->data();
        $this->postRecord($data)->assertSessionHasNoErrors()->assertRedirect();
        $original = RegionalClothingSettlement::first()->toArray();
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->assertSame($original, RegionalClothingSettlement::first()->toArray());
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
        $row = $this->report()[$this->scope->id];
        $this->assertSame(3091766, $row['net_cents']);
        $this->assertSame(-39500, $row['balance_cents']);
        $this->assertSame(0, $row['pending_cents']);
        $this->assertTrue($row['refunds']->first()->after_recorded_payout);
        $this->assertFalse($row['refunds']->first()->after_recorded_deadline);
        $this->assertNull($row['refunds']->first()->completed_by);
        $page = $this->get(route('admin.events.finances', $this->event))->assertOk()
            ->assertSee('Recover or offset')->assertSee('395.00')->assertSee('After recorded payout')
            ->assertSee('No deadline recorded')->assertSee('QA Refund Initiator')->assertSee('Not recorded')
            ->assertSee('Net clothing funds before payouts')->assertDontSee('Net payable to convenor');
        $this->assertSame(0.0, $page->viewData('netRegistrationIncome'));
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertNothingSent();
    }

    public function test_evidenced_actual_overpayment_retains_original_shortfall_separate_from_later_refund(): void
    {
        $this->refund();
        $data = $this->data(['amount' => '31312.98']);
        $this->postRecord($data)->assertSessionHasErrors('amount');
        $data += ['overpayment_confirmed' => 1, 'overpayment_reason' => 'Statement proves original payout exceeded recorded clothing funds by 32 cents.'];
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
        $this->assertDatabaseHas('regional_clothing_settlements', ['amount' => '31312.98', 'funding_shortfall' => '0.32']);
        $row = $this->report()[$this->scope->id];
        $this->assertSame(-39532, $row['balance_cents']);
        $this->assertSame(32, $row['shortfall_cents']);
        $this->postRecord(array_replace($data, ['overpayment_reason' => 'Different explanation must reject replay']))->assertSessionHasErrors('request_token');
        $this->get(route('admin.events.finances', $this->event))->assertOk()->assertSee('395.32')->assertSee('Recorded overpayment / shortfall: R 0.32');
    }

    public function test_recovery_is_bounded_exact_idempotent_and_does_not_change_individual_refund_resolution(): void
    {
        $refund = $this->refund();
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $data = $this->data(['kind' => 'recovery', 'amount' => '200.01', 'occurred_at' => '2026-10-20 12:00:00']);
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->assertSame(-19499, $this->report()[$this->scope->id]['balance_cents']);
        $this->postRecord($this->data(['kind' => 'recovery', 'amount' => '195.00', 'occurred_at' => '2026-10-20 13:00:00']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('regional_clothing_settlements', 2);
        $this->assertSame($refund->reconciliation_status, $refund->fresh()->reconciliation_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_paired_offset_requires_both_recipient_agreement_and_moves_only_available_clothing_payable(): void
    {
        [$source] = $this->region($this->event, 'QA North');
        $this->refund();
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $data = $this->data(['kind' => 'offset', 'source_event_region_id' => $source->id, 'amount' => '395.00', 'occurred_at' => '2026-10-20 12:00:00',
            'evidence' => 'QA North recipient and QA West recipient signed agreement QA-AGREEMENT-1']);
        $this->postRecord($data)->assertSessionHasErrors('offset_agreement_confirmed');
        $data['offset_agreement_confirmed'] = 1;
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('regional_clothing_settlements', 3);
        $legs = RegionalClothingSettlement::where('request_token', $data['request_token'])->get();
        $this->assertCount(2, $legs);
        $this->assertSame(['offset_in', 'offset_out'], $legs->pluck('kind')->all());
        $report = $this->report();
        $this->assertSame(0, $report[$this->scope->id]['balance_cents']);
        $this->assertSame(500, $report[$source->id]['balance_cents']);
        $this->assertSame(0, $report->sum('offset_cents'));
        $this->assertSame(0, $report->sum('recovery_cents'));
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_pending_refunds_reserve_funds_and_do_not_create_recoverable_debt(): void
    {
        $this->refund('pending');
        $this->postRecord($this->data(['occurred_at' => '2026-10-20 12:00:00']))->assertSessionHasErrors('amount');
        $this->postRecord($this->data(['amount' => '30917.66', 'occurred_at' => '2026-10-20 12:00:00']))->assertSessionHasNoErrors();
        $row = $this->report()[$this->scope->id];
        $this->assertSame(39500, $row['balance_cents']);
        $this->assertSame(39500, $row['pending_cents']);
        $this->assertSame(0, $row['available_cents']);
        $this->postRecord($this->data(['kind' => 'recovery', 'amount' => '0.01', 'occurred_at' => '2026-10-20 13:00:00']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_as_of_dates_reject_payout_before_receipt_recovery_before_refund_and_future_records(): void
    {
        $this->refund();
        $this->postRecord($this->data(['occurred_at' => '2026-09-30 12:00:00']))->assertSessionHasErrors('amount');
        $this->postRecord($this->data(['occurred_at' => '2026-10-20 12:00:00']))->assertSessionHasErrors('amount');
        $this->postRecord($this->data(['occurred_at' => '2026-10-22 12:00:00']))->assertSessionHasErrors('occurred_at');
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $this->postRecord($this->data(['kind' => 'recovery', 'amount' => '1.00', 'occurred_at' => '2026-10-14 12:00:00']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_authorization_event_scope_same_names_invalid_amount_and_payload_tampering_preserve_counts(): void
    {
        $url = route('admin.events.clothing-settlements.store', $this->event);
        $this->post($url, $this->data())->assertRedirect('login');
        $this->actingAs(User::factory()->create())->post($url, $this->data())->assertForbidden();
        [$foreign] = $this->region(Event::factory()->create(), 'QA West');
        $this->postRecord($this->data(['event_region_id' => $foreign->id]))->assertSessionHasErrors('event_region_id');
        foreach (['0', '-1', '1.001', '1e3'] as $amount) {
            $this->postRecord($this->data(['amount' => $amount]))->assertSessionHasErrors('amount');
        }
        $data = $this->data();
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->postRecord(array_replace($data, ['amount' => '1.00']))->assertSessionHasErrors('request_token');
        $this->postRecord(array_replace($data, ['reference' => 'Different']))->assertSessionHasErrors('request_token');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
        $this->assertDatabaseCount('event_payouts', 0);
    }

    public function test_invalid_offsets_and_pending_source_liabilities_leave_no_partial_legs(): void
    {
        [$source, $sourceOrder] = $this->region($this->event, 'QA North');
        [$foreign] = $this->region(Event::factory()->create(), 'QA Foreign');
        $this->refund();
        $this->refund('pending', '10.00', $sourceOrder);
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $data = $this->data(['kind' => 'offset', 'source_event_region_id' => $source->id, 'amount' => '395.00', 'occurred_at' => '2026-10-20 12:00:00', 'offset_agreement_confirmed' => 1]);
        $this->postRecord($data)->assertSessionHasErrors('amount');
        $this->postRecord(array_replace($data, ['source_event_region_id' => $foreign->id]))->assertSessionHasErrors('source_event_region_id');
        $this->postRecord(array_replace($data, ['source_event_region_id' => $this->scope->id]))->assertSessionHasErrors('source_event_region_id');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_immutable_audit_snapshots_and_foreign_team_receipts_do_not_authorize_payout(): void
    {
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $entry = RegionalClothingSettlement::firstOrFail();
        try { $entry->update(['amount' => '1']); $this->fail('Record must be immutable'); } catch (\Illuminate\Validation\ValidationException) {}
        try { $entry->delete(); $this->fail('Record must not be deleted'); } catch (\Illuminate\Validation\ValidationException) {}
        $name = $entry->recorded_by_name;
        $this->admin->update(['name' => 'Changed account name']);
        $this->assertSame($name, $entry->fresh()->recorded_by_name);
        $other = Event::factory()->create();
        $this->order->team->update(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $other->id])->id]);
        $this->assertSame(0, $this->report()[$this->scope->id]['net_cents']);
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_validation_preserves_inputs_and_request_token_for_review(): void
    {
        $data = $this->data(['amount' => '99999999.99']);
        $this->actingAs($this->admin)->from(route('admin.events.finances', $this->event))->post(route('admin.events.clothing-settlements.store', $this->event), $data)
            ->assertSessionHasErrors('amount')->assertSessionHasInput('request_token', $data['request_token'])->assertSessionHasInput('evidence', $data['evidence']);
        $this->get(route('admin.events.finances', $this->event))->assertOk()->assertSee('explicitly acknowledge')->assertSee($data['request_token']);
        $this->assertDatabaseCount('regional_clothing_settlements', 0);
    }

    public function test_offset_date_cannot_spend_later_source_receipts_and_overpayment_override_cannot_credit_recovery(): void
    {
        [$source, $order] = $this->region($this->event, 'QA Source');
        $order->update(['paid_at' => '2026-10-20 12:00:00']);
        $this->refund();
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $offset = $this->data(['kind' => 'offset', 'source_event_region_id' => $source->id, 'amount' => '395.00',
            'occurred_at' => '2026-10-16 12:00:00', 'offset_agreement_confirmed' => 1]);
        $this->postRecord($offset)->assertSessionHasErrors('amount');
        $recovery = $this->data(['kind' => 'recovery', 'amount' => '395.01', 'occurred_at' => '2026-10-20 12:00:00',
            'overpayment_confirmed' => 1, 'overpayment_reason' => 'This must never bypass recovery limits']);
        $this->postRecord($recovery)->assertSessionHasErrors('overpayment_confirmed');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_refund_deadline_classification_uses_original_snapshot_not_a_changed_current_deadline(): void
    {
        $refund = $this->refund();
        FinanceMutationScope::run('refund_state_write', fn () => $refund->update(['clothing_deadline_at' => '2026-10-12 12:00:00']));
        $this->scope->forceFill(['clothing_deadline_at' => '2026-11-01 12:00:00'])->save();
        $this->assertTrue($this->report()[$this->scope->id]['refunds']->first()->after_recorded_deadline);
        $this->assertDatabaseCount('regional_clothing_settlements', 0);
    }

    public function test_date_only_evidence_does_not_invent_time_or_intraday_order_and_precision_cannot_be_spoofed(): void
    {
        $refund = $this->refund();
        $data = $this->data(['occurred_at' => '2026-10-07']);
        $this->postRecord($data)->assertSessionHasNoErrors();
        $entry = RegionalClothingSettlement::firstOrFail();
        $this->assertTrue($entry->hasDateOnlyEvidence());
        $this->assertStringStartsWith(RegionalClothingSettlement::DATE_ONLY_EVIDENCE, $entry->evidence);
        $this->assertTrue($this->report()[$this->scope->id]['refunds']->first()->after_recorded_payout);
        FinanceMutationScope::run('refund_state_write', fn () => $refund->update(['refunded_at' => '2026-10-07 15:00:00']));
        $this->assertFalse($this->report()[$this->scope->id]['refunds']->first()->after_recorded_payout);
        [$other] = $this->region($this->event, 'QA Exact Time');
        $this->postRecord($this->data(['event_region_id' => $other->id, 'amount' => '1.00', 'occurred_at' => '2026-10-07 12:00:00',
            'evidence' => RegionalClothingSettlement::DATE_ONLY_EVIDENCE.'User text cannot spoof the precision marker']))->assertSessionHasNoErrors();
        $this->assertFalse(RegionalClothingSettlement::latest('id')->firstOrFail()->hasDateOnlyEvidence());
    }

    public function test_precise_timestamps_normalize_timezone_before_storage_and_idempotency(): void
    {
        $data = $this->data(['occurred_at' => '2026-10-07T12:00:00+02:00']);
        $this->postRecord($data)->assertSessionHasNoErrors();
        $this->postRecord(array_replace($data, ['occurred_at' => '2026-10-07T10:00:00Z']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
        $this->assertSame(\Carbon\CarbonImmutable::parse('2026-10-07T10:00:00Z')->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            RegionalClothingSettlement::firstOrFail()->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_unknown_time_recovery_cannot_fund_a_precisely_timed_payout_on_same_day(): void
    {
        $this->refund();
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $this->postRecord($this->data(['kind' => 'recovery', 'amount' => '395.00', 'occurred_at' => '2026-10-20']))->assertSessionHasNoErrors();
        $extra = $this->order->replicate();
        $extra->forceFill(['total' => '100.00', 'amount_paid' => '100.00', 'payfast_fee' => '0.00', 'paid_at' => '2026-10-20 11:00:00'])->save();
        $this->assertSame(10000, $this->report()[$this->scope->id]['balance_cents']);
        $this->postRecord($this->data(['amount' => '100.00', 'occurred_at' => '2026-10-20 12:00:00']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('regional_clothing_settlements', 2);
    }

    public function test_precise_recovery_cannot_assume_unknown_time_payout_already_happened_on_same_day(): void
    {
        $refund = $this->refund();
        $this->postRecord($this->data(['occurred_at' => '2026-10-07']))->assertSessionHasNoErrors();
        FinanceMutationScope::run('refund_state_write', fn () => $refund->forceFill(['created_at' => '2026-10-06 12:00:00', 'refunded_at' => '2026-10-07 15:00:00'])->save());
        $this->postRecord($this->data(['kind' => 'recovery', 'amount' => '395.00', 'occurred_at' => '2026-10-07 16:00:00']))->assertSessionHasErrors('occurred_at');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_date_only_recovery_cannot_assume_same_day_refund_already_created_the_deficit(): void
    {
        $this->refund();
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $this->postRecord($this->data(['kind' => 'recovery', 'amount' => '395.00', 'occurred_at' => '2026-10-15']))->assertSessionHasErrors('occurred_at');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_duplicate_associations_count_physical_funds_once_retain_all_audit_and_block_second_payout(): void
    {
        $this->postRecord($this->data(['amount' => '100.00']))->assertSessionHasNoErrors();
        $duplicate = DB::table('event_regions')->insertGetId(['event_id' => $this->event->id, 'region_id' => $this->scope->region_id, 'ordering' => 2]);
        // Retain historical evidence attached to either association. This fixture
        // models an already recorded second association; no cleanup is inferred.
        $legacy = RegionalClothingSettlement::firstOrFail()->replicate();
        $legacy->event_region_id = $duplicate;
        $legacy->request_token = (string) Str::uuid();
        $legacy->save();
        $report = $this->report();
        $this->assertCount(1, $report);
        $row = $report->first();
        $this->assertSame(2, $row['association_count']);
        $this->assertFalse($row['recording_available']);
        $this->assertSame(3131266, $row['net_cents']);
        $this->assertSame(20000, $row['payout_cents']);
        $this->assertSame(3111266, $row['balance_cents']);
        $this->assertCount(2, $row['entries']);
        foreach ([$this->scope->id, $duplicate] as $association) {
            $this->postRecord($this->data(['event_region_id' => $association, 'amount' => '31312.66']))->assertSessionHasErrors('event_region_id');
        }
        $this->assertDatabaseCount('regional_clothing_settlements', 2);
        $page = $this->get(route('admin.events.finances', $this->event))->assertOk()->assertSee('Ambiguous event associations');
        $page->assertDontSee('name="event_region_id"', false);
    }

    public function test_duplicate_source_region_cannot_fund_an_offset_and_same_physical_region_cannot_offset_itself(): void
    {
        [$source] = $this->region($this->event, 'QA Duplicate Source');
        $duplicateSource = DB::table('event_regions')->insertGetId(['event_id' => $this->event->id, 'region_id' => $source->region_id, 'ordering' => 2]);
        $this->refund();
        $this->postRecord($this->data())->assertSessionHasNoErrors();
        $data = $this->data(['kind' => 'offset', 'amount' => '395.00', 'occurred_at' => '2026-10-20 12:00:00', 'offset_agreement_confirmed' => 1]);
        foreach ([$source->id, $duplicateSource] as $association) {
            $this->postRecord($data + ['source_event_region_id' => $association])->assertSessionHasErrors('source_event_region_id');
        }
        $report = $this->report();
        $this->assertCount(2, $report);
        $this->assertSame(40000, $report[$source->id]['net_cents']);
        $this->assertFalse($report[$source->id]['recording_available']);
        $duplicateTarget = DB::table('event_regions')->insertGetId(['event_id' => $this->event->id, 'region_id' => $this->scope->region_id, 'ordering' => 2]);
        $this->postRecord($data + ['source_event_region_id' => $duplicateTarget])->assertSessionHasErrors('event_region_id');
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
