<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, Player, Team, TeamPaymentOrder, TeamPlayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCheckoutRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_renders_team_controls_and_stored_amounts_without_mutating_payment(): void
    {
        $order = $this->order();
        $original = $order->fresh()->getAttributes();
        config(['app.url' => 'https://canonical.example', 'services.payfast.notify_url' => 'https://individual.example/notify',
            'services.payfast.notify_team_url' => '/notify_team']);

        foreach ([1, 2] as $visit) {
            $this->actingAs($order->user)->get(route('team.checkout', $order))
                ->assertOk()
                ->assertViewIs('frontend.payfast.team_payment')
                ->assertViewHas('total', 490.0)
                ->assertViewHas('walletReserved', 40.0)
                ->assertViewHas('payfastDue', 450.0)
                ->assertViewHas('autoSubmitPayfast', false)
                ->assertSee('R490.00')
                ->assertSee('R450.00')
                ->assertSee('I have read and accept the registration terms and refund policy above.')
                ->assertSee('name="terms_accepted" value="1"', false)
                ->assertSee('name="notify_url" value="https://canonical.example/notify_team"', false)
                ->assertDontSee('https://individual.example/notify', false)
                ->assertSee(route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]), false)
                ->assertDontSee("document.getElementById('teamPayfastForm').submit();", false);
        }

        $this->assertSame($original, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('team_payment_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_another_payer_cannot_view_the_order(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create())->get(route('team.checkout', $order))->assertForbidden();
        $this->assertDatabaseCount('team_payment_orders', 1);
    }

    public function test_checkout_displays_saved_terms_without_replacing_them(): void
    {
        $order = $this->order();
        $terms = '<p>Existing Cape Tennis registration conditions.</p>';
        \App\Models\SiteSetting::set('terms_conditions_content', $terms);
        $this->actingAs($order->user)->get(route('team.checkout', $order))
            ->assertOk()
            ->assertSee($terms, false)
            ->assertSee('Registration, withdrawals and refunds');
        $this->assertSame($terms, \App\Models\SiteSetting::get('terms_conditions_content'));
    }

    public function test_checkout_rejects_a_team_from_another_event(): void
    {
        $order = $this->order();
        $order->update(['event_id' => Event::factory()->create()->id]);
        $this->actingAs($order->user)->get(route('team.checkout', $order))->assertRedirect()->assertSessionHasErrors();
    }

    public function test_wallet_only_checkout_uses_team_completion_controls(): void
    {
        $order = $this->order();
        $order->update(['wallet_reserved' => 490, 'payfast_amount_due' => 0]);
        $this->actingAs($order->user)->get(route('team.checkout', $order))
            ->assertOk()
            ->assertSee(route('team.hybrid.complete', $order), false)
            ->assertSee('Confirm Wallet Payment')
            ->assertSee('id="teamCompleteTerms" required', false)
            ->assertDontSee('id="teamPayfastForm"', false);
        $this->assertFalse($order->fresh()->wallet_debited);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_payfast_handoff_requires_explicit_terms_acceptance_without_changing_order(): void
    {
        $order = $this->order();
        $before = $order->fresh()->getAttributes();
        $url = route('team.payment.payfast.handoff', [$order->team_id, $order->player_id, $order->event_id]);
        foreach ([[], ['terms_accepted' => '0']] as $consent) {
            $this->actingAs($order->user)->post($url, ['custom_int5' => $order->id] + $consent)
                ->assertRedirect()->assertSessionHasErrors('terms_accepted');
            $this->assertSame($before, $order->fresh()->getAttributes());
            $this->assertDatabaseCount('team_payment_orders', 1);
            $this->assertDatabaseCount('wallet_transactions', 0);
            $this->assertDatabaseCount('user_players', 0);
        }
    }

    public function test_wallet_application_and_completion_require_acceptance_before_financial_mutations(): void
    {
        $order = $this->order();
        \App\Models\Wallet::factory()->forUser($order->user)->create();
        $before = $order->fresh()->getAttributes();
        foreach ([route('team.hybrid.pay'), route('team.hybrid.complete', $order)] as $url) {
            foreach ([[], ['terms_accepted' => '0']] as $consent) {
                $this->actingAs($order->user)->post($url, ['custom_int5' => $order->id] + $consent)
                    ->assertRedirect()->assertSessionHasErrors('terms_accepted');
                $this->assertSame($before, $order->fresh()->getAttributes());
                $this->assertDatabaseCount('wallet_transactions', 0);
                $this->assertDatabaseCount('transactions_pf', 0);
                $this->assertDatabaseCount('team_payment_orders', 1);
            }
        }
    }

    private function order(): TeamPaymentOrder
    {
        $event = Event::factory()->create(['published' => true, 'signUp' => true, 'status' => 'active', 'start_date' => now()->addMonth(), 'deadline' => 2, 'entryFee' => 400]);
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id, 'noProfile' => false, 'published' => true]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);

        return TeamPaymentOrder::create(['user_id' => User::factory()->create()->id, 'team_id' => $team->id, 'player_id' => $player->id, 'event_id' => $event->id, 'total_amount' => 490, 'wallet_reserved' => 40, 'payfast_amount_due' => 450, 'pay_status' => false, 'payfast_paid' => false, 'wallet_debited' => false]);
    }
}
