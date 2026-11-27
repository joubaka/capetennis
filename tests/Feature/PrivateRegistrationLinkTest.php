<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\PrivateRegistrationLinkService;
use App\Http\Middleware\{EnsureAgreementAccepted, EnsurePlayerProfileUpdated};
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventType, Player, PrivateRegistrationLink, Registration, RegistrationOrder, SiteSetting, User, Wallet, WalletTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, File, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivateRegistrationLinkTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private CategoryEvent $category;
    private Player $player;
    private User $admin;
    private User $payer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware([EnsureAgreementAccepted::class, EnsurePlayerProfileUpdated::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Individual', 'type' => EventType::INDIVIDUAL, 'code' => 'private-link-test']);
        $this->event = Event::factory()->create(['name' => 'Private link test event', 'eventType' => $type, 'start_date' => now()->addDays(3)->toDateString(), 'signUp' => false, 'published' => true, 'entryFee' => 300]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id, 'entry_fee' => 175.50]);
        $this->player = Player::factory()->create(['name' => 'Sample', 'surname' => 'Player']);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $this->payer = User::factory()->create(['email' => $this->player->email]);
        $this->payer->players()->attach($this->player);
    }

    private function reviewedContacts(): string
    {
        return app(\App\Services\PrivateRegistrationInvitationService::class)->contacts($this->player->fresh())['fingerprint'];
    }

    private function issue(): array
    {
        $issued = app(PrivateRegistrationLinkService::class)->issue($this->event, $this->category, $this->player, $this->admin, strtolower(trim($this->player->email)), $this->reviewedContacts());
        return [$issued['url'], PrivateRegistrationLink::latest('id')->first()->id];
    }

    private function submit(int $grantId, array $extra = [])
    {
        return $this->actingAs($this->payer)->post(route('pay.now.payfast'), array_replace([
            'private_registration_link_id' => $grantId, 'terms_accepted' => 1,
            'player' => [$this->player->id], 'category' => [$this->category->id],
        ], $extra));
    }

    public function test_closed_event_private_checkout_preserves_withdrawal_and_exact_new_payment(): void
    {
        $orange = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $old = Registration::create();
        $old->players()->attach($this->player);
        $old->categoryEvents()->attach($orange->id, ['user_id' => $this->payer->id, 'payment_status_id' => 1, 'status' => 'withdrawn', 'withdrawn_at' => now(), 'refund_status' => CategoryEventRegistration::REFUND_NOT_REFUNDED]);
        $before = DB::table('category_event_registrations')->where('registration_id', $old->id)->first();
        [$url, $grantId] = $this->issue();
        $landing = $this->actingAs($this->payer)->get($url)->assertOk()->assertSee('Sample Player')->assertSee('R175.50')->assertSee('Continue to payment');
        $this->submit($grantId, ['amount' => 1])->assertOk()->assertViewIs('frontend.payfast.check_out');
        $order = RegistrationOrder::sole();
        $this->assertSame(175.50, (float) $order->total_fee);
        $this->assertSame(175.50, (float) $order->payfast_amount_due);
        $this->assertFalse($order->pay_status);
        $this->assertSame($order->id, (int) PrivateRegistrationLink::sole()->order_id);
        $this->assertEquals($before, DB::table('category_event_registrations')->where('registration_id', $old->id)->first());
        $this->assertFalse((bool) $this->event->fresh()->signUp);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
        $this->assertDatabaseCount('user_players', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->submit($grantId)->assertRedirect(route('registration.checkout', $order));
        $this->assertDatabaseCount('registration_orders', 1);
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->export('private-checkout.html', $landing->getContent());
        Mail::assertNothingOutgoing();
    }

    public function test_admin_generation_is_scoped_and_anonymous_landing_requires_login(): void
    {
        $route = route('admin.category.private-link.create', [$this->event, $this->category]);
        $create = $this->actingAs($this->admin)->get($route)->assertOk()->assertSee('Enable and email registration');
        $result = $this->post(route('admin.category.private-link.store', [$this->event, $this->category]), ['player_id' => $this->player->id, 'reviewed_email' => strtolower(trim($this->player->email)), 'reviewed_contacts' => $this->reviewedContacts()])->assertOk()->assertSee('Copy link');
        $this->assertDatabaseCount('private_registration_links', 1);
        $this->export('private-create.html', $create->getContent());
        $this->export('private-created.html', $result->getContent());
        $this->actingAs($this->payer)->get($route)->assertForbidden();
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->post(route('admin.category.private-link.store', [$this->event, $this->category]), ['player_id' => $this->player->id, 'reviewed_email' => strtolower(trim($this->player->email)), 'reviewed_contacts' => $this->reviewedContacts()])->assertForbidden();
        $foreign = CategoryEvent::factory()->create();
        $this->actingAs($this->admin)->get(route('admin.category.private-link.create', [$this->event, $foreign]))->assertNotFound();
        [$url] = $this->issue();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->assertSame($url, session('url.intended'));
    }

    public function test_cart_tampering_mixed_basket_missing_terms_and_public_checkout_fail(): void
    {
        [, $grantId] = $this->issue();
        $other = Player::factory()->create();
        $foreign = CategoryEvent::factory()->create();
        foreach ([['player' => [$other->id]], ['category' => [$foreign->id]], ['player' => [$this->player->id, $other->id], 'category' => [$this->category->id, $this->category->id]], ['terms_accepted' => 0]] as $extra) {
            $this->submit($grantId, $extra)->assertSessionHasErrors();
        }
        $this->submit(99999999)->assertNotFound();
        $this->actingAs($this->payer)->post(route('pay.now.payfast'), ['player' => [$this->player->id], 'category' => [$this->category->id], 'terms_accepted' => 1])->assertSessionHasErrors();
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertNull(PrivateRegistrationLink::sole()->order_id);
    }

    public function test_expiry_issuer_revocation_and_current_category_gates_are_rechecked(): void
    {
        [$url, $grantId] = $this->issue();
        PrivateRegistrationLink::query()->update(['expires_at' => now()->subSecond()]);
        $this->submit($grantId)->assertSessionHasErrors();
        $unavailable = $this->get($url)->assertGone()->assertSee('Registration link unavailable')->assertDontSee('Continue to payment');
        $this->export('private-unavailable.html', $unavailable->getContent());
        PrivateRegistrationLink::query()->update(['expires_at' => now()->addDay()]);
        DB::table('event_admins')->where('user_id', $this->admin->id)->delete();
        $this->submit($grantId)->assertForbidden();
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        foreach ([['locked_at' => now()], ['cancelled_at' => now()]] as $changes) {
            $this->category->update($changes);
            $this->submit($grantId)->assertSessionHasErrors();
            $this->category->update(['locked_at' => null, 'cancelled_at' => null]);
        }
        $this->event->update(['published' => false]);
        $this->submit($grantId)->assertSessionHasErrors();
        $this->event->update(['published' => true, 'start_date' => today()]);
        $this->submit($grantId)->assertSessionHasErrors();
        $this->assertDatabaseCount('registration_orders', 0);
    }

    public function test_global_shutdown_and_full_division_do_not_create_checkout_or_consume_link(): void
    {
        [, $grantId] = $this->issue();
        SiteSetting::set('registration_open', '0');
        $this->submit($grantId)->assertSessionHasErrors();
        SiteSetting::set('registration_open', '1');
        $this->category->update(['entry_limit' => 0]);
        $this->submit($grantId)->assertSessionHasErrors();
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertNull(PrivateRegistrationLink::sole()->order_id);
    }

    public function test_multiple_links_cannot_create_competing_sponsor_checkouts(): void
    {
        [, $first] = $this->issue();
        [, $second] = $this->issue();
        $this->submit($first)->assertOk();
        $this->payer = User::factory()->create();
        $this->submit($second)->assertForbidden();
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
    }

    public function test_private_order_supports_existing_wallet_reservation_without_early_debit(): void
    {
        [, $grantId] = $this->issue();
        $wallet = Wallet::factory()->forUser($this->payer)->create();
        WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'credit', 'amount' => 100, 'source_type' => 'test_seed', 'source_id' => 0, 'meta' => []]);
        $this->submit($grantId)->assertOk();
        $order = RegistrationOrder::sole();
        $this->postJson(route('registration.hybrid.apply-wallet'), ['order_id' => $order->id])->assertOk()->assertJson(['wallet_applied' => 100, 'payfast_due' => 75.50]);
        $this->assertFalse($order->fresh()->wallet_debited);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('registration_orders', 1);

        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant', 'services.payfast.passphrase_live' => 'test-passphrase', 'services.payment_failure_alert.enabled' => false]);
        $payload = ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => 'PRIVATE-LINK-PF',
            'amount_gross' => '75.50', 'custom_int5' => $order->id, 'custom_int4' => $this->payer->id,
            'custom_int3' => $this->event->id, 'custom_int2' => $this->player->id, 'custom_int1' => $this->category->id];
        $payload['signature'] = md5(http_build_query($payload).'&passphrase=test-passphrase');
        for ($i = 0; $i < 2; $i++) {
            $this->call('POST', route('notify'), $payload, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload))->assertOk();
        }
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertTrue($order->fresh()->wallet_debited);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'debit', 'amount' => 100, 'source_id' => $order->id]);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', ['pf_payment_id' => 'PRIVATE-LINK-PF', 'amount_gross' => 75.50]);
        $this->assertDatabaseHas('category_event_registrations', ['category_event_id' => $this->category->id, 'payment_status_id' => 1]);
        $this->assertFalse((bool) $this->event->fresh()->signUp);
    }

    public function test_agreement_gate_retains_private_return_url(): void
    {
        $this->withMiddleware(EnsureAgreementAccepted::class);
        SiteSetting::set('require_code_of_conduct', '1');
        $agreement = \App\Models\Agreement::create(['title' => 'Test conduct', 'version' => 'test', 'content' => 'Conduct terms', 'is_active' => true]);
        [$url] = $this->issue();
        $this->actingAs($this->payer)->get($url)->assertRedirect(route('agreements.show'));
        $this->assertSame($url, session('url.intended'));
        \App\Models\PlayerAgreement::create(['player_id' => $this->player->id, 'agreement_id' => $agreement->id,
            'accepted_by_type' => 'player', 'accepted_at' => now(), 'content_snapshot' => $agreement->content]);
        $this->get($url)->assertOk()->assertSee('Sample Player');
    }

    public function test_invitation_formats_and_draft_events_cannot_issue_links(): void
    {
        foreach (['masters', EventType::INTERPROVINCIAL_TRIALS_CODE] as $code) {
            DB::table('eventtypes')->where('id', $this->event->eventType)->update(['code' => $code]);
            $this->actingAs($this->admin)->post(route('admin.category.private-link.store', [$this->event, $this->category]), ['player_id' => $this->player->id, 'reviewed_email' => strtolower(trim($this->player->email)), 'reviewed_contacts' => $this->reviewedContacts()])->assertSessionHasErrors();
        }
        DB::table('eventtypes')->where('id', $this->event->eventType)->update(['code' => 'private-link-test', 'type' => EventType::CAMP]);
        $this->post(route('admin.category.private-link.store', [$this->event, $this->category]), ['player_id' => $this->player->id, 'reviewed_email' => strtolower(trim($this->player->email)), 'reviewed_contacts' => $this->reviewedContacts()])->assertSessionHasErrors();
        DB::table('eventtypes')->where('id', $this->event->eventType)->update(['type' => EventType::INDIVIDUAL]);
        $this->event->update(['status' => 'draft']);
        $this->post(route('admin.category.private-link.store', [$this->event, $this->category]), ['player_id' => $this->player->id, 'reviewed_email' => strtolower(trim($this->player->email)), 'reviewed_contacts' => $this->reviewedContacts()])->assertSessionHasErrors();
        $this->assertDatabaseCount('private_registration_links', 0);
    }

    public function test_only_selected_players_linked_account_sees_closed_event_registration(): void
    {
        [$url, $id] = $this->issue();
        $linked = $this->actingAs($this->payer)->get(route('events.show', $this->event))->assertOk()
            ->assertSee('Registration available for your player')->assertSee('Register and pay')->assertSee($url, false);
        $this->export('event-linked.html', $linked->getContent());
        $other = User::factory()->create();
        $other->players()->attach(Player::factory()->create());
        $unlinked = $this->actingAs($other)->get(route('events.show', $this->event))->assertOk()
            ->assertDontSee('Registration available for your player')->assertDontSee($url, false);
        $this->export('event-other-account.html', $unlinked->getContent());
        $this->get($url)->assertForbidden();
        $this->payer = $other;
        $this->submit($id)->assertForbidden();
        auth()->logout();
        $guest = $this->get(route('events.show', $this->event))->assertOk()->assertDontSee('Registration available for your player')->assertDontSee($url, false);
        $this->export('event-guest.html', $guest->getContent());
        $this->assertDatabaseCount('registration_orders', 0);
    }

    public function test_removed_account_link_blocks_redemption_and_other_linked_account_cannot_resume_payers_order(): void
    {
        [$url, $id] = $this->issue();
        $other = User::factory()->create();
        $other->players()->attach($this->player);
        $this->submit($id)->assertOk();
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->get(route('events.show', $this->event))->assertDontSee($url, false);
        $this->payer->players()->detach($this->player);
        $this->actingAs($this->payer)->get($url)->assertForbidden();
        $this->submit($id)->assertForbidden();
        $this->assertDatabaseCount('registration_orders', 1);
    }

    public function test_new_grant_requires_linked_account_and_does_not_link_by_email(): void
    {
        $this->payer->players()->detach($this->player);
        $this->payer->update(['email' => $this->player->email]);
        $this->actingAs($this->admin)->post(route('admin.category.private-link.store', [$this->event, $this->category]),
            ['player_id' => $this->player->id, 'reviewed_email' => $this->player->email, 'reviewed_contacts' => $this->reviewedContacts()])->assertSessionHasErrors();
        $this->assertDatabaseCount('private_registration_links', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_normal_open_registration_remains_sponsored_without_a_player_link(): void
    {
        $this->event->update(['signUp' => true, 'deadline' => 0]);
        $sponsor = User::factory()->create();
        $this->actingAs($sponsor)->post(route('pay.now.payfast'), ['player' => [$this->player->id], 'category' => [$this->category->id], 'terms_accepted' => 1])
            ->assertOk()->assertViewIs('frontend.payfast.check_out');
        $this->assertSame($sponsor->id, (int) RegistrationOrder::sole()->user_id);
        $this->assertFalse($sponsor->players()->whereKey($this->player->id)->exists());
    }

    public function test_managed_invitation_has_one_actionable_primary_and_link_free_copies_with_stale_checks(): void
    {
        $this->player->update(['email' => ' Primary@Example.COM ', 'email_2' => 'extra@example.com', 'email_3' => 'third@example.com']);
        $this->payer->update(['email' => 'primary@example.com']);
        [$url] = $this->issue();
        $this->issue();
        $this->assertDatabaseCount('private_registration_links', 1);
        $this->assertDatabaseCount('bulk_email_logs', 3);
        $this->assertDatabaseCount('jobs', 3);
        $log = \App\Models\BulkEmailLog::where('payload->recipient_kind', 'primary-player')->sole();
        $this->assertSame('primary@example.com', $log->recipient_email);
        $this->assertSame('private_registration_invitation', $log->mail_type);
        $this->assertSame('enqueued', $log->payload['queue_state']);
        $this->assertStringContainsString($url, $log->payload['body']);
        $service = app(\App\Services\PrivateRegistrationInvitationService::class);
        $this->assertNotNull($service->authorizedMailable($log));
        $copies = \App\Models\BulkEmailLog::where('payload->recipient_kind', 'player-notification')->get();
        $this->assertSame(['extra@example.com', 'third@example.com'], $copies->pluck('recipient_email')->sort()->values()->all());
        foreach ($copies as $copy) {
            $this->assertNotNull($service->authorizedMailable($copy));
            $this->assertStringNotContainsString('href', $copy->payload['body']);
            $this->assertStringNotContainsString($url, $copy->payload['body']);
        }
        $reviewedBeforeChange = $this->reviewedContacts();
        $this->player->update(['email_3' => 'replacement@example.com']);
        $this->assertNull($service->authorizedMailable($log));
        foreach ($copies as $copy) $this->assertNull($service->authorizedMailable($copy));
        $this->actingAs($this->admin)->post(route('admin.category.private-link.store', [$this->event, $this->category]),
            ['player_id' => $this->player->id, 'reviewed_email' => 'primary@example.com', 'reviewed_contacts' => $reviewedBeforeChange])->assertSessionHasErrors();
        $this->assertDatabaseCount('private_registration_links', 1);
        $this->player->update(['email' => 'changed@example.com']);
        $this->assertNull($service->authorizedMailable($log));
        $this->issue();
        $this->assertDatabaseCount('private_registration_links', 2);
        $this->assertDatabaseCount('bulk_email_logs', 7);
        $newLog = \App\Models\BulkEmailLog::where('payload->recipient_kind', 'primary-player')->latest('id')->first();
        $this->assertSame('changed@example.com', $newLog->recipient_email);
        $this->assertNull($service->authorizedMailable($log));
        $this->assertNotNull($service->authorizedMailable($newLog));
        DB::table('event_admins')->where('user_id', $this->admin->id)->delete();
        $this->assertNull($service->authorizedMailable($newLog));
        $super = User::factory()->create()->assignRole('super-user');
        $this->admin = $super;
        $this->issue();
        $this->assertDatabaseCount('private_registration_links', 3);
        $this->assertDatabaseCount('bulk_email_logs', 11);
        $this->assertNotNull($service->authorizedMailable(\App\Models\BulkEmailLog::latest('id')->first()));
        $this->category->update(['cancelled_at' => now()]);
        $this->assertNull($service->authorizedMailable(\App\Models\BulkEmailLog::latest('id')->first()));
        Mail::assertNothingOutgoing();
    }

    public function test_recipient_review_missing_contact_and_failed_outbox_do_not_enable_or_send(): void
    {
        $reviewUrl = route('admin.category.private-link.recipient', [$this->event, $this->category, $this->player]);
        $this->actingAs($this->payer)->getJson($reviewUrl)->assertForbidden();
        $this->actingAs($this->admin)->getJson($reviewUrl)->assertOk()->assertJson(['email' => strtolower(trim($this->player->email)), 'linked' => true]);
        $postUrl = route('admin.category.private-link.store', [$this->event, $this->category]);
        $this->post($postUrl, ['player_id' => $this->player->id, 'reviewed_email' => 'old@example.com', 'reviewed_contacts' => $this->reviewedContacts()])->assertSessionHasErrors();
        $this->player->update(['email' => null, 'email_2' => 'secondary@example.com']);
        $this->post($postUrl, ['player_id' => $this->player->id, 'reviewed_email' => 'secondary@example.com', 'reviewed_contacts' => $this->reviewedContacts()])->assertSessionHasErrors();
        $this->player->update(['email' => 'primary@example.com']);
        $this->mock(\App\Services\BulkMailDispatcher::class)->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Test outbox unavailable'));
        $this->post($postUrl, ['player_id' => $this->player->id, 'reviewed_email' => 'primary@example.com', 'reviewed_contacts' => $this->reviewedContacts()])->assertSessionHasErrors();
        $this->assertDatabaseCount('private_registration_links', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_invitation_transport_requires_exact_reviewed_envelope_and_body(): void
    {
        $this->issue();
        $log = \App\Models\BulkEmailLog::sole();
        $log->update(['status' => 'sending']);
        $mail = app(\App\Services\PrivateRegistrationInvitationService::class)->authorizedMailable($log);
        $this->assertNotNull($mail);
        $message = fn () => (new \Symfony\Component\Mime\Email)
            ->from(new \Symfony\Component\Mime\Address($mail->fromAddress, $mail->fromName))
            ->replyTo($mail->replyToAddress)->to($log->recipient_email)->subject($mail->subjectLine)->html($mail->body);
        $event = fn ($email) => new \Illuminate\Mail\Events\MessageSending($email, ['private_registration_invitation_log_id' => $log->id]);
        app('events')->dispatch($event($message()));
        foreach ([fn ($email) => $email->cc('extra@example.com'), fn ($email) => $email->addTo('extra@example.com'),
            fn ($email) => $email->html($mail->body.'<p>Changed content</p>')] as $alter) {
            try {
                app('events')->dispatch($event($alter($message())));
                $this->fail('An altered private invitation reached transport approval.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
        }
        Mail::assertNothingOutgoing();
    }

    public function test_grant_invitation_log_and_queued_job_rollback_together(): void
    {
        try {
            DB::transaction(function () {
                $this->issue();
                $this->assertDatabaseCount('jobs', 1);
                throw new \RuntimeException('Test enclosing transaction rollback');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Test enclosing transaction rollback', $e->getMessage());
        }
        $this->assertDatabaseCount('private_registration_links', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingOutgoing();
    }

    private function export(string $name, string $html): void
    {
        if (getenv('CT_PRIVATE_LINK_QA') !== '1') return;
        File::ensureDirectoryExists(storage_path('app/testing/private-link-qa'));
        file_put_contents(storage_path('app/testing/private-link-qa/'.$name), $html);
    }
}
