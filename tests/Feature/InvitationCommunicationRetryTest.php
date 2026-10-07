<?php

namespace Tests\Feature;

use App\Jobs\SendInterprovincialTrialInvitationEmailJob;
use App\Jobs\SendTeamSelectionInvitationEmailJob;
use App\Models\{BulkEmailLog, CategoryEvent, Event, EventRegion, EventRegionManager, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, Team, TeamRegion, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\{EventCommunicationService, InvitationMailSecurity};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvitationCommunicationRetryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $trial = false): array
    {
        Bus::fake();
        Mail::fake();
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => $trial ? 'Interprovincial Trials' : 'Team tournament', 'type' => $trial ? EventType::INDIVIDUAL : EventType::TEAM, 'code' => $trial ? EventType::INTERPROVINCIAL_TRIALS_CODE : 'retry-team']);
        $event = Event::factory()->create(['eventType' => $type]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $region = TeamRegion::create(['region_name' => 'Retry region']);
        $eventRegion = EventRegion::findOrFail(DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id]));
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        if ($trial) {
            $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $actor->id]);
            $invitation = InterprovincialTrialInvitation::create(['event_id' => $event->id, 'batch_id' => $batch->id, 'category_event_id' => $category->id, 'player_id' => $player->id, 'nomination_id' => 1, 'status' => 'failed']);
            $mailType = 'interprovincial_trial_invitation';
        } else {
            $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]);
            $import = TeamSelectionImport::create(['source_id' => $event->id, 'event_id' => $event->id, 'region_id' => $region->id, 'series_id' => 1, 'ranking_run_id' => 'retry-run-'.$event->id, 'status' => 'sent']);
            $invitation = TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $event->id, 'region_id' => $region->id, 'team_id' => $team->id, 'player_id' => $player->id, 'ranking_list_id' => 1, 'ranking_position' => 1, 'queue_position' => 1, 'status' => 'invited']);
            $mailType = 'team_selection_invitation';
        }
        $payload = ['event_id' => $event->id, 'recipient_email' => 'parent@example.test', 'recipient_name' => 'Parent', 'related_type' => $invitation::class, 'related_id' => $invitation->id, 'rendered_html' => '<p>Exact signed invitation</p>', 'rendered_subject' => 'Exact invitation', 'kind' => 'initial'];
        $payload['payload_integrity'] = app(InvitationMailSecurity::class)->payloadIntegrity($payload);
        $log = BulkEmailLog::create(['mail_type' => $mailType, 'related_type' => $invitation::class, 'related_id' => $invitation->id, 'recipient_email' => 'parent@example.test', 'recipient_name' => 'Parent', 'status' => 'failed', 'failed_at' => now(), 'payload' => $payload]);

        return [$event, $actor, $log, $eventRegion];
    }

    public function test_team_invitation_retry_requires_exact_approval_and_preserves_signed_payload(): void
    {
        [$event, $actor, $log] = $this->fixture();
        $service = app(EventCommunicationService::class);
        $original = $log->fresh()->payload;
        $preview = $service->previewRetry($event, $log, $actor);
        Bus::assertNothingDispatched();
        $this->assertSame(1, $service->approve($preview, $actor, false)['queued']);
        $this->assertSame(0, $service->approve($preview, $actor, false)['queued']);
        $this->assertSame($original, $log->fresh()->payload);
        $this->assertSame('queued', $log->fresh()->status);
        Bus::assertDispatched(SendTeamSelectionInvitationEmailJob::class, 1);
        $this->assertDatabaseCount('bulk_email_logs', 1);
    }

    public function test_eligible_signed_team_invitation_reaches_transport_without_a_second_review_blocker(): void
    {
        [$event, $actor, $log] = $this->fixture();
        $log->update(['status' => 'queued', 'failed_at' => null]);
        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        $this->mock(\App\Services\MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');

        (new SendTeamSelectionInvitationEmailJob($log->id, $event->id))->handle();

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertSame('invited', TeamSelectionInvitation::findOrFail($log->related_id)->status);
    }

    public function test_team_invitation_mailer_preparation_error_is_reported_as_failed_instead_of_stuck_sending(): void
    {
        [$event, $actor, $log] = $this->fixture();
        $log->update(['status' => 'queued', 'failed_at' => null]);
        $this->mock(\App\Services\MailAccountManager::class)->shouldReceive('getMailer')->once()->andThrow(new \RuntimeException('Mailer unavailable'));

        $job = new SendTeamSelectionInvitationEmailJob($log->id, $event->id);
        $job->handle();

        $this->assertSame('failed', $log->fresh()->status);
        $this->assertNull($log->fresh()->sent_at);
        $this->assertNull($log->fresh()->accepted_at);
        $this->assertDatabaseCount('event_communication_batches', 0);
        Mail::assertNothingSent();
    }

    public function test_interpro_invitation_retry_uses_its_original_dedicated_job(): void
    {
        [$event, $actor, $log] = $this->fixture(true);
        $service = app(EventCommunicationService::class);
        $original = $log->fresh()->payload;
        $preview = $service->previewRetry($event, $log, $actor);
        $this->assertSame(1, $service->approve($preview, $actor, false)['queued']);
        $this->assertSame($original, $log->fresh()->payload);
        Bus::assertDispatched(SendInterprovincialTrialInvitationEmailJob::class, 1);
        Bus::assertNotDispatched(SendTeamSelectionInvitationEmailJob::class);
    }

    public function test_real_team_campaign_producer_creates_a_retryable_signed_snapshot(): void
    {
        [$event, $actor, $fabricated] = $this->fixture();
        $invitation = TeamSelectionInvitation::findOrFail($fabricated->related_id);
        $fabricated->delete();
        $import = $invitation->selectionImport;
        $import->update(['status' => 'draft']);
        $invitation->update(['roster_rank' => 1]);
        $deadlines = ['response_deadline' => now()->addDay(), 'payment_deadline' => now()->addDays(2)];
        $service = app(\App\Services\TeamSelection\TeamSelectionInvitationService::class);
        $this->assertNotEmpty($service->previewCampaign($import, $deadlines));
        $service->send($import, $deadlines, $actor, true);
        $log = BulkEmailLog::where('related_type', TeamSelectionInvitation::class)->where('related_id', $invitation->id)->sole();
        $this->assertTrue(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $event->id));
        $original = $log->fresh()->payload;
        $log->markAsFailed('Temporary transport failure');
        Bus::fake();
        $retry = app(EventCommunicationService::class)->previewRetry($event, $log, $actor);
        $this->assertSame(1, app(EventCommunicationService::class)->approve($retry, $actor, false)['queued']);
        $this->assertSame($original, $log->fresh()->payload);
        Bus::assertDispatched(SendTeamSelectionInvitationEmailJob::class, 1);
    }

    public function test_regional_manager_cannot_retry_or_view_another_regions_invitation(): void
    {
        [$event, $actor, $log] = $this->fixture();
        $manager = User::factory()->create()->assignRole('admin');
        $region = TeamRegion::create(['region_name' => 'Other region']);
        $eventRegion = EventRegion::findOrFail(DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id]));
        EventRegionManager::create(['event_id' => $event->id, 'region_id' => $region->id, 'event_region_id' => $eventRegion->id, 'user_id' => $manager->id, 'assigned_by' => $actor->id]);
        $this->actingAs($manager)->post(route('backend.event-communications.retry-preview', [$event, $log]))->assertNotFound();
        $this->actingAs($manager)->get(route('backend.event-communications.index', $event))->assertOk()->assertDontSee('parent@example.test');
        Bus::assertNothingDispatched();
    }

    public function test_signed_content_changes_after_preview_require_another_review(): void
    {
        [$event, $actor, $log] = $this->fixture();
        $preview = app(EventCommunicationService::class)->previewRetry($event, $log, $actor);
        $payload = $log->payload;
        $payload['rendered_subject'] = 'Changed subject';
        $payload['payload_integrity'] = app(InvitationMailSecurity::class)->payloadIntegrity($payload);
        $log->update(['payload' => $payload]);
        $this->actingAs($actor)->post(route('backend.event-communications.send', $event), ['token' => $preview->token, 'confirm_send' => 1])->assertUnprocessable();
        $this->assertSame('failed', $log->fresh()->status);
        Bus::assertNothingDispatched();
    }

    public function test_accepted_invitation_and_cross_event_log_cannot_be_retried(): void
    {
        [$event, $actor, $log] = $this->fixture();
        $log->update(['accepted_at' => now()]);
        $this->actingAs($actor)->post(route('backend.event-communications.retry-preview', [$event, $log]))->assertUnprocessable();
        [$otherEvent, $otherActor] = $this->fixture();
        $this->actingAs($otherActor)->post(route('backend.event-communications.retry-preview', [$otherEvent, $log]))->assertNotFound();
        Bus::assertNothingDispatched();
    }

    public function test_an_old_duplicate_job_cannot_resend_a_failed_team_invitation(): void
    {
        [$event, , $log] = $this->fixture();
        (new SendTeamSelectionInvitationEmailJob($log->id, $event->id))->handle();

        $this->assertSame('failed', $log->fresh()->status);
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    public function test_nested_signed_snapshot_survives_mysql_roundtrip_but_not_content_or_list_mutation(): void
    {
        [$event, $actor, $log] = $this->fixture();
        $security = app(InvitationMailSecurity::class);
        $payload = $log->payload;
        $payload['campaign'] = ['message' => 'Exact approved message', 'options' => ['z' => 'last', 'a' => 'first'], 'sections' => ['first', 'second']];
        $payload['payload_integrity'] = $security->payloadIntegrity($payload);
        $log->update(['payload' => $payload]);
        $stored = $log->fresh();
        $this->assertTrue($security->logMatchesSignedSnapshot($stored, $event->id));
        foreach ([
            ['rendered_subject', 'Changed subject'],
            ['rendered_html', '<p>Changed body</p>'],
            ['campaign.options.a', 'Changed nested option'],
            ['campaign.sections', ['second', 'first']],
        ] as [$field, $value]) {
            $tampered = $stored->replicate();
            $changed = $stored->payload;
            data_set($changed, $field, $value);
            $tampered->payload = $changed;
            $this->assertFalse($security->logMatchesSignedSnapshot($tampered, $event->id));
        }
        // An old signature remains valid only when its exact stored JSON still verifies.
        $legacy = $stored->payload;
        unset($legacy['payload_integrity']);
        ksort($legacy);
        $legacy['payload_integrity'] = hash_hmac('sha256', json_encode($legacy, JSON_THROW_ON_ERROR), (string) config('app.key'));
        $stored->payload = $legacy;
        $this->assertTrue($security->logMatchesSignedSnapshot($stored, $event->id));
    }

}
