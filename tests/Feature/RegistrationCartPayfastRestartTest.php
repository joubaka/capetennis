<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Http\Middleware\EnsureAgreementAccepted;
use App\Http\Middleware\EnsurePlayerProfileUpdated;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\RegistrationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegistrationCartPayfastRestartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureAgreementAccepted::class, EnsurePlayerProfileUpdated::class]);
    }

    public function test_opening_cart_leaves_handoff_alone_and_explicit_confirm_replaces_it_once(): void
    {
        [$payer, $category, $players] = $this->cart();
        $old = $this->startHandoff($payer, $category, $players);
        $handoff = $old->payfast_handed_off_at->toIso8601String();
        $this->actingAs($payer)->get(route('register.register', $category->event_id))
            ->assertOk()->assertViewIs('frontend.event.checkout');
        $this->assertSame($handoff, $old->fresh()->payfast_handed_off_at->toIso8601String());
        $this->assertDatabaseCount('registration_orders', 1);

        $response = $this->confirm($payer, $category, array_reverse($players))
            ->assertOk()->assertViewIs('frontend.payfast.check_out');
        $fresh = $response->viewData('order');
        $this->assertNotSame($old->id, $fresh->id);
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame(200.0, $fresh->payfast_amount_due);
        $this->assertSame(0.0, $fresh->wallet_reserved);
        $this->assertNull($fresh->payfast_handed_off_at);
        $retained = DB::table('activity_log')->where('subject_id', $old->id)
            ->where('description', 'Expired unresolved PayFast handoff retained for reconciliation')
            ->value('properties');
        $this->assertSame($handoff, json_decode((string) $retained, true)['handed_off_at'] ?? null);

        $this->confirm($payer, $category, $players)->assertRedirect(route('registration.checkout', $fresh));
        $this->assertDatabaseCount('registration_orders', 2);
        $this->assertDatabaseCount('registration_order_items', 4);
        $this->assertSame(2, CategoryEventRegistration::count());
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_partial_cart_foreign_payer_and_paid_entries_do_not_replace_the_original(): void
    {
        [$payer, $category, $players] = $this->cart();
        $old = $this->startHandoff($payer, $category, $players);
        $handoff = $old->payfast_handed_off_at->toISOString();

        $this->confirm($payer, $category, [$players[0]])->assertRedirect(route('registration.checkout', $old));
        $other = User::factory()->create();
        $this->confirm($other, $category, $players)->assertOk()->assertViewIs('frontend.payfast.check_out');
        $this->assertSame($handoff, $old->fresh()->payfast_handed_off_at->toISOString());
        $this->assertDatabaseMissing('activity_log', ['description' => 'Unpaid PayFast checkout superseded by confirmed registration cart']);

        $entry = CategoryEventRegistration::where('registration_id', $old->items->first()->registration_id)->firstOrFail();
        $entry->update(['payment_status_id' => 1]);
        $this->confirm($payer, $category, $players)->assertRedirect()->assertSessionHasErrors('msg');
        $this->assertSame($handoff, $old->fresh()->payfast_handed_off_at->toISOString());
        $this->assertSame('pending', $old->fresh()->status);
        $this->assertDatabaseCount('registration_orders', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function cart(): array
    {
        $type = EventType::query()->forceCreate(['name' => 'Individual', 'type' => EventType::INDIVIDUAL]);
        $event = Event::factory()->create(['eventType' => $type->id, 'deadline' => 0]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => 100]);

        return [User::factory()->create(), $category, Player::factory()->count(2)->create()->all()];
    }

    private function startHandoff(User $payer, CategoryEvent $category, array $players): RegistrationOrder
    {
        $order = $this->confirm($payer, $category, $players)->assertOk()->viewData('order');

        return app(RegistrationPaymentService::class)->preparePayfastHandoff($order, $payer, 0, 200);
    }

    private function confirm(User $payer, CategoryEvent $category, array $players): TestResponse
    {
        return $this->actingAs($payer)->post(route('pay.now.payfast'), [
            'terms_accepted' => '1',
            'player' => array_map(fn (Player $player) => $player->id, $players),
            'category' => array_fill(0, count($players), $category->id),
        ]);
    }
}
