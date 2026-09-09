<?php

namespace Tests\Feature\Clothing;

use App\Models\CategoryEvent;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\User;
use App\Services\Clothing\ClothingOrderService;
use App\Services\Clothing\ClothingPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClothingOrderIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_valid_order_uses_locked_catalogue_values_snapshots_and_idempotency(): void
    {
        $data = $this->orderContext();
        $token = (string) str()->uuid();
        $service = app(ClothingOrderService::class);

        $order = $service->create(
            $data['user'], $data['event'], $data['region'], $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 2]], $token
        );

        $this->assertSame('pending', $order->status);
        $this->assertSame('740.00', $order->total);
        $this->assertSame('740.00', $order->payfast_amount_due);
        $line = $order->items()->firstOrFail();
        $this->assertSame('West Coast Shirt', $line->item_name);
        $this->assertSame('11-12', $line->size_name);
        $this->assertSame(2, (int) $line->qty);
        $this->assertSame('370.00', (string) $line->price);
        $this->assertSame('740.00', (string) $line->line_total);

        $same = $service->create(
            $data['user'], $data['event'], $data['region'], $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 2]], $token
        );
        $this->assertSame($order->id, $same->id);
        $this->assertSame(1, ClothingOrder::where('request_token', $token)->count());
    }

    public function test_order_rejects_closed_catalogue_unowned_player_and_wrong_item_size_pair(): void
    {
        $data = $this->orderContext();
        $service = app(ClothingOrderService::class);
        $data['region']->update(['clothing_order' => false]);
        $this->expectValidationKey('region_id', fn () => $service->create(
            $data['user'], $data['event'], $data['region']->fresh(), $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], (string) str()->uuid()
        ));

        $data['region']->update(['clothing_order' => true]);
        $stranger = User::factory()->create();
        $this->expectValidationKey('player_id', fn () => $service->create(
            $stranger, $data['event'], $data['region']->fresh(), $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], (string) str()->uuid()
        ));

        $otherItem = ClothingItemType::create([
            'item_type_name' => 'Cap', 'price' => 230, 'region_id' => $data['region']->id,
        ]);
        $otherSize = ClothingSize::create(['size' => 'One size', 'item_type' => $otherItem->id]);
        $this->expectValidationKey('items', fn () => $service->create(
            $data['user'], $data['event'], $data['region']->fresh(), $data['team'], $data['player'],
            [$data['item']->id => ['size' => $otherSize->id, 'qty' => 1]], (string) str()->uuid()
        ));
    }

    public function test_order_rejects_region_explicitly_excluded_from_online_clothing(): void
    {
        $data = $this->orderContext();
        $data['region']->update(['clothing_admin' => 0, 'clothing_order' => 1]);
        $token = (string) str()->uuid();

        $this->expectValidationKey('region_id', fn () => app(ClothingOrderService::class)->create(
            $data['user'], $data['event'], $data['region']->fresh(), $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], $token
        ));

        $this->assertDatabaseMissing('clothing_orders', ['request_token' => $token]);
    }

    public function test_payment_requires_exact_amount_and_is_idempotent(): void
    {
        $data = $this->orderContext();
        $order = app(ClothingOrderService::class)->create(
            $data['user'], $data['event'], $data['region'], $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], (string) str()->uuid()
        );
        $payments = app(ClothingPaymentService::class);

        try {
            $payments->finalizePayfast($order->id, 'PF-WRONG', 1.00);
            $this->fail('Expected amount mismatch to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('does not match', $exception->getMessage());
        }
        $this->assertSame(0, (int) $order->fresh()->pay_status);

        $paid = $payments->finalizePayfast($order->id, 'PF-CLOTHING-1', 370.00);
        $this->assertSame(1, (int) $paid->pay_status);
        $this->assertTrue((bool) $paid->payfast_paid);
        $this->assertSame('completed', $paid->status);
        $this->assertSame('PF-CLOTHING-1', $paid->pf_id);
        $this->assertSame('370.00', $paid->amount_paid);

        $again = $payments->finalizePayfast($order->id, 'PF-CLOTHING-1', 370.00);
        $this->assertSame($paid->id, $again->id);
    }

    public function test_clothing_itn_requires_valid_signature_and_exact_amount(): void
    {
        $data = $this->orderContext();
        $order = app(ClothingOrderService::class)->create(
            $data['user'], $data['event'], $data['region'], $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], (string) str()->uuid()
        );
        config([
            'services.payfast.sandbox' => false,
            'services.payfast.merchant_id' => 'live-test-merchant',
            'services.payfast.passphrase_live' => 'clothing-test-passphrase',
            'services.payfast.passphrase_sandbox' => null,
            'services.payfast.passphrase' => null,
        ]);
        $payload = [
            'merchant_id' => 'live-test-merchant',
            'payment_status' => 'COMPLETE',
            'amount_gross' => '370.00',
            'custom_int5' => $order->id,
            'pf_payment_id' => 'PF-ITN-CLOTHING-1',
        ];

        $this->post(route('notify.clothing'), $payload + ['signature' => 'invalid'])->assertStatus(400);
        $this->assertSame(0, (int) $order->fresh()->pay_status);

        $wrongMerchantPayload = array_replace($payload, ['merchant_id' => 'sandbox-test-merchant']);
        $wrongMerchantSignature = md5(http_build_query($wrongMerchantPayload).'&passphrase='.urlencode('clothing-test-passphrase'));
        $this->post(route('notify.clothing'), $wrongMerchantPayload + ['signature' => $wrongMerchantSignature])->assertStatus(400);
        $this->assertSame(0, (int) $order->fresh()->pay_status);

        $signature = md5(http_build_query($payload).'&passphrase='.urlencode('clothing-test-passphrase'));
        $this->post(route('notify.clothing'), $payload + ['signature' => $signature])->assertOk();
        $this->assertSame(1, (int) $order->fresh()->pay_status);
        $this->assertSame('PF-ITN-CLOTHING-1', $order->fresh()->pf_id);
    }

    private function orderContext(): array
    {
        $typeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Clothing team event', 'type' => 2,
            'code' => 'cloth-order-'.substr((string) str()->uuid(), 0, 8), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $event = Event::factory()->create(['eventType' => $typeId]);
        $region = TeamRegion::create(['region_name' => 'West Coast 2026', 'clothing_order' => true]);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $categoryEvent->id]);
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        $item = ClothingItemType::create([
            'item_type_name' => 'West Coast Shirt', 'price' => 370,
            'region_id' => $region->id, 'ordering' => 1,
        ]);
        $size = ClothingSize::create(['size' => '11-12', 'item_type' => $item->id, 'ordering' => 1]);

        return compact('event', 'region', 'team', 'user', 'player', 'item', 'size');
    }

    private function expectValidationKey(string $key, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected validation error for {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }
}
