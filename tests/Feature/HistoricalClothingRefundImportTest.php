<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Finance\Services\RegionalClothingSettlementService;
use App\Models\CategoryEvent;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\ClothingRefundHistoricalImport;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistoricalClothingRefundImportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $importer;
    private User $sourceActor;
    private ClothingOrder $order;
    private array $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 12:00:00'));
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        $this->mock(\App\Services\Payfast::class)->shouldNotReceive('refundUsingAvailableMethod');
        $this->mock(\App\Domain\Refunds\Services\RefundExecutionService::class)->shouldNotReceive('executeBankRefund')->shouldNotReceive('executeWalletRefund');
        Role::findOrCreate('super-user', 'web');
        $this->importer = User::factory()->create()->assignRole('super-user');
        $this->sourceActor = User::factory()->create(['name' => 'QA Original Source User', 'email' => 'qa-source-user@example.invalid']);
        $this->event = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'QA Historical West', 'clothing_order' => false]);
        DB::table('event_regions')->insert(['event_id' => $this->event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $this->event->id])->id, 'region_id' => $region->id]);
        $payer = User::factory()->create();
        $player = Player::factory()->create();
        $this->order = ClothingOrder::create([
            'event_id' => $this->event->id, 'team_id' => $team->id, 'player_id' => $player->id, 'user_id' => $payer->id,
            'pay_status' => 1, 'status' => 'completed', 'total' => '1965.00', 'amount_paid' => '1965.00',
            'subtotal' => '1884.13', 'payfast_fee' => '80.87', 'payfast_paid' => true,
            'payfast_amount_due' => '1965.00', 'payfast_pf_payment_id' => 'QA-PF-ORIGINAL', 'pf_id' => 'QA-PF-ORIGINAL',
            'paid_at' => '2026-09-17 20:11:16', 'wallet_reserved' => 0, 'wallet_debited' => false,
        ]);
        $catalogue = \App\Models\ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => 'QA Shirt', 'price' => 395]);
        $size = \App\Models\ClothingSize::create(['item_type' => $catalogue->id, 'size' => '13-14', 'ordering' => 1]);
        $line = $this->order->items()->create(['clothing_order_item_id' => $catalogue->id, 'clothing_item_size' => $size->id,
            'qty' => 2, 'price' => '395.00', 'line_total' => '790.00', 'item_name' => 'QA Shirt', 'size_name' => '13-14']);
        $this->snapshot = [
            'source_system' => 'cape-tennis-live', 'source_refund_id' => 12345,
            'source_url' => 'https://example.invalid/backend/clothing/refund-history', 'source_timezone' => config('app.timezone'),
            'source_request_token' => (string) Str::uuid(), 'source_request_fingerprint' => str_repeat('b', 64),
            'source_updated_at' => '2026-10-15 12:10:39', 'event_id' => $this->event->id, 'event_name' => $this->event->name,
            'order_id' => $this->order->id, 'region_id' => $region->id, 'player_id' => $player->id, 'player_name' => $player->full_name,
            'payer_id' => $payer->id, 'payer_email' => $payer->email, 'original_pf_payment_id' => 'QA-PF-ORIGINAL',
            'paid_gross' => '1965.00', 'payfast_fee' => '80.87', 'payfast_amount_due' => '1965.00', 'paid_at' => '2026-09-17 20:11:16',
            'refund_method' => 'payfast', 'refund_status' => 'completed', 'refund_gross' => '395.00', 'refund_fee' => '0.00', 'refund_net' => '395.00',
            'reason' => 'Synthetic shirt not received', 'external_reference' => null,
            'requested_at' => '2026-10-15 12:10:34', 'refunded_at' => '2026-10-15 12:10:39',
            'requested_actor' => ['source_user_id' => 9988, 'name' => 'QA Original Source User', 'email' => $this->sourceActor->email],
            'completed_actor' => null, 'clothing_deadline_at' => null, 'late_refund_note' => null,
            'reconciliation_status' => null, 'reconciliation_note' => null, 'reconciled_at' => null, 'reconciled_by_source_user_id' => null,
            'provider_attempt' => ['source_attempt_id' => 987, 'source_refund_id' => 12345, 'source_order_id' => $this->order->id,
                'status' => 'confirmed', 'pf_payment_id' => 'QA-PF-ORIGINAL', 'amount' => '395.00',
                'requested_by_source_user_id' => 9988, 'confirmed_at' => '2026-10-15 12:10:39'],
            'items' => [['source_order_item_id' => 998877, 'local_order_item_id' => $line->id, 'item_name' => 'QA Shirt',
                'size_name' => '13-14', 'quantity' => 1, 'amount' => '395.00']],
        ];
    }

    private function runImport(?array $snapshot = null, bool $apply = true): array
    {
        return app(FinancialLedgerService::class)->importHistoricalClothingRefund($this->event, $this->importer, $snapshot ?? $this->snapshot, $apply);
    }

    private function reject(array $snapshot, string $field): void
    {
        try { $this->runImport($snapshot); $this->fail('Import must reject invalid historical evidence'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey($field, $exception->errors()); }
    }

    public function test_preview_exact_import_and_replay_preserve_source_actors_timestamps_and_no_money_or_mail_side_effects(): void
    {
        $originalOrder = $this->order->fresh()->toArray();
        $this->assertTrue($this->runImport(apply: false)['preview']);
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('clothing_refund_historical_imports', 0);
        $result = $this->runImport();
        $this->assertTrue($result['created']);
        $refund = $result['refund']->fresh();
        $this->assertSame('395.00', $refund->refund_net);
        $this->assertSame($this->sourceActor->id, $refund->requested_by);
        $this->assertSame('QA Original Source User', $refund->requested_by_name);
        $this->assertNull($refund->completed_by);
        $this->assertNull($refund->completed_by_name);
        $this->assertSame('2026-10-15 12:10:34', $refund->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 12:10:39', $refund->refunded_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-15 12:10:39', $refund->updated_at->format('Y-m-d H:i:s'));
        $import = ClothingRefundHistoricalImport::firstOrFail();
        $this->assertSame($this->importer->id, $import->imported_by);
        $this->assertSame(9988, $import->source_snapshot['requested_actor']['source_user_id']);
        $this->assertNull($import->source_snapshot['completed_actor']);
        $this->assertSame('confirmed', $import->source_snapshot['provider_attempt']['status']);
        $this->assertFalse($this->runImport()['created']);
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('clothing_refund_items', 1);
        $this->assertDatabaseCount('clothing_refund_historical_imports', 1);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertSame($originalOrder, $this->order->fresh()->toArray());
        Mail::assertNothingSent(); Notification::assertNothingSent(); Http::assertNothingSent();
    }

    public function test_completed_actor_is_original_verified_identity_and_unknown_actor_is_never_importer(): void
    {
        $snapshot = $this->snapshot;
        $snapshot['requested_actor'] = null;
        $snapshot['completed_actor'] = ['source_user_id' => 34567, 'name' => 'QA Completing Source User', 'email' => $this->sourceActor->email];
        $refund = $this->runImport($snapshot)['refund'];
        $this->assertSame(0, $refund->requested_by);
        $this->assertNull($refund->requested_by_name);
        $this->assertSame($this->sourceActor->id, $refund->completed_by);
        $this->assertSame('QA Completing Source User', $refund->completed_by_name);
        $this->assertNotSame($this->importer->id, $refund->completed_by);
    }

    public function test_event_order_payer_player_region_and_original_payment_mismatches_are_rejected_atomically(): void
    {
        foreach (['event_id' => 999999, 'event_name' => 'Foreign event', 'order_id' => 999999, 'payer_email' => 'wrong@example.invalid',
            'payer_id' => 999999, 'player_id' => 999999, 'player_name' => 'Foreign Player', 'region_id' => 999999,
            'original_pf_payment_id' => 'Foreign-PF', 'paid_gross' => '1964.99', 'payfast_fee' => '80.88', 'paid_at' => '2026-09-18 12:00:00'] as $field => $value) {
            $snapshot = array_replace($this->snapshot, [$field => $value]);
            if ($field === 'order_id') $snapshot['provider_attempt']['source_order_id'] = $value;
            // Source provider identity is itself verified before local matching.
            $expected = in_array($field, ['event_id', 'event_name'], true) ? 'event_id'
                : ($field === 'original_pf_payment_id' ? 'provider_attempt' : ($field === 'paid_gross' ? 'payfast_amount_due' : 'order_id'));
            $this->reject($snapshot, $expected);
        }
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('clothing_refund_historical_imports', 0);
    }

    public function test_provider_evidence_precision_and_paid_funding_must_match_exactly(): void
    {
        foreach (['status' => 'uncertain', 'source_refund_id' => 987654, 'source_order_id' => 987654, 'pf_payment_id' => 'Foreign-PF', 'amount' => '395.01'] as $field => $value) {
            $snapshot = $this->snapshot;
            $snapshot['provider_attempt'][$field] = $value;
            $this->reject($snapshot, $field === 'status' ? 'provider_attempt.status' : 'provider_attempt');
        }
        $this->reject(array_replace($this->snapshot, ['requested_at' => '2026-10-15']), 'requested_at');
        $this->reject(array_replace($this->snapshot, ['paid_at' => '2026-10-16 12:00:00']), 'refunded_at');
        $this->reject(array_replace($this->snapshot, ['payfast_amount_due' => '300.00']), 'payfast_amount_due');
        $this->assertDatabaseCount('clothing_refunds', 0);
    }

    public function test_item_mapping_precision_quantity_and_existing_allocations_cannot_be_bypassed(): void
    {
        foreach (['local_order_item_id' => 999999, 'item_name' => 'Foreign shirt', 'size_name' => 'Foreign size', 'quantity' => 3] as $field => $value) {
            $snapshot = $this->snapshot; $snapshot['items'][0][$field] = $value;
            $this->reject($snapshot, 'items');
        }
        $snapshot = $this->snapshot; $snapshot['items'][0]['amount'] = '395.001';
        $this->reject($snapshot, 'items.0.amount');
        $prior = FinanceMutationScope::run('refund_state_write', fn () => $this->order->refunds()->create([
            'event_id' => $this->event->id, 'payer_id' => $this->order->user_id, 'requested_by' => $this->importer->id,
            'request_token' => (string) Str::uuid(), 'request_fingerprint' => str_repeat('c', 64), 'refund_method' => 'bank', 'refund_status' => 'pending',
            'refund_gross' => '790.00', 'refund_fee' => 0, 'refund_net' => '790.00', 'reason' => 'Existing pending original item refund',
        ]));
        $prior->items()->create(['clothing_order_item_id' => $this->snapshot['items'][0]['local_order_item_id'], 'quantity' => 2, 'amount' => '790.00']);
        $this->reject($this->snapshot, 'items');
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('clothing_refund_historical_imports', 0);
    }

    public function test_conflicting_source_replay_and_immutable_provenance_do_not_overwrite_financial_history(): void
    {
        $this->runImport();
        $this->reject(array_replace($this->snapshot, ['reason' => 'Conflicting source reason']), 'source_refund_id');
        $record = ClothingRefundHistoricalImport::firstOrFail();
        try { $record->update(['source_refund_id' => 'Other']); $this->fail('Provenance must be immutable'); } catch (ValidationException) {}
        try { $record->delete(); $this->fail('Provenance must not be deleted'); } catch (ValidationException) {}
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('clothing_refund_historical_imports', 1);
    }

    public function test_recorded_payout_and_imported_refund_reconcile_395_plus_32_cent_original_difference(): void
    {
        $scope = DB::table('event_regions')->where('event_id', $this->event->id)->first();
        app(FinancialLedgerService::class)->recordClothingSettlement($this->event, $this->importer, [
            'event_region_id' => $scope->id, 'kind' => 'payout', 'amount' => '1884.45', 'occurred_at' => '2026-10-07',
            'recipient_name' => 'QA Historical Recipient', 'reference' => 'QA-PAID', 'evidence' => 'Synthetic historical payment statement',
            'request_token' => (string) Str::uuid(), 'confirmed' => 1, 'overpayment_confirmed' => 1,
            'overpayment_reason' => 'Original statement paid 32 cents above clothing receipts',
        ]);
        $this->runImport();
        $ledger = app(FinancialLedgerService::class)->buildForEvent($this->event);
        $report = app(RegionalClothingSettlementService::class)->report($this->event, $ledger['paymentRows']->merge($ledger['refundRows']))->first();
        $this->assertSame(-39532, $report['balance_cents']);
        $this->assertSame(32, $report['shortfall_cents']);
        $this->assertTrue($report['refunds']->first()->after_recorded_payout);
        $this->assertSame(0.0, $ledger['totals']['registration_net']);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_local_only_super_user_gate_rejects_ordinary_actor_and_production_environment(): void
    {
        try {
            app(FinancialLedgerService::class)->importHistoricalClothingRefund($this->event, $this->sourceActor, $this->snapshot, true);
            $this->fail('Ordinary user must not import');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        $this->app['env'] = 'production';
        try { $this->runImport(); $this->fail('Production environment must not import'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(403, $exception->getStatusCode()); }
        finally { $this->app['env'] = 'testing'; }
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('clothing_refund_historical_imports', 0);
    }
}
