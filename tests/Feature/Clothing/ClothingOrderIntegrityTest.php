<?php

namespace Tests\Feature\Clothing;

use App\Models\CategoryEvent;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\Player;
use App\Models\SiteSetting;
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
        $this->assertSame('740.00', $order->subtotal);
        $this->assertSame('29.28', $order->payfast_fee);
        $this->assertSame('769.28', $order->total);
        $this->assertSame('769.28', $order->payfast_amount_due);
        $line = $order->items()->firstOrFail();
        $this->assertSame('West Coast Shirt', $line->item_name);
        $this->assertSame('11-12', $line->size_name);
        $this->assertSame(2, (int) $line->qty);
        $this->assertEquals(370.00, (float) $line->price);
        $this->assertEquals(740.00, (float) $line->line_total);

        $same = $service->create(
            $data['user'], $data['event'], $data['region'], $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 2]], $token
        );
        $this->assertSame($order->id, $same->id);
        $this->assertSame(1, ClothingOrder::where('request_token', $token)->count());
    }

    public function test_order_rejects_closed_catalogue_and_wrong_item_size_pair_but_allows_unlinked_user(): void
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
        $unlinkedOrder = $service->create(
            $stranger, $data['event'], $data['region']->fresh(), $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], (string) str()->uuid()
        );
        $this->assertSame($stranger->id, $unlinkedOrder->user_id);
        $this->assertSame($data['player']->id, $unlinkedOrder->player_id);

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

    public function test_published_roster_shows_a_labelled_clothing_action_when_ordering_is_open(): void
    {
        $data = $this->orderContext();
        $data['team']->update(['published' => 1]);

        $html = view('frontend.event.partials.profile-team', [
            'team' => $data['team']->fresh()->load('teamPlayers.player.users'),
            'region' => $data['region']->fresh(),
            'event' => $data['event']->fresh(),
        ])->render();

        $this->assertStringContainsString('Order clothing', $html);
        $this->assertStringContainsString('class="btn btn-sm btn-outline-secondary clothing-order"', $html);
    }

    public function test_unpaid_roster_player_can_order_clothing_when_ordering_is_open(): void
    {
        $data = $this->orderContext();
        $data['team']->update(['published' => 1]);
        $data['team']->teamPlayers()->update(['pay_status' => 0]);

        $html = view('frontend.event.partials.profile-team', [
            'team' => $data['team']->fresh()->load('teamPlayers.player.users'),
            'region' => $data['region']->fresh(),
            'event' => $data['event']->fresh(),
        ])->render();

        $this->assertStringContainsString('team-registration-button', $html);
        $this->assertStringContainsString('Register', $html);
        $this->assertStringContainsString('Order clothing', $html);
        $this->assertStringContainsString('class="btn btn-sm btn-outline-secondary clothing-order"', $html);
    }

    public function test_clothing_modal_has_a_server_generated_request_token_and_submit_fallback(): void
    {
        $html = view('frontend.event.partials._clothing_order_modal')->render();

        $this->assertMatchesRegularExpression(
            '/name="request_token"\s+id="clothing_request_token"\s+value="[0-9a-f-]{36}"/i',
            $html
        );
        $this->assertStringContainsString("form.addEventListener('submit'", $html);
        $this->assertStringContainsString("modal.addEventListener('show.bs.modal'", $html);
    }

    public function test_order_form_lists_the_inclusive_customer_unit_price(): void
    {
        $data = $this->orderContext();

        $this->actingAs($data['user'])
            ->post(route('get.region.clothing.items'), ['region' => $data['region']->id])
            ->assertOk()
            ->assertSee('R385.78')
            ->assertSee('data-price="370.00"', false);
    }

    public function test_customer_checkout_never_displays_the_internal_payment_fee_breakdown(): void
    {
        $data = $this->orderContext();
        $order = app(ClothingOrderService::class)->create(
            $data['user'], $data['event'], $data['region'], $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], (string) str()->uuid()
        )->load('items.order.player');
        $payfast = new class {
            public function getForm(): string
            {
                return '<form id="payfastForm"></form>';
            }
        };
        $viewData = [
            'items' => $order->items,
            'payfast' => $payfast,
            'order' => $order,
            'total' => (float) $order->total,
            'subtotal' => (float) $order->subtotal,
            'payfastFee' => (float) $order->payfast_fee,
        ];

        $customerHtml = view('frontend.clothing.cart-clothing', $viewData)->render();
        $this->assertStringNotContainsString('PayFast fee', $customerHtml);
        $this->assertStringNotContainsString('Clothing subtotal', $customerHtml);
        $this->assertStringContainsString('Total payable', $customerHtml);
        $this->assertStringContainsString('R'.number_format((float) $order->total, 2), $customerHtml);
        $this->assertStringContainsString('table-responsive d-none d-md-block', $customerHtml);
        $this->assertStringContainsString('class="d-md-none"', $customerHtml);
        $this->assertStringContainsString('d-grid d-sm-block', $customerHtml);
    }

    public function test_checkout_normalises_an_existing_pending_order_to_its_customer_total(): void
    {
        $data = $this->orderContext();
        $token = (string) str()->uuid();
        $order = app(ClothingOrderService::class)->create(
            $data['user'], $data['event'], $data['region'], $data['team'], $data['player'],
            [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]], $token
        );
        $order->items()->update(['price' => 354.22, 'line_total' => 354.22]);

        $this->actingAs($data['user'])->post(route('clothingOrder.store'), [
            'player_id' => $data['player']->id,
            'team_id' => $data['team']->id,
            'event_id' => $data['event']->id,
            'region_id' => $data['region']->id,
            'request_token' => $token,
            'items' => [$data['item']->id => ['size' => $data['size']->id, 'qty' => 1]],
        ])->assertOk()
            ->assertSee('R385.78')
            ->assertDontSee('R354.22')
            ->assertDontSee('PayFast fee');
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

        $paid = $payments->finalizePayfast($order->id, 'PF-CLOTHING-1', 385.78);
        $this->assertSame(1, (int) $paid->pay_status);
        $this->assertTrue((bool) $paid->payfast_paid);
        $this->assertSame('completed', $paid->status);
        $this->assertSame('PF-CLOTHING-1', $paid->pf_id);
        $this->assertSame('385.78', $paid->amount_paid);

        $again = $payments->finalizePayfast($order->id, 'PF-CLOTHING-1', 385.78);
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
            'amount_gross' => '385.78',
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
        SiteSetting::set('payfast_fee_percentage', 3.2, SiteSetting::GROUP_PAYFAST);
        SiteSetting::set('payfast_fee_flat', 2.00, SiteSetting::GROUP_PAYFAST);
        SiteSetting::set('payfast_vat_rate', 14, SiteSetting::GROUP_PAYFAST);
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
