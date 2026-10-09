<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayfastCheckoutRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private User $payer;
    private Event $event;
    private RegistrationOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureAgreementAccepted::class,
            \App\Http\Middleware\EnsurePlayerProfileUpdated::class,
        ]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->operator = User::factory()->create()->assignRole('super-user');
        $this->payer = User::factory()->create();
        $this->event = Event::factory()->create();
        $this->order = $this->unresolvedOrder($this->event);
    }

    public function test_payer_status_and_repeated_payment_post_do_not_resend_or_change_money(): void
    {
        $handoff = $this->order->payfast_handed_off_at->toISOString();
        $response = $this->actingAs($this->payer)->get(route('registration.checkout', $this->order));
        $this->saveSnapshot('pending', $response->getContent());
        $response
            ->assertOk()->assertSee('We are waiting for your payment result')
            ->assertSee('This does not mean payment was completed.')
            ->assertSee('Check payment status')->assertDontSee('id="hybridPayForm"', false);
        $this->post(route('registration.hybrid.pay'), ['custom_int5' => $this->order->id])
            ->assertRedirect(route('registration.checkout', $this->order));
        $this->get(route('registration.hybrid.cancel', $this->order->id))
            ->assertRedirect(route('registration.checkout', $this->order));
        $this->assertSame($handoff, $this->order->fresh()->payfast_handed_off_at->toISOString());
        $this->assertSame(40.0, $this->order->fresh()->wallet_reserved);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->actingAs(User::factory()->create())->get(route('registration.checkout', $this->order))->assertForbidden();
        $this->order->forceFill(['pay_status' => 1])->save();
        $this->actingAs($this->payer)->get(route('registration.checkout', $this->order))
            ->assertRedirect(route('frontend.registration.success', ['order' => $this->order->id]));
    }

    public function test_super_user_can_cancel_verified_closed_attempt_without_recreating_records_or_debiting_wallet(): void
    {
        $this->actingAs($this->operator)->get(route('backend.payfast-handoffs.events'))
            ->assertOk()->assertSee($this->event->name);
        $other = $this->unresolvedOrder(Event::factory()->create(['name' => 'Other isolated event']));
        $response = $this->get(route('backend.payfast-handoffs.index', $this->event));
        $this->saveSnapshot('admin', $response->getContent());
        $response
            ->assertOk()->assertSee("Order #{$this->order->id}")->assertDontSee("Order #{$other->id}");
        $orderCount = RegistrationOrder::count();
        $itemCount = RegistrationOrderItems::count();
        $this->postRelease()->assertRedirect(route('backend.payfast-handoffs.index', $this->event));
        $this->assertNull($this->order->fresh()->payfast_handed_off_at);
        $this->assertSame(0.0, $this->order->fresh()->wallet_reserved);
        $this->assertSame(0.0, $this->order->fresh()->payfast_amount_due);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertDatabaseCount('registration_orders', $orderCount);
        $this->assertDatabaseCount('registration_order_items', $itemCount);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $this->order->id, 'causer_id' => $this->operator->id, 'description' => 'supervised unresolved PayFast handoff released']);
        $this->actingAs($this->payer)->get(route('registration.checkout', $this->order))
            ->assertRedirect(route('events.show', $this->event));
        try {
            app(RegistrationPaymentService::class)->finalizePayfastPayment($this->order->fresh(), 200, ['pf_payment_id' => 'LATE-COMPLETE']);
            $this->fail('A cancelled checkout must not settle from a delayed callback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('cancelled', $exception->getMessage());
        }
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertFalse($this->order->fresh()->pay_status);
        $this->assertSame(0.0, $this->order->fresh()->payfast_amount_due);
        $this->assertNotNull($other->fresh()->payfast_handed_off_at);
    }

    public function test_release_requires_role_evidence_terminal_confirmation_and_current_attempt(): void
    {
        $this->actingAs($this->payer)->get(route('backend.payfast-handoffs.events'))->assertForbidden();
        $this->postRelease()->assertForbidden();
        $this->actingAs($this->operator);
        foreach ([['provider_attempt_closed' => '0'], ['evidence_reference' => ''], ['handed_off_at' => now()->toISOString()]] as $invalid) {
            $this->postRelease($invalid)->assertSessionHasErrors();
            $this->assertNotNull($this->order->fresh()->payfast_handed_off_at);
        }
        $this->postRelease()->assertSessionHasNoErrors();
        $this->postRelease()->assertSessionHasErrors('payment');
        $this->assertSame(1, DB::table('activity_log')->where('description', 'supervised unresolved PayFast handoff released')->count());
    }

    public function test_wrong_event_mixed_event_and_new_settlement_evidence_cannot_be_released(): void
    {
        $this->actingAs($this->operator);
        $otherEvent = Event::factory()->create();
        $this->post(route('backend.payfast-handoffs.release', [$otherEvent, $this->order]), $this->payload())->assertNotFound();
        $item = $this->order->items->first();
        $mixedItem = $item->replicate();
        $mixedItem->category_event_id = CategoryEvent::factory()->create(['event_id' => $otherEvent->id])->id;
        $mixedItem->save();
        $this->postRelease()->assertNotFound();
        $mixedItem->delete();
        $this->order->forceFill(['payfast_pf_payment_id' => 'PF-SETTLED'])->save();
        $this->postRelease()->assertSessionHasErrors('payment');
        $this->assertNotNull($this->order->fresh()->payfast_handed_off_at);
        $this->assertDatabaseMissing('activity_log', ['description' => 'supervised unresolved PayFast handoff released']);
    }

    public function test_stale_form_cannot_cancel_a_newer_attempt_on_the_same_order(): void
    {
        $oldPayload = $this->payload();
        $this->order->forceFill(['payfast_handed_off_at' => now()])->save();
        $newHandoff = $this->order->fresh()->payfast_handed_off_at->toISOString();
        $this->actingAs($this->operator)->post(route('backend.payfast-handoffs.release', [$this->event, $this->order]), $oldPayload)
            ->assertSessionHasErrors('payment');
        $this->assertSame($newHandoff, $this->order->fresh()->payfast_handed_off_at->toISOString());
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(40.0, $this->order->fresh()->wallet_reserved);
    }

    public function test_missing_payer_rolls_back_cancellation(): void
    {
        DB::table('users')->where('id', $this->payer->id)->delete();
        $this->actingAs($this->operator)->postRelease()->assertNotFound();
        $this->assertNotNull($this->order->fresh()->payfast_handed_off_at);
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(40.0, $this->order->fresh()->wallet_reserved);
        $this->assertDatabaseMissing('activity_log', ['description' => 'supervised unresolved PayFast checkout cancelled']);
    }

    public function test_invitation_reset_failure_rolls_back_release_cancellation_and_audit(): void
    {
        $handoff = $this->order->payfast_handed_off_at->toISOString();
        $this->app->instance(\App\Services\Masters\MastersInvitationService::class, new class {
            public function resetCancelledPayment(RegistrationOrder $order, User $payer): void
            {
                throw new \RuntimeException('Reset failed');
            }
        });
        try {
            app(RegistrationPaymentService::class)->cancelUnresolvedPayfastHandoff($this->order, $this->operator, 'PF-CASE:ROLLBACK');
            $this->fail('A failed reset must fail the whole cancellation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Reset failed', $exception->getMessage());
        }
        $this->assertSame($handoff, $this->order->fresh()->payfast_handed_off_at->toISOString());
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(40.0, $this->order->fresh()->wallet_reserved);
        $this->assertSame(160.0, $this->order->fresh()->payfast_amount_due);
        $this->assertDatabaseMissing('activity_log', ['description' => 'supervised unresolved PayFast handoff released']);
        $this->assertDatabaseMissing('activity_log', ['description' => 'supervised unresolved PayFast checkout cancelled']);
    }

    private function payload(): array
    {
        return ['handed_off_at' => $this->order->payfast_handed_off_at->toISOString(), 'provider_attempt_closed' => '1', 'evidence_reference' => 'PF-CASE:1042'];
    }

    private function saveSnapshot(string $name, string $html): void
    {
        if (getenv('PAYFAST_RECOVERY_SNAPSHOT') !== '1') {
            return;
        }
        $path = storage_path('app/testing/payfast-recovery');
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path.'/'.$name.'.html', $html);
    }

    private function postRelease(array $overrides = [])
    {
        return $this->post(route('backend.payfast-handoffs.release', [$this->event, $this->order]), array_replace($this->payload(), $overrides));
    }

    private function unresolvedOrder(Event $event): RegistrationOrder
    {
        $order = RegistrationOrder::create(['user_id' => $this->payer->id, 'wallet_reserved' => 40, 'payfast_amount_due' => 160, 'total_fee' => 200, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false, 'status' => 'pending', 'payfast_handed_off_at' => now()->subHour()]);
        (new RegistrationOrderItems())->forceFill(['order_id' => $order->id, 'registration_id' => 1000 + $order->id, 'category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'player_id' => 3000 + $order->id, 'user_id' => $this->payer->id, 'item_price' => 200])->save();

        return $order->fresh('items');
    }
}
