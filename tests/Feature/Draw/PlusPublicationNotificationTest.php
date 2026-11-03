<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\DrawPublicationService;
use App\Jobs\SendPlusPublicationNotification;
use App\Mail\PlusPublicationMail;
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, OrderOfPlay, Player, PlayerPlusAccessOverride, PlusPublicationDelivery, PlusPublicationState, Registration, Team, TeamFixture, TeamPlayer, TeamTie, User, Venue};
use App\Services\{MailAccountManager, PlusPublicationNotificationService};
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Tests\TestCase;

class PlusPublicationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->travelTo(now()->startOfSecond());
        config(['publication_notifications.delivery_enabled' => true]);
        // Every named managed transport is in-memory, including tests of the real mail listener.
        foreach (array_keys(config('mail.mailers')) as $mailer) config(["mail.mailers.{$mailer}" => ['transport' => 'array']]);
    }

    private function context(bool $public = false, bool $eventPublic = true): array
    {
        $event = Event::factory()->create(['published' => $eventPublic, 'eventType' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => $public]);
        $draw->settings()->create(['workflow' => 'round_robin', 'schedule_visibility' => 'full_schedule']);
        $players = collect(); $registrations = collect();
        foreach (range(1, 4) as $index) {
            $player = Player::factory()->create(['email' => "player{$index}@example.test"]);
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['registration_id' => $registration->id, 'category_event_id' => $category->id]);
            $draw->registrations()->attach($registration);
            $this->grant($player);
            $players->push($player); $registrations->push($registration);
        }
        $fixtures = collect();
        foreach ([0, 2] as $index) $fixtures->push(Fixture::factory()->create(['draw_id' => $draw->id,
            'registration1_id' => $registrations[$index]->id, 'registration2_id' => $registrations[$index + 1]->id,
            'round' => 1, 'match_nr' => $index + 1, 'match_status' => 0]));
        return [$event, $draw, $players, $fixtures];
    }

    private function grant(Player $player): void
    {
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
    }

    private function slot(Draw $draw, Fixture $fixture, string $time = '2026-11-15 08:00:00'): OrderOfPlay
    {
        $venue = Venue::forceCreate(['name' => 'Test courts']);
        $draw->venues()->attach($venue, ['num_courts' => 2]);
        $draw->event->venues()->attach($venue, ['num_courts' => 2]);
        return OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => $time, 'duration_minutes' => 60]);
    }

    public function test_draw_publication_waits_five_minutes_and_repeated_publication_is_a_noop(): void
    {
        [$event, $draw] = $this->context();
        $service = app(PlusPublicationNotificationService::class);
        app(DrawPublicationService::class)->publish($draw);
        $state = PlusPublicationState::sole();
        $this->assertTrue($state->due_at->equalTo(now()->addMinutes(5)));
        $this->assertSame(0, $service->prepareDue());
        $this->travel(4)->minutes();
        app(DrawPublicationService::class)->publish($draw->fresh());
        $this->assertSame($state->generation, $state->fresh()->generation);
        $this->assertSame(0, $service->prepareDue());
        $this->travel(59)->seconds();
        $this->assertSame(0, $service->prepareDue());
        $this->travel(1)->seconds();
        $this->assertSame(4, $service->prepareDue());
        $this->assertDatabaseCount('jobs', 4);
        $this->assertSame(0, $service->prepareDue());
        $this->assertDatabaseCount('plus_publication_deliveries', 4);
    }

    public function test_meaningful_schedule_updates_restart_wait_but_draft_and_identical_republish_do_not(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context(true);
        $slot = $this->slot($draw, $fixtures[0]);
        $publication = app(SchedulePublicationService::class);
        $publication->publish($event, ['draw_id' => $draw->id]);
        $state = PlusPublicationState::sole();
        $this->assertFalse($state->changes['draw']);
        $this->travel(2)->minutes();
        $publication->publish($event, ['draw_id' => $draw->id]);
        $this->assertSame($state->generation, $state->fresh()->generation);
        $slot->update(['court' => '2']);
        $this->assertSame($state->generation, $state->fresh()->generation);
        $publication->publish($event, ['fixture_keys' => ['individual:'.$fixtures[0]->id]]);
        $this->assertTrue($state->fresh()->due_at->equalTo(now()->addMinutes(5)));
        $this->travel(3)->minutes();
        $this->assertSame(0, app(PlusPublicationNotificationService::class)->prepareDue());
        $this->travel(2)->minutes();
        $this->assertSame(2, app(PlusPublicationNotificationService::class)->prepareDue());
        $this->assertSame($players->take(2)->pluck('id')->all(), PlusPublicationDelivery::orderBy('player_id')->pluck('player_id')->all());
    }

    public function test_preview_wait_begins_only_when_draw_and_event_become_public(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context(false, false);
        $this->slot($draw, $fixtures[0]);
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $this->assertNull(PlusPublicationState::sole()->due_at);
        app(DrawPublicationService::class)->publish($draw);
        $this->assertNull(PlusPublicationState::sole()->due_at);
        $this->travel(20)->minutes();
        $event->update(['published' => true]);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
        $this->travel(5)->minutes();
        $this->assertSame(4, app(PlusPublicationNotificationService::class)->prepareDue());
    }

    public function test_hide_and_unpublish_cancel_pending_and_reappearance_gets_a_fresh_wait(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context(true);
        $this->slot($draw, $fixtures[0]);
        $publication = app(SchedulePublicationService::class);
        $publication->publish($event, ['draw_id' => $draw->id]);
        $publication->hide($event, ['draw_id' => $draw->id]);
        $this->assertNull(PlusPublicationState::sole()->due_at);
        $this->travel(15)->minutes();
        $this->assertSame(0, app(PlusPublicationNotificationService::class)->prepareDue());
        $publication->publish($event, ['draw_id' => $draw->id]);
        $this->travel(5)->minutes();
        $this->assertSame(2, app(PlusPublicationNotificationService::class)->prepareDue());
        $delivery = PlusPublicationDelivery::first();
        $draw->refresh()->update(['published' => false]);
        $this->assertFalse(app(PlusPublicationNotificationService::class)->current($delivery));
        $draw->update(['published' => true]);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
        $this->assertFalse(app(PlusPublicationNotificationService::class)->current($delivery));
    }

    public function test_plus_contacts_are_current_deduplicated_and_sponsors_are_not_implicitly_recipients(): void
    {
        [$event, $draw, $players] = $this->context();
        $parent = User::factory()->create(['email' => 'parent@example.test']);
        $players[0]->users()->attach($parent);
        $players[0]->update(['email_2' => ' PARENT@EXAMPLE.TEST ', 'email_3' => 'extra@example.test']);
        $players[1]->plusOverride->update(['mode' => 'suspended']);
        $players[2]->plusOverride->update(['ends_at' => now()]);
        $players[3]->plusOverride->delete();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $service = app(PlusPublicationNotificationService::class);
        $this->assertSame(3, $service->prepareDue());
        $this->assertEqualsCanonicalizing(['player1@example.test', 'parent@example.test', 'extra@example.test'], PlusPublicationDelivery::pluck('recipient')->all());
        $parentDelivery = PlusPublicationDelivery::where('recipient', $parent->email)->sole();
        $players[0]->users()->detach($parent);
        $players[0]->update(['email_2' => null]);
        $this->assertFalse($service->current($parentDelivery));
        $extra = PlusPublicationDelivery::where('recipient', 'extra@example.test')->sole();
        $this->assertTrue($service->current($extra));
        $players[0]->plusOverride->update(['mode' => 'suspended']);
        $this->assertFalse($service->current($extra));
    }

    public function test_withdrawal_cross_event_and_non_plus_players_are_excluded(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context();
        $entry = CategoryEventRegistration::where('registration_id', $fixtures[0]->registration1_id)->sole();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $other = CategoryEvent::factory()->create();
        CategoryEventRegistration::where('registration_id', $fixtures[0]->registration2_id)->update(['category_event_id' => $other->id]);
        $players[2]->plusOverride->delete();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $this->assertSame(1, app(PlusPublicationNotificationService::class)->prepareDue());
        $this->assertSame($players[3]->id, PlusPublicationDelivery::sole()->player_id);
    }

    public function test_draw_and_schedule_changes_combine_and_hiding_schedule_retains_draw_notice(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $this->slot($draw, $fixtures[0]);
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $state = PlusPublicationState::sole();
        $this->assertTrue($state->changes['draw']);
        $this->assertNotEmpty($state->changes['fixtures']);
        app(SchedulePublicationService::class)->hide($event, ['draw_id' => $draw->id]);
        $this->assertTrue($state->fresh()->changes['draw']);
        $this->assertEmpty($state->fresh()->changes['fixtures']);
        $this->travel(5)->minutes();
        $this->assertSame(4, app(PlusPublicationNotificationService::class)->prepareDue());
    }

    public function test_default_disabled_delivery_never_queues_or_sends_and_historical_publications_are_not_backfilled(): void
    {
        [$event, $draw] = $this->context(true);
        app(DrawPublicationService::class)->publish($draw);
        $this->assertNull(PlusPublicationState::sole()->due_at);
        $this->travel(20)->minutes();
        $this->assertSame(0, app(PlusPublicationNotificationService::class)->prepareDue());
        $draw->refresh()->update(['published' => false]);
        app(DrawPublicationService::class)->publish($draw->fresh());
        $this->travel(5)->minutes();
        config(['publication_notifications.delivery_enabled' => false]);
        Mail::fake();
        $this->assertSame(0, app(PlusPublicationNotificationService::class)->prepareDue());
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingSent();
    }

    public function test_transaction_rollback_and_queue_failure_do_not_leave_orphaned_campaign_or_deliveries(): void
    {
        [$event, $draw] = $this->context();
        DB::beginTransaction();
        app(DrawPublicationService::class)->publish($draw);
        DB::rollBack();
        $this->assertDatabaseCount('plus_publication_states', 0);
        app(DrawPublicationService::class)->publish($draw->fresh());
        $this->travel(5)->minutes();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        try {
            app(PlusPublicationNotificationService::class)->prepareDue();
            $this->fail('Queue submission must fail the whole reservation transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Queue unavailable', $exception->getMessage());
        }
        $this->assertNull(PlusPublicationState::sole()->prepared_at);
        $this->assertDatabaseCount('plus_publication_deliveries', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_managed_mail_job_is_idempotent_and_player_links_are_ordinary_authenticated_links(): void
    {
        [$event, $draw, $players] = $this->context();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $service = app(PlusPublicationNotificationService::class);
        $service->prepareDue();
        $delivery = PlusPublicationDelivery::first();
        $job = new SendPlusPublicationNotification($delivery->id);
        $job->handle($service);
        $this->assertSame('sent', $delivery->fresh()->status);
        $transport = Mail::mailer(app(MailAccountManager::class)->getMailer())->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $job->handle($service);
        $this->assertCount(1, $transport->messages());
        $body = (new PlusPublicationMail($delivery))->body();
        $this->assertStringContainsString(e(route('my.tennis', ['player' => $delivery->player_id])), $body);
        $this->assertStringContainsString(e(route('public.roundrobin.show', $draw)), $body);
        $this->assertStringNotContainsString('signature=', $body);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_claimed_or_stale_messages_cannot_send_and_transport_uncertainty_is_not_retried(): void
    {
        [$event, $draw] = $this->context();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $service = app(PlusPublicationNotificationService::class);
        $service->prepareDue();
        $delivery = PlusPublicationDelivery::first();
        $draw->refresh()->update(['published' => false]);
        Mail::fake();
        (new SendPlusPublicationNotification($delivery->id))->handle($service);
        Mail::assertNothingSent();
        $this->assertSame('superseded', $delivery->fresh()->status);
        $draw->update(['published' => true]);
        $this->travel(5)->minutes();
        $service->prepareDue();
        $delivery = PlusPublicationDelivery::where('status', 'pending')->first();
        Mail::swap(\Mockery::mock(\Illuminate\Mail\MailManager::class));
        Mail::shouldReceive('mailer')->once()->andReturnSelf();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Ambiguous transport failure'));
        $job = new SendPlusPublicationNotification($delivery->id);
        try { $job->handle($service); $this->fail('Expected transport failure.'); }
        catch (\RuntimeException) { $this->assertSame('acceptance_unknown', $delivery->fresh()->status); }
        $job->handle($service);
    }

    public function test_team_roster_and_linked_imported_profiles_are_event_scoped(): void
    {
        [$event, $draw, $players] = $this->context(true);
        $draw->registrations()->detach();
        $draw->drawFixtures()->delete();
        $category = CategoryEvent::find($draw->category_event_id);
        $team = Team::factory()->create(['category_event_id' => $category->id]);
        $foreign = Team::factory()->create();
        $draw->teams_in_draw()->attach([$team->id, $foreign->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $players[0]->id, 'rank' => 1]);
        TeamPlayer::create(['team_id' => $foreign->id, 'player_id' => $players[1]->id, 'rank' => 1]);
        \App\Models\NoProfileTeamPlayer::forceCreate(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Player', 'rank' => 2, 'pay_status' => 0, 'player_profile' => $players[2]->id]);
        $ids = app(PlusPublicationNotificationService::class)->participants($draw->fresh());
        $this->assertSame([$players[0]->id, $players[2]->id], $ids->all());
    }

    public function test_fixture_only_individual_participants_include_doubles_without_draw_pivot(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context();
        $doublesPartner = Player::factory()->create();
        $this->grant($doublesPartner);
        $fixtures[0]->registration1->players()->attach($doublesPartner);
        $draw->registrations()->detach();
        $this->assertCount(5, app(PlusPublicationNotificationService::class)->participants($draw));
    }

    public function test_mail_review_rejects_tampered_recipient_and_content(): void
    {
        [$event, $draw] = $this->context();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        app(PlusPublicationNotificationService::class)->prepareDue();
        $delivery = PlusPublicationDelivery::first();
        $delivery->update(['status' => 'sending', 'claimed_at' => now()]);
        $message = new PlusPublicationMail($delivery);
        $listener = app(\App\Listeners\RequireEventEmailReview::class);
        foreach (['recipient', 'body', 'cc', 'subject', 'profile_href', 'text'] as $tamper) {
            $body = $message->body();
            if ($tamper === 'profile_href') $body = str_replace(e(route('my.tennis', ['player' => $delivery->player_id])), 'https://evil.example/steal', $body);
            if ($tamper === 'text') $body = str_replace('has been published', 'has been cancelled', $body);
            $email = (new \Symfony\Component\Mime\Email())->from(new \Symfony\Component\Mime\Address(config('mail.from.address'), config('mail.from.name')))
                ->replyTo(new \Symfony\Component\Mime\Address(config('mail.from.address'), config('mail.from.name')))
                ->to($tamper === 'recipient' ? 'outsider@example.test' : $delivery->recipient)
                ->subject($tamper === 'subject' ? 'Changed subject' : $message->subjectLine())
                ->html($tamper === 'body' ? '<p>Unreviewed content</p>' : $body);
            if ($tamper === 'cc') $email->cc('outsider@example.test');
            try {
                $listener->handle(new \Illuminate\Mail\Events\MessageSending($email, ['publicationDelivery' => $delivery]));
                $this->fail('Tampered '.$tamper.' must be rejected.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
    }

    public function test_scoped_changes_accumulate_and_old_generation_cannot_send(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context(true);
        $first = $this->slot($draw, $fixtures[0]);
        $second = $this->slot($draw, $fixtures[1], '2026-11-15 09:00:00');
        $publication = app(SchedulePublicationService::class);
        $publication->publish($event, ['fixture_keys' => ['individual:'.$fixtures[0]->id]]);
        $this->travel(2)->minutes();
        $publication->publish($event, ['fixture_keys' => ['individual:'.$fixtures[1]->id]]);
        $this->assertCount(2, PlusPublicationState::sole()->changes['fixtures']);
        $this->travel(3)->minutes();
        $this->assertSame(0, app(PlusPublicationNotificationService::class)->prepareDue());
        $this->travel(2)->minutes();
        $this->assertSame(4, app(PlusPublicationNotificationService::class)->prepareDue());
        $old = PlusPublicationDelivery::first();
        $first->update(['court' => '2']);
        $publication->publish($event, ['fixture_keys' => ['individual:'.$fixtures[0]->id]]);
        $this->assertFalse(app(PlusPublicationNotificationService::class)->current($old));
        $this->travel(5)->minutes();
        $this->assertSame(4, app(PlusPublicationNotificationService::class)->prepareDue());
    }

    public function test_flexible_monrad_publication_has_the_same_wait_and_its_own_public_link(): void
    {
        [$event, $draw, $players] = $this->context();
        $draw->drawFixtures()->delete();
        $draw->settings()->update(['workflow' => 'custom_monrad']);
        CategoryEventRegistration::where('category_event_id', $draw->category_event_id)->update(['payment_status_id' => 1, 'status' => 'registered']);
        $slots = [];
        foreach ($draw->registrations as $index => $registration) $slots[['aa', 'ab', 'ba', 'bb'][$index]] = ['type' => 'player', 'id' => $registration->id];
        $service = app(\App\Services\Draw\FlexibleMonradService::class);
        $service->save($draw, ['size' => 4, 'slots' => $slots], 0);
        $record = $service->generate($draw, 1);
        $service->publish($draw, $record->revision, true);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
        $this->travel(5)->minutes();
        $this->assertSame(4, app(PlusPublicationNotificationService::class)->prepareDue());
        $body = (new PlusPublicationMail(PlusPublicationDelivery::first()))->body();
        $this->assertStringContainsString(e(route('public.flexible-monrad.show', $draw)), $body);
    }

    public function test_bulk_publication_is_scoped_and_unauthorized_calls_cannot_create_campaigns(): void
    {
        [$event, $draw] = $this->context();
        $actor = User::factory()->create();
        $url = route('backend.event-draws.bulk-publication', $event);
        $payload = ['operation' => 'draws', 'draw_ids' => [$draw->id]];
        $this->actingAs($actor)->postJson($url, $payload)->assertForbidden();
        $this->assertDatabaseCount('plus_publication_states', 0);
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $actor->assignRole('admin');
        $event->admins()->attach($actor);
        $foreign = Draw::factory()->create();
        $this->postJson($url, ['operation' => 'draws', 'draw_ids' => [$draw->id, $foreign->id]])->assertUnprocessable();
        $this->assertDatabaseCount('plus_publication_states', 0);
        $this->postJson($url, $payload)->assertOk();
        $this->assertSame($draw->id, PlusPublicationState::sole()->draw_id);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
    }

    public function test_paid_membership_qualifies_player_without_notifying_unrelated_payer(): void
    {
        [$event, $draw, $players] = $this->context();
        foreach ($players as $player) $player->plusOverride->delete();
        $sponsor = User::factory()->create(['email' => 'sponsor@example.test']);
        $order = \App\Models\PlayerPlusOrder::create(['player_id' => $players[0]->id, 'user_id' => $sponsor->id,
            'request_token' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'completed', 'pay_status' => true,
            'payfast_paid' => true, 'activated_at' => now()->subDay(), 'expires_at' => now()->subDay()]);
        \App\Models\PlayerPlusMembership::create(['player_id' => $players[0]->id, 'order_id' => $order->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $this->assertSame(1, app(PlusPublicationNotificationService::class)->prepareDue());
        $delivery = PlusPublicationDelivery::sole();
        $this->assertSame($players[0]->email, $delivery->recipient);
        $this->assertNotSame($sponsor->email, $delivery->recipient);
        $this->assertDatabaseCount('user_players', 0);
        $order->update(['payfast_paid' => false]);
        $this->assertFalse(app(PlusPublicationNotificationService::class)->current($delivery));
    }

    public function test_schedule_visibility_changes_restart_pending_public_projection_only(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context(true);
        $fixtures[1]->update(['registration1_id' => $fixtures[0]->registration1_id, 'registration2_id' => $fixtures[0]->registration2_id]);
        $this->slot($draw, $fixtures[0]);
        $this->slot($draw, $fixtures[1], '2026-11-15 09:00:00');
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $this->travel(2)->minutes();
        $draw->settings->update(['schedule_visibility' => 'first_match']);
        $this->assertCount(1, PlusPublicationState::sole()->snapshot['schedule']);
        $this->assertCount(1, PlusPublicationState::sole()->changes['fixtures']);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
        $draw->settings()->delete();
        $draw->unsetRelation('settings');
        $draw->settings()->create(['workflow' => 'round_robin', 'schedule_visibility' => 'first_match']);
        $this->assertCount(1, PlusPublicationState::sole()->snapshot['schedule']);
        $this->travel(1)->minutes();
        $draw->settings()->first()->update(['schedule_visibility' => 'full_schedule']);
        $this->assertCount(2, PlusPublicationState::sole()->snapshot['schedule']);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
    }

    public function test_event_hide_cancels_existing_deliveries_and_republishing_starts_five_minutes(): void
    {
        [$event, $draw] = $this->context();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $service = app(PlusPublicationNotificationService::class);
        $service->prepareDue();
        $delivery = PlusPublicationDelivery::first();
        $event->update(['published' => false]);
        $this->assertFalse($service->current($delivery));
        $this->assertNull(PlusPublicationState::sole()->due_at);
        $this->travel(1)->minutes();
        $event->update(['published' => true]);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
        $this->assertFalse($service->current($delivery));
    }

    public function test_legacy_imported_fixture_profile_and_team_substitution_use_current_event_roster(): void
    {
        [$event, $draw, $players] = $this->context(true);
        $draw->drawFixtures()->delete();
        $draw->registrations()->detach();
        $team = Team::factory()->create(['category_event_id' => $draw->category_event_id]);
        $imported = \App\Models\NoProfileTeamPlayer::forceCreate(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Player', 'rank' => 1, 'pay_status' => 0, 'player_profile' => $players[0]->id]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => 1, 'numSets' => 3, 'match_nr' => 1, 'round_nr' => 1]);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_no_profile_id' => $imported->id]);
        $service = app(PlusPublicationNotificationService::class);
        $this->assertSame([$players[0]->id], $service->participants($draw->fresh())->all());
        $draw->teams_in_draw()->attach($team);
        $team->competitionSubstitutions()->create(['event_id' => $event->id, 'actor_id' => User::factory()->create()->id,
            'request_key' => 'test-substitution', 'fingerprint' => str_repeat('a', 64), 'created_at' => now(),
            'details' => ['scope' => 'next', 'new_type' => 'profile', 'new_identity_id' => $players[1]->id,
            'source_rank' => 1, 'anchor_type' => 'imported', 'anchor_id' => $imported->id]]);
        $this->assertSame([$players[1]->id], $service->participants($draw->fresh())->all());
    }

    public function test_worker_backlog_carries_unsent_notices_without_resending_already_sent_draw_notice(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $service = app(PlusPublicationNotificationService::class);
        $this->assertSame(4, $service->prepareDue());
        $sent = PlusPublicationDelivery::where('player_id', $players[0]->id)->sole();
        $sent->update(['status' => 'sent', 'sent_at' => now()]);
        $this->slot($draw, $fixtures[0]);
        app(SchedulePublicationService::class)->publish($event, ['fixture_keys' => ['individual:'.$fixtures[0]->id]]);
        $this->travel(5)->minutes();
        $this->assertSame(4, $service->prepareDue());
        $latest = PlusPublicationDelivery::where('status', 'pending')->get()->keyBy('player_id');
        $this->assertFalse($latest[$players[0]->id]->changes['draw']);
        $this->assertCount(1, $latest[$players[0]->id]->changes['fixtures']);
        foreach ([1, 2, 3] as $index) $this->assertTrue($latest[$players[$index]->id]->changes['draw']);
        $this->assertEmpty($latest[$players[2]->id]->changes['fixtures']);
        $this->assertSame('sent', $sent->fresh()->status);
        $this->assertSame(3, PlusPublicationDelivery::where('status', 'superseded')->count());
    }

    public function test_queued_delivery_reconciles_changed_public_bracket_into_a_fresh_wait(): void
    {
        [$event, $draw, $players, $fixtures] = $this->context();
        app(DrawPublicationService::class)->publish($draw);
        $this->travel(5)->minutes();
        $service = app(PlusPublicationNotificationService::class);
        $service->prepareDue();
        $old = PlusPublicationDelivery::first();
        $fixtures[0]->update(['registration2_id' => $fixtures[1]->registration2_id]);
        Mail::fake();
        (new SendPlusPublicationNotification($old->id))->handle($service);
        Mail::assertNothingSent();
        $this->assertSame('superseded', $old->fresh()->status);
        $this->assertTrue(PlusPublicationState::sole()->due_at->equalTo(now()->addMinutes(5)));
        $this->travel(5)->minutes();
        $this->assertSame(4, $service->prepareDue());
    }
}
