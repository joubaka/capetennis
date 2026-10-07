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
