<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Event, EventAdmin, EventNomination, Player, Registration, RegistrationManualReceipt, RegistrationOrder, RegistrationOrderItems, SiteSetting, TrialPaymentProof, User, WalletTransaction};
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CavaliersNominationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Mail::fake(); Bus::fake();
        config(['services.payfast.merchant_id' => 'fictional-merchant', 'services.payfast.merchant_key' => 'fictional-key',
            'services.payfast.sandbox_id' => 'fictional-sandbox', 'services.payfast.sandbox_key' => 'fictional-key', 'services.payfast.sandbox' => false]);
        foreach (['registration_open' => '1', 'require_profile_update' => '0', 'require_code_of_conduct' => '0', 'player_email_on_registration' => '0'] as $key => $value) SiteSetting::set($key, $value);
        $event = Event::factory()->create(['id' => 252, 'eventType' => 5, 'published' => true, 'signUp' => true,
            'start_date' => now()->addMonth(), 'deadline' => 2, 'entryFee' => 0, 'status' => 'active']);
        $division = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'U11 Girls'])->id,
            'nominations_published' => true, 'entry_fee' => 0]);
        $owner = User::factory()->create();
        $player = Player::factory()->create(['name' => 'Fictional', 'surname' => 'Nominee', 'userId' => $owner->id]);
        $other = Player::factory()->create(['name' => 'Other', 'surname' => 'Nominee']);
        $reserve = Player::factory()->create(['name' => 'Private', 'surname' => 'Reserve']);
        $nomination = EventNomination::forceCreate(['event_id' => $event->id, 'category_event_id' => $division->id, 'player_id' => $player->id, 'roster_position' => 1]);
        EventNomination::forceCreate(['event_id' => $event->id, 'category_event_id' => $division->id, 'player_id' => $other->id, 'roster_position' => 2]);
        DB::table('cavaliers_trial_roster_drafts')->insert(['event_id' => $event->id, 'category_event_id' => $division->id, 'revision' => (string) Str::uuid(),
            'selected' => json_encode([$player->id, $other->id]), 'reserves' => json_encode([$reserve->id]), 'declined' => '[]']);
        return [$event, $division, $player, $owner, $nomination, $other, $reserve];
    }

    private function url(array $scenario): string
    {
        return route('cavaliers.nominations.decline', [$scenario[0], $scenario[1], $scenario[4]->id]);
    }

    private function export(string $name, \Illuminate\Testing\TestResponse $response): void
    {
        if ($output = getenv('CT_CAVALIERS_WORKFLOW_QA_OUTPUT')) {
            if (! is_dir($output)) mkdir($output, 0777, true);
            file_put_contents($output.'/'.$name.'.html', $response->getContent());
        }
    }

    private function entry(CategoryEvent $division, Player $player, User $payer, int $paid = 1, array $extra = []): CategoryEventRegistration
    {
        $registration = Registration::create([]); $registration->players()->attach($player);
        return CategoryEventRegistration::create(['registration_id' => $registration->id, 'category_event_id' => $division->id, 'user_id' => $payer->id,
            'payment_status_id' => $paid, 'status' => 'active', 'refund_status' => 'not_refunded', ...$extra]);
    }

    private function order(CategoryEvent $division, Player $player, User $payer, array $extra = []): RegistrationOrder
    {
        $registration = Registration::create([]); $registration->players()->attach($player);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 100, 'wallet_reserved' => 0, 'payfast_amount_due' => 100,
            'wallet_debited' => false, 'payfast_paid' => false, 'pay_status' => false, 'status' => 'cancelled', ...$extra]);
        (new RegistrationOrderItems())->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id, 'category_event_id' => $division->id,
            'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 100])->save();
        return $order;
    }

    public function test_sponsor_can_open_exact_nominee_checkout_and_register_free_without_becoming_player_owner(): void
    {
        [$event, $division, $player, $owner, $nomination] = $this->scenario();
        $sponsor = User::factory()->create();
        $checkout = $this->actingAs($sponsor)->get(route('register.register', ['id' => $event->id, 'nomination' => $nomination->id]))
            ->assertOk()->assertViewHas('nomination', fn ($selected) => $selected->id === $nomination->id)
            ->assertSee('value="'.$player->id.'" selected', false);
        $this->export('checkout', $checkout);
        $this->post(route('pay.now.payfast'), ['player' => [$player->id], 'category' => [$division->id], 'terms_accepted' => '1'])->assertRedirect();
        $order = RegistrationOrder::sole();
        $this->assertSame($sponsor->id, (int) $order->user_id);
        $this->assertTrue($order->pay_status);
        $this->assertSame(0.0, (float) $order->total_fee);
        $this->assertSame('free', $order->payment_method);
        $this->assertDatabaseHas('category_event_registrations', ['category_event_id' => $division->id, 'user_id' => $sponsor->id, 'payment_status_id' => 1]);
        $this->assertDatabaseMissing('user_players', ['user_id' => $sponsor->id, 'player_id' => $player->id]);
        $this->assertSame($owner->id, (int) $player->fresh()->userId);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Registered');
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_registered_marker_is_paid_active_and_category_specific_and_closed_lists_have_no_register_action(): void
    {
        [$event, $division, $player, $owner, $nomination, $other] = $this->scenario();
        $second = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true]);
        EventNomination::create(['event_id' => $event->id, 'category_event_id' => $second->id, 'player_id' => $other->id]);
        $this->entry($division, $player, $owner, 1, ['payment_method' => 'free']);
        $this->entry($division, $other, $owner, 0);
        $this->entry($second, $other, $owner, 1);
        $third = Player::factory()->create();
        EventNomination::create(['event_id' => $event->id, 'category_event_id' => $division->id, 'player_id' => $third->id]);
        $this->entry($division, $third, $owner, 1, ['withdrawn_at' => now(), 'status' => 'withdrawn']);
        $page = $this->get(route('events.show', $event))->assertOk();
        $this->export('registered', $page);
        $keys = $page->viewData('nomRegisteredLookup');
        $this->assertEqualsCanonicalizing([$player->id.'-'.$division->id, $other->id.'-'.$second->id], array_keys($keys));
        $event->update(['signUp' => false]);
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('?nomination=', false);
    }

    public function test_confirmed_owner_decline_is_atomic_audited_idempotent_and_keeps_other_roster_places(): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner, $nomination, $other, $reserve] = $s;
        $this->actingAs($owner);
        $before = DB::table('cavaliers_trial_roster_drafts')->where('category_event_id', $division->id)->first();
        $auditBefore = DB::table('activity_log')->where('log_name', 'cavaliers-nomination')->count();
        $review = $this->get($this->url($s))->assertOk()->assertSee('Confirm decline');
        $this->export('decline', $review);
        $this->export('public-actions', $this->get(route('events.show', $event))->assertOk());
        $this->assertModelExists($nomination);
        $this->assertSame($before->revision, DB::table('cavaliers_trial_roster_drafts')->where('category_event_id', $division->id)->value('revision'));
        $this->assertSame($auditBefore, DB::table('activity_log')->where('log_name', 'cavaliers-nomination')->count());
        $token = $review->viewData('token');
        $this->post($this->url($s), ['token' => $token])->assertSessionHasErrors('confirmed');
        foreach ([1, 2] as $attempt) $this->post($this->url($s), ['token' => $token, 'confirmed' => '1'])->assertRedirect(route('events.show', $event));
        $after = DB::table('cavaliers_trial_roster_drafts')->where('category_event_id', $division->id)->first();
        $this->assertSame([$other->id], json_decode($after->selected, true));
        $this->assertSame([$reserve->id], json_decode($after->reserves, true));
        $this->assertSame([$player->id], json_decode($after->declined, true));
        $this->assertNotSame($before->revision, $after->revision);
        $this->assertModelMissing($nomination);
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'cavaliers-nomination')->count());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'cavaliers-nomination', 'causer_id' => $owner->id]);
        $this->assertTrue((bool) $division->fresh()->nominations_published);
        $this->get(route('events.show', $event))->assertOk()->assertDontSee($player->full_name)->assertSee($other->full_name)->assertDontSee($reserve->full_name);
        $this->post(route('pay.now.payfast'), ['player' => [$player->id], 'category' => [$division->id], 'terms_accepted' => '1'])->assertSessionHasErrors('msg');
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_only_linked_account_or_exact_event_manager_can_decline_and_cross_event_or_stale_reviews_fail(): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner] = $s;
        $this->getJson($this->url($s))->assertUnauthorized();
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->getJson($this->url($s))->assertForbidden();
        $stranger->assignRole(Role::findOrCreate('admin', 'web'));
        $foreign = Event::factory()->create(); EventAdmin::create(['event_id' => $foreign->id, 'user_id' => $stranger->id]);
        $this->getJson($this->url($s))->assertForbidden();
        $linked = User::factory()->create(); $linked->players()->attach($player->id);
        $linkedReview = $this->actingAs($linked)->get($this->url($s))->assertOk();
        $this->actingAs($stranger)->postJson($this->url($s), ['token' => $linkedReview->viewData('token'), 'confirmed' => '1'])->assertConflict();
        $this->actingAs($owner);
        $review = $this->get($this->url($s))->assertOk();
        $token = $review->viewData('token');
        $foreignDivision = CategoryEvent::factory()->create(['event_id' => $foreign->id]);
        $this->postJson(route('cavaliers.nominations.decline.confirm', [$event, $foreignDivision, $s[4]->id]), ['token' => $token, 'confirmed' => '1'])->assertConflict();
        DB::table('cavaliers_trial_roster_drafts')->where('category_event_id', $division->id)->update(['revision' => (string) Str::uuid()]);
        $this->postJson($this->url($s), ['token' => $token, 'confirmed' => '1'])->assertConflict();
        $this->assertModelExists($s[4]);
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $stranger->id]);
        $this->actingAs($stranger); $review = $this->get($this->url($s))->assertOk();
        $this->post($this->url($s), ['token' => $review->viewData('token'), 'confirmed' => '1'])->assertRedirect();
        $this->assertModelMissing($s[4]);
    }

    public static function protectedOrders(): array
    {
        return array_map(fn ($kind) => [$kind], ['pending', 'handoff', 'reserved', 'paid', 'wallet_debited', 'eft_pending', 'eft_verified', 'manual_receipt', 'transaction', 'wallet_transaction']);
    }

    #[DataProvider('protectedOrders')]
    public function test_decline_never_changes_pending_or_evidenced_orders_of_any_payer(string $kind): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner] = $s;
        $payer = User::factory()->create();
        $extra = match ($kind) {'pending' => ['status' => 'pending'], 'handoff' => ['payfast_handed_off_at' => now()->subDay()],
            'reserved' => ['wallet_reserved' => 25], 'paid' => ['payfast_paid' => true], 'wallet_debited' => ['wallet_debited' => true], default => []};
        $order = $this->order($division, $player, $payer, $extra); $order->forceFill(['created_at' => now()->subDays(2)])->save();
        if (str_starts_with($kind, 'eft_')) TrialPaymentProof::create(['order_id' => $order->id, 'event_id' => $event->id, 'payer_id' => $payer->id,
            'path' => 'fictional/proof.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'status' => substr($kind, 4)]);
        if ($kind === 'manual_receipt') RegistrationManualReceipt::create(['order_id' => $order->id, 'event_id' => $event->id, 'amount' => 100,
            'method' => 'eft', 'reference' => 'FICTIONAL', 'verified_by_user_id' => $payer->id, 'paid_at' => now()]);
        if ($kind === 'transaction') DB::table('transactions_pf')->insert(['custom_int5' => $order->id, 'amount_gross' => 100, 'created_at' => now(), 'updated_at' => now()]);
        if ($kind === 'wallet_transaction') {
            $wallet = $payer->wallet()->create([]);
            WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'debit', 'amount' => 100, 'source_type' => 'event_registration_wallet_payment', 'source_id' => $order->id]);
        }
        $before = $order->fresh()->getAttributes();
        $this->actingAs($owner)->getJson($this->url($s))->assertUnprocessable();
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertModelExists($s[4]);
        $this->assertSame('[]', DB::table('cavaliers_trial_roster_drafts')->where('category_event_id', $division->id)->value('declined'));
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_paid_free_entries_and_checkout_started_after_preview_block_decline(): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner] = $s;
        $this->actingAs($owner); $review = $this->get($this->url($s))->assertOk();
        $entry = $this->entry($division, $player, User::factory()->create(), 1, ['payment_method' => 'free']);
        $before = $entry->fresh()->getAttributes();
        $this->postJson($this->url($s), ['token' => $review->viewData('token'), 'confirmed' => '1'])->assertUnprocessable();
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertModelExists($s[4]);
    }

    public function test_closed_unpublished_and_unlisted_admissions_fail_and_nomination_is_rechecked_after_lock(): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner, $nomination] = $s;
        $sponsor = User::factory()->create(); $this->actingAs($sponsor);
        $cart = ['player' => [$player->id], 'category' => [$division->id], 'terms_accepted' => '1'];
        $division->update(['nominations_published' => false]);
        $this->post(route('pay.now.payfast'), $cart)->assertSessionHasErrors('msg');
        $division->update(['nominations_published' => true]);
        $unlisted = Player::factory()->create();
        $this->post(route('pay.now.payfast'), [...$cart, 'player' => [$unlisted->id]])->assertSessionHasErrors('msg');
        $event->update(['signUp' => false]);
        $this->post(route('pay.now.payfast'), $cart)->assertSessionHasErrors('msg');
        $this->actingAs($owner)->getJson($this->url($s))->assertUnprocessable();
        $event->update(['signUp' => true]); $this->actingAs($sponsor);
        $armed = true;
        DB::listen(function (QueryExecuted $query) use (&$armed, $nomination) {
            if ($armed && str_contains($query->sql, 'from `category_events`') && str_contains($query->sql, 'for update')) {
                $armed = false; DB::table('event_nominations')->where('id', $nomination->id)->delete();
            }
        });
        $this->post(route('pay.now.payfast'), $cart)->assertSessionHasErrors('msg');
        $this->assertFalse($armed, 'The test must reach the category-lock boundary after valid preflight.');
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('category_event_registrations', 0);
    }

    public function test_clean_canonically_cancelled_checkout_can_be_declined_but_never_repaid_or_reopened(): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner] = $s;
        $order = $this->order($division, $player, $owner);
        $this->actingAs($owner); $review = $this->get($this->url($s))->assertOk();
        $this->post($this->url($s), ['token' => $review->viewData('token'), 'confirmed' => '1'])->assertRedirect();
        $payments = app(RegistrationPaymentService::class);
        foreach ([fn () => $payments->reservePayment($order, 100, 0), fn () => $payments->finalizeWalletPayment($order), fn () => $payments->preparePayfastHandoff($order, $owner, 0, 100)] as $action) {
            try { $action(); $this->fail('A closed checkout was reactivated.'); }
            catch (\Illuminate\Validation\ValidationException $exception) { $this->assertArrayHasKey('payment', $exception->errors()); }
        }
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_decline_invalidates_old_admin_saves_and_replay_cannot_remove_a_freshly_republished_nominee(): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner, $nomination, $other, $reserve] = $s;
        $manager = User::factory()->create(); $manager->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $manager->id]);
        $draftUrl = route('backend.cavaliers-roster.show', [$event, $division]);
        $publishUrl = route('backend.cavaliers-roster.nominations', [$event, $division]);
        $old = $this->actingAs($manager)->getJson($draftUrl)->assertOk()->json();
        $review = $this->actingAs($owner)->get($this->url($s))->assertOk();
        $token = $review->viewData('token');
        $this->post($this->url($s), ['token' => $token, 'confirmed' => '1'])->assertRedirect();
        $this->actingAs($manager)->putJson($draftUrl, ['revision' => $old['revision'], 'selected' => [$player->id, $other->id], 'reserves' => [$reserve->id], 'declined' => []])->assertConflict();
        $this->postJson($publishUrl, ['publish' => true, 'revision' => $old['revision'], 'fingerprint' => $old['publication']['fingerprint']])->assertConflict();
        // A manager may deliberately review and republish a fresh choice; the old
        // user's decision token must not delete that new nomination identity.
        $current = $this->getJson($draftUrl)->assertOk()->json();
        $saved = $this->putJson($draftUrl, ['revision' => $current['revision'], 'selected' => [$player->id, $other->id], 'reserves' => [$reserve->id], 'declined' => []])->assertOk()->json('revision');
        $this->postJson($publishUrl, ['publish' => true, 'revision' => $saved, 'fingerprint' => $current['publication']['fingerprint']])->assertOk();
        $new = EventNomination::where('event_id', $event->id)->where('category_event_id', $division->id)->where('player_id', $player->id)->sole();
        $this->assertNotSame($nomination->id, $new->id);
        $this->actingAs($owner)->postJson($this->url($s), ['token' => $token, 'confirmed' => '1'])->assertConflict();
        $this->assertModelExists($new);
        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'cavaliers-nomination')->count());
    }

    public function test_html_late_checkout_rejection_returns_to_event_instead_of_looping_through_confirmation(): void
    {
        $s = $this->scenario(); [$event, $division, $player, $owner] = $s;
        $this->actingAs($owner);
        $review = $this->get($this->url($s))->assertOk();
        $this->order($division, $player, User::factory()->create(), ['status' => 'pending']);
        $this->from($this->url($s))->post($this->url($s), ['token' => $review->viewData('token'), 'confirmed' => '1'])
            ->assertRedirect(route('events.show', $event))->assertSessionHasErrors('nomination');
        $this->from($this->url($s))->get($this->url($s))->assertRedirect(route('events.show', $event))->assertSessionHasErrors('nomination');
        $this->getJson($this->url($s))->assertUnprocessable()->assertJsonValidationErrors('nomination');
        $this->assertModelExists($s[4]);
        $this->assertSame('[]', DB::table('cavaliers_trial_roster_drafts')->where('category_event_id', $division->id)->value('declined'));
        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'cavaliers-nomination')->count());
    }
}
