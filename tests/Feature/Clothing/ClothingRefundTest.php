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

    public function test_refund_mutations_reject_managers_when_no_deadline_is_set(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture();
        foreach ([$payer, User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'))] as $actor) {
            DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
            $this->actingAs($actor)->get(route('backend.clothing.refunds.show', [$event, $order]))->assertOk();
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

    public function test_scoped_manager_can_refund_before_deadline_but_not_at_or_after_or_without_deadline(): void
    {
        [$super, , $event, $order, $region] = $this->fixture();
        $manager = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $manager->id]);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-15 17:00', 'Africa/Johannesburg'));
        $deadlineUrl = route('backend.clothing.deadline', [$event, $region]);
        $this->actingAs($super)->post($deadlineUrl, ['clothing_deadline_at' => '2026-10-15T18:00'])->assertSessionHasNoErrors();
        $scope = \App\Models\EventRegion::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('2026-10-15 18:00', $scope->clothing_deadline_at->timezone('Africa/Johannesburg')->format('Y-m-d H:i'));
        $this->actingAs($manager)->post($deadlineUrl, ['clothing_deadline_at' => '2026-10-16T18:00'])->assertForbidden();
        $this->patchJson(route('eventRegion.update', $scope->id), ['region_name' => $region->region_name, 'clothing_deadline_at' => '2026-10-16T18:00'])->assertOk();
        $this->assertSame('2026-10-15 18:00', $scope->fresh()->clothing_deadline_at->format('Y-m-d H:i'));
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->post($url, $this->request($order))->assertSessionHasNoErrors();
        $this->travelTo($scope->clothing_deadline_at);
        $this->post($url, $this->request($order))->assertForbidden();
        $this->get(route('backend.clothing.refunds.show', [$event, $order]))->assertOk()->assertSee('Only a super-user can refund');
        DB::table('event_regions')->where('id', $scope->id)->update(['clothing_deadline_at' => null]);
        $this->post($url, $this->request($order))->assertForbidden();
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_late_refund_requires_note_and_records_payout_reconciliation_without_payout_mutation(): void
    {
        [$super, $payer, $event, $order] = $this->fixture();
        DB::table('event_regions')->where('event_id', $event->id)->update(['clothing_deadline_at' => now()->subMinute()]);
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->actingAs($super)->post($url, $this->request($order))->assertSessionHasErrors('late_refund_note');
        $this->assertDatabaseCount('clothing_refunds', 0);
        $input = $this->request($order) + ['late_refund_note' => 'Supplier already paid: reconcile clothing payout'];
        $this->post($url, $input)->assertSessionHasNoErrors();
        $this->post($url, $input)->assertSessionHasNoErrors();
        $refund = ClothingRefund::firstOrFail();
        $this->assertSame('open', $refund->reconciliation_status);
        $this->assertSame($input['late_refund_note'], $refund->late_refund_note);
        $this->assertNotNull($refund->clothing_deadline_at);
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $resolve = route('backend.clothing.refunds.reconcile', [$event, $order, $refund]);
        $this->actingAs($payer)->post($resolve, ['reconciliation_note' => 'Not authorized'])->assertForbidden();
        $this->actingAs($super)->post($resolve, ['reconciliation_note' => 'Supplier credit note CN123 recorded'])->assertSessionHasNoErrors();
        $this->post($resolve, ['reconciliation_note' => 'Different note'])->assertSessionHasNoErrors();
        $this->assertSame('Supplier credit note CN123 recorded', $refund->fresh()->reconciliation_note);
        $this->assertSame('resolved', $refund->fresh()->reconciliation_status);
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_orders_group_teams_show_full_names_and_sort_by_team_position(): void
    {
        [$super, , $event, $order, $region] = $this->fixture();
        $order->player->update(['name' => 'Zulu', 'surname' => 'First Ranked']);
        \App\Models\TeamPlayer::create(['team_id' => $order->team_id, 'player_id' => $order->player_id, 'rank' => 1]);
        $player = Player::factory()->create(['name' => 'Alpha', 'surname' => 'Second Ranked']);
        $second = $order->replicate();
        $second->player_id = $player->id;
        $second->request_token = (string) str()->uuid();
        $second->payfast_pf_payment_id = 'another-payment';
        $second->save();
        $item = $order->items->first()->replicate();
        $item->clothing_order_id = $second->id;
        $item->save();
        \App\Models\TeamPlayer::create(['team_id' => $order->team_id, 'player_id' => $player->id, 'rank' => 2]);
        $url = route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $event->id]);
        $this->actingAs($super)->get($url)->assertOk()->assertSee('data-clothing-team-group', false)->assertSeeInOrder(['Zulu First Ranked', 'Alpha Second Ranked']);
        $this->get($url.'&sort=name')->assertOk()->assertSeeInOrder(['Alpha Second Ranked', 'Zulu First Ranked']);
    }

    public function test_management_of_the_same_region_in_another_event_does_not_grant_refund_access(): void
    {
        [$super, , $event, $order, $region] = $this->fixture();
        $actor = User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'));
        $other = Event::factory()->create();
        DB::table('event_regions')->insert(['event_id' => $other->id, 'region_id' => $region->id, 'ordering' => 1, 'clothing_deadline_at' => now()->addDay()]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $actor->id]);
        DB::table('event_regions')->where('event_id', $event->id)->update(['clothing_deadline_at' => now()->addDay()]);
        $this->actingAs($actor)->post(route('backend.clothing.refunds.store', [$event, $order]), $this->request($order))->assertForbidden();
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
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
