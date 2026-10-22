<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\ConvenorPayoutProjection;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Finance\Services\RegionalClothingSettlementService;
use App\Models\CategoryEvent;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventRegion;
use App\Models\EventRegionManager;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConvenorPayoutProjectionTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $actor;
    private User $directorUser;
    private EventConvenor $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 12:00:00'));
        Role::findOrCreate('super-user', 'web');
        $this->actor = User::factory()->create()->assignRole('super-user');
        $this->event = Event::factory()->create();
        $this->directorUser = User::factory()->create(['name' => 'QA Director']);
        $this->director = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $this->directorUser->id, 'role' => 'hoof', 'profit_share_pct' => 0]);
    }

    private function region(string $name, string $gross = '1000.00', ?User $manager = null, bool $assign = true, ?Event $event = null): array
    {
        $event ??= $this->event;
        $manager ??= $this->directorUser;
        $region = TeamRegion::create(['region_name' => $name, 'clothing_order' => false]);
        $scopeId = DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
        if ($assign) EventRegionManager::create(['event_id' => $event->id, 'event_region_id' => $scopeId, 'region_id' => $region->id, 'user_id' => $manager->id]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'region_id' => $region->id]);
        $order = ClothingOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'user_id' => User::factory()->create()->id,
            'pay_status' => 1, 'status' => 'completed', 'total' => $gross, 'amount_paid' => $gross, 'payfast_fee' => 0, 'paid_at' => '2026-09-01 12:00:00']);
        $catalogue = \App\Models\ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => $name.' Shirt', 'price' => $gross]);
        $size = \App\Models\ClothingSize::create(['item_type' => $catalogue->id, 'size' => '13-14', 'ordering' => 1]);
        $order->items()->create(['clothing_order_item_id' => $catalogue->id, 'clothing_item_size' => $size->id,
            'qty' => 2, 'price' => '395.00', 'line_total' => $gross, 'item_name' => $name.' Shirt', 'size_name' => '13-14']);
        return [EventRegion::findOrFail($scopeId), $order];
    }

    private function refund(ClothingOrder $order, string $amount = '395.00', string $status = 'completed', int $quantity = 1): ClothingRefund
    {
        return FinanceMutationScope::run('refund_state_write', function () use ($order, $amount, $status, $quantity) {
            $refund = $order->refunds()->create(['event_id' => $order->event_id, 'payer_id' => $order->user_id, 'requested_by' => $this->actor->id,
                'request_token' => (string) Str::uuid(), 'request_fingerprint' => str_repeat('d', 64), 'refund_method' => 'payfast', 'refund_status' => $status,
                'refund_gross' => $amount, 'refund_fee' => 0, 'refund_net' => $amount, 'reason' => 'Synthetic clothing refund',
                'refunded_at' => $status === 'completed' ? '2026-10-15 12:00:00' : null]);
            $refund->forceFill(['created_at' => '2026-10-10 12:00:00'])->save();
            $refund->items()->create(['clothing_order_item_id' => $order->items()->first()->id, 'quantity' => $quantity, 'amount' => $amount]);
            return $refund;
        });
    }

    private function settlement(EventRegion $scope, string $amount, string $kind = 'payout', array $changes = []): void
    {
        app(FinancialLedgerService::class)->recordClothingSettlement($this->event, $this->actor, array_replace([
            'event_region_id' => $scope->id, 'kind' => $kind, 'amount' => $amount,
            'recipient_name' => 'QA Recipient', 'occurred_at' => $kind === 'payout' ? '2026-10-07 12:00:00' : '2026-10-20 12:00:00',
            'reference' => 'QA-STATEMENT', 'evidence' => 'Synthetic verified payment evidence',
            'request_token' => (string) Str::uuid(), 'confirmed' => 1,
        ], $changes));
    }

    private function recon(?EventConvenor $director = null, float $base = 28597.87): array
    {
        return ['convenor' => $director ?? $this->director, 'total_paid' => 0.0, 'owed_back' => 0.0, 'reimbursed' => 0.0,
            'profit_share_pct' => 0.0, 'profit_share_amount' => 0.0, 'final_payout' => $base];
    }

    private function report(Event $event): Collection
    {
        $ledger = app(FinancialLedgerService::class)->buildForEvent($event);
        return app(RegionalClothingSettlementService::class)->report($event, $ledger['paymentRows']->merge($ledger['refundRows']));
    }

    private function project(?Collection $recon = null, ?Collection $report = null): array
    {
        return app(ConvenorPayoutProjection::class)->build($this->event, $recon ?? collect([$this->recon()]), $report ?? $this->report($this->event));
    }

    private function html(array $projection, Collection $recon, float $profit = 50065.93): string
    {
        return view('backend.event.partials.convenor-payout-breakdown', ['recon' => $recon, 'netProfit' => $profit, 'convenorPayoutProjection' => $projection])->render();
    }

    public function test_exact_user_match_deducts_current_clothing_recovery_and_keeps_different_regional_account_separate(): void
    {
        [$west, $order] = $this->region('QA West');
        $player = \App\Models\Player::factory()->create(['name' => 'Refund', 'surname' => 'Player']);
        $order->update(['player_id' => $player->id]);
        $this->settlement($west, '1000.32', changes: ['overpayment_confirmed' => 1, 'overpayment_reason' => 'Verified original payout exceeded funds by 32 cents']);
        $this->refund($order);
        $otherDirector = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $this->actor->id, 'role' => 'hulp']);
        $otherManager = User::factory()->create(['name' => $this->actor->name]);
        [$otherScope] = $this->region('QA Other Region', '100.00', $otherManager);
        $this->settlement($otherScope, '276.04', changes: ['overpayment_confirmed' => 1, 'overpayment_reason' => 'Verified historical amount exceeded clothing funds']);
        $recon = collect([$this->recon(), $this->recon($otherDirector, 25498.06)]);
        $result = $this->project($recon);
        $card = $result['cards'][$this->director->id];
        $this->assertSame(2859787, $card['registration_cents']);
        $this->assertSame(-39532, $card['clothing_cents']);
        $this->assertSame(2820255, $card['combined_cents']);
        $this->assertSame(2549806, $result['cards'][$otherDirector->id]['combined_cents']);
        $this->assertCount(1, $result['unmatched_regions']);
        $this->assertSame(-17604, $result['unmatched_regions']->first()['balance_cents']);
        $line = $card['regions']->first();
        $this->assertSame(1, $line['refunded_line_count']);
        $this->assertSame(1, $line['recorded_quantity']);
        $this->assertTrue($line['refunds']->first()['after_payout']);
        $this->assertSame('Refund Player', $line['refunds']->first()['player_name']);
        $html = $this->html($result, $recon);
        $this->assertStringContainsString('28,202.55', $html);
        $this->assertStringContainsString('QA West Shirt', $html);
        $this->assertStringContainsString('Refund Player', $html);
        $this->assertStringContainsString('After recorded payout', $html);
        $this->assertStringContainsString('Original payout difference: R 0.32', $html);
        $this->assertStringContainsString('Regional clothing kept separate', $html);
    }

    public function test_multiple_regions_net_current_balances_and_reserve_pending_without_treating_it_as_completed_debt(): void
    {
        [, $positive] = $this->region('QA Positive', '100.00');
        $this->refund($positive, '30.00', 'pending');
        [$negative, $negativeOrder] = $this->region('QA Negative', '200.00');
        $this->settlement($negative, '200.00');
        $this->refund($negativeOrder, '50.00');
        $result = $this->project(collect([$this->recon(base: 100.00)]));
        $card = $result['cards'][$this->director->id];
        $this->assertSame(2000, $card['clothing_cents']);
        $this->assertSame(12000, $card['combined_cents']);
        $this->assertCount(2, $card['regions']);
        $positiveLine = $card['regions']->firstWhere('region_name', 'QA Positive');
        $this->assertSame(7000, $positiveLine['contribution_cents']);
        $this->assertSame(3000, $positiveLine['pending_cents']);
        $this->assertSame(0, $positiveLine['completed_refund_cents']);
        $this->assertSame(0, $positiveLine['refunded_line_count']);
    }

    public function test_actual_recovery_and_offset_are_already_in_balances_and_completed_refund_is_not_deducted_again(): void
    {
        [$west, $order] = $this->region('QA West');
        $this->settlement($west, '1000.32', changes: ['overpayment_confirmed' => 1, 'overpayment_reason' => 'Verified original payout exceeded funds by 32 cents']);
        $this->refund($order);
        $this->settlement($west, '200.01', 'recovery');
        $this->assertSame(-19531, $this->project()['cards'][$this->director->id]['clothing_cents']);
        $second = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $this->actor->id, 'role' => 'hulp']);
        [$source] = $this->region('QA Offset Source', '500.00', $this->actor);
        $this->settlement($west, '195.31', 'offset', ['source_event_region_id' => $source->id,
            'offset_agreement_confirmed' => 1, 'occurred_at' => '2026-10-21 12:00:00']);
        $recon = collect([$this->recon(), $this->recon($second, 1000.00)]);
        $result = $this->project($recon);
        $this->assertSame(2859787, $result['cards'][$this->director->id]['combined_cents']);
        $this->assertSame(0, $result['cards'][$this->director->id]['clothing_cents']);
        $this->assertSame(130469, $result['cards'][$second->id]['combined_cents']);
        $this->assertSame(39500, $result['cards'][$this->director->id]['regions']->first()['completed_refund_cents']);
    }

    public function test_duplicate_directors_and_physical_associations_fail_closed_without_applying_clothing_twice(): void
    {
        [$scope] = $this->region('QA Duplicate', '100.00');
        // The database already enforces unique(event_id, user_id). A repeated
        // incoming reconciliation row must still fail closed before keying cards.
        $result = $this->project(collect([$this->recon(), $this->recon()]));
        $this->assertSame(0, $result['cards']->sum('clothing_cents'));
        $this->assertStringContainsString('multiple event director', $result['unmatched_regions']->first()['reason']);
        DB::table('event_regions')->insert(['event_id' => $this->event->id, 'region_id' => $scope->region_id, 'ordering' => 2]);
        $result = $this->project();
        $this->assertSame(0, $result['cards']->sum('clothing_cents'));
        $this->assertCount(1, $result['unmatched_regions']);
        $this->assertStringContainsString('association', $result['unmatched_regions']->first()['reason']);
    }

    public function test_cross_event_reports_and_inconsistent_assignment_tuples_cannot_supply_director_clothing(): void
    {
        [$scope] = $this->region('QA Local', '100.00');
        $foreign = Event::factory()->create();
        $this->region('QA Foreign', '999.99', event: $foreign);
        $assignment = EventRegionManager::where('event_region_id', $scope->id)->firstOrFail();
        $assignment->update(['event_id' => $foreign->id]);
        $result = $this->project(report: $this->report($this->event)->merge($this->report($foreign)));
        $this->assertSame(0, $result['cards'][$this->director->id]['clothing_cents']);
        $this->assertCount(1, $result['unmatched_regions']);
        $this->assertStringContainsString('assignment', $result['unmatched_regions']->first()['reason']);
        $this->assertSame('QA Local', $result['unmatched_regions']->first()['region_name']);
    }

    public function test_canonical_default_manager_is_used_only_when_unambiguous_and_pending_unmatched_money_is_reserved(): void
    {
        [, $order] = $this->region('QA Fallback', '100.00', assign: false);
        $this->refund($order, '30.00', 'pending');
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $this->directorUser->id]);
        EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $this->actor->id, 'role' => 'score-keeper']);
        $this->assertSame(7000, $this->project()['cards'][$this->director->id]['clothing_cents']);
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $this->actor->id]);
        $result = $this->project();
        $this->assertSame(0, $result['cards'][$this->director->id]['clothing_cents']);
        $this->assertSame(7000, $result['unmatched_regions']->first()['projected_cents']);
        $html = $this->html($result, collect([$this->recon()]));
        $this->assertStringContainsString('70.00 available to pay after pending refunds', $html);
        $this->assertStringContainsString('Pending refund liability: R 30.00', $html);
    }

    public function test_partial_amount_lines_and_negative_combined_balance_are_labelled_without_inventing_item_units(): void
    {
        [$scope, $order] = $this->region('QA Partial');
        $this->settlement($scope, '1000.00');
        $this->refund($order, '395.00', quantity: 0);
        $recon = collect([$this->recon(base: 100.00)]);
        $result = $this->project($recon);
        $line = $result['cards'][$this->director->id]['regions']->first();
        $this->assertSame(1, $line['refunded_line_count']);
        $this->assertSame(0, $line['recorded_quantity']);
        $this->assertSame(1, $line['partial_amount_lines']);
        $this->assertSame(-29500, $result['cards'][$this->director->id]['combined_cents']);
        $html = $this->html($result, $recon, -100.00);
        $this->assertStringContainsString('Projected amount to recover:', $html);
        $this->assertStringContainsString('295.00', $html);
        $this->assertStringContainsString('Partial amount: QA Partial Shirt', $html);
        $this->assertStringContainsString('no recorded unit quantity', $html);
        $this->assertStringContainsString('Registration loss:', $html);
        $this->assertStringNotContainsString('0 units', $html);
    }

    public function test_projection_does_not_mutate_financial_records_or_registration_values(): void
    {
        [$scope, $order] = $this->region('QA Read Only');
        $this->settlement($scope, '1000.00');
        $this->refund($order);
        $tables = ['regional_clothing_settlements', 'clothing_refunds', 'clothing_refund_items', 'event_payouts', 'wallet_transactions', 'clothing_provider_refund_attempts'];
        $before = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        $recon = collect([$this->recon()]);
        $ledgerBefore = app(FinancialLedgerService::class)->buildForEvent($this->event)['totals'];
        $this->project($recon);
        $this->project($recon);
        foreach ($before as $table => $snapshot) $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
        $this->assertSame(28597.87, $recon->first()['final_payout']);
        $this->assertSame($ledgerBefore, app(FinancialLedgerService::class)->buildForEvent($this->event)['totals']);
    }
}
