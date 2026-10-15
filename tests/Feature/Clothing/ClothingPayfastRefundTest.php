<?php

namespace Tests\Feature\Clothing;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEvent;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Services\Clothing\ClothingRefundService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClothingPayfastRefundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_confirmed_payfast_dispatch_is_not_repeated_and_uncertain_dispatch_blocks_retry(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $payfast = $this->mock(\App\Services\Payfast::class);
        $payfast->shouldReceive('refundUsingAvailableMethod')->once()->with($order->payfast_pf_payment_id, '25.01', 'Clothing item refund', [])->andReturn([
            'success' => true, 'data' => ['code' => 200, 'status' => 'success', 'data' => ['response' => true]],
        ]);
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '25.01', 'method' => 'payfast', 'reason' => 'Returned clothing'];
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->actingAs($admin)->post($url, $input)->assertSessionHasNoErrors();
        $this->post($url, $input)->assertSessionHasNoErrors();
        $refund = ClothingRefund::firstOrFail();
        $this->assertSame('completed', $refund->refund_status);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'confirmed', 'amount' => '25.01']);
        $this->post(route('backend.clothing.refunds.complete', [$event, $order, $refund]), ['reference' => 'another-payment', 'paid_to_original_payer' => 1])->assertSessionHasErrors('refund');

        $payfast->shouldReceive('refundUsingAvailableMethod')->once()->andThrow(new \RuntimeException('Connection lost'));
        $input['request_token'] = (string) str()->uuid();
        $input['amount'] = '10.00';
        $this->post($url, $input)->assertSessionHasErrors('refund');
        $this->post($url, $input)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 2);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['status' => 'uncertain', 'amount' => '10.00']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, $order->fresh()->pay_status);
        $input['request_token'] = (string) str()->uuid();
        $this->post($url, $input)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 2);
        $this->assertDatabaseCount('clothing_refunds', 2);
    }

    public function test_confirmed_provider_attempt_can_finish_locally_without_another_dispatch(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture();
        $this->mock(\App\Services\Payfast::class)->shouldNotReceive('refundUsingAvailableMethod');
        $refund = app(\App\Domain\Finance\Services\RefundRequestService::class)->requestClothingRefund($event, $order, $admin, [
            'request_token' => (string) str()->uuid(), 'mode' => 'full', 'item_id' => $order->items->first()->id, 'method' => 'payfast', 'reason' => 'Returned shirt',
        ]);
        DB::table('clothing_provider_refund_attempts')->insert([
            'clothing_refund_id' => $refund->id, 'clothing_order_id' => $order->id, 'pf_payment_id' => $order->payfast_pf_payment_id,
            'amount' => $refund->refund_net, 'requested_by' => $admin->id, 'status' => 'confirmed', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = route('backend.clothing.refunds.retry', [$event, $order, $refund]);
        $this->actingAs($payer)->post($url)->assertForbidden();
        $otherEvent = Event::factory()->create();
        $this->actingAs($admin)->post(route('backend.clothing.refunds.retry', [$otherEvent, $order, $refund]))->assertNotFound();
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->actingAs($admin)->post($url)->assertSessionHasNoErrors();
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertSame('completed', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_malformed_provider_success_is_uncertain_and_blocks_manual_completion(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $this->mock(\App\Services\Payfast::class)->shouldReceive('refundUsingAvailableMethod')->once()->andReturn(['success' => true, 'data' => []]);
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.01', 'method' => 'payfast', 'reason' => 'Returned clothing'];
        $this->actingAs($admin)->post(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('refund');
        $refund = ClothingRefund::firstOrFail();
        $this->assertSame('pending', $refund->refund_status);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
        $this->post(route('backend.clothing.refunds.complete', [$event, $order, $refund]), ['reference' => 'manual-123', 'paid_to_original_payer' => 1])->assertSessionHasErrors('refund');
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_bank_details_are_forwarded_ephemerally_and_never_flashed_after_provider_error(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $bank = ['account_holder' => 'Original Payer', 'bank_name' => 'FNB', 'branch_code' => '250655', 'account_number' => '123456789', 'account_type' => 'current'];
        $this->mock(\App\Services\Payfast::class)->shouldReceive('refundUsingAvailableMethod')->once()->with($order->payfast_pf_payment_id, '10.01', 'Clothing item refund', $bank)->andReturn(['success' => false]);
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.01', 'method' => 'payfast', 'reason' => 'Returned clothing', 'bank_details' => $bank + ['original_payer' => 1]];
        $this->actingAs($admin)->post(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('refund');
        $this->assertNull(session()->getOldInput('bank_details'));
        $input['bank_details']['account_number'] = 'invalid-bank-account';
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('account_number');
        $this->assertNull(session()->getOldInput('bank_details'));
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertStringNotContainsString('123456789', json_encode(DB::table('clothing_provider_refund_attempts')->get()));
        $this->assertStringNotContainsString('123456789', json_encode(DB::table('activity_log')->get()));
    }

    public function test_late_pending_provider_refund_cannot_dispatch_without_note_and_manager_cannot_recover_after_cutoff(): void
    {
        [$super, , $event, $order] = $this->fixture();
        $manager = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $manager->id]);
        DB::table('event_regions')->where('event_id', $event->id)->update(['clothing_deadline_at' => now()->addMinute()]);
        $refund = app(\App\Domain\Finance\Services\RefundRequestService::class)->requestClothingRefund($event, $order, $manager, [
            'request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.01', 'method' => 'payfast', 'reason' => 'Returned clothing',
        ]);
        $this->travel(2)->minutes();
        $payfast = $this->mock(\App\Services\Payfast::class);
        $payfast->shouldReceive('refundUsingAvailableMethod')->once()->andReturn(['success' => true, 'data' => ['code' => 200, 'status' => 'success', 'data' => ['response' => true]]]);
        $url = route('backend.clothing.refunds.retry', [$event, $order, $refund]);
        $this->actingAs($manager)->post($url)->assertForbidden();
        $this->actingAs($super)->post($url)->assertSessionHasErrors('late_refund_note');
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 0);
        $this->post($url, ['late_refund_note' => 'Reconcile regional clothing payout'])->assertSessionHasNoErrors();
        $this->assertSame('completed', $refund->fresh()->refund_status);
        $this->assertSame('open', $refund->fresh()->reconciliation_status);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function fixture(int $quantity = 3): array
    {
        $admin = User::factory()->create()->assignRole(Role::findOrCreate('super-user', 'web'));
        $payer = User::factory()->create();
        $event = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Clothing refund region']);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id]);
        $player = Player::factory()->create();
        $catalogue = ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => 'Refund shirt', 'price' => 100]);
        $size = ClothingSize::create(['item_type' => $catalogue->id, 'size' => 'Medium']);
        $order = ClothingOrder::create([
            'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id, 'user_id' => $payer->id,
            'pay_status' => 1, 'status' => 'completed', 'subtotal' => $quantity * 100,
            'payfast_fee' => $quantity * 10, 'total' => $quantity * 110, 'amount_paid' => $quantity * 110,
            'payfast_paid' => true, 'payfast_pf_payment_id' => 'clothing-'.$payer->id,
            'payfast_amount_due' => $quantity * 110, 'paid_at' => now(), 'request_token' => (string) str()->uuid(),
        ]);
        $order->items()->create([
            'clothing_order_item_id' => $catalogue->id, 'clothing_item_size' => $size->id,
            'qty' => $quantity, 'price' => 110, 'line_total' => $quantity * 110, 'item_name' => 'Refund shirt', 'size_name' => 'Medium',
        ]);

        return [$admin, $payer, $event, $order->load('items'), $region];
    }
}
