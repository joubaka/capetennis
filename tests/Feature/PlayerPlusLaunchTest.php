<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\PlayerPlusController;
use App\Listeners\RequireEventEmailReview;
use App\Models\{AuditEvent, BulkEmailLog, Player, PlayerPlusMembership, PlayerPlusOrder, SiteSetting, User};
use App\Services\Performance\{PlayerPlusNotifications, PlayerPlusReadiness, PlayerPlusReconciliation, PlayerPlusService, PlayerRatingAccess, PlayerRatingHistoryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\{Bus, DB, Mail, Schema, URL};
use Illuminate\Support\Str;
use Mockery;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Mime\{Address, Email};
use Tests\TestCase;

class PlayerPlusLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep legacy-price fixtures explicit; new offers default to R699.
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '49900');
        Mail::fake();
        Bus::fake();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        config(['mail.from.address' => 'noreply@capetennis.co.za', 'mail.from.name' => 'Cape Tennis']);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->history(['reason' => null, 'series' => [], 'stale' => false]);
    }

    private function history(array $data): void
    {
        $mock = Mockery::mock(PlayerRatingHistoryService::class);
        $mock->shouldReceive('forPlayer')->andReturn($data);
        $this->app->instance(PlayerRatingHistoryService::class, $mock);
    }

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    private function order(?Player $player = null, ?User $payer = null): PlayerPlusOrder
    {
        return app(PlayerPlusService::class)->start($player ?? Player::factory()->create(), $payer ?? User::factory()->create(), (string) Str::uuid());
    }

    private function itn(PlayerPlusOrder $order, string $provider = 'plus-launch-receipt'): array
    {
        return ['custom_str5' => 'PlayerPlus', 'custom_int2' => $order->player_id, 'custom_int4' => $order->user_id,
            'custom_int5' => $order->id, 'payment_status' => 'COMPLETE', 'amount_gross' => PlayerPlusService::amount($order->price_cents), 'pf_payment_id' => $provider];
    }

    private function activate(PlayerPlusOrder $order, string $provider = 'plus-launch-receipt'): PlayerPlusOrder
    {
        app(PlayerPlusService::class)->handoff($order, $order->user);
        app(PlayerPlusService::class)->receive($this->itn($order, $provider));
        return $order->fresh();
    }

    private function purchaseData(int $price = 49900): array
    {
        return ['request_token' => (string) Str::uuid(), 'confirm' => '1', 'terms_version' => PlayerPlusController::TERMS_VERSION,
            'quoted_price_cents' => $price, 'plan' => 'annual_recurring', 'recurring_consent' => '1'];
    }

    private function export(string $name, $response): void
    {
        if (getenv('CT_PLUS_QA_HTML') !== '1') return;
        \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('framework/testing'));
        file_put_contents(storage_path('framework/testing/player-plus-'.$name.'.html'), $response->getContent());
    }

    public function test_annual_price_setting_is_super_user_only_and_exact(): void
    {
        $url = route('settings.player-plus-price');
        $this->actingAs(User::factory()->create())->postJson($url, ['player_plus_annual_price' => '649.95'])->assertForbidden();
        $this->actingAs($this->admin());
        foreach (['0', '0.99', '1.00', '4.99', '100000', '499.001', '-1', '1e3', '1,000', '0499', 'NaN'] as $value) {
            $this->postJson($url, ['player_plus_annual_price' => $value])->assertUnprocessable()->assertJsonValidationErrors('player_plus_annual_price');
        }
        $this->assertSame(49900, PlayerPlusService::priceCents());
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '499');
        $this->assertSame(PlayerPlusService::PRICE_CENTS, PlayerPlusService::priceCents());
        foreach (['5.00' => PlayerPlusService::MIN_PRICE_CENTS, '99999.99' => PlayerPlusService::MAX_PRICE_CENTS, '649.95' => 64995] as $value => $cents) {
            $this->postJson($url, ['player_plus_annual_price' => $value])->assertOk();
            $this->assertSame($cents, PlayerPlusService::priceCents());
        }
        $this->get(route('player-plus.index'))->assertOk()->assertSee('Choose a player');
        $this->get(route('player-plus.player', Player::factory()->create()))->assertOk()->assertSee('R649.95');
    }

    public function test_legacy_low_pending_checkout_cannot_start_handoff_and_is_not_repriced_or_closed(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create();
        $order = PlayerPlusOrder::create(['player_id' => $player->id, 'user_id' => $payer->id,
            'request_token' => (string) Str::uuid(), 'price_cents' => 100, 'payfast_amount_due' => '1.00', 'expires_at' => now()->addMinutes(5)]);
        $before = $order->fresh()->getAttributes();
        $this->actingAs($payer)->post(route('player-plus.orders.pay', $order))->assertRedirect()->assertSessionHasErrors('membership');
        $this->assertStringContainsString('R5.00 minimum', session('errors')->first('membership'));
        $this->assertStringContainsString('Close it and start a new checkout', session('errors')->first('membership'));
        $again = app(PlayerPlusService::class)->start($player, $payer, $order->request_token);
        $this->assertSame($order->id, $again->id);
        $this->assertSame(100, $again->price_cents);
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('player_plus_orders', 1);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_verified_historical_low_handoff_still_settles_only_its_original_snapshot(): void
    {
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, (string) PlayerPlusService::MIN_PRICE_CENTS);
        $payer = User::factory()->create(); $player = Player::factory()->create(['userId' => $payer->id]);
        $order = PlayerPlusOrder::create(['player_id' => $player->id, 'user_id' => $payer->id,
            'request_token' => (string) Str::uuid(), 'price_cents' => 100, 'payfast_amount_due' => '1.00',
            'payfast_handed_off_at' => now(), 'expires_at' => now()->addMinutes(5)]);
        $order->refresh();
        $handoff = $order->payfast_handed_off_at;
        $expiry = $order->expires_at;
        app(PlayerPlusService::class)->receive($this->itn($order, 'historical-low-verified-receipt'));
        app(PlayerPlusService::class)->receive($this->itn($order, 'historical-low-verified-receipt'));
        $order->refresh();
        $this->assertSame(100, $order->price_cents);
        $this->assertSame('completed', $order->status);
        $this->assertEquals($handoff, $order->payfast_handed_off_at);
        $this->assertEquals($expiry, $order->expires_at);
        $this->assertTrue(app(PlayerRatingAccess::class)->allows($payer, $player));
        $this->assertDatabaseHas('transactions_pf', ['pf_payment_id' => 'historical-low-verified-receipt', 'amount_gross' => '1.00']);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_existing_order_keeps_its_price_after_setting_change_in_forms_receipts_and_membership(): void
    {
        $order = $this->order();
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '79995');
        $this->assertSame($order->id, app(PlayerPlusService::class)->start($order->player, $order->user, $order->request_token)->id);
        $this->assertSame($order->id, $this->order($order->player, $order->user)->id);
        $this->actingAs($order->user)->get(route('player-plus.orders.show', $order))->assertOk()->assertSee('R499.00')->assertDontSee('R799.95');
        $form = app(PlayerPlusService::class)->form($order);
        $this->assertStringContainsString('name="amount" value="499.00"', $form);
        $order = $this->activate($order);
        $this->assertSame(49900, $order->price_cents);
        $statement = app(\App\Services\AccountStatementService::class)->build($order->user, now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(49900, $statement['paid']);
        $this->assertDatabaseHas('transactions_pf', ['custom_str5' => 'PlayerPlus', 'custom_int5' => $order->id, 'amount_gross' => '499.00']);
        $next = $this->order();
        $this->assertSame(79995, $next->price_cents);
        app(PlayerPlusService::class)->handoff($next, $next->user);
        app(PlayerPlusService::class)->receive($this->itn($next, 'new-price-receipt'));
        $this->assertNotNull($next->fresh()->activated_at);
        $this->assertDatabaseCount('player_plus_memberships', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_order_price_cannot_be_rewritten(): void
    {
        $order = $this->order();
        $this->expectException(\LogicException::class);
        $order->update(['price_cents' => 100]);
    }

    public function test_callback_rejects_noncanonical_or_wrong_amounts_before_receipt_creation(): void
    {
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '64995');
        $order = $this->order();
        app(PlayerPlusService::class)->handoff($order, $order->user);
        foreach (['649.94', '649.950', '6.4995e2', '0649.95', '+649.95', '649,95', ' 649.95', '649.95 ', 'NaN', '499.00'] as $amount) {
            try {
                app(PlayerPlusService::class)->receive(array_replace($this->itn($order), ['amount_gross' => $amount]));
                $this->fail('Invalid amount accepted: '.$amount);
            } catch (\Illuminate\Validation\ValidationException) {}
        }
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        app(PlayerPlusService::class)->receive($this->itn($order));
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('player_plus_memberships', 1);
    }

    public function test_purchase_requires_current_disclosure_and_rejects_changed_quote_without_creating_order(): void
    {
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'synthetic-plus-merchant',
            'services.payfast.sandbox_key' => 'synthetic-plus-key', 'services.payfast.passphrase_sandbox' => 'synthetic-plus-passphrase']);
        $payer = User::factory()->create(); $player = Player::factory()->create();
        $url = route('player-plus.store', $player);
        $this->actingAs($payer)->post($url, ['request_token' => (string) Str::uuid(), 'confirm' => '1'])->assertSessionHasErrors(['terms_version', 'quoted_price_cents']);
        $this->post($url, $this->purchaseData(PlayerPlusService::MIN_PRICE_CENTS - 1))->assertSessionHasErrors('quoted_price_cents');
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '59900');
        $this->post($url, $this->purchaseData())->assertSessionHasErrors('membership');
        $this->assertDatabaseCount('player_plus_orders', 0);
        $data = $this->purchaseData(59900);
        $this->post($url, $data)->assertOk()->assertViewIs('frontend.player-plus.launch');
        $order = PlayerPlusOrder::sole();
        $this->assertSame(59900, $order->price_cents);
        $event = AuditEvent::where('action', 'player-plus.purchase-details-accepted')->sole();
        $this->assertSame($payer->id, $event->actor_id);
        $this->assertSame(59900, $event->metadata['price_cents']);
        $this->assertSame(PlayerPlusController::TERMS_VERSION, $event->metadata['terms_version']);
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '69900');
        $this->post($url, $data)->assertRedirect(route('player-plus.orders.show', $order));
        $this->assertSame(1, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        $this->assertDatabaseCount('player_plus_orders', 1);
    }

    public function test_sales_pause_preserves_verified_benefits_and_blocks_new_handoffs(): void
    {
        $owner = User::factory()->create(); $player = Player::factory()->create(['userId' => $owner->id]);
        $order = $this->activate($this->order($player, $owner));
        $pending = $this->order();
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->assertTrue(app(PlayerRatingAccess::class)->allows($owner, $player));
        $this->assertTrue(app(PlayerRatingAccess::class)->allowsDiscovery($owner));
        $this->actingAs($owner)->get(route('player-plus.player', $player))->assertOk()->assertSee('covered until')->assertSee(route('my.tennis.pathway', $player), false);
        $this->actingAs($pending->user)->post(route('player-plus.orders.pay', $pending))->assertNotFound();
        $this->post(route('player-plus.store', $pending->player), $this->purchaseData())->assertNotFound();
        $this->travelTo($order->term_ends_at);
        $this->assertFalse(app(PlayerRatingAccess::class)->allows($owner, $player));
        $this->actingAs($owner)->get(route('player-plus.player', $player))->assertOk()->assertSee('Membership expired')->assertSee('purchases are currently paused');
    }

    public function test_missing_plus_schema_does_not_break_browse_or_orders_pages(): void
    {
        $player = Player::factory()->create(); $user = User::factory()->create();
        Schema::shouldReceive('hasTable')->andReturnUsing(fn ($table) => ! in_array($table, ['player_plus_memberships', 'player_plus_orders'], true));
        $this->actingAs($user)->get(route('player-plus.index'))->assertOk()->assertSee('purchases are currently paused');
        $this->get(route('player-plus.player', $player))->assertOk()->assertSee('purchases are currently paused');
        $this->get(route('player-plus.orders.index'))->assertOk()->assertSee('no Player Plus orders');
        $this->get(route('player-plus.orders.show', 123))->assertNotFound();
        $this->post(route('player-plus.store', $player), $this->purchaseData())->assertNotFound();
    }

    public function test_purchase_page_prioritises_available_plan_and_keeps_explicit_unchecked_consents(): void
    {
        $payer = User::factory()->create();
        $player = Player::factory()->create(['name' => 'Purchase QA', 'surname' => 'Player']);
        SiteSetting::set(PlayerPlusService::MONTHLY_ENABLED_SETTING, '0');
        $response = $this->actingAs($payer)->get(route('player-plus.player', $player))->assertOk()
            ->assertSee('Annual Player Plus')->assertSee('Monthly purchases are currently unavailable')
            ->assertSee('data-plus-plan-choices', false)->assertSee('Review &amp; pay', false)
            ->assertSee('data-plus-stepper', false)->assertDontSee('data-player-plus-benefits', false)
            ->assertDontSee('R69.00')->assertDontSee('data-player-plus-pricing', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//*[@data-plus-player-page]//form');
        $this->assertSame(1, $forms->length);
        $form = $forms->item(0);
        foreach (['confirm', 'recurring_consent'] as $name) {
            $inputs = $xpath->query('.//input[@name="'.$name.'"]', $form);
            $this->assertSame(1, $inputs->length);
            $this->assertTrue($inputs->item(0)->hasAttribute('required'));
            $this->assertFalse($inputs->item(0)->hasAttribute('checked'));
            $this->assertSame(1, $xpath->query('.//label[@for="'.$inputs->item(0)->getAttribute('id').'"]', $form)->length);
        }
        foreach (['request_token', 'terms_version', '_token'] as $name) $this->assertSame(1, $xpath->query('.//input[@name="'.$name.'"]', $form)->length);
        $this->assertSame('annual_recurring', $xpath->query('.//input[@name="plan"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame('49900', $xpath->query('.//input[@name="quoted_price_cents"]', $form)->item(0)->getAttribute('value'));
        $this->assertLessThan(strpos($response->getContent(), 'class="pp-purchase"'), strpos($response->getContent(), 'class="pp-sponsor-note"'));
        $this->assertDatabaseCount('player_plus_orders', 0);
    }

    public function test_purchase_page_validation_errors_are_visible_and_do_not_preselect_consent(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(['userId' => $payer->id]);
        $this->history(['reason' => null, 'stale' => false, 'series' => []]);
        $response = $this->actingAs($payer)->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
            'confirm' => 'Confirm the purchase details.', 'recurring_consent' => 'Authorise recurring payments.',
        ]))])->get(route('player-plus.player', $player))->assertOk()->assertSee('Please review your checkout details')
            ->assertSee('aria-describedby="pp-annual-confirm-error"', false)->assertSee('aria-describedby="pp-annual-recurring-error"', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//*[@data-plus-player-page]//input[@type="checkbox"][@checked]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-plus-readiness]/following::form[contains(@class, "pp-purchase")]')->length);
    }

    public function test_readiness_exposes_only_availability_to_currently_linked_accounts(): void
    {
        $owner = User::factory()->create(); $player = Player::factory()->create(['userId' => $owner->id]);
        $url = route('player-plus.player', $player);
        $this->history(['reason' => null, 'stale' => false, 'series' => ['ball-standard' => [['score' => 73.123456]]]]);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Saved progress is available')->assertDontSee('73.123456');
        $this->history(['reason' => 'private-source-message', 'series' => []]);
        $this->get($url)->assertOk()->assertSee('Awaiting a saved rating update')->assertDontSee('private-source-message');
        $this->history(['reason' => null, 'stale' => false, 'series' => []]);
        $this->get($url)->assertOk()->assertSee('No eligible rating evidence yet');
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $unlinked = $this->actingAs(User::factory()->create())->get($url)->assertOk()->assertViewHas('readiness', null);
        $document = new \DOMDocument;
        $document->loadHTML($unlinked->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $this->assertSame(0, (new \DOMXPath($document))->query('//*[@data-plus-readiness]')->length);
    }

    public function test_payer_order_history_is_paginated_and_private_from_linked_players(): void
    {
        $payer = User::factory()->create(); $linked = User::factory()->create();
        $player = Player::factory()->create(['userId' => $linked->id]);
        for ($i = 0; $i < 21; $i++) PlayerPlusOrder::create(['player_id' => $player->id, 'user_id' => $payer->id, 'request_token' => (string) Str::uuid(), 'price_cents' => 49900, 'expires_at' => now()->addMinutes(5)]);
        $other = $this->order();
        $this->actingAs($payer)->get(route('player-plus.orders.index'))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 21 && $orders->count() === 20)->assertDontSee($other->player->full_name);
        $first = PlayerPlusOrder::where('user_id', $payer->id)->first();
        $this->actingAs($linked)->get(route('player-plus.orders.index'))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        $this->get(route('player-plus.orders.show', $first))->assertForbidden();
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_confirmation_is_reserved_once_for_payer_only_and_not_for_rejected_callback(): void
    {
        $order = $this->order();
        app(PlayerPlusService::class)->handoff($order, $order->user);
        app(PlayerPlusService::class)->receive(array_replace($this->itn($order), ['payment_status' => 'FAILED']));
        $this->assertSame(0, BulkEmailLog::where('mail_type', 'player_plus_activation')->count());
        app(PlayerPlusService::class)->receive($this->itn($order));
        app(PlayerPlusService::class)->receive($this->itn($order));
        $log = BulkEmailLog::where('mail_type', 'player_plus_activation')->sole();
        $this->assertSame(mb_strtolower($order->user->email), $log->recipient_email);
        $this->assertSame(PlayerPlusOrder::class, $log->related_type);
        $this->assertTrue($log->payload['manual_retry_only']);
        $message = app(PlayerPlusNotifications::class)->authorizedMailable($log);
        $this->assertNotNull($message);
        $this->assertStringContainsString('purchased membership term', $message->body);
        $this->assertStringNotContainsString('my-tennis/players/', $message->body);
        $this->travelTo($order->fresh()->term_ends_at->addDay());
        $this->assertStringNotContainsString('Membership is active', app(PlayerPlusNotifications::class)->authorizedMailable($log)->body);
        $order->user->update(['email' => 'changed-payer@example.test']);
        $this->assertNull(app(PlayerPlusNotifications::class)->authorizedMailable($log));
        Mail::assertNothingSent();
    }

    public function test_managed_confirmation_rolls_back_with_checkout_and_submits_only_after_outer_commit(): void
    {
        $order = $this->order();
        DB::beginTransaction();
        $this->activate($order);
        $this->assertSame(1, BulkEmailLog::where('mail_type', 'player_plus_activation')->count());
        Bus::assertNotDispatched(\App\Jobs\SendBulkEmailJob::class);
        DB::rollBack();
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertSame(0, BulkEmailLog::where('mail_type', 'player_plus_activation')->count());
    }

    public function test_transport_guard_rejects_forged_recipient_content_and_envelope(): void
    {
        $order = $this->activate($this->order());
        $log = BulkEmailLog::where('mail_type', 'player_plus_activation')->sole();
        $log->update(['status' => 'sending']);
        $canonical = app(PlayerPlusNotifications::class)->authorizedMailable($log);
        $listener = app(RequireEventEmailReview::class);
        $html = (new \ReflectionMethod($listener, 'previewHtml'))->invoke($listener, $canonical->body);
        $message = (new Email)->from(new Address(config('mail.from.address'), $canonical->fromName))->replyTo($canonical->replyToAddress)
            ->to($log->recipient_email)->subject($canonical->subjectLine)->html($html);
        $data = ['player_plus_notification_log_id' => $log->id, 'event_mail_reviewed' => true];
        $this->assertNull($listener->handle(new MessageSending($message, $data)));
        foreach (['to', 'cc', 'bcc', 'from', 'replyTo', 'subject', 'html', 'text', 'attach'] as $field) {
            $changed = clone $message;
            $changed->{$field}(in_array($field, ['subject', 'html', 'text', 'attach'], true) ? 'Altered' : 'other@example.test');
            try { $listener->handle(new MessageSending($changed, $data)); $this->fail('Forged '.$field.' accepted.'); }
            catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
        $log->update(['status' => 'queued']);
        try { $listener->handle(new MessageSending($message, $data)); $this->fail('Queued log accepted for transport.'); }
        catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $canonicalPayload = $log->payload;
        foreach ([['payload' => array_replace($canonicalPayload, ['kind' => 'reconciliation'])],
            ['payload' => array_replace($canonicalPayload, ['content_fingerprint' => str_repeat('0', 64)])],
            ['related_type' => User::class], ['related_id' => $order->id + 100]] as $change) {
            $log->update(array_replace(['status' => 'sending', 'payload' => $canonicalPayload,
                'related_type' => PlayerPlusOrder::class, 'related_id' => $order->id], $change));
            try { $listener->handle(new MessageSending($message, $data)); $this->fail('Forged managed-log metadata accepted.'); }
            catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
    }

    public function test_ordinary_unreviewed_event_mail_still_requires_review(): void
    {
        $event = \App\Models\Event::factory()->create();
        $event->setRelation('eventTypeModel', (new \App\Models\EventType)->forceFill(['type' => \App\Models\EventType::TEAM]));
        $this->mock(\App\Services\EventCommunicationService::class)->shouldReceive('draftFixed')->once()->andReturn(new \App\Models\EventCommunicationBatch);
        $message = (new Email)->from('noreply@capetennis.co.za')->to('payer@example.test')->subject('Ordinary event mail')->html('<p>Test</p>');
        $this->assertFalse(app(RequireEventEmailReview::class)->handle(new MessageSending($message, ['review_event' => $event])));
    }

    public function test_renewal_reminder_is_once_per_current_term_and_withheld_after_expiry_or_renewal(): void
    {
        $order = $this->activate($this->order());
        $notifications = app(PlayerPlusNotifications::class);
        $this->travelTo($order->term_ends_at->subDays(15));
        $notifications->record($order, 'renewal');
        $this->assertSame(0, BulkEmailLog::where('mail_type', 'player_plus_renewal')->count());
        $this->travelTo($order->term_ends_at->subDays(14));
        $this->artisan('player-plus:notifications')->assertSuccessful();
        $this->assertSame(0, BulkEmailLog::where('mail_type', 'player_plus_renewal')->count());
        $this->artisan('player-plus:notifications --apply')->assertSuccessful();
        $this->artisan('player-plus:notifications --apply')->assertSuccessful();
        $log = BulkEmailLog::where('mail_type', 'player_plus_renewal')->sole();
        $this->assertNotNull($notifications->authorizedMailable($log));
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->assertNull($notifications->authorizedMailable($log));
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $this->travelTo($order->term_ends_at);
        $this->assertNull($notifications->authorizedMailable($log));
        $renewal = $this->order($order->player, $order->user);
        app(PlayerPlusService::class)->handoff($renewal, $renewal->user);
        app(PlayerPlusService::class)->receive($this->itn($renewal, 'renewal-receipt'));
        $this->assertNull($notifications->authorizedMailable($log));
        $this->assertDatabaseCount('player_plus_memberships', 1);
    }

    public function test_confirmation_repair_does_not_starve_behind_missing_payer_contact(): void
    {
        $first = $this->activate($this->order());
        $second = $this->order();
        // Test repair of legacy completed rows without an outbox reservation.
        DB::table('bulk_email_logs')->where('related_type', PlayerPlusOrder::class)->where('related_id', $first->id)->delete();
        $first->user->update(['email' => 'invalid-contact']);
        $second->update(['status' => 'completed', 'pay_status' => true, 'payfast_paid' => true,
            'activated_at' => now(), 'term_starts_at' => now(), 'term_ends_at' => now()->addYear()]);
        $this->artisan('player-plus:notifications --apply --limit=1')->assertSuccessful();
        $this->assertDatabaseHas('bulk_email_logs', ['related_id' => $first->id, 'mail_type' => 'player_plus_activation', 'status' => 'skipped']);
        $this->artisan('player-plus:notifications --apply --limit=1')->assertSuccessful();
        $this->assertDatabaseHas('bulk_email_logs', ['related_id' => $second->id, 'mail_type' => 'player_plus_activation', 'status' => 'queued']);
    }

    public function test_late_and_additional_payment_reviews_preserve_money_and_reopen_only_for_new_receipts(): void
    {
        SiteSetting::set('admin_notification_email', 'plus-operator@example.test');
        $order = $this->activate($this->order());
        $term = $order->term_ends_at->toIso8601String();
        app(PlayerPlusService::class)->receive($this->itn($order, 'extra-one'));
        $order->refresh();
        $service = app(PlayerPlusReconciliation::class);
        $fingerprint = $service->fingerprint($order);
        $admin = $this->admin();
        $url = route('superadmin.player-plus.store', $order);
        $payload = ['payment_fingerprint' => $fingerprint, 'reference' => 'EXTERNAL-REVIEW-123', 'note' => 'Verified provider evidence and retained the approved external resolution.', 'confirm' => '1'];
        $this->actingAs($order->user)->get(route('superadmin.player-plus.index'))->assertForbidden();
        $this->get(route('superadmin.player-plus.show', $order))->assertForbidden();
        $this->post($url, $payload)->assertForbidden();
        $this->actingAs($admin)->get(route('superadmin.player-plus.show', $order))->assertOk()->assertSee('extra-one');
        $before = $order->getAttributes();
        $this->post($url, $payload)->assertRedirect();
        $this->post($url, $payload)->assertRedirect();
        $this->assertSame(1, $service->reviews($order)->count());
        $this->assertSame($before, $order->fresh()->getAttributes());
        $log = BulkEmailLog::where('mail_type', 'player_plus_reconciliation')->sole();
        $this->assertNull(app(PlayerPlusNotifications::class)->authorizedMailable($log));
        app(PlayerPlusService::class)->receive($this->itn($order, 'extra-two'));
        $order->refresh();
        $newFingerprint = $service->fingerprint($order);
        $this->assertNotSame($fingerprint, $newFingerprint);
        $this->assertFalse($service->reviews($order)->where('metadata->payment_fingerprint', $newFingerprint)->exists());
        $this->post($url, $payload)->assertSessionHasErrors('review');
        app(PlayerPlusService::class)->receive($this->itn($order, 'extra-one'));
        $this->assertSame($newFingerprint, $service->fingerprint($order->fresh()));
        app(PlayerPlusService::class)->receive($this->itn($order, 'extra-two'));
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'player_plus_reconciliation')->count());
        $this->assertSame($term, $order->fresh()->term_ends_at->toIso8601String());
        $this->assertDatabaseCount('transactions_pf', 3);
        $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $late = $this->order();
        app(PlayerPlusService::class)->handoff($late, $late->user);
        $this->travelTo($late->fresh()->expires_at);
        app(PlayerPlusService::class)->receive($this->itn($late, 'late-money'));
        $late->refresh();
        $service->review($late, $admin, $service->fingerprint($late), 'EXTERNAL-LATE-REVIEW', 'Reviewed late money and retained external resolution evidence.');
        $this->assertNull($late->fresh()->activated_at);
        $this->assertSame('reconciliation_required', $late->fresh()->status);
        $this->assertDatabaseCount('player_plus_memberships', 1);
    }

    public function test_reconciliation_service_itself_rejects_non_super_user(): void
    {
        $order = $this->order();
        $this->expectException(HttpException::class);
        app(PlayerPlusReconciliation::class)->review($order, $order->user, str_repeat('0', 64), 'reference', 'Resolution note with evidence.');
    }

    public function test_admin_review_renders_one_locked_receipt_snapshot_and_rejects_later_money(): void
    {
        $order = $this->activate($this->order());
        app(PlayerPlusService::class)->receive($this->itn($order, 'snapshot-extra-one'));
        $order->refresh();
        $service = app(PlayerPlusReconciliation::class);
        $expected = hash('sha256', json_encode([$order->id, $order->player_id, $order->user_id, $order->price_cents,
            $service->receipts($order)->get(['id', 'pf_payment_id', 'amount_gross'])->toArray()], JSON_THROW_ON_ERROR));
        $this->assertSame($expected, $service->fingerprint($order));
        $level = DB::transactionLevel();
        $mock = Mockery::mock(PlayerPlusReconciliation::class)->makePartial();
        $mock->shouldReceive('fingerprint')->once()->andReturnUsing(function ($lockedOrder) use ($service, $level) {
            $this->assertGreaterThan($level, DB::transactionLevel());
            return $service->fingerprint($lockedOrder);
        });
        $this->app->instance(PlayerPlusReconciliation::class, $mock);
        $this->actingAs($this->admin())->get(route('superadmin.player-plus.show', $order))->assertOk()
            ->assertViewHas('fingerprint', $expected)->assertSee('2 verified receipts')->assertSee('across every page');
        $this->app->instance(PlayerPlusReconciliation::class, $service);
        app(PlayerPlusService::class)->receive($this->itn($order, 'snapshot-extra-two'));
        $this->post(route('superadmin.player-plus.store', $order), ['payment_fingerprint' => $expected,
            'reference' => 'STALE-REVIEW', 'note' => 'Reviewed the earlier displayed payment evidence.', 'confirm' => '1'])->assertSessionHasErrors('review');
        $this->assertSame(0, $service->reviews($order)->count());
        $this->assertDatabaseCount('transactions_pf', 3);
        $this->assertDatabaseCount('player_plus_memberships', 1);
    }

    public function test_invalid_operator_contact_has_visible_queue_and_explicit_safe_notification_repair(): void
    {
        SiteSetting::set('admin_notification_email', '');
        $order = $this->activate($this->order());
        app(PlayerPlusService::class)->receive($this->itn($order, 'unnotified-extra'));
        $this->assertSame(0, BulkEmailLog::where('mail_type', 'player_plus_reconciliation')->count());
        $this->actingAs($this->admin())->get(route('superadmin.player-plus.index'))->assertOk()->assertSee('Needs review');
        SiteSetting::set('admin_notification_email', 'plus-operator@example.test');
        $this->artisan('player-plus:notifications --order='.$order->id)->assertSuccessful();
        $this->assertSame(0, BulkEmailLog::where('mail_type', 'player_plus_reconciliation')->count());
        $this->artisan('player-plus:notifications --order='.$order->id.' --apply')->assertSuccessful();
        $this->assertSame('plus-operator@example.test', BulkEmailLog::where('mail_type', 'player_plus_reconciliation')->sole()->recipient_email);
    }

    public function test_bounded_repair_cursor_scans_past_invalid_renewal_contact_and_repairs_missing_operator_alerts(): void
    {
        $first = $this->activate($this->order(), 'cursor-first');
        $second = $this->activate($this->order(), 'cursor-second');
        $first->user->update(['email' => 'invalid-contact']);
        SiteSetting::set('admin_notification_email', '');
        app(PlayerPlusService::class)->receive($this->itn($first, 'cursor-first-extra'));
        app(PlayerPlusService::class)->receive($this->itn($second, 'cursor-second-extra'));
        $this->travelTo($first->term_ends_at->subDays(13));
        $this->artisan('player-plus:notifications --apply --limit=1')->assertSuccessful();
        $this->assertSame(0, BulkEmailLog::where('mail_type', 'player_plus_renewal')->count());
        $cache = \Illuminate\Support\Facades\Cache::store('array');
        $cursor = $cache->get('player-plus:notification-cursor:renewal');
        $this->artisan('player-plus:notifications --limit=1')->assertSuccessful();
        $this->assertSame($cursor, $cache->get('player-plus:notification-cursor:renewal'));
        SiteSetting::set('admin_notification_email', 'operator-corrected@example.test');
        $this->artisan('player-plus:notifications --apply --limit=1')->assertSuccessful();
        $this->assertSame($second->id, BulkEmailLog::where('mail_type', 'player_plus_renewal')->sole()->related_id);
        $this->artisan('player-plus:notifications --apply --limit=1')->assertSuccessful();
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'player_plus_reconciliation')->count());
        $this->artisan('player-plus:notifications --apply --limit=1')->assertSuccessful();
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'player_plus_reconciliation')->count());
    }

    public function test_explicit_repair_regenerates_only_skipped_pretransport_content(): void
    {
        $order = $this->activate($this->order());
        $log = BulkEmailLog::where('mail_type', 'player_plus_activation')->sole();
        $order->player->update(['name' => 'Updated QA name']);
        $this->assertNull(app(PlayerPlusNotifications::class)->authorizedMailable($log));
        $log->update(['status' => 'skipped', 'skipped_at' => now()]);
        $this->artisan('player-plus:notifications --order='.$order->id.' --apply')->assertSuccessful();
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'player_plus_activation')->count());
        $new = BulkEmailLog::where('mail_type', 'player_plus_activation')->latest('id')->first();
        $this->assertNotNull(app(PlayerPlusNotifications::class)->authorizedMailable($new));
        $this->artisan('player-plus:notifications --order='.$order->id.' --apply')->assertSuccessful();
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'player_plus_activation')->count());
        foreach (['sent', 'sending', 'acceptance_unknown', 'failed'] as $status) {
            $new->update(['status' => $status]);
            $order->player->update(['name' => 'Updated QA '.$status]);
            $this->artisan('player-plus:notifications --order='.$order->id.' --apply')->assertSuccessful();
            $this->assertSame(2, BulkEmailLog::where('mail_type', 'player_plus_activation')->count());
        }
    }

    public function test_customer_launch_pages_and_synthetic_browser_fixtures(): void
    {
        URL::forceRootUrl('http://localhost');
        $payer = User::factory()->create(['name' => 'Plus QA Parent', 'email' => 'plus-parent@example.test']);
        $player = Player::factory()->create(['name' => 'Plus QA', 'surname' => 'Player', 'userId' => $payer->id]);
        $this->actingAs($payer);
        $this->export('orders-empty', $this->get(route('player-plus.orders.index'))->assertOk());
        $this->history(['reason' => null, 'stale' => false, 'series' => ['ball-standard' => [['score' => 50]]]]);
        $this->export('index', $this->get(route('player-plus.index'))->assertOk()->assertSee('data-plus-stepper', false)->assertSee('Who is Player Plus for?')->assertDontSee('data-player-plus-benefits', false));
        $this->export('player', $this->get(route('player-plus.player', $player))->assertOk()->assertSee('Saved progress is available'));
        SiteSetting::set(PlayerPlusService::MONTHLY_ENABLED_SETTING, '1');
        $this->export('player-monthly', $this->get(route('player-plus.player', $player))->assertOk()->assertSee('Monthly Player Plus'));
        SiteSetting::set(PlayerPlusService::MONTHLY_ENABLED_SETTING, '0');
        $this->export('terms', $this->get(route('player-plus.terms'))->assertOk()->assertSee('Purchase details version'));
        $order = $this->order($player, $payer);
        $this->export('orders', $this->get(route('player-plus.orders.index'))->assertOk());
        $this->export('order', $this->get(route('player-plus.orders.show', $order))->assertOk());
        $order = $this->activate($order);
        $this->export('player-active', $this->get(route('player-plus.player', $player))->assertOk()->assertSee('View tennis pathway')->assertDontSee('data-plus-stepper', false));
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->export('paused', $this->get(route('player-plus.index'))->assertOk()->assertSee('purchases are currently paused'));
        $this->export('player-paused', $this->get(route('player-plus.player', Player::factory()->create()))->assertOk()->assertSee('Purchases are paused'));
        SiteSetting::set('admin_notification_email', 'plus-operator@example.test');
        app(PlayerPlusService::class)->receive($this->itn($order, 'qa-extra-receipt'));
        $this->actingAs($this->admin());
        $this->export('admin', $this->get(route('superadmin.player-plus.show', $order))->assertOk());
        if (getenv('CT_PLUS_QA_HTML') === '1') {
            $this->export('settings', $this->get(route('settings.index'))->assertOk()->assertSee('Save annual price'));
        }
    }
}
