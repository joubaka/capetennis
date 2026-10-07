<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\{BulkEmailLog, CategoryEvent, CategoryEventRegistration, Event, EventCommunicationBatch, EventNomination, Player, Registration, Team, TeamRegion, User};
use App\Services\{EventAnnouncementService, EventCommunicationService, MailAccountManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AllEventEmailSetupTest extends TestCase
{
    use RefreshDatabase;

    public static function workflows(): array
    {
        return [
            'individual' => [1, null], 'team' => [2, null], 'camp' => [3, null],
            'type 4' => [4, null], 'type 5' => [5, null], 'type 6' => [6, null],
            'type 7' => [7, null], 'Masters' => [1, 'masters'],
            'Interprovincial Trials' => [1, 'interprovincial-trials'],
        ];
    }

    private function setupAudience(int $kind, ?string $code): array
    {
        Queue::fake();
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Mail regression '.$kind, 'type' => $kind, 'code' => $code]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => false, 'signUp' => 0]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = null;
        if ($event->isTeam()) {
            $region = TeamRegion::create(['region_name' => 'Mail regression region']);
            $event->regions()->attach($region);
            $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id, 'published' => false]);
        }
        $players = collect();
        foreach (['First', 'Second', 'Missing'] as $name) {
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Recipient', 'email' => $name === 'Missing' ? null : 'shared@example.test', 'userId' => null]);
            $players->push($player);
            if ($team) {
                $team->players()->attach($player, ['rank' => $players->count(), 'pay_status' => 0]);
            } elseif ($event->isInterprovincialTrials()) {
                EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
            } else {
                $registration = Registration::factory()->create();
                $registration->players()->attach($player);
                CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
            }
        }
        // A matching address or name elsewhere must never expand this event's audience.
        $foreignCategory = CategoryEvent::factory()->create();
        $foreignRegistration = Registration::factory()->create();
        $foreignRegistration->players()->attach(Player::factory()->create(['email' => 'foreign@example.test', 'userId' => null]));
        CategoryEventRegistration::factory()->create(['category_event_id' => $foreignCategory->id, 'registration_id' => $foreignRegistration->id, 'payment_status_id' => 1]);
        $this->actingAs($actor);

        return [$event, $actor, $players];
    }

    private function sendExactlyOnce(BulkEmailLog $log): void
    {
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        $job = new SendBulkEmailJob($log->id);
        $job->handle();
        $job->handle();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('shared@example.test', $message->getTo()[0]->getAddress());
        $this->assertSame($log->payload['subject'], $message->getSubject());
        $text = fn (string $html) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $this->assertSame($text($log->payload['body']), $text($message->getHtmlBody()));
        $this->assertSame(1, BulkEmailLog::where('status', 'sent')->count());
        $this->assertSame(0, BulkEmailLog::where('recipient_email', 'foreign@example.test')->count());
        $this->assertSame(0, EventCommunicationBatch::where('status', 'draft')->count());
    }

    #[DataProvider('workflows')]
    public function test_approved_communication_sends_once_with_exact_event_audience_and_player_coverage(int $kind, ?string $code): void
    {
        [$event, $actor, $players] = $this->setupAudience($kind, $code);
        $service = app(EventCommunicationService::class);
        $batch = $service->preview($event, $actor, ['scope' => 'all', 'filter' => 'all', 'recipients' => 'players'], 'Event update', 'Arrangements for this event');
        $this->assertSame(['shared@example.test'], array_column($batch->recipients, 'email'));
        $this->assertEqualsCanonicalizing($players->take(2)->map(fn ($player) => 'player:'.$player->id)->all(), $batch->recipients[0]['player_keys']);
        $this->assertCount(1, $batch->issues);
        $this->assertStringContainsString('Missing Recipient', $batch->issues[0]);
        $this->assertStringContainsString('First Recipient', $batch->recipients[0]['html']);
        $this->assertStringContainsString('Second Recipient', $batch->recipients[0]['html']);
        $this->assertSame(1, $service->approve($batch, $actor, true)['queued']);
        $this->assertSame(0, $service->approve($batch, $actor, true)['queued']);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $log = $batch->logs()->where('status', 'queued')->sole();
        $this->assertSame($event->id, $log->payload['event_id']);
        $this->assertSame($actor->id, $log->payload['created_by']);
        $this->sendExactlyOnce($log);
        $log->update(['evidence_status' => 'server_accepted', 'accepted_at' => now()]);
        $this->assertSame(2, $batch->serverAcceptedPlayerCount());
        $this->get(route('backend.event-communications.index', ['event' => $event, 'batch' => $batch->id, 'report_scope' => 'batch']))->assertOk()->assertSee('Event update')->assertSee('shared@example.test');
    }

    #[DataProvider('workflows')]
    public function test_confirmed_announcement_sends_once_and_has_a_communications_record(int $kind, ?string $code): void
    {
        [$event, , $players] = $this->setupAudience($kind, $code);
        $service = app(EventAnnouncementService::class);
        $snapshot = $service->audienceSnapshot($event);
        $this->assertSame(['shared@example.test'], $snapshot['recipients']->pluck('email')->all());
        $this->assertCount(1, $snapshot['excluded']);
        $response = $this->postJson(route('admin.events.announcements.store', $event), [
            'title' => 'Announcement regression', 'message' => '<p>Exact announcement content</p>',
            'sendMail' => 1, 'confirm_recipients' => 1, 'recipient_hash' => $service->recipientHash($event),
        ])->assertOk()->assertJsonPath('mail.queued', 1);
        $batch = EventCommunicationBatch::sole();
        $this->assertNotNull($batch->approved_at);
        $this->assertSame($event->id, $batch->event_id);
        $reportUrl = $event->isTeam()
            ? route('backend.event-communications.index', ['event' => $event->id, 'batch' => $batch->id, 'report_scope' => 'batch'])
            : route('backend.event-mail-log.index', $event);
        $response->assertJsonPath('report_url', $reportUrl);
        if ($event->isTeam()) {
            $this->assertEqualsCanonicalizing($players->take(2)->map(fn ($player) => 'player:'.$player->id)->all(), $batch->recipients[0]['player_keys']);
        }
        $log = $batch->logs()->where('status', 'queued')->sole();
        $this->assertSame('event_announcement', $log->mail_type);
        $this->assertSame($event->id, $log->payload['event_id']);
        $this->assertStringContainsString('Exact announcement content', $log->payload['body']);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->assertSame(1, $batch->logs()->where('status', 'skipped')->count());
        $this->sendExactlyOnce($log);
        $this->get(route('backend.event-communications.index', $event))->assertOk()->assertSee('Announcement regression');
    }
}
