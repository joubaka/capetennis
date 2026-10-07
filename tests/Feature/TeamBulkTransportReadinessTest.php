<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\{BulkEmailLog, Event, EventCommunicationBatch, EventType, User};
use App\Services\{EventCommunicationService, MailAccountManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamBulkTransportReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_team_announcement_sends_to_every_linked_roster_profile_and_parent_without_invitation_gates(): void
    {
        Queue::fake();
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Imported roster email', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => false, 'signUp' => 0, 'status' => 'closed']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $region = \App\Models\TeamRegion::create(['region_name' => 'Announcement roster region']);
        $event->regions()->attach($region->id);
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = \App\Models\Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id, 'published' => false]);
        $parent = User::factory()->create(['email' => 'shared-parent@example.test']);
        for ($rank = 1; $rank <= 8; $rank++) {
            $player = \App\Models\Player::factory()->create(['email' => 'roster'.$rank.'@example.test', 'userId' => null]);
            $player->users()->attach($parent);
            \App\Models\NoProfileTeamPlayer::create([
                'team_id' => $team->id, 'rank' => $rank, 'name' => 'Imported', 'surname' => 'Player '.$rank,
                'player_profile' => $player->id, 'email' => 'discard-imported'.$rank.'@example.test', 'pay_status' => 0,
            ]);
        }
        $service = app(\App\Services\EventAnnouncementService::class);
        $response = $this->actingAs($actor)->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Team arrangements', 'message' => '<p>Every roster player receives this announcement.</p>',
            'sendMail' => 1, 'confirm_recipients' => 1, 'recipient_hash' => $service->recipientHash($event),
        ])->assertOk()->assertJsonPath('mail.queued', 9);
        $snapshot = EventCommunicationBatch::whereNotNull('approved_at')->sole();
        $response->assertJsonPath('report_url', route('backend.event-communications.index', ['event' => $event->id, 'batch' => $snapshot->id, 'report_scope' => 'batch']));
        $this->assertSame(8, collect($snapshot->recipients)->flatMap(fn ($recipient) => $recipient['player_keys'])->unique()->count());
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->times(9)->andReturn('array');
        foreach (BulkEmailLog::all() as $log) {
            $job = new SendBulkEmailJob($log->id);
            $job->handle();
            $job->handle();
            $this->assertSame('sent', $log->fresh()->status);
        }

        $this->assertCount(9, Mail::mailer('array')->getSymfonyTransport()->messages());
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame($snapshot->recipients[0]['html'], $message->getHtmlBody());
        $this->assertSame(0, EventCommunicationBatch::where('status', 'draft')->count());
        $this->assertDatabaseCount('bulk_email_logs', 9);
        $this->assertSame(0, BulkEmailLog::where('recipient_email', 'like', 'discard-imported%')->count());
        $this->assertSame(1, BulkEmailLog::where('recipient_email', $parent->email)->count());
        $this->assertSame(0, $snapshot->serverAcceptedPlayerCount());
        BulkEmailLog::where('recipient_email', $parent->email)->sole()->update(['evidence_status' => 'server_accepted', 'accepted_at' => now()]);
        $this->assertSame(8, $snapshot->serverAcceptedPlayerCount());
        $this->actingAs($actor)->get(route('backend.event-communications.index', $event))->assertOk()->assertSee('Team arrangements');
    }

    public function test_legacy_held_team_announcement_can_be_reviewed_and_sent_without_a_false_failure_or_duplicate(): void
    {
        Queue::fake();
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Roster team event', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->twice()->andReturn('array');
        $original = BulkEmailLog::create([
            'mail_type' => 'event_announcement', 'recipient_email' => 'roster@example.test', 'status' => 'queued',
            'payload' => ['event_id' => $event->id, 'created_by' => $actor->id, 'event_name' => $event->name, 'title' => 'Roster news', 'message' => 'All roster players can receive this update.'],
        ]);
        $legacyJob = new SendBulkEmailJob($original->id);
        $legacyJob->handle();

        $this->assertSame('skipped', $original->fresh()->status);
        $this->assertNull($original->fresh()->failed_at);
        $this->assertNull($original->fresh()->sent_at);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        $draft = EventCommunicationBatch::where('status', 'draft')->sole();
        $service = app(EventCommunicationService::class);
        $review = $service->previewDraft($draft, $actor);
        $this->assertSame(1, $service->approve($review, $actor, false)['queued']);
        $approved = $review->logs()->sole();
        $job = new SendBulkEmailJob($approved->id, true);
        $job->handle();
        $job->handle();
        $legacyJob->handle();

        $this->assertSame('sent', $approved->fresh()->status);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(0, $service->approve($review, $actor, false)['queued']);
        $this->assertSame('skipped', $original->fresh()->status);
    }
}
