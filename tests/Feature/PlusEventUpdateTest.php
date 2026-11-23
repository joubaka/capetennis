<?php
namespace Tests\Feature;

use App\Jobs\SendPlusEventUpdate;
use App\Mail\PlusEventUpdateMail;
use App\Models\{Announcement, CategoryEvent, CategoryEventRegistration, Draw, Event, Player, PlayerPlusAccessOverride, PlusEventUpdateDelivery, PlusEventUpdateState, Registration, User};
use App\Services\{MailAccountManager, PlusDigestPreferences, PlusEventUpdateService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Tests\TestCase;

class PlusEventUpdateTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(now()->startOfSecond());
        config(['plus_event_updates.delivery_enabled' => true]);
        foreach (array_keys(config('mail.mailers')) as $mailer) config(["mail.mailers.{$mailer}" => ['transport' => 'array']]);
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->andReturn('array');
        Bus::fake();
    }
    private function context(): array
    {
        $user = User::factory()->create(['email' => 'parent@example.test']);
        $player = Player::factory()->create(['userId' => $user->id, 'email' => 'parent@example.test', 'email_2' => 'extra@example.test']);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $event = Event::factory()->create(['published' => true, 'name' => 'Fictional junior event']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $registration = Registration::factory()->create(); $registration->players()->attach($player);
        $entry = CategoryEventRegistration::factory()->create(['registration_id' => $registration->id, 'category_event_id' => $category->id, 'status' => 'active']);
        app(PlusEventUpdateService::class)->reconcile();
        return [$event->fresh(), $user, $player, $entry, $category, $registration];
    }
    private function edit(Event $event): void { $event->update(['information' => 'New venue information '.now()->timestamp]); }
    public function test_ten_minutes_reset_noop_and_combined_latest_announcement(): void
    {
        [$event] = $this->context(); $service = app(PlusEventUpdateService::class);
        $this->assertSame(0, $service->prepareDue());
        $this->edit($event); $state = PlusEventUpdateState::where('event_id', $event->id)->sole();
        $this->assertTrue($state->due_at->equalTo(now()->addMinutes(10)));
        $this->assertEquals($state->snapshot, $service->snapshot($event->fresh()));
        $this->travel(9)->minutes(); $service->record($event); $this->assertSame(0, $service->prepareDue());
        $this->assertSame($state->generation, $state->fresh()->generation);
        $post = Announcement::create(['event_id' => $event->id, 'title' => 'Venue confirmed', 'message' => 'Latest venue']);
        $this->travel(9)->minutes(); $this->assertSame(0, $service->prepareDue());
        $this->travel(1)->minutes(); $this->assertSame(2, $service->prepareDue());
        $delivery = PlusEventUpdateDelivery::where('recipient', 'parent@example.test')->sole();
        $this->assertSame([$post->id], $delivery->changes['announcements']); $this->assertTrue($delivery->changes['information']);
        $this->assertSame(0, $service->prepareDue());
        $this->assertStringContainsString('Latest venue', (new PlusEventUpdateMail($delivery))->body());
    }
    public function test_pending_backlog_carries_unsent_draw_and_announcement_changes_without_old_posts(): void
    {
        [$event, , , , $category, $registration] = $this->context();
        $old = Announcement::create(['event_id' => $event->id, 'title' => 'Old announcement', 'message' => 'Old content']);
        $this->travel(10)->minutes(); app(PlusEventUpdateService::class)->prepareDue();
        PlusEventUpdateDelivery::query()->update(['status' => 'sent']);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => false]);
        $draw->registrations()->attach($registration); $draw->update(['published' => true]);
        $post = Announcement::create(['event_id' => $event->id, 'title' => 'New announcement', 'message' => 'New content']);
        $this->travel(10)->minutes(); app(PlusEventUpdateService::class)->prepareDue();
        $this->travel(1)->minutes(); $this->edit($event);
        $this->travel(10)->minutes(); app(PlusEventUpdateService::class)->prepareDue();
        $delivery = PlusEventUpdateDelivery::where('status', 'pending')->where('recipient', 'parent@example.test')->sole();
        $this->assertTrue($delivery->changes['draws']); $this->assertContains($post->id, $delivery->changes['announcements']);
        $body = (new PlusEventUpdateMail($delivery))->body(); $this->assertStringNotContainsString('Old content', $body);
    }
    public function test_off_snooze_withdrawal_and_revocation_are_rechecked_after_queue(): void
    {
        [$event, $user, $player, $entry] = $this->context(); $service = app(PlusEventUpdateService::class);
        $this->edit($event); $this->travel(10)->minutes(); $service->prepareDue();
        $delivery = PlusEventUpdateDelivery::where('recipient', $user->email)->sole();
        $this->assertTrue($service->current($delivery));
        app(PlusDigestPreferences::class)->update($user, 'off'); $this->assertFalse($service->current($delivery));
        $extra = PlusEventUpdateDelivery::where('recipient', 'extra@example.test')->sole(); $this->assertFalse($service->current($extra));
        (new SendPlusEventUpdate($delivery->id))->handle($service); $this->assertSame('superseded', $delivery->fresh()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
    public function test_raw_changes_during_pause_are_silently_baselined_after_resume_then_new_changes_send(): void
    {
        [$event, $user] = $this->context(); $service = app(PlusEventUpdateService::class); $preferences = app(PlusDigestPreferences::class);
        $preferences->update($user, 'off'); $this->travel(1)->minutes();
        DB::table('events')->where('id', $event->id)->update(['venue_notes' => 'Paused change not captured']);
        $preferences->update($user, 'daily'); $service->reconcile(); $this->travel(10)->minutes();
        $this->assertSame(0, $service->prepareDue());
        $this->edit($event->fresh()); $this->travel(10)->minutes(); $this->assertSame(2, $service->prepareDue());
        $delivery = PlusEventUpdateDelivery::where('recipient', $user->email)->sole();
        $preferences->update($user, 'daily');
        $this->assertTrue($service->current($delivery), 'Repeated active daily save must preserve event updates while morning baseline is pending.');
        $preferences->establishResumeBaseline($user); $this->assertTrue($service->current($delivery), 'Morning baseline must not invalidate event updates.');
    }
    public function test_pending_checkout_other_event_hidden_event_and_revoked_plus_are_excluded(): void
    {
        [$event, $user, $player, $entry] = $this->context();
        $entry->update(['status' => 'pending_checkout']); $this->edit($event); $this->travel(10)->minutes();
        $this->assertSame(0, app(PlusEventUpdateService::class)->prepareDue());
        $entry->update(['status' => 'active']); $this->travel(1)->minutes(); $this->edit($event); $this->travel(10)->minutes();
        app(PlusEventUpdateService::class)->prepareDue(); $delivery = PlusEventUpdateDelivery::where('recipient', $user->email)->sole();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]); $this->assertFalse(app(PlusEventUpdateService::class)->current($delivery));
        $event->update(['published' => false]); $this->assertNull(PlusEventUpdateState::where('event_id', $event->id)->sole()->due_at);
    }
    public function test_transport_claim_is_idempotent_and_final_envelope_tampering_fails(): void
    {
        [$event] = $this->context(); $service = app(PlusEventUpdateService::class);
        $this->edit($event); $this->travel(10)->minutes(); $service->prepareDue();
        $delivery = PlusEventUpdateDelivery::where('recipient', 'parent@example.test')->sole();
        $job = new SendPlusEventUpdate($delivery->id); $job->handle($service); $job->handle($service);
        $this->assertSame('sent', $delivery->fresh()->status); $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(1, count($job->middleware()));
    }
    public function test_current_membership_event_and_preferences_are_rechecked_at_transport(): void
    {
        [$event, $user, $player, $entry] = $this->context(); $service = app(PlusEventUpdateService::class);
        $other = Event::factory()->create(['published' => true]);
        $this->edit($event); $this->travel(10)->minutes(); $service->prepareDue();
        $delivery = PlusEventUpdateDelivery::where('recipient', $user->email)->sole();
        PlayerPlusAccessOverride::where('player_id', $player->id)->update(['mode' => 'suspended']);
        $this->assertFalse($service->current($delivery));
        PlayerPlusAccessOverride::where('player_id', $player->id)->update(['mode' => 'grant']);
        $this->assertTrue($service->current($delivery));
        $category = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $entry->update(['category_event_id' => $category->id]); $this->assertFalse($service->current($delivery));
    }
    public function test_snooze_expiry_and_new_plus_activation_never_send_old_event_changes(): void
    {
        [$event, $user, $player] = $this->context(); $service = app(PlusEventUpdateService::class);
        app(PlusDigestPreferences::class)->update($user, 'snooze7');
        DB::table('events')->where('id', $event->id)->update(['venue_notes' => 'Paused venue']);
        $this->travel(8)->days(); $service->reconcile(); $this->travel(10)->minutes();
        $this->assertSame(0, $service->prepareDue());
        $this->edit($event->fresh()); $this->travel(10)->minutes(); $this->assertSame(2, $service->prepareDue());
        $delivery = PlusEventUpdateDelivery::where('recipient', $user->email)->sole();
        $order = \App\Models\PlayerPlusOrder::create(['player_id' => $player->id, 'user_id' => $user->id,
            'request_token' => (string) \Illuminate\Support\Str::uuid(), 'expires_at' => now()->subYear(),
            'status' => 'completed', 'pay_status' => true, 'payfast_paid' => true, 'activated_at' => now()->subYear()]);
        \App\Models\PlayerPlusMembership::create(['player_id' => $player->id, 'order_id' => $order->id,
            'starts_at' => now()->subYear(), 'ends_at' => now()->subDay()]);
        PlayerPlusAccessOverride::where('player_id', $player->id)->update(['starts_at' => now()]);
        $this->assertFalse($service->current($delivery), 'New activation cannot inherit the earlier campaign.');
    }
    public function test_uncertain_claim_and_tampered_final_envelope_never_resend(): void
    {
        [$event] = $this->context(); $service = app(PlusEventUpdateService::class);
        $this->edit($event); $this->travel(10)->minutes(); $service->prepareDue();
        $delivery = PlusEventUpdateDelivery::where('recipient', 'parent@example.test')->sole();
        $delivery->update(['status' => 'sending', 'claimed_at' => now()]);
        try {
            Mail::mailer('array')->to('wrong@example.test')->send(new PlusEventUpdateMail($delivery));
            $this->fail('Expected final recipient guard to reject tampering.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        $delivery->update(['status' => 'pending']);
        Mail::shouldReceive('mailer')->once()->with('array')->andReturnSelf();
        Mail::shouldReceive('to')->once()->with('parent@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Acceptance uncertain'));
        $job = new SendPlusEventUpdate($delivery->id);
        try { $job->handle($service); $this->fail('Expected transport failure'); } catch (\RuntimeException $exception) { $this->assertSame('Acceptance uncertain', $exception->getMessage()); }
        $this->assertSame('acceptance_unknown', $delivery->fresh()->status); $job->handle($service);
    }
    public function test_accepted_exact_revisions_are_not_repeated_in_morning_and_web_read_is_unchanged(): void
    {
        [$event] = $this->context(); $service = app(PlusEventUpdateService::class);
        $post = Announcement::create(['event_id' => $event->id, 'title' => 'Updated venue', 'message' => 'Fictional courts']);
        $this->edit($event); $this->travel(10)->minutes(); $service->prepareDue();
        $delivery = PlusEventUpdateDelivery::where('recipient', 'parent@example.test')->sole();
        $delivery->update(['status' => 'sent']);
        $this->assertTrue($service->alreadyDelivered('parent@example.test', ['key' => 'announcement:'.$post->id, 'revision' => $post->readRevision(), 'snapshot' => ['event_id' => $event->id]]));
        $this->assertTrue($service->alreadyDelivered('parent@example.test', ['key' => 'event:'.$event->id, 'revision' => app(\App\Services\PlusMorningDigestService::class)->eventRevision($event->fresh()), 'snapshot' => ['event_id' => $event->id]]));
        $this->assertDatabaseCount('plus_digest_seen', 0);
    }
    public function test_only_unsent_contacts_receive_carried_announcement_when_next_generation_is_prepared(): void
    {
        [$event] = $this->context(); $service = app(PlusEventUpdateService::class);
        $post = Announcement::create(['event_id' => $event->id, 'title' => 'Venue announcement', 'message' => 'New courts']);
        $this->travel(10)->minutes(); $service->prepareDue();
        PlusEventUpdateDelivery::where('recipient', 'parent@example.test')->update(['status' => 'sent']);
        $this->travel(1)->minutes(); $this->edit($event); $this->travel(10)->minutes(); $service->prepareDue();
        $parent = PlusEventUpdateDelivery::where('recipient', 'parent@example.test')->where('status', 'pending')->sole();
        $extra = PlusEventUpdateDelivery::where('recipient', 'extra@example.test')->where('status', 'pending')->sole();
        $this->assertArrayNotHasKey('announcements', $parent->changes);
        $this->assertSame([$post->id], $extra->changes['announcements']);
        $this->assertTrue($service->current($parent)); $this->assertTrue($service->current($extra));
    }
    public function test_shared_family_address_keeps_notice_when_one_original_entrant_withdraws(): void
    {
        [$event, $user, $player, $entry, $category] = $this->context();
        $second = Player::factory()->create(['userId' => $user->id, 'email' => $user->email]);
        PlayerPlusAccessOverride::create(['player_id' => $second->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $registration = Registration::factory()->create(); $registration->players()->attach($second);
        CategoryEventRegistration::factory()->create(['registration_id' => $registration->id, 'category_event_id' => $category->id, 'status' => 'active']);
        $this->edit($event); $this->travel(10)->minutes(); app(PlusEventUpdateService::class)->prepareDue();
        $delivery = PlusEventUpdateDelivery::where('recipient', $user->email)->sole();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->assertTrue(app(PlusEventUpdateService::class)->current($delivery));
    }
    public function test_team_members_receive_event_information_before_a_draw_exists(): void
    {
        [$event, $user, $player, $entry, $category] = $this->context();
        $entry->update(['status' => 'pending_checkout']);
        $team = \App\Models\Team::factory()->create(['category_event_id' => $category->id]);
        \App\Models\TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1]);
        app(PlusEventUpdateService::class)->reconcile(); $this->edit($event);
        $this->travel(10)->minutes(); $this->assertSame(2, app(PlusEventUpdateService::class)->prepareDue());
    }
    public function test_silent_baseline_revisions_never_leak_into_a_later_fresh_burst(): void
    {
        [$event, $user] = $this->context(); $service = app(PlusEventUpdateService::class);
        app(PlusDigestPreferences::class)->update($user, 'off');
        $old = Announcement::create(['event_id' => $event->id, 'title' => 'Paused notice', 'message' => 'Old paused details']);
        app(PlusDigestPreferences::class)->update($user, 'daily');
        $service->reconcile();
        $this->travel(1)->minutes();
        $new = Announcement::create(['event_id' => $event->id, 'title' => 'Fresh notice', 'message' => 'New details']);
        $this->travel(10)->minutes(); $this->assertSame(2, $service->prepareDue());
        $delivery = PlusEventUpdateDelivery::where('recipient', $user->email)->sole();
        $this->assertSame([$new->id], $delivery->changes['announcements']);
        $this->assertStringNotContainsString('Old paused details', (new PlusEventUpdateMail($delivery))->body());
    }
}
