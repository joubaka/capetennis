<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\Announcement;
use App\Models\BulkEmailLog;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Services\EventAnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAnnouncementAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Queue::fake();
    }

    public function test_preview_and_queue_use_union_of_nominated_and_registered_players(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $nominated = $this->nominate($event, $category, 'nominated@example.com');
        $registered = $this->registerPaid($event, $category, 'registered@example.com');

        $response = $this->actingAs($admin)->get(route('admin.events.announcements', $event));
        $response->assertOk()
            ->assertSee('nominated@example.com')
            ->assertSee('registered@example.com');

        $this->publishWithCurrentAudience($admin, $event)->assertOk()
            ->assertJsonPath('mail.queued', 2)
            ->assertJsonPath('message', 'Announcement published and 2 emails queued.');

        $this->assertEqualsCanonicalizing(
            [$nominated->email, $registered->email],
            BulkEmailLog::query()->pluck('recipient_email')->all(),
        );
        Queue::assertPushed(SendBulkEmailJob::class, 2);
    }

    public function test_announcement_log_preserves_event_and_initiating_actor(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $this->nominate($event, $category, 'announce@example.test');
        $this->publishWithCurrentAudience($admin, $event)->assertOk()->assertJsonPath('mail_level', 'success');
        $payload = BulkEmailLog::sole()->payload;
        $this->assertSame($event->id, $payload['event_id']);
        $this->assertSame($admin->id, $payload['created_by']);
    }

    public function test_zero_queued_announcement_returns_error_feedback_while_preserving_publication_result(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $this->nominate($event, $category, 'announce@example.test');
        $this->mock(\App\Services\BulkMailDispatcher::class)->shouldReceive('dispatch')->once()
            ->andReturn(['total' => 1, 'queued' => 0, 'skipped' => 0, 'failed' => 1, 'invalid' => 0, 'duplicate' => 0]);
        $this->publishWithCurrentAudience($admin, $event)->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('mail_level', 'error')->assertJsonPath('mail.queued', 0)->assertJsonPath('mail.failed', 1);
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_player_in_both_groups_is_queued_once(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $player = $this->nominate($event, $category, 'overlap@example.com');
        $directUser = User::factory()->create(['email' => 'direct-contact@example.com']);
        $linkedUser = User::factory()->create(['email' => 'linked-contact@example.com']);
        $player->update(['userId' => $directUser->id]);
        $player->users()->attach($linkedUser);
        $this->attachPaidRegistration($category, $player);

        $this->publishWithCurrentAudience($admin, $event)->assertOk()
            ->assertJsonPath('mail.queued', 1);

        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => 'overlap@example.com']);
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'direct-contact@example.com']);
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'linked-contact@example.com']);
        Queue::assertPushed(SendBulkEmailJob::class, 1);
    }

    public function test_registration_branch_only_includes_active_paid_players(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $this->registerPaid($event, $category, 'active-paid@example.com');

        foreach ([['active', null, 'unpaid@example.com'], ['withdrawn', 1, 'withdrawn@example.com']] as [$status, $paymentStatus, $email]) {
            $player = Player::factory()->create(['email' => $email]);
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create([
                'category_event_id' => $category->id,
                'registration_id' => $registration->id,
                'status' => $status,
                'payment_status_id' => $paymentStatus,
                'withdrawn_at' => $status === 'withdrawn' ? now() : null,
            ]);
        }

        $this->publishWithCurrentAudience($admin, $event)->assertOk()
            ->assertJsonPath('mail.queued', 1);
        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => 'active-paid@example.com']);
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'unpaid@example.com']);
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'withdrawn@example.com']);
    }

    public function test_invalid_and_missing_addresses_are_excluded_and_other_events_are_isolated(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $this->nominate($event, $category, 'valid@example.com');
        $this->nominate($event, $category, 'not-an-email');
        $this->nominate($event, $category, null);

        $otherEvent = Event::factory()->create();
        $otherCategory = CategoryEvent::factory()->for($otherEvent)->create();
        $this->nominate($otherEvent, $otherCategory, 'other-event@example.com');
        $this->registerPaid($otherEvent, $otherCategory, 'other-paid@example.com');

        $recipients = app(EventAnnouncementService::class)->recipientEmails($event);

        $this->assertSame(['valid@example.com'], $recipients->all());
        $this->publishWithCurrentAudience($admin, $event)->assertOk()
            ->assertJsonPath('mail.queued', 1)->assertJsonPath('mail.skipped', 2)->assertJsonPath('mail.invalid', 2)
            ->assertJsonPath('mail_level', 'warning');
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'other-event@example.com']);
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'other-paid@example.com']);
    }

    public function test_team_roster_players_remain_in_the_announcement_audience(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $teamTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Announcement team event',
            'type' => EventType::TEAM,
            'code' => 'announcement-team-event',
        ]);
        $event->update(['eventType' => $teamTypeId]);
        $event->unsetRelation('eventTypeModel');
        $region = TeamRegion::create(['region_name' => 'Team announcement region']);
        $event->regions()->attach($region->id, ['ordering' => 1]);
        $team = Team::factory()->create([
            'region_id' => $region->id,
            'category_event_id' => $category->id,
        ]);
        $rosterPlayer = Player::factory()->create(['email' => 'roster@example.com']);
        $team->players()->attach($rosterPlayer, ['rank' => 1, 'pay_status' => 0]);

        $this->publishWithCurrentAudience($admin, $event)->assertOk()
            ->assertJsonPath('mail.queued', 1);

        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => 'roster@example.com']);
    }

    public function test_team_announcement_audience_isolated_when_another_event_shares_its_region(): void
    {
        [, $event, $category] = $this->eventAdmin();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Scoped announcement team', 'type' => EventType::TEAM]);
        $event->update(['eventType' => $type]);
        $other = Event::factory()->create(['eventType' => $type]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $region = TeamRegion::create(['region_name' => 'Shared announcement region']);
        foreach ([$event, $other] as $item) $item->regions()->attach($region->id, ['ordering' => 1]);
        foreach ([[$category, 'scoped@example.test'], [$otherCategory, 'foreign@example.test']] as [$teamCategory, $email]) {
            $team = Team::factory()->create(['category_event_id' => $teamCategory->id, 'region_id' => $region->id]);
            $player = Player::factory()->create(['email' => $email, 'userId' => null]);
            $team->players()->attach($player, ['rank' => 1, 'pay_status' => 0]);
        }
        $this->assertSame(['scoped@example.test'], app(EventAnnouncementService::class)->recipientEmails($event)->all());
    }

    public function test_primary_contact_falls_back_to_linked_account_and_email_dedupe_is_case_insensitive(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $linkedOnly = $this->nominate($event, $category, null);
        $linkedAccount = User::factory()->create(['email' => 'Linked@Example.com']);
        $linkedOnly->users()->attach($linkedAccount);
        $this->nominate($event, $category, 'linked@example.com');

        $this->publishWithCurrentAudience($admin, $event)->assertOk()
            ->assertJsonPath('mail.queued', 1);

        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => 'linked@example.com']);
    }

    public function test_dispatch_uses_the_validated_snapshot_without_requerying_the_audience(): void
    {
        [, $event, $category] = $this->eventAdmin();
        $first = $this->nominate($event, $category, 'snapshot@example.com');
        $service = app(EventAnnouncementService::class);
        $snapshot = $service->recipients($event);
        $this->nominate($event, $category, 'added-after-validation@example.com');
        $announcement = Announcement::create([
            'event_id' => $event->id,
            'title' => 'Snapshot contract',
            'message' => '<p>Only the confirmed snapshot.</p>',
        ]);

        $stats = $service->dispatch($announcement, $snapshot);

        $this->assertSame(1, $stats['queued']);
        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => $first->email]);
        $this->assertDatabaseMissing('bulk_email_logs', ['recipient_email' => 'added-after-validation@example.com']);
    }

    public function test_email_send_rejects_an_empty_audience_without_writes(): void
    {
        [$admin, $event] = $this->eventAdmin();

        $this->actingAs($admin)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Empty audience',
            'message' => '<p>Update.</p>',
            'sendMail' => 1,
            'confirm_recipients' => 1,
            'recipient_hash' => app(EventAnnouncementService::class)->recipientHash($event),
        ])->assertUnprocessable()->assertJsonValidationErrors('sendMail');

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_email_send_requires_explicit_confirmation_without_writes(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $this->nominate($event, $category, 'confirmed@example.com');

        $this->actingAs($admin)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Confirmation required',
            'message' => '<p>Update.</p>',
            'sendMail' => 1,
            'recipient_hash' => app(EventAnnouncementService::class)->recipientHash($event),
        ])->assertUnprocessable()->assertJsonValidationErrors('confirm_recipients');

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_changed_recipient_list_rejects_stale_hash_without_writes(): void
    {
        [$admin, $event, $category] = $this->eventAdmin();
        $this->nominate($event, $category, 'first@example.com');
        $staleHash = app(EventAnnouncementService::class)->recipientHash($event);
        $this->nominate($event, $category, 'added@example.com');

        $this->actingAs($admin)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Stale list',
            'message' => '<p>Update.</p>',
            'sendMail' => 1,
            'confirm_recipients' => 1,
            'recipient_hash' => $staleHash,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('confirm_recipients')
            ->assertJsonPath('errors.confirm_recipients.0', 'The recipient list changed. Review the current recipients and confirm again.');

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_portal_only_announcement_still_requires_no_recipient_confirmation(): void
    {
        [$admin, $event] = $this->eventAdmin();

        $this->actingAs($admin)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Portal only',
            'message' => '<p>Published without mail.</p>',
            'sendMail' => 0,
        ])->assertOk()
            ->assertJsonPath('message', 'Announcement published on the event page. No email was sent.');

        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    private function publishWithCurrentAudience(User $admin, Event $event)
    {
        return $this->actingAs($admin)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Event update',
            'message' => '<p>Please read this update.</p>',
            'sendMail' => 1,
            'confirm_recipients' => 1,
            'recipient_hash' => app(EventAnnouncementService::class)->recipientHash($event),
        ]);
    }

    /** @return array{User, Event, CategoryEvent} */
    private function eventAdmin(): array
    {
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->for($event)->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        return [$admin, $event, $category];
    }

    private function nominate(Event $event, CategoryEvent $category, ?string $email): Player
    {
        $player = Player::factory()->create(['email' => $email, 'userId' => null]);
        EventNomination::create([
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'player_id' => $player->id,
        ]);

        return $player;
    }

    private function registerPaid(Event $event, CategoryEvent $category, string $email): Player
    {
        $player = Player::factory()->create(['email' => $email, 'userId' => null]);
        $this->attachPaidRegistration($category, $player);

        return $player;
    }

    private function attachPaidRegistration(CategoryEvent $category, Player $player): void
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        CategoryEventRegistration::factory()->paid()->create([
            'category_event_id' => $category->id,
            'registration_id' => $registration->id,
            'status' => 'active',
        ]);
    }
}
