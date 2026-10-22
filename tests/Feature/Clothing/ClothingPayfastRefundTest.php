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
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClothingPayfastRefundTest extends TestCase
{
    use \Tests\Concerns\ReviewsClothingRefunds;
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        // Provider dispatch requires transaction level zero. These tests rebuild
        // without a wrapping transaction, so the next RefreshDatabase class
        // must rebuild rather than inherit this test's committed fixture rows.
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_bank_preflight_failure_can_retry_the_same_saved_refund_once_with_ephemeral_validated_details(): void
    {
        [$admin, $payer, $event, $order] = $this->fixture(4);
        Http::preventStrayRequests();
        Http::fake([
            'api.payfast.co.za/refunds/query/*' => Http::response([
                'status' => 'REFUNDABLE', 'amount_available_for_refund' => 44000,
                'refund_full' => ['method' => 'PAYMENT_SOURCE'], 'refund_partial' => ['method' => 'BANK_PAYOUT'],
                'bank_names' => [['bank_name' => 'FNB', 'label' => 'FNB']],
            ]),
            'api.payfast.co.za/refunds/*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]]),
        ]);
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id,
            'amount' => '395.00', 'method' => 'payfast', 'reason' => 'Returned clothing'];
        $response = $this->actingAs($admin)->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('refund');
        $refund = ClothingRefund::firstOrFail();
        $attempt = DB::table('clothing_provider_refund_attempts')->first();
        $this->assertSame('not_dispatched', $attempt->status);
        $this->assertSame('pending', $refund->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Retry saved PayFast refund of R395.00');
        $audit = DB::table('activity_log')->where('description', 'Clothing PayFast refund attempt result')->first();
        $this->assertSame('bank_details_required', json_decode($audit->properties, true)['failure_code']);

        $url = route('backend.clothing.refunds.retry', [$event, $order, $refund]);
        $bank = ['account_holder' => 'Original Payer', 'bank_name' => 'FNB', 'branch_code' => '250655',
            'account_number' => '0123456789', 'account_type' => 'current', 'original_payer' => 1];
        $this->actingAs($payer)->post($url, ['bank_details' => $bank])->assertForbidden();
        $otherEvent = Event::factory()->create();
        $this->actingAs($admin)->post(route('backend.clothing.refunds.retry', [$otherEvent, $order, $refund]), ['bank_details' => $bank])->assertNotFound();
        $this->post($url, ['bank_details' => array_replace($bank, ['account_number' => 'invalid'])])->assertSessionHasErrors('account_number');
        $this->assertNull(session()->getOldInput('bank_details'));
        Http::assertSentCount(1);

        // Submitted financial values cannot replace the exact saved refund amount.
        $this->post($url, ['bank_details' => $bank, 'amount' => '999.99'])->assertSessionHasNoErrors();
        $this->post($url)->assertSessionHasNoErrors();
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['amount'] === 39500
            && $request['bank_account_number'] === '0123456789' && $request['bank_account_holder'] === 'Original Payer');
        $this->assertSame('completed', $refund->fresh()->refund_status);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['id' => $attempt->id, 'status' => 'confirmed', 'amount' => '395.00']);
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, $order->fresh()->pay_status);
        $this->assertStringNotContainsString('0123456789', json_encode(DB::table('activity_log')->get()));
        $this->assertStringNotContainsString('0123456789', json_encode(DB::table('clothing_provider_refund_attempts')->get()));
        $this->assertNull(session()->getOldInput('bank_details'));
    }

    public function test_query_failure_can_retry_but_a_lost_post_response_must_never_retry(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        Http::preventStrayRequests();
        $calls = [];
        Http::fake(function ($request) use (&$calls) {
            $calls[] = $request->method();
            if (count($calls) === 1 || $request->method() === 'POST') {
                throw new \RuntimeException('Connection lost; private diagnostic must not be stored');
            }
            return Http::response(['status' => 'REFUNDABLE', 'amount_available_for_refund' => 33000,
                'refund_full' => ['method' => 'PAYMENT_SOURCE'], 'refund_partial' => ['method' => 'PAYMENT_SOURCE']]);
        });
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id,
            'amount' => '25.01', 'method' => 'payfast', 'reason' => 'Returned clothing'];
        $this->actingAs($admin)->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('refund');
        $refund = ClothingRefund::firstOrFail();
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'not_dispatched']);
        $url = route('backend.clothing.refunds.retry', [$event, $order, $refund]);
        $this->post($url)->assertSessionHasErrors('refund');
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
        $this->post($url)->assertSessionHasErrors('refund');
        $this->assertSame(['GET', 'GET', 'POST'], $calls);
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertStringNotContainsString('private diagnostic', json_encode(DB::table('activity_log')->get()));
    }

    public function test_not_dispatched_retry_is_blocked_by_another_uncertain_attempt_on_the_order(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $requests = app(\App\Domain\Finance\Services\RefundRequestService::class);
        foreach (['not_dispatched', 'uncertain'] as $status) {
            $refund = $requests->requestClothingRefund($event, $order, $admin, ['request_token' => (string) str()->uuid(),
                'mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.01', 'method' => 'payfast', 'reason' => 'Returned shirt']);
            DB::table('clothing_provider_refund_attempts')->insert(['clothing_refund_id' => $refund->id,
                'clothing_order_id' => $order->id, 'pf_payment_id' => $order->payfast_pf_payment_id, 'amount' => '10.01',
                'requested_by' => $admin->id, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        }
        Http::preventStrayRequests();
        Http::fake();
        $retryRefund = ClothingRefund::orderBy('id')->firstOrFail();
        $this->actingAs($admin)->post(route('backend.clothing.refunds.retry', [$event, $order, $retryRefund]))->assertSessionHasErrors('refund');
        Http::assertNothingSent();
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $retryRefund->id, 'status' => 'not_dispatched']);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_final_bank_validation_and_uncertain_provider_errors_return_to_a_working_get_without_success_modal(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id,
            'amount' => '10.01', 'method' => 'payfast', 'reason' => 'Returned clothing'];
        $page = $this->actingAs($admin)->post(route('backend.clothing.refunds.review', [$event, $order]), $input)->assertOk()->assertSessionHasNoErrors();
        $token = $page->viewData('reviewToken');
        $bank = ['account_holder' => 'QA Payer', 'bank_name' => 'FNB', 'branch_code' => '250655', 'account_number' => 'invalid', 'account_type' => 'current', 'original_payer' => 1];
        $response = $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $token, 'bank_details' => $bank])
            ->assertSessionHasErrors('account_number')->assertSessionMissing('clothing_refund_completed');
        $response->assertRedirect(route('backend.clothing.refunds.show', [$event, $order]));
        $this->assertNull(session()->getOldInput('bank_details'));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Select items to refund');
        $this->assertDatabaseCount('clothing_refunds', 0);
        $this->mock(\App\Services\Payfast::class)->shouldReceive('refundUsingAvailableMethod')->once()->andThrow(new \RuntimeException('Connection lost'));
        $response = $this->post(route('backend.clothing.refunds.store', [$event, $order]), ['review_token' => $token])
            ->assertSessionHasErrors('refund')->assertSessionMissing('clothing_refund_completed');
        $response->assertRedirect(route('backend.clothing.refunds.show', [$event, $order]));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('needs reconciliation')->assertDontSee('id="clothing-refund-completed"', false);
        $this->assertDatabaseHas('clothing_refunds', ['refund_status' => 'pending', 'refund_net' => '10.01']);
        $this->assertDatabaseCount('wallet_transactions', 0);
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
        $this->actingAs($admin)->postRefund($url, $input)->assertSessionHasNoErrors();
        $this->postRefund($url, $input)->assertSessionHasNoErrors();
        $refund = ClothingRefund::firstOrFail();
        $this->assertSame('completed', $refund->refund_status);
        $this->assertSame($admin->id, $refund->completed_by);
        $this->assertSame($admin->name, $refund->completed_by_name);
        $this->assertSame($admin->name, $refund->requested_by_name);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('clothing_refunds', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'confirmed', 'amount' => '25.01']);
        $this->postRefund(route('backend.clothing.refunds.complete', [$event, $order, $refund]), ['reference' => 'another-payment', 'paid_to_original_payer' => 1])->assertSessionHasErrors('refund');

        $payfast->shouldReceive('refundUsingAvailableMethod')->once()->andThrow(new \RuntimeException('Connection lost'));
        $input['request_token'] = (string) str()->uuid();
        $input['amount'] = '10.00';
        $this->postRefund($url, $input)->assertSessionHasErrors('refund');
        $this->postRefund($url, $input)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 2);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['status' => 'uncertain', 'amount' => '10.00']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, $order->fresh()->pay_status);
        $input['request_token'] = (string) str()->uuid();
        $this->postRefund($url, $input)->assertSessionHasErrors('refund');
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
        $this->actingAs($payer)->postRefund($url)->assertForbidden();
        $otherEvent = Event::factory()->create();
        $this->actingAs($admin)->postRefund(route('backend.clothing.refunds.retry', [$otherEvent, $order, $refund]))->assertNotFound();
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->actingAs($admin)->postRefund($url)->assertSessionHasNoErrors();
        $this->postRefund($url)->assertSessionHasNoErrors();
        $this->assertSame('completed', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_malformed_provider_success_is_uncertain_and_blocks_manual_completion(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $this->mock(\App\Services\Payfast::class)->shouldReceive('refundUsingAvailableMethod')->once()->andReturn(['success' => true, 'data' => []]);
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.01', 'method' => 'payfast', 'reason' => 'Returned clothing'];
        $this->actingAs($admin)->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('refund');
        $refund = ClothingRefund::firstOrFail();
        $this->assertSame('pending', $refund->refund_status);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
        $this->postRefund(route('backend.clothing.refunds.complete', [$event, $order, $refund]), ['reference' => 'manual-123', 'paid_to_original_payer' => 1])->assertSessionHasErrors('refund');
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_bank_details_are_forwarded_ephemerally_and_never_flashed_after_provider_error(): void
    {
        [$admin, , $event, $order] = $this->fixture();
        $bank = ['account_holder' => 'Original Payer', 'bank_name' => 'FNB', 'branch_code' => '250655', 'account_number' => '123456789', 'account_type' => 'current'];
        $this->mock(\App\Services\Payfast::class)->shouldReceive('refundUsingAvailableMethod')->once()->with($order->payfast_pf_payment_id, '10.01', 'Clothing item refund', $bank)->andReturn(['success' => false]);
        $input = ['request_token' => (string) str()->uuid(), 'mode' => 'amount', 'item_id' => $order->items->first()->id, 'amount' => '10.01', 'method' => 'payfast', 'reason' => 'Returned clothing', 'bank_details' => $bank + ['original_payer' => 1]];
        $this->actingAs($admin)->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('refund');
        $this->assertNull(session()->getOldInput('bank_details'));
        $input['bank_details']['account_number'] = 'invalid-bank-account';
        $this->postRefund(route('backend.clothing.refunds.store', [$event, $order]), $input)->assertSessionHasErrors('account_number');
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
        $this->actingAs($manager)->postRefund($url)->assertForbidden();
        $this->actingAs($super)->postRefund($url)->assertSessionHasErrors('late_refund_note');
        $this->assertDatabaseCount('clothing_provider_refund_attempts', 0);
        $this->postRefund($url, ['late_refund_note' => 'Reconcile regional clothing payout'])->assertSessionHasNoErrors();
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
