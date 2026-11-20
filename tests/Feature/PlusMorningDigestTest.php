<?php

namespace Tests\Feature;

use App\Jobs\SendPlusMorningDigest;
use App\Mail\PlusMorningDigestMail;
use App\Models\{Announcement, Event, PlusDigestDelivery, User};
use App\Services\{MailAccountManager, PlusMorningDigestService};
use App\Services\PlusDigestPreferences;
use App\Services\Performance\PlayerPlusEntitlement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, DB, Mail, Schema};
use Tests\TestCase;

class PlusMorningDigestTest extends TestCase
{
    private PlusMorningDigestService $service;

    public function test_authenticated_preferences_belong_only_to_actor_and_queued_mail_stops_immediately(): void
    {
        $this->get(route('player-plus.notifications'))->assertRedirect(route('login'));
        $this->patchJson(route('player-plus.notifications.update'), ['choice' => 'off'])->assertUnauthorized();
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan(); $this->service->prepare();
        $delivery = PlusDigestDelivery::firstOrFail();
        $actor = User::firstOrFail(); $actor->setRelation('roles', collect());
        $this->actingAs($actor)->patch(route('player-plus.notifications.update'), ['choice' => 'off', 'user_id' => 999])->assertRedirect(route('player-plus.notifications'));
        $this->assertDatabaseHas('plus_digest_preferences', ['user_id' => 1, 'enabled' => false]);
        $this->assertDatabaseMissing('plus_digest_preferences', ['user_id' => 999]);
        Schema::create('site_settings', function (Blueprint $t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        $this->get(route('player-plus.notifications'))->assertOk()->assertSee('Turn morning emails off')->assertSee('Snooze for 30 days');
        $this->assertSame('suppressed', $delivery->fresh()->status);
        (new SendPlusMorningDigest($delivery->id))->handle($this->service);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertFalse($this->service->current($delivery));
        $this->patchJson(route('player-plus.notifications.update'), ['choice' => 'bad'])->assertUnprocessable();
    }

    public function test_snooze_expiry_silently_baselines_even_changes_not_scanned_during_pause(): void
    {
        $this->event(); $this->baseline();
        $preferences = app(PlusDigestPreferences::class); $user = User::firstOrFail();
        $preferences->update($user, 'snooze7');
        $this->assertFalse($preferences->allows($user));
        $this->travel(2)->days(); $this->event(2);
        $this->travel(6)->days();
        $this->assertTrue($preferences->allows($user));
        $this->service->scan(); $this->assertSame(0, $this->service->prepare());
        $this->assertDatabaseCount('plus_digest_deliveries', 0);
        $this->travel(1)->days(); $this->event(3); $this->service->scan();
        $this->assertSame(1, $this->service->prepare());
        $this->assertSame(3, PlusDigestDelivery::firstOrFail()->items[0]['snapshot']['event_id']);
    }

    public function test_manual_resume_no_backlog_repeated_daily_saves_do_not_discard_fresh_changes(): void
    {
        $this->event(); $this->baseline();
        $preferences = app(PlusDigestPreferences::class); $user = User::firstOrFail();
        $preferences->update($user, 'off'); $this->event(2); $this->service->scan();
        $preferences->update($user, 'daily');
        $this->assertSame(0, $this->service->prepare());
        $this->travel(1)->days(); $this->event(3); $this->service->scan();
        $preferences->update($user, 'daily');
        $this->assertSame(1, $this->service->prepare());
        $this->assertCount(1, PlusDigestDelivery::firstOrFail()->items);
        $this->assertSame(3, PlusDigestDelivery::firstOrFail()->items[0]['snapshot']['event_id']);
    }

    public function test_daily_save_after_automatic_snooze_resume_preserves_fresh_updates(): void
    {
        $this->event(); $this->baseline();
        $preferences = app(PlusDigestPreferences::class); $user = User::firstOrFail();
        $preferences->update($user, 'snooze7');
        $this->travel(2)->days(); $this->event(2);
        $this->travel(6)->days(); $this->service->scan();
        $this->assertSame(0, $this->service->prepare(), 'Automatic resumption first establishes a silent baseline.');
        $resumed = $preferences->forUser($user);
        $this->assertFalse((bool) $resumed->needs_baseline);
        $this->assertTrue(\Illuminate\Support\Carbon::parse($resumed->snoozed_until)->isPast());
        $this->travel(1)->days(); $this->event(3); $this->service->scan();
        $preferences->update($user, 'daily');
        $saved = $preferences->forUser($user);
        $this->assertFalse((bool) $saved->needs_baseline, 'An expired, already resumed snooze is not a new pause.');
        $this->assertSame($resumed->resume_after, $saved->resume_after);
        $this->assertSame(1, $this->service->prepare());
        $this->assertCount(1, PlusDigestDelivery::firstOrFail()->items);
        $this->assertSame(3, PlusDigestDelivery::firstOrFail()->items[0]['snapshot']['event_id']);
    }

    public function test_missing_preferences_migration_fails_closed_and_final_guard_rechecks_snooze(): void
    {
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan(); $this->service->prepare();
        $delivery = PlusDigestDelivery::firstOrFail();
        app(PlusDigestPreferences::class)->update(User::firstOrFail(), 'snooze30');
        $this->assertFalse($this->service->current($delivery));
        Schema::drop('plus_digest_preferences');
        $this->assertFalse($this->service->eligible(User::firstOrFail()));
        $this->assertFalse($this->service->current($delivery));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'plus_morning_digest.delivery_enabled' => true, 'mail.mailers.array' => ['transport' => 'array']]);
        DB::purge('sqlite');
        Schema::create('events', function (Blueprint $t) {
            $t->id(); $t->boolean('published'); $t->string('name'); $t->text('information')->nullable();
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable(); $t->integer('deadline')->nullable();
            $t->text('venue_notes')->nullable(); $t->boolean('signUp')->default(false); $t->timestamps();
        });
        Schema::create('announcements', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('event_id'); $t->string('title'); $t->text('message'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email'); $t->timestamps(); });
        Schema::create('players', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('userId')->nullable(); });
        Schema::create('user_players', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('player_id'); $t->timestamps(); });
        Schema::create('player_plus_orders', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('player_id'); $t->string('status'); $t->boolean('pay_status'); $t->boolean('payfast_paid'); $t->timestamp('activated_at')->nullable(); });
        Schema::create('player_plus_memberships', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('player_id'); $t->unsignedBigInteger('order_id'); $t->timestamp('starts_at'); $t->timestamp('ends_at'); });
        Schema::create('player_plus_access_overrides', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('player_id'); $t->string('mode'); $t->timestamp('starts_at'); $t->timestamp('ends_at'); });
        (require database_path('migrations/2026_11_20_120000_create_plus_morning_digests.php'))->up();
        (require database_path('migrations/2026_11_20_180000_create_plus_digest_preferences.php'))->up();
        DB::table('users')->insert(['id' => 1, 'name' => 'Demo parent', 'email' => 'parent@example.test']);
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->andReturn('array');
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-20 06:02:00', 'Africa/Johannesburg'));
        DB::table('players')->insert(['id' => 1, 'userId' => 1]);
        DB::table('player_plus_access_overrides')->insert(['player_id' => 1, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        Bus::fake();
        $this->service = app(PlusMorningDigestService::class);
    }

    private function event(int $id = 1, bool $published = true): void
    {
        DB::table('events')->insert(['id' => $id, 'published' => $published, 'name' => 'Junior event '.$id,
            'information' => 'Registration details', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02']);
    }

    private function baseline(): void
    {
        $this->service->scan();
        $this->assertSame(0, $this->service->prepare());
        $this->travel(1)->days();
    }

    public function test_initial_baseline_no_history_flood_empty_days_and_conditional_idempotent_new_event_digest(): void
    {
        $this->event(); $this->baseline();
        $this->service->scan();
        $this->assertSame(0, $this->service->prepare());
        $this->travel(1)->days();
        $this->event(2); $this->event(3, false);
        $this->service->scan();
        $this->assertSame(1, $this->service->prepare());
        $this->assertSame(0, $this->service->prepare());
        $delivery = PlusDigestDelivery::firstOrFail();
        $this->assertCount(1, $delivery->items);
        $this->assertSame(2, $delivery->items[0]['snapshot']['event_id']);
        $mail = new PlusMorningDigestMail($delivery);
        $this->assertSame('1 new event added - Cape Tennis Plus', $mail->subjectLine());
        $this->assertStringContainsString(route('events.show', 2), $mail->body());
        Bus::assertDispatchedTimes(SendPlusMorningDigest::class, 1);
    }

    public function test_scan_after_six_includes_changes_since_overnight_scan_and_catches_up_accounts(): void
    {
        $this->event(); $this->baseline();
        $this->travelTo(now('Africa/Johannesburg')->setTime(3, 12));
        $this->service->scan(); $this->assertSame(0, $this->service->prepare());
        $this->event(2);
        $this->travelTo(now('Africa/Johannesburg')->setTime(6, 2));
        $this->assertSame(0, $this->service->prepare());
        $this->service->scan(); $this->assertSame(1, $this->service->prepare(1));
        $this->assertDatabaseCount('plus_digest_deliveries', 1);
    }

    public function test_exact_seen_revisions_are_suppressed_and_edited_announcements_become_unseen(): void
    {
        $this->event(); $this->baseline();
        DB::table('announcements')->insert(['id' => 1, 'event_id' => 1, 'title' => 'Nominations', 'message' => 'Open now']);
        $post = Announcement::firstOrFail(); $user = User::firstOrFail();
        $this->assertTrue($this->service->markSeen($user, 'announcement:1', $post->readRevision()));
        $this->service->scan(); $this->assertSame(0, $this->service->prepare());
        $this->travel(1)->days();
        DB::table('announcements')->where('id', 1)->update(['message' => 'Deadline extended']);
        $this->service->scan(); $this->assertSame(1, $this->service->prepare());
        $this->assertSame('announcement', PlusDigestDelivery::firstOrFail()->items[0]['kind']);
    }

    public function test_claim_prunes_hidden_items_without_losing_other_changes_and_sends_once(): void
    {
        $this->event(); $this->baseline(); $this->event(2); $this->event(3);
        $this->service->scan(); $this->service->prepare();
        DB::table('events')->where('id', 2)->update(['published' => false]);
        $job = new SendPlusMorningDigest(PlusDigestDelivery::firstOrFail()->id);
        $job->handle($this->service); $job->handle($this->service);
        $delivery = PlusDigestDelivery::firstOrFail();
        $this->assertSame('sent', $delivery->status); $this->assertCount(1, $delivery->items);
        $this->assertSame(3, $delivery->items[0]['snapshot']['event_id']);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_revoked_entitlement_and_changed_recipient_are_rechecked_before_transport(): void
    {
        $this->event(); $this->baseline(); $this->event(2);
        $this->service->scan(); $this->service->prepare();
        $this->mock(PlayerPlusEntitlement::class)->shouldReceive('discovery')->andReturn(false);
        (new SendPlusMorningDigest(PlusDigestDelivery::firstOrFail()->id))->handle($this->service);
        $this->assertSame('suppressed', PlusDigestDelivery::firstOrFail()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_payload_cap_preserves_later_changes_for_next_day(): void
    {
        $this->event(); $this->baseline();
        for ($id = 2; $id <= 28; $id++) $this->event($id);
        $this->service->scan(); $this->service->prepare();
        $this->assertCount(25, PlusDigestDelivery::firstOrFail()->items);
        $first = PlusDigestDelivery::firstOrFail();
        $first->update(['status' => 'sent']); $this->service->consume($first);
        $this->travel(1)->days(); $this->service->scan(); $this->service->prepare();
        $this->assertCount(2, PlusDigestDelivery::orderByDesc('id')->firstOrFail()->items);
    }

    public function test_inactive_low_id_prefix_does_not_starve_linked_active_plus_accounts(): void
    {
        DB::table('players')->delete(); DB::table('player_plus_access_overrides')->delete();
        for ($id = 2; $id <= 60; $id++) DB::table('users')->insert(['id' => $id, 'name' => 'Demo '.$id, 'email' => 'demo'.$id.'@example.test']);
        DB::table('players')->insert(['id' => 5, 'userId' => null]);
        DB::table('user_players')->insert(['user_id' => 60, 'player_id' => 5]);
        DB::table('player_plus_access_overrides')->insert(['player_id' => 5, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addYear()]);
        $this->event(); $this->service->scan(); $this->service->prepare(1);
        $this->assertDatabaseHas('plus_digest_accounts', ['user_id' => 60]);
        $this->assertDatabaseMissing('plus_digest_accounts', ['user_id' => 1]);
        $this->travel(1)->days(); $this->event(2); $this->service->scan();
        $this->assertSame(1, $this->service->prepare(1));
        $this->assertSame(60, PlusDigestDelivery::firstOrFail()->user_id);
    }

    public function test_disabled_command_does_not_scan_queue_or_send_and_held_attempt_retries_next_day(): void
    {
        config(['plus_morning_digest.delivery_enabled' => false]);
        $this->artisan('player-plus:morning-digest')->assertSuccessful();
        $this->assertDatabaseCount('plus_digest_runs', 0); Bus::assertNothingDispatched();
        config(['plus_morning_digest.delivery_enabled' => true]);
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan(); $this->service->prepare();
        $delivery = PlusDigestDelivery::firstOrFail(); $delivery->update(['status' => 'held']);
        $this->travel(1)->days(); $this->service->scan(); $this->assertSame(1, $this->service->prepare());
        $this->assertSame($delivery->items, PlusDigestDelivery::orderByDesc('id')->firstOrFail()->items);
    }

    public function test_changed_email_expiry_suspension_and_sponsor_only_never_receive_discovery_digest(): void
    {
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan(); $this->service->prepare();
        DB::table('users')->where('id', 1)->update(['email' => 'changed@example.test']);
        (new SendPlusMorningDigest(PlusDigestDelivery::firstOrFail()->id))->handle($this->service);
        $this->assertSame('suppressed', PlusDigestDelivery::firstOrFail()->status);
        $user = User::firstOrFail();
        DB::table('player_plus_access_overrides')->update(['ends_at' => now()->subMinute()]);
        $this->assertFalse($this->service->eligible($user));
        DB::table('player_plus_access_overrides')->update(['ends_at' => now()->addYear(), 'mode' => 'suspended']);
        $this->assertFalse($this->service->eligible($user));
        DB::table('player_plus_access_overrides')->update(['mode' => 'grant']);
        DB::table('players')->update(['userId' => null]);
        $this->assertFalse($this->service->eligible($user));
    }

    public function test_uncertain_transport_consumes_range_once_and_cannot_be_automatically_resent(): void
    {
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan(); $this->service->prepare();
        $delivery = PlusDigestDelivery::firstOrFail();
        Mail::shouldReceive('mailer')->once()->with('array')->andReturnSelf();
        Mail::shouldReceive('to')->once()->with('parent@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Transport acceptance uncertain'));
        $job = new SendPlusMorningDigest($delivery->id);
        try { $job->handle($this->service); $this->fail('Expected transport failure'); } catch (\RuntimeException $e) { $this->assertSame('Transport acceptance uncertain', $e->getMessage()); }
        $this->assertSame('acceptance_unknown', $delivery->fresh()->status);
        $job->handle($this->service);
        $this->travel(1)->days(); $this->service->scan(); $this->assertSame(0, $this->service->prepare());
    }

    public function test_new_event_remains_new_after_edit_and_public_document_metadata_changes_are_detected(): void
    {
        Schema::create('files', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->string('name'); $t->timestamps(); });
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan();
        DB::table('files')->insert(['event_id' => 2, 'name' => 'Nomination details.pdf', 'created_at' => now(), 'updated_at' => now()]);
        $this->service->scan(); $this->service->prepare();
        $delivery = PlusDigestDelivery::firstOrFail();
        $this->assertSame('new_event', $delivery->items[0]['kind']);
        $this->assertSame('Nomination details.pdf', $delivery->items[0]['snapshot']['documents']['rows'][0]['name']);
        $this->assertNull($delivery->items[0]['snapshot']['registration_closes_at']);
        $this->assertStringNotContainsString('Registration closes:', (new PlusMorningDigestMail($delivery))->body());
    }

    public function test_pending_recovery_and_next_day_supersession_do_not_duplicate_transport(): void
    {
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan(); $this->service->prepare();
        $delivery = PlusDigestDelivery::firstOrFail();
        $this->travel(16)->minutes(); $this->service->recoverPending();
        Bus::assertDispatchedTimes(SendPlusMorningDigest::class, 2);
        $this->travel(1)->days(); $this->service->scan(); $this->service->prepare();
        (new SendPlusMorningDigest($delivery->id))->handle($this->service);
        $this->assertSame('superseded', $delivery->fresh()->status);
        $latest = PlusDigestDelivery::orderByDesc('id')->firstOrFail();
        (new SendPlusMorningDigest($latest->id))->handle($this->service);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_pre_transport_hold_preserves_cursor_and_crashed_claim_is_not_retried(): void
    {
        $this->event(); $this->baseline(); $this->event(2); $this->service->scan(); $this->service->prepare();
        $delivery = PlusDigestDelivery::firstOrFail();
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, fn () => false);
        (new SendPlusMorningDigest($delivery->id))->handle($this->service);
        $this->assertSame('held', $delivery->fresh()->status);
        $this->assertSame(0, (int) DB::table('plus_digest_accounts')->value('cursor'));
        $delivery->update(['status' => 'sending', 'claimed_at' => now()]);
        $this->travel(1)->days(); $this->service->scan(); $this->assertSame(0, $this->service->prepare());
        $this->assertSame('acceptance_unknown', $delivery->fresh()->status);
        $this->assertSame($delivery->end_cursor, (int) DB::table('plus_digest_accounts')->value('cursor'));
    }
}
