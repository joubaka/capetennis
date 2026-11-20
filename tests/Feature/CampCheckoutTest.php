<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Http\Middleware\{EnsureAgreementAccepted, EnsurePlayerProfileUpdated};
use App\Models\{CampConfiguration, CampNominee, CategoryEvent, Event, Player, PracticeCampPlan, RegistrationOrder, User};
use App\Services\Camps\{CampAdmission, CampInvitations, CampWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue((DB::connection()->getDriverName() === 'mysql' && DB::connection()->getDatabaseName() === 'ct_testing')
            || (DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:'), 'Camp checkout tests require an isolated testing database.');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-20 10:00:00'));
        Mail::fake();
        DB::table('eventtypes')->insertOrIgnore(['id' => 2, 'name' => 'Camp', 'type' => 3]);
        $this->withoutMiddleware([EnsureAgreementAccepted::class, EnsurePlayerProfileUpdated::class]);
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant', 'services.payfast.passphrase_live' => 'test-passphrase', 'services.payment_failure_alert.enabled' => false]);
    }

    private function scenario(string $method, float $fee = 200.25, int $capacity = 10): array
    {
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create();
        $event = Event::factory()->create(['eventType' => 2, 'name' => 'Fictional Camp', 'start_date' => '2027-01-07', 'end_date' => '2027-01-09', 'deadline' => 0, 'entryFee' => $fee, 'published' => true, 'signUp' => true]);
        $division = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => $fee]);
        $player = Player::factory()->create(['name' => 'Fictional', 'surname' => 'Child', 'dateOfBirth' => '2018-04-05', 'gender' => 1]);
        $plan = PracticeCampPlan::create(['camp_key' => 'fictional-'.$event->id, 'event_id' => $event->id, 'created_by' => $admin->id, 'updated_by' => $admin->id, 'revision' => (string) Str::uuid(), 'candidates' => []]);
        CampConfiguration::create(['event_id' => $event->id, 'method' => $method, 'capacity' => $capacity, 'divisions' => [$division->id => ['min_age' => 9, 'max_age' => 10, 'gender' => 0]], 'revision' => (string) Str::uuid(), 'updated_by' => $admin->id]);
        return [$admin, $payer, $event, $division, $player, $plan];
    }

    private function checkout(User $payer, CategoryEvent $division, Player $player)
    {
        return $this->actingAs($payer)->post(route('pay.now.payfast'), ['terms_accepted' => '1', 'player' => [$player->id], 'category' => [$division->id]]);
    }

    public function test_open_and_open_with_invitations_allow_unlinked_sponsor_free_checkout_and_replay_does_not_duplicate(): void
    {
        foreach (['open', 'open_with_invitations'] as $method) {
            [, $payer, $event, $division, $player] = $this->scenario($method, 0);
            $this->checkout($payer, $division, $player)->assertRedirect();
            $order = RegistrationOrder::where('user_id', $payer->id)->sole();
            $this->assertTrue($order->pay_status); $this->assertSame(0.0, (float) $order->total_fee);
            $this->assertDatabaseHas('category_event_registrations', ['category_event_id' => $division->id, 'payment_status_id' => 1, 'user_id' => $payer->id]);
            $this->checkout($payer, $division, $player)->assertSessionHasErrors();
            $this->assertSame(1, RegistrationOrder::where('user_id', $payer->id)->count());
        }
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_invitation_only_exact_division_gate_rejects_forgery_and_capacity_holds_release_only_on_canonical_cancellation(): void
    {
        [$admin, $payer, $event, $division, $player, $plan] = $this->scenario('invitation_only', 200.25, 1);
        $this->checkout($payer, $division, $player)->assertSessionHasErrors();
        $this->assertDatabaseCount('registration_orders', 0);
        app(CampWorkflow::class)->approve($admin, $plan, $player->id, $division->id, 'Reviewed');
        $foreign = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $config = CampConfiguration::where('event_id', $event->id)->first();
        $config->update(['divisions' => array_replace($config->divisions, [$foreign->id => ['min_age' => 9, 'max_age' => 10, 'gender' => 0]])]);
        $this->checkout($payer, $foreign, $player)->assertSessionHasErrors();
        $this->checkout($payer, $division, $player)->assertOk();
        $order = RegistrationOrder::where('user_id', $payer->id)->sole();
        $this->assertSame(200.25, (float) $order->total_fee);
        $second = Player::factory()->create(['dateOfBirth' => '2018-05-01', 'gender' => 1]);
        app(CampWorkflow::class)->approve($admin, $plan, $second->id, $division->id, 'Reviewed');
        $secondPayer = User::factory()->create();
        $this->checkout($secondPayer, $division, $second)->assertSessionHasErrors('camp');
        $this->assertDatabaseCount('registration_orders', 1);
        app(RegistrationPaymentService::class)->cancelPayment($order);
        $this->assertSame([], app(CampAdmission::class)->occupied($event->id));
        $this->checkout($secondPayer, $division, $second)->assertOk();
        $this->assertDatabaseCount('registration_orders', 2); $this->assertDatabaseCount('user_players', 0);
    }

    public function test_wallet_camp_checkout_finalizes_once_and_tracks_paid_without_nominee_financial_writes(): void
    {
        [$admin, $payer, $event, $division, $player, $plan] = $this->scenario('invitation_only');
        $configuration = CampConfiguration::where('event_id', $event->id)->firstOrFail();
        $configuration->update(['registration_closes_at' => '2027-01-02 17:00:00']);
        $deadlineLabel = $configuration->fresh()->registration_closes_at->copy()->timezone('Africa/Johannesburg')->format('D d M Y H:i').' SAST';
        $this->get(route('events.show', $event))->assertOk()->assertSee($deadlineLabel);
        $nominee = app(CampWorkflow::class)->approve($admin, $plan, $player->id, $division->id, 'Reviewed');
        $this->actingAs($payer)->get(route('register.register', [$event->id, 'camp_nominee' => $nominee->id]))
            ->assertOk()->assertSee('>'.$player->full_name.'</option>', false);
        $payer->wallet()->create()->transactions()->create(['type' => 'credit', 'amount' => 200.25, 'source_type' => 'test']);
        $this->checkout($payer, $division, $player)->assertOk();
        $order = RegistrationOrder::where('user_id', $payer->id)->sole();
        $payments = app(RegistrationPaymentService::class);
        $payments->reservePayment($order, 200.25, 0);
        $payments->finalizeWalletPayment($order->fresh(), ['user_id' => $payer->id]);
        $payments->finalizeWalletPayment($order->fresh(), ['user_id' => $payer->id]);
        $this->assertSame('paid', app(CampInvitations::class)->participation($nominee));
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'debit', 'amount' => 200.25]);
        $this->assertDatabaseCount('registration_orders', 1); $this->assertDatabaseCount('category_event_registrations', 1); $this->assertDatabaseCount('user_players', 0);
    }

    public function test_verified_camp_payfast_exact_amount_replay_preserves_original_admitted_checkout_after_settings_change(): void
    {
        [$admin, $payer, $event, $division, $player, $plan] = $this->scenario('invitation_only');
        $nominee = app(CampWorkflow::class)->approve($admin, $plan, $player->id, $division->id, 'Reviewed');
        $this->checkout($payer, $division, $player)->assertOk();
        $order = RegistrationOrder::where('user_id', $payer->id)->sole();
        app(RegistrationPaymentService::class)->preparePayfastHandoff($order, $payer, 0, 200.25);
        CampConfiguration::where('event_id', $event->id)->update(['registration_closes_at' => now()->subMinute()]);
        $event->update(['entryFee' => 999]); $division->update(['entry_fee' => 999]);
        $payload = ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => 'PF-CAMP-TEST', 'amount_gross' => '200.25',
            'custom_int5' => $order->id, 'custom_int4' => $payer->id, 'custom_int3' => $event->id, 'custom_int2' => $player->id, 'custom_int1' => $division->id];
        $send = function (array $fields) { $fields['signature'] = md5(http_build_query($fields).'&passphrase=test-passphrase'); return $this->call('POST', route('notify'), $fields, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($fields)); };
        $send([...$payload, 'amount_gross' => '200.24'])->assertStatus(400);
        $send($payload)->assertOk(); $send($payload)->assertOk();
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertSame('paid', app(CampInvitations::class)->participation($nominee));
        $this->assertDatabaseCount('transactions_pf', 1); $this->assertDatabaseCount('registration_orders', 1); $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseHas('transactions_pf', ['pf_payment_id' => 'PF-CAMP-TEST', 'amount_gross' => 200.25]);
        $this->assertDatabaseCount('user_players', 0);
    }
}
