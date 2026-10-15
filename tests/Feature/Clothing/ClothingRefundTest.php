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
    use \Tests\Concerns\ReviewsClothingRefunds;
    use RefreshDatabase;

    private function multiItemFixture(): array
    {
        [$admin, $payer, $event, $order, $region] = $this->fixture(2);
        $payer->update(['first_name' => 'QA Original', 'last_name' => 'Payer', 'email' => 'qa-payer@example.invalid']);
        $order->items->first()->update(['qty' => 2, 'price' => '395.00', 'line_total' => '790.00', 'item_name' => 'QA Tournament Shirt', 'size_name' => 'Medium']);
        $order->items()->create(['clothing_order_item_id' => $order->items->first()->clothing_order_item_id,
            'clothing_item_size' => $order->items->first()->clothing_item_size, 'qty' => 1, 'price' => '250.50',
            'line_total' => '250.50', 'item_name' => 'QA Cap', 'size_name' => 'One size']);
        $order->update(['total' => '1040.50', 'amount_paid' => '1040.50', 'payfast_amount_due' => '1040.50']);
        return [$admin, $payer, $event, $order->fresh('items'), $region];
    }

    private function reviewSelection(Event $event, ClothingOrder $order, array $input)
    {
        $response = $this->post(route('backend.clothing.refunds.review', [$event, $order]), $input);
        return $response->assertOk()->assertSessionHasNoErrors();
    }

    private function exportRefundQa(string $name, string $html): void
    {
        if (getenv('CT_REFUND_FLOW_QA') !== '1') return;
        $directory = storage_path('app/qa/clothing-refund-flow');
        if (! is_dir($directory)) mkdir($directory, 0777, true);
        $html = preg_replace('/(<input[^>]+name="(?:_token|review_token|request_token)"[^>]*value=")[^"]*(")/', '$1$2', $html);
        file_put_contents($directory.'/'.$name.'.html', $html);
    }

    public function test_all_purchased_lines_start_unselected_and_review_never_moves_money(): void
    {
        [$admin, , $event, $order] = $this->multiItemFixture();
        $this->actingAs($admin);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $ledger = app(FinancialLedgerService::class)->buildForEvent($event);
        $page = $this->get(route('backend.clothing.refunds.show', [$event, $order]))->assertOk()
            ->assertSee('QA Tournament Shirt')->assertSee('QA Cap')->assertSee('Select all remaining items')
            ->assertSee('value="2" data-refund-quantity disabled', false)->assertDontSee('data-select-refund checked', false)
            ->assertDontSee('Confirm refund');
        $this->exportRefundQa('selection', $page->getContent());
        $review = $this->reviewSelection($event, $order, $this->request($order));
        $review->assertSee('395.00')->assertSee('Partial order refund')->assertSee('QA Cap')->assertSee('Not selected');
        $this->assertSame(39500, $review->viewData('quote')['total_cents']);
        $this->assertSame(0, $review->viewData('quote')['rows'][1]['selected_cents']);
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('clothing_refund_items', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 0);
        $this->assertEquals($ledger, app(FinancialLedgerService::class)->buildForEvent($event));
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_combined_review_and_success_modal_use_exact_selected_lines_and_return_to_filtered_list(): void
    {
        [$admin, $payer, $event, $order, $region] = $this->multiItemFixture();
        $this->actingAs($admin);
        $input = $this->request($order, 'wallet', 2);
        $input['items'][$order->items->last()->id] = 1;
        $input += ['return_sort' => 'name', 'return_search' => 'QA', 'return_team' => $order->team_id];
        $review = $this->reviewSelection($event, $order, $input)->assertSee('Full original order refund')->assertSee('1,040.50');
        $this->exportRefundQa('review', $review->getContent());
        $this->assertSame(104050, $review->viewData('quote')['total_cents']);
        $token = $review->viewData('reviewToken');
        $response = $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $token])->assertSessionHasNoErrors();
        $response->assertRedirect(route('backend.region.clothing.orders', ['region' => $region->id, 'event_id' => $event->id, 'sort' => 'name', 'search' => 'QA', 'team' => $order->team_id]));
        $receipt = session('clothing_refund_completed');
        $this->assertSame('1040.50', $receipt['net']);
        $this->assertCount(2, $receipt['items']);
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('clothing_refund_items', 2);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertEquals(1040.50, $payer->fresh()->wallet->balance);
        $list = $this->get($response->headers->get('Location'))->assertOk()->assertSee('Refund completed')->assertSee('1,040.50')->assertSee('QA Cap');
        $this->assertSame(1, substr_count($list->getContent(), 'id="clothing-refund-completed"'));
        $list->assertDontSee('data-refund-toggle')->assertDontSee('data-clothing-refund-selection');
        $this->exportRefundQa('completed', $list->getContent());
        $this->get($response->headers->get('Location'))->assertDontSee('id="clothing-refund-completed"', false);
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $token])->assertSessionHasNoErrors()->assertSessionMissing('clothing_refund_completed');
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_review_requires_selection_and_http_confirmation_cannot_bypass_or_tamper_with_review(): void
    {
        [$admin, , $event, $order] = $this->multiItemFixture();
        $this->actingAs($admin);
        $input = $this->request($order);
        $this->post(route('backend.clothing.refunds.review', [$event, $order]), array_replace($input, ['items' => []]))->assertSessionHasErrors();
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('review_token');
        $review = $this->reviewSelection($event, $order, $input);
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $review->viewData('reviewToken'), 'items' => [$order->items->first()->id => 2]])->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_review_cannot_be_reused_by_another_actor_or_event_and_expires(): void
    {
        [$admin, , $event, $order] = $this->multiItemFixture();
        $this->actingAs($admin);
        $token = $this->reviewSelection($event, $order, $this->request($order))->viewData('reviewToken');
        $otherAdmin = User::factory()->create()->assignRole('super-user');
        $this->actingAs($otherAdmin)->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $token])->assertForbidden();
        $otherEvent = Event::factory()->create();
        $this->actingAs($admin)->post(route('backend.clothing.refunds.store', [$otherEvent, $order]), ['review_token' => $token])->assertNotFound();
        $this->travel(21)->minutes();
        $response = $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $token])->assertSessionHasErrors('refund');
        $response->assertRedirect(route('backend.clothing.refunds.show', [$event, $order]));
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertDatabaseCount('clothing_refunds', 0);
    }

    public function test_intervening_pending_refund_rejects_stale_review_instead_of_silently_reducing_amount(): void
    {
        [$admin, , $event, $order] = $this->multiItemFixture();
        $this->actingAs($admin);
        $input = $this->request($order, 'wallet', 2);
        $review = $this->reviewSelection($event, $order, $input);
        app(\App\Domain\Finance\Services\RefundRequestService::class)->requestClothingRefund($event, $order, $admin,
            $this->request($order, 'payfast') + ['mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.00']);
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $review->viewData('reviewToken')])->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $fresh = $this->reviewSelection($event, $order, $input)->assertSee('780.00');
        $this->assertSame(78000, $fresh->viewData('quote')['total_cents']);
        $input['items'][$order->items->last()->id] = 1;
        $remaining = $this->reviewSelection($event, $order, $input)->assertSee('Full remaining order refund');
        $this->assertSame(103050, $remaining->viewData('quote')['total_cents']);
    }

    public function test_selecting_both_units_refunds_the_full_790_not_half_the_order(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture(2);
        $order->update(['total' => '790.00', 'amount_paid' => '790.00', 'payfast_amount_due' => '790.00']);
        $order->items->first()->update(['price' => '395.00', 'line_total' => '790.00']);
        $this->actingAs($admin);
        $review = $this->reviewSelection($event, $order, $this->request($order, 'wallet', 2))
            ->assertSee('Full original order refund')->assertSee('790.00');
        $this->assertSame(79000, $review->viewData('quote')['total_cents']);
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $review->viewData('reviewToken')])->assertSessionHasNoErrors();
        $this->assertSame('790.00', ClothingRefund::firstOrFail()->refund_net);
        $this->assertEquals(790, $payer->fresh()->wallet->balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_pending_history_change_at_the_same_amount_still_invalidates_review(): void
    {
        [$admin, , $event, $order] = $this->multiItemFixture();
        $pending = app(\App\Domain\Finance\Services\RefundRequestService::class)->requestClothingRefund($event, $order, $admin,
            $this->request($order, 'payfast') + ['mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.00']);
        $this->actingAs($admin);
        $review = $this->reviewSelection($event, $order, $this->request($order));
        DB::table('clothing_refunds')->where('id', $pending->id)->update(['refund_status' => 'completed']);
        $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $review->viewData('reviewToken')])->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_refund_mutations_reject_managers_when_no_deadline_is_set(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture();
        foreach ([$payer, User::factory()->create()->assignRole(Role::findOrCreate('admin', 'web'))] as $actor) {
            DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
            $this->actingAs($actor)->get(route('backend.clothing.refunds.show', [$event, $order]))->assertOk();
            $this->actingAs($actor)->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $this->request($order))->assertForbidden();
        }
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_cross_event_route_is_rejected_before_any_refund_is_created(): void
    {
        [$admin, , , $order] = $this->fixture();
        $other = Event::factory()->create();
        $this->actingAs($admin)->postRefund(route('backend.clothing.refunds.store', [$other, $order]), $this->request($order))->assertNotFound();
        $this->assertDatabaseCount('clothing_refunds', 0);
    }

    public function test_super_user_can_open_refund_forms_from_paid_orders(): void
    {
        [$admin, , $event, $order, $region] = $this->fixture();
        $this->actingAs($admin)->get(route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $event->id]))
            ->assertOk()->assertSee('Refund')->assertSee('Select items to refund')->assertDontSee('data-refund-toggle');
        $this->get(route('backend.clothing.refunds.show', [$event, $order]))->assertOk()->assertSee('Refund history');
    }

    public function test_partial_amount_then_quantity_then_full_refund_are_exact_and_preserve_paid_order(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture();
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $request = $this->request($order) + ['mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '25.01'];
        $this->actingAs($admin)->postRefund($url, $request)->assertSessionHasNoErrors()->assertRedirect();
        $this->postRefund($url, $request)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(2501, ClothingRefundService::cents($payer->fresh()->wallet->transactions->first()->amount));
        $this->postRefund($url, $this->request($order) + ['mode' => 'quantity'])->assertSessionHasNoErrors();
        $this->postRefund($url, $this->request($order) + ['mode' => 'full', 'item_id' => $order->items->first()->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clothing_refunds', 3);
        $this->assertDatabaseCount('clothing_refund_items', 3);
        $this->assertDatabaseCount('wallet_transactions', 3);
        $this->assertEquals(330, $payer->fresh()->wallet->balance);
        $this->assertSame(0, $order->items->first()->fresh()->remaining_quantity);
        $this->assertSame(1, $order->fresh()->pay_status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('330.00', $order->fresh()->amount_paid);
        $this->postRefund($url, $this->request($order))->assertSessionHasErrors('refund');
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
        $this->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $request)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 1);
        $url = route('backend.clothing.refunds.complete', [$event, $order, $refund]);
        $this->postRefund($url, ['reference' => 'PF-refund-123', 'paid_to_original_payer' => 1])->assertSessionHasNoErrors();
        $this->postRefund($url, ['reference' => 'PF-refund-123', 'paid_to_original_payer' => 1])->assertSessionHasNoErrors();
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
        $this->actingAs($admin)->postRefund($url, $request)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 0);
        $request['item_id'] = $order->items->first()->id;
        $this->postRefund($url, $request)->assertSessionHasNoErrors();
        $request['amount'] = '11.00';
        $this->postRefund($url, $request)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_hybrid_payfast_cap_and_unreconciled_payment_are_rejected(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $order->update(['payfast_amount_due' => '20.00']);
        $input = $this->request($order, 'payfast') + ['mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '20.01'];
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->actingAs($admin)->postRefund($url, $input)->assertSessionHasErrors('refund');
        $order->update(['amount_paid' => '329.99']);
        $this->postRefund($url, $this->request($order))->assertSessionHasErrors('refund');
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
            $this->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $this->request($order))->assertSessionHasNoErrors();
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
        $this->actingAs($super)->postRefund($deadlineUrl, ['clothing_deadline_at' => '2026-10-15T18:00'])->assertSessionHasNoErrors();
        $scope = \App\Models\EventRegion::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('2026-10-15 18:00', $scope->clothing_deadline_at->timezone('Africa/Johannesburg')->format('Y-m-d H:i'));
        $this->actingAs($manager)->postRefund($deadlineUrl, ['clothing_deadline_at' => '2026-10-16T18:00'])->assertForbidden();
        $this->patchJson(route('eventRegion.update', $scope->id), ['region_name' => $region->region_name, 'clothing_deadline_at' => '2026-10-16T18:00'])->assertOk();
        $this->assertSame('2026-10-15 18:00', $scope->fresh()->clothing_deadline_at->format('Y-m-d H:i'));
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->postRefund($url, $this->request($order))->assertSessionHasNoErrors();
        $this->travelTo($scope->clothing_deadline_at);
        $this->postRefund($url, $this->request($order))->assertForbidden();
        $this->get(route('backend.clothing.refunds.show', [$event, $order]))->assertOk()->assertSee('Only a super-user can refund');
        DB::table('event_regions')->where('id', $scope->id)->update(['clothing_deadline_at' => null]);
        $this->postRefund($url, $this->request($order))->assertForbidden();
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_late_refund_requires_note_and_records_payout_reconciliation_without_payout_mutation(): void
    {
        [$super, $payer, $event, $order] = $this->fixture();
        DB::table('event_regions')->where('event_id', $event->id)->update(['clothing_deadline_at' => now()->subMinute()]);
        $url = route('backend.clothing.refunds.store', [$event, $order]);
        $this->actingAs($super)->postRefund($url, $this->request($order))->assertSessionHasErrors('late_refund_note');
        $this->assertDatabaseCount('clothing_refunds', 0);
        $input = $this->request($order) + ['late_refund_note' => 'Supplier already paid: reconcile clothing payout'];
        $this->postRefund($url, $input)->assertSessionHasNoErrors();
        $this->postRefund($url, $input)->assertSessionHasNoErrors();
        $refund = ClothingRefund::firstOrFail();
        $this->assertSame('open', $refund->reconciliation_status);
        $this->assertSame($input['late_refund_note'], $refund->late_refund_note);
        $this->assertNotNull($refund->clothing_deadline_at);
        $this->assertDatabaseCount('event_payouts', 0);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $resolve = route('backend.clothing.refunds.reconcile', [$event, $order, $refund]);
        $this->actingAs($payer)->postRefund($resolve, ['reconciliation_note' => 'Not authorized'])->assertForbidden();
        $this->actingAs($super)->postRefund($resolve, ['reconciliation_note' => 'Supplier credit note CN123 recorded'])->assertSessionHasNoErrors();
        $this->postRefund($resolve, ['reconciliation_note' => 'Different note'])->assertSessionHasNoErrors();
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
        $this->actingAs($actor)->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $this->request($order))->assertForbidden();
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
