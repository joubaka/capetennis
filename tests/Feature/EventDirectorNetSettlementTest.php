<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\EventDirectorNetSettlementService;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Finance\Services\RegionalClothingSettlementService;
use App\Models\CategoryEvent;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventPayout;
use App\Models\EventRegion;
use App\Models\EventRegionManager;
use App\Models\RegionalClothingSettlement;
use App\Models\RegistrationManualReceipt;
use App\Models\RegistrationOrder;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventDirectorNetSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $actor;
    private EventConvenor $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 12:00:00'));
        Role::findOrCreate('super-user', 'web');
        $this->actor = User::factory()->create()->assignRole('super-user');
        $this->event = Event::factory()->create(['cape_tennis_fee' => 0]);
        $this->director = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => User::factory()->create()->id, 'role' => 'hoof', 'profit_share_pct' => 100]);
        $order = RegistrationOrder::create(['user_id' => $this->actor->id, 'total_fee' => '28597.87', 'pay_status' => true]);
        RegistrationManualReceipt::create(['order_id' => $order->id, 'event_id' => $this->event->id, 'amount' => '28597.87',
            'method' => 'eft', 'reference' => 'SYNTHETIC-INCOME', 'verified_by_user_id' => $this->actor->id, 'paid_at' => '2026-09-01 12:00:00']);
    }

    private function region(string $name = 'Synthetic Region', string $gross = '1000.00'): array
    {
        $region = TeamRegion::create(['region_name' => $name, 'clothing_order' => false]);
        $scope = EventRegion::findOrFail(DB::table('event_regions')->insertGetId(['event_id' => $this->event->id, 'region_id' => $region->id, 'ordering' => 1]));
        EventRegionManager::create(['event_id' => $this->event->id, 'event_region_id' => $scope->id, 'region_id' => $region->id, 'user_id' => $this->director->user_id]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $this->event->id])->id, 'region_id' => $region->id]);
        $order = ClothingOrder::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'user_id' => User::factory()->create()->id,
            'pay_status' => 1, 'status' => 'completed', 'total' => $gross, 'amount_paid' => $gross, 'payfast_fee' => 0, 'paid_at' => '2026-09-01 12:00:00']);

        return [$scope, $order];
    }

    private function refund(ClothingOrder $order, string $amount = '790.25', string $status = 'completed'): void
    {
        FinanceMutationScope::run('refund_state_write', function () use ($order, $amount, $status) {
            $refund = $order->refunds()->create(['event_id' => $this->event->id, 'payer_id' => $order->user_id, 'requested_by' => $this->actor->id,
                'request_token' => (string) Str::uuid(), 'request_fingerprint' => str_repeat('d', 64), 'refund_method' => 'payfast', 'refund_status' => $status,
                'refund_gross' => $amount, 'refund_fee' => 0, 'refund_net' => $amount, 'reason' => 'Synthetic clothing refund',
                'refunded_at' => $status === 'completed' ? '2026-10-15 12:00:00' : null]);
            $refund->forceFill(['created_at' => '2026-10-10 12:00:00'])->save();
        });
    }

    private function payout(EventRegion $scope, string $amount = '1000.00'): void
    {
        app(FinancialLedgerService::class)->recordClothingSettlement($this->event, $this->actor, [
            'event_region_id' => $scope->id, 'kind' => 'payout', 'amount' => $amount,
            'recipient_user_id' => $this->director->user_id, 'occurred_at' => '2026-10-07 12:00:00',
            'reference' => 'SYNTHETIC-PRIOR-PAYMENT', 'request_token' => (string) Str::uuid(), 'confirmed' => 1,
        ]);
    }

    private function preview(): array
    {
        return app(EventDirectorNetSettlementService::class)->previews($this->event)[$this->director->id];
    }

    private function data(array $changes = []): array
    {
        $preview = $this->preview();

        return array_replace(['payment_mode' => 'full_net', 'convenor_id' => $this->director->id,
            'amount' => number_format($preview['cash_cents'] / 100, 2, '.', ''), 'preview_token' => $preview['preview_token'],
            'idempotency_key' => (string) Str::uuid(), 'settled_at' => '2026-10-20T12:00',
            'reference' => 'SYNTHETIC-NET-PAYMENT', 'settlement_confirmed' => 1], $changes);
    }

    private function postPayment(array $data)
    {
        return $this->actingAs($this->actor)->post(route('admin.events.expense-reimbursements.store', $this->event), $data);
    }

    public function test_full_net_payment_clears_registration_and_clothing_deficit_once_with_correct_cash(): void
    {
        [$scope, $order] = $this->region();
        $this->payout($scope);
        $this->refund($order);
        $preview = $this->preview();
        $this->assertSame(2859787, $preview['registration_cents']);
        $this->assertSame(-79025, $preview['clothing'][0]['contribution_cents']);
        $this->assertSame(2780762, $preview['cash_cents']);
        if (getenv('CT_DIRECTOR_PAYMENT_QA_HTML') === '1') {
            $response = $this->actingAs($this->actor)->get(route('admin.events.finances', $this->event))->assertOk();
            $directory = storage_path('framework/testing');
            if (! is_dir($directory)) mkdir($directory, 0755, true);
            file_put_contents($directory.'/director-payments.html', $response->getContent());
        }
        $data = $this->data();
        $this->postPayment($data)->assertSessionHasNoErrors()->assertRedirect();
        $original = EventPayout::firstOrFail()->toArray();
        $this->postPayment($data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($original, EventPayout::first()->toArray());
        $this->assertDatabaseCount('event_payouts', 1);
        $this->assertDatabaseCount('regional_clothing_settlements', 2);
        $this->assertDatabaseHas('regional_clothing_settlements', ['event_payout_id' => $original['id'], 'kind' => 'recovery', 'amount' => '790.25']);
        $this->assertSame(0, $this->preview()['cash_cents']);
        $ledger = app(FinancialLedgerService::class)->buildForEvent($this->event);
        $this->assertSame(0.0, $ledger['totals']['registration_balance']);
        $this->assertSame(0.0, $ledger['totals']['clothing_balance']);
        $this->assertSame(790.25, $ledger['totals']['clothing_withheld']);
        $this->assertSame(28807.62, $ledger['totals']['combined_paid_out']); // Prior R1,000 + current R27,807.62.
        $this->assertSame(-27807.62, $ledger['payoutRows']->first()->cash_net);
        $this->postPayment(array_replace($data, ['reference' => 'ALTERED']))->assertSessionHasErrors('amount');
        $this->get(route('admin.events.finances', $this->event))->assertOk()->assertSee('Full net settlement')->assertSee('27,807.62')->assertSee('Withheld from director settlement');
    }

    public function test_full_settlement_pays_only_available_clothing_and_preserves_pending_refunds(): void
    {
        [$scope, $order] = $this->region();
        $this->refund($order, '100.01', 'pending');
        $preview = $this->preview();
        $this->assertSame(89999, $preview['clothing'][0]['contribution_cents']);
        $this->assertSame(10001, $preview['clothing'][0]['pending_cents']);
        $this->postPayment($this->data())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('regional_clothing_settlements', ['event_region_id' => $scope->id, 'kind' => 'payout', 'amount' => '899.99']);
        $ledger = app(FinancialLedgerService::class)->buildForEvent($this->event);
        $this->assertSame(29497.86, $ledger['totals']['combined_paid_out']);
        $this->assertSame(100.01, $ledger['totals']['clothing_balance']);
        $this->assertSame(0, $this->preview()['cash_cents']);
    }

    public function test_stale_pending_liability_or_identity_change_cannot_be_settled(): void
    {
        [$scope, $order] = $this->region();
        $data = $this->data();
        $this->refund($order, '100.00', 'pending');
        $this->postPayment($data)->assertSessionHasErrors('amount');
        $fresh = $this->data();
        EventRegionManager::where('event_region_id', $scope->id)->update(['user_id' => User::factory()->create()->id]);
        $this->postPayment($fresh)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('regional_clothing_settlements', 0);
    }

    public function test_clothing_chronology_failure_rolls_back_registration_settlement(): void
    {
        [$scope, $order] = $this->region();
        $this->payout($scope);
        $this->refund($order);
        $this->postPayment($this->data(['settled_at' => '2026-10-08T12:00']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_authorization_exact_cash_confirmation_and_cross_event_replay(): void
    {
        $data = $this->data();
        $url = route('admin.events.expense-reimbursements.store', $this->event);
        $this->post($url, $data)->assertRedirect('login');
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create()->assignRole('admin');
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin)->post($url, $data)->assertForbidden();
        $this->postPayment(array_replace($data, ['amount' => '28597.88']))->assertSessionHasErrors('amount');
        $this->postPayment(array_replace($data, ['amount' => '28597.871']))->assertSessionHasErrors('amount');
        $this->postPayment(array_replace($data, ['settlement_confirmed' => 0]))->assertSessionHasErrors('settlement_confirmed');
        $this->postPayment($data)->assertSessionHasNoErrors();
        $other = Event::factory()->create();
        $foreign = EventConvenor::create(['event_id' => $other->id, 'user_id' => User::factory()->create()->id, 'role' => 'hoof']);
        $this->post(route('admin.events.expense-reimbursements.store', $other), array_replace($data, ['convenor_id' => $foreign->id]))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payouts', 1);
    }

    public function test_linked_clothing_leg_cannot_be_replayed_under_another_token(): void
    {
        [$scope] = $this->region();
        $this->postPayment($this->data())->assertSessionHasNoErrors();
        $payout = EventPayout::firstOrFail();
        try {
            app(FinancialLedgerService::class)->recordClothingSettlement($this->event, $this->actor, [
                'event_region_id' => $scope->id, 'kind' => 'payout', 'amount' => '1000.00',
                'recipient_user_id' => $this->director->user_id, 'occurred_at' => '2026-10-20 12:00:00',
                'reference' => 'SYNTHETIC-NET-PAYMENT', 'request_token' => (string) Str::uuid(), 'confirmed' => 1,
            ], $payout);
            $this->fail('Must reject a mismatched settlement leg token');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('settlement', $exception->errors());
        }
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }

    public function test_ambiguous_clothing_association_blocks_full_settlement(): void
    {
        [$scope] = $this->region();
        $data = $this->data();
        DB::table('event_regions')->insert(['event_id' => $this->event->id, 'region_id' => $scope->region_id, 'ordering' => 2]);
        $this->assertFalse($this->preview()['available']);
        $this->postPayment($data)->assertSessionHasErrors('amount');
        $this->postPayment($this->data())->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('regional_clothing_settlements', 0);
    }

    public function test_deficit_cannot_be_withheld_from_another_regions_payable(): void
    {
        [$deficitScope, $deficitOrder] = $this->region('Synthetic deficit region', '40000.00');
        $this->payout($deficitScope, '40000.00');
        $this->refund($deficitOrder, '30000.00');
        $this->region('Synthetic payable region', '10000.00');
        $preview = $this->preview();
        $this->assertSame(859787, $preview['cash_cents']);
        $this->assertFalse($preview['available']);
        $this->postPayment($this->data())->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('regional_clothing_settlements', 1);
    }
}
