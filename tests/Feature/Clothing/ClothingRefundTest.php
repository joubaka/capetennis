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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClothingRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_routes_reject_regular_payers_and_event_managers(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture();
        foreach ([$payer, User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'))] as $actor) {
            DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
            $this->actingAs($actor)->get(route('backend.clothing.refunds.show', [$event, $order]))->assertForbidden();
            $this->actingAs($actor)->post(route('backend.clothing.refunds.store', [$event, $order]), $this->request($order))->assertForbidden();
        }
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_cross_event_route_is_rejected_before_any_refund_is_created(): void
    {
        [$admin, , , $order] = $this->fixture();
        $other = Event::factory()->create();
        $this->actingAs($admin)->post(route('backend.clothing.refunds.store', [$other, $order]), $this->request($order))->assertNotFound();
        $this->assertDatabaseCount('clothing_refunds', 0);
    }

    public function test_super_user_can_open_refund_forms_from_paid_orders(): void
    {
        [$admin, , $event, $order, $region] = $this->fixture();
        $this->actingAs($admin)->get(route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $event->id]))
            ->assertOk()->assertSee('Refund')->assertSee('Partial rand amount')->assertSee('Full remaining item amount');
        $this->get(route('backend.clothing.refunds.show', [$event, $order]))->assertOk()->assertSee('Refund history');
    }

    public function test_partial_amount_then_quantity_then_full_refund_are_exact_and_preserve_paid_order(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture();
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $request = $this->request($order) + ['mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '25.01'];
        $this->actingAs($admin)->post($url, $request)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, $request)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(2501, ClothingRefundService::cents($payer->fresh()->wallet->transactions->first()->amount));
        $this->post($url, $this->request($order) + ['mode' => 'quantity'])->assertSessionHasNoErrors();
        $this->post($url, $this->request($order) + ['mode' => 'full', 'item_id' => $order->items->first()->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clothing_refunds', 3);
        $this->assertDatabaseCount('clothing_refund_items', 3);
        $this->assertDatabaseCount('wallet_transactions', 3);
        $this->assertEquals(330, $payer->fresh()->wallet->balance);
        $this->assertSame(0, $order->items->first()->fresh()->remaining_quantity);
        $this->assertSame(1, $order->fresh()->pay_status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('330.00', $order->fresh()->amount_paid);
        $this->post($url, $this->request($order))->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    public function test_pending_payfast_amount_reserves_money_and_completion_is_idempotent(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $request = $this->request($order, 'payfast') + ['mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '100.25'];
        $this->actingAs($admin);
        app(\App\Domain\Finance\Services\RefundRequestService::class)->requestClothingRefund($event, $order, $admin, $request);
        $refund = ClothingRefund::firstOrFail();
        $this->assertSame('pending', $refund->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $request['request_token'] = (string) str()->uuid();
        $request['amount'] = '229.76';
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), $request)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 1);
        $url = route('backend.clothing.refunds.complete', [$event, $order, $refund]);
        $this->post($url, ['reference' => 'PF-refund-123', 'paid_to_original_payer' => 1])->assertSessionHasNoErrors();
        $this->post($url, ['reference' => 'PF-refund-123', 'paid_to_original_payer' => 1])->assertSessionHasNoErrors();
        $this->assertSame('completed', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('clothing_refunds', 1);
    }

    public function test_foreign_item_and_changed_replay_are_rejected_without_extra_records(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        [, , , $other] = $this->fixture();
        $request = $this->request($order) + ['mode' => 'amount', 'item_id' => $other->items->first()->id, 'amount' => '10.00'];
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->actingAs($admin)->post($url, $request)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 0);
        $request['item_id'] = $order->items->first()->id;
        $this->post($url, $request)->assertSessionHasNoErrors();
        $request['amount'] = '11.00';
        $this->post($url, $request)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_hybrid_payfast_cap_and_unreconciled_payment_are_rejected(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $order->update(['payfast_amount_due' => '20.00']);
        $input = $this->request($order, 'payfast') + ['mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '20.01'];
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->actingAs($admin)->post($url, $input)->assertSessionHasErrors('refund');
        $order->update(['amount_paid' => '329.99']);
        $this->post($url, $this->request($order))->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_uneven_quantity_prices_preserve_the_final_cent(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture();
        $order->update(['total' => '100.00', 'amount_paid' => '100.00', 'payfast_amount_due' => '100.00']);
        $order->items->first()->update(['price' => '33.33', 'line_total' => '100.00']);
        $this->actingAs($admin);
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('backend.clothing.refunds.store', [$event, $order]), $this->request($order))->assertSessionHasNoErrors();
        }
        $this->assertSame(['33.33', '33.33', '33.34'], ClothingRefund::orderBy('id')->pluck('refund_net')->all());
        $this->assertEquals(100, $payer->fresh()->wallet->balance);
        $this->assertDatabaseCount('wallet_transactions', 3);
    }

    private function request(ClothingOrder $order, string $method = 'wallet', int $quantity = 1): array
    {
        return ['request_token' => (string) str()->uuid(), 'method' => $method, 'reason' => 'Returned clothing', 'items' => [$order->items->first()->id => $quantity]];
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
