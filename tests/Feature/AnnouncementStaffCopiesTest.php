<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\Announcement;
use App\Models\BulkEmailLog;
use App\Models\Event;
use App\Models\User;
use App\Services\EventAnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnnouncementStaffCopiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-user', 'web');
    }

    public function test_posting_without_participant_email_copies_actor_event_admins_and_super_users_once(): void
    {
        $actor = User::factory()->create()->assignRole('super-user');
        $admin = User::factory()->create()->assignRole('admin');
        $otherSuper = User::factory()->create()->assignRole('super-user');
        $foreign = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        $event->admins()->attach([$actor->id, $admin->id]);
        Event::factory()->create()->admins()->attach($foreign);

        $this->actingAs($actor)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Weather update', 'message' => '<p>Start at ten.</p>', 'sendMail' => 0,
        ])->assertOk()->assertJsonPath('mail', null)->assertJsonPath('copies.queued', 3);

        $this->assertEqualsCanonicalizing([$actor->email, $admin->email, $otherSuper->email], BulkEmailLog::pluck('recipient_email')->all());
        foreach (BulkEmailLog::all() as $log) {
            $this->assertSame($event->id, $log->payload['event_id']);
            $this->assertSame($actor->id, $log->payload['created_by']);
            $this->assertSame('admin_copy', $log->payload['recipient_kind']);
            $this->assertStringContainsString('Weather update', $log->payload['message']);
            $this->assertStringContainsString('Start at ten.', $log->payload['message']);
        }
        $announcement = Announcement::sole();
        app(EventAnnouncementService::class)->dispatchCopies($announcement, $actor);
        $this->assertDatabaseCount('bulk_email_logs', 3);
        Queue::assertPushed(SendBulkEmailJob::class, 3);

        $this->patchJson(route('admin.announcements.update', $announcement), ['title' => 'Changed', 'message' => '<p>Changed.</p>'])->assertOk();
        Queue::assertPushed(SendBulkEmailJob::class, 3);
    }

    public function test_staff_already_in_participant_audience_receives_only_one_email(): void
    {
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $announcement = Announcement::create(['event_id' => $event->id, 'title' => 'Update', 'message' => '<p>Content.</p>']);
        $service = app(EventAnnouncementService::class);
        $stats = $service->dispatch($announcement, collect([['email' => strtoupper($actor->email), 'name' => $actor->name]]), $actor);
        $this->assertSame(1, $stats['queued']);
        $this->assertSame(0, $service->dispatchCopies($announcement, $actor)['queued']);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Queue::assertPushed(SendBulkEmailJob::class, 1);
    }

    public function test_failed_copy_queue_preserves_announcement_and_returns_warning(): void
    {
        $actor = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $this->mock(\App\Services\BulkMailDispatcher::class)->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->actingAs($actor)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Update', 'message' => '<p>Content.</p>', 'sendMail' => 0,
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('copies.failed', 1)->assertJsonPath('mail_level', 'warning');
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_foreign_event_admin_cannot_post_or_trigger_staff_copies(): void
    {
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        $this->actingAs($actor)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Update', 'message' => '<p>Content.</p>', 'sendMail' => 0,
        ])->assertForbidden();
        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Queue::assertNothingPushed();
    }

    public static function scopedCopyEvents(): array
    {
        return ['team' => [2, null, false], 'trials' => [1, 'interprovincial-trials', false], 'regional' => [2, null, true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('scopedCopyEvents')]
    public function test_staff_copy_reaches_transport_without_holding_participant_review(int $kind, ?string $code, bool $regional): void
    {
        $actor = User::factory()->create()->assignRole('super-user');
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId(['name' => 'Staff copy transport', 'type' => $kind, 'code' => $code]);
        $event = Event::factory()->create(['eventType' => $type]);
        $attributes = ['event_id' => $event->id, 'title' => 'Copy title', 'message' => '<p>Exact copy content.</p>'];
        if ($regional) {
            $region = \App\Models\TeamRegion::create(['region_name' => 'Copy region']);
            $event->regions()->attach($region);
            $eventRegion = \App\Models\EventRegion::where('event_id', $event->id)->where('region_id', $region->id)->firstOrFail();
            $announcement = \App\Models\TeamSelectionRegionAnnouncement::create($attributes + ['region_id' => $region->id, 'event_region_id' => $eventRegion->id, 'created_by' => $actor->id]);
        } else {
            $announcement = Announcement::create($attributes);
        }
        $service = app(EventAnnouncementService::class);
        $this->assertSame(1, $service->dispatchCopies($announcement, $actor)['queued']);
        $log = BulkEmailLog::sole();
        if ($regional) {
            $this->assertSame($region->id, $log->payload['region_id']);
            $this->assertSame($eventRegion->id, $log->payload['event_region_id']);
        }
        $this->mock(\App\Services\MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        $job = new SendBulkEmailJob($log->id);
        $job->handle();
        $job->handle();
        $this->assertSame('sent', $log->fresh()->status);
        $messages = \Illuminate\Support\Facades\Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame($actor->email, $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('Copy title', $message->getHtmlBody());
        $this->assertStringContainsString('Exact copy content.', $message->getHtmlBody());
        $this->assertSame(0, \App\Models\EventCommunicationBatch::where('status', 'draft')->count());
        $this->assertSame(0, $service->dispatchCopies($announcement, $actor)['queued']);
        $this->assertDatabaseCount('event_communication_batches', 1);
    }
}
