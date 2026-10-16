<?php

namespace Tests\Unit;

use App\Jobs\SendTeamSelectionInvitationEmailJob;
use App\Mail\TeamSelectionInvitationMail;
use App\Models\{BulkEmailLog, Event, EventCommunicationBatch, TeamRegion, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\{EventCommunicationService, EventMailAttemptRecorder, EventMailLogService, InvitationMailSecurity};
use App\Services\TeamSelection\{TeamSelectionClothingMailPolicy, TeamSelectionInvitationService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, DB, Mail, Schema};
use Tests\TestCase;

class TeamSelectionClothingMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'clothing_mail_test', 'database.connections.clothing_mail_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'cache.default' => 'array']);
        DB::purge('clothing_mail_test');
        foreach ([
            'team_regions' => ['region_name', 'clothing_admin', 'clothing_order'],
            'clothing_item_types' => ['region_id', 'item_type_name', 'price', 'ordering'],
            'clothing_sizes' => ['item_type', 'size', 'ordering'],
            'site_settings' => ['key', 'value'],
            'events' => ['name', 'published', 'logo', 'start_date', 'end_date', 'entryFee', 'organizer', 'email', 'venue_notes'],
            'team_selection_imports' => ['event_id', 'region_id', 'status', 'response_deadline', 'payment_deadline', 'replacement_payment_deadline', 'communication_snapshot', 'include_clothing'],
            'team_selection_invitations' => ['event_id', 'region_id', 'import_id', 'team_id', 'player_id', 'status', 'roster_rank'],
            'teams' => ['name', 'region_id'],
            'team_players' => ['team_id', 'rank', 'player_id'],
            'no_profile_team_players' => ['team_id', 'rank'],
            'players' => ['name', 'surname', 'email', 'userId'],
            'bulk_email_logs' => ['mail_type', 'related_type', 'related_id', 'recipient_email', 'recipient_name', 'status', 'payload', 'queued_at', 'sent_at', 'accepted_at', 'failed_at', 'skipped_at', 'error_message', 'retry_actor_id'],
            'event_communication_batches' => ['event_id', 'created_by', 'token', 'subject', 'body', 'options', 'recipients', 'issues', 'fingerprint', 'status', 'approved_at'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns): void {
                $table->id();
                foreach ($columns as $column) $table->text($column)->nullable();
                $table->timestamps();
            });
        }
        $this->mock(EventMailAttemptRecorder::class)->shouldReceive('record', 'recordOriginal')->byDefault();
        $this->mock(EventMailLogService::class)->shouldReceive('recordIssue')->byDefault();
        Bus::fake();
        Mail::fake();
        DB::table('team_regions')->insert(['id' => 1, 'region_name' => 'Region A', 'clothing_admin' => 1, 'clothing_order' => 1]);
        DB::table('clothing_item_types')->insert(['id' => 1, 'region_id' => 1, 'item_type_name' => 'Regional shirt', 'price' => 250, 'ordering' => 1]);
        DB::table('clothing_sizes')->insert(['id' => 1, 'item_type' => 1, 'size' => 'M', 'ordering' => 1]);
        DB::table('events')->insert(['id' => 1, 'name' => 'Regional event', 'published' => 1]);
        DB::table('team_selection_imports')->insert(['id' => 1, 'event_id' => 1, 'region_id' => 1, 'status' => 'sent', 'response_deadline' => now()->addDays(2), 'payment_deadline' => now()->addDays(2)]);
    }

    protected function tearDown(): void
    {
        DB::purge('clothing_mail_test');
        parent::tearDown();
    }

    private function campaign(): array
    {
        return ['subject' => 'Team invitation', 'message' => 'Please join the regional team.', 'include_clothing' => true, 'clothing_items' => [['name' => 'Regional shirt', 'price' => 250, 'sizes' => ['M']]], 'hash' => 'historical-campaign'];
    }

    private function invitation(): TeamSelectionInvitation
    {
        DB::table('teams')->insertOrIgnore(['id' => 1, 'region_id' => 1, 'name' => 'Regional team']);
        DB::table('players')->insertOrIgnore(['id' => 1, 'name' => 'Selected', 'surname' => 'Player']);
        DB::table('team_selection_invitations')->insertOrIgnore(['id' => 1, 'event_id' => 1, 'region_id' => 1, 'import_id' => 1, 'team_id' => 1, 'player_id' => 1, 'status' => 'invited', 'roster_rank' => 1]);

        return TeamSelectionInvitation::with(['selectionImport.event', 'region', 'team', 'player'])->findOrFail(1);
    }

    private function log(string $status = 'failed'): BulkEmailLog
    {
        $invitation = $this->invitation();
        $mail = new TeamSelectionInvitationMail($invitation, 'invitation', $this->campaign());
        $payload = ['kind' => 'invitation', 'campaign' => $this->campaign(), 'event_id' => 1, 'region_id' => 1,
            'rendered_subject' => $mail->envelope()->subject, 'rendered_html' => $mail->render(),
            'recipient_email' => 'parent@example.test', 'recipient_name' => 'Selected Player', 'related_type' => TeamSelectionInvitation::class, 'related_id' => 1];
        $payload['payload_integrity'] = app(InvitationMailSecurity::class)->payloadIntegrity($payload);

        return BulkEmailLog::create(['mail_type' => 'team_selection_invitation', 'related_type' => TeamSelectionInvitation::class, 'related_id' => 1,
            'recipient_email' => 'parent@example.test', 'recipient_name' => 'Selected Player', 'status' => $status, 'payload' => $payload]);
    }

    public function test_new_campaign_preview_uses_current_ordering_status_even_with_stale_loaded_region(): void
    {
        $import = TeamSelectionImport::with(['event', 'region.clothingItems.sizes'])->findOrFail(1);
        $import->event->setRelation('venues', collect());
        $details = ['include_clothing' => true, 'response_deadline' => now()->addDays(2)->toIso8601String(), 'payment_deadline' => now()->addDays(2)->toIso8601String()];
        $service = app(TeamSelectionInvitationService::class);
        $open = $service->previewCampaign($import, $details);
        $this->assertTrue($open['include_clothing']);
        $this->assertCount(1, $open['clothing_items']);
        DB::table('team_regions')->where('id', 1)->update(['clothing_order' => 0]);
        $closed = $service->previewCampaign($import, $details);
        $this->assertFalse($closed['include_clothing']);
        $this->assertSame([], $closed['clothing_items']);
        $html = (new TeamSelectionInvitationMail($this->invitation(), 'invitation', $closed))->render();
        $this->assertStringNotContainsString('Optional regional clothing', $html);
        $this->assertStringNotContainsString('Regional shirt', $html);
        $this->assertStringNotContainsString('How to order:', $html);
    }

    public function test_saved_campaign_custom_resend_omits_closed_clothing_without_changing_history(): void
    {
        $import = TeamSelectionImport::findOrFail(1);
        $import->update(['communication_snapshot' => $this->campaign(), 'include_clothing' => true]);
        $method = new \ReflectionMethod(TeamSelectionInvitationService::class, 'customCampaignSnapshot');
        DB::table('team_regions')->where('id', 1)->update(['clothing_order' => 0]);
        $campaign = $method->invoke(app(TeamSelectionInvitationService::class), $import, 'Fresh invitation', 'Please respond.');
        $this->assertFalse($campaign['include_clothing']);
        $this->assertSame([], $campaign['clothing_items']);
        $this->assertSame($this->campaign(), $import->fresh()->communication_snapshot);
        $this->assertTrue($import->fresh()->include_clothing);
    }

    public function test_catalogue_participation_size_and_region_boundaries_are_respected(): void
    {
        $policy = app(TeamSelectionClothingMailPolicy::class);
        $this->assertTrue($policy->available(1));
        DB::table('team_regions')->insert(['id' => 2, 'region_name' => 'Other', 'clothing_admin' => 1, 'clothing_order' => 1]);
        $this->assertFalse($policy->available(2));
        DB::table('team_regions')->where('id', 1)->update(['clothing_admin' => 0]);
        $this->assertFalse($policy->available(1));
        DB::table('team_regions')->where('id', 1)->update(['clothing_admin' => 1]);
        DB::table('clothing_sizes')->delete();
        $this->assertFalse($policy->available(1));
    }

    public function test_closing_after_queue_holds_approved_message_without_rewriting_snapshot_or_sending(): void
    {
        $log = $this->log('queued');
        $snapshot = $log->payload;
        DB::table('team_regions')->where('id', 1)->update(['clothing_order' => 0]);
        (new SendTeamSelectionInvitationEmailJob($log->id, 1))->handle();
        $this->assertSame('skipped', $log->fresh()->status);
        $this->assertStringContainsString('Clothing ordering closed', $log->fresh()->error_message);
        $this->assertSame($snapshot, $log->fresh()->payload);
        Mail::assertNothingSent();
    }

    public function test_failed_retry_creates_fresh_clothing_free_signed_log_and_preserves_original(): void
    {
        $log = $this->log();
        $snapshot = $log->payload;
        $event = Event::findOrFail(1);
        $actor = new User();
        $actor->id = 5;
        $service = \Mockery::mock(EventCommunicationService::class, [app(\App\Services\TeamSelection\RegionManagerAccessService::class), app(\App\Services\TeamSelection\TeamSelectionContactService::class), app(\App\Services\BulkMailDispatcher::class)])->makePartial();
        $service->shouldReceive('regions')->andReturn(collect());
        $service->shouldReceive('invitationLogs')->andReturnUsing(fn () => BulkEmailLog::query());
        $service->shouldReceive('authorizeRankingBatch', 'validateWrapUpResults')->andReturnNull();
        $openPreview = $service->previewRetry($event, $log, $actor);
        DB::table('team_regions')->where('id', 1)->update(['clothing_order' => 0]);
        try {
            $service->approve($openPreview, $actor, false);
            $this->fail('Closing clothing must invalidate an earlier clothing-bearing retry preview.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(1, BulkEmailLog::count());
        $batch = $service->previewRetry($event, $log, $actor);
        $secondPreview = $service->previewRetry($event, $log, $actor);
        $this->assertStringNotContainsString('Regional shirt', $batch->recipients[0]['html']);
        $this->assertStringNotContainsString('Optional regional clothing', $batch->recipients[0]['html']);
        $this->assertSame(1, BulkEmailLog::count());
        $this->assertSame(['queued' => 1, 'duplicate' => false], $service->approve($batch, $actor, false));
        $this->assertSame(2, BulkEmailLog::count());
        $retry = BulkEmailLog::latest('id')->firstOrFail();
        $this->assertSame($log->id, $retry->payload['retry_of_log_id']);
        $this->assertFalse($retry->payload['campaign']['include_clothing']);
        $this->assertTrue(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($retry, 1));
        $this->assertSame($snapshot, $log->fresh()->payload);
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame(['queued' => 0, 'duplicate' => true], $service->approve($batch->fresh(), $actor, false));
        try {
            $service->approve($secondPreview, $actor, false);
            $this->fail('A second preview of the original must not queue a second revised retry.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('preview', $exception->errors());
        }
        DB::table('team_regions')->where('id', 1)->update(['clothing_order' => 1]);
        $reopenedPreview = $service->previewRetry($event, $log->fresh(), $actor);
        try {
            $service->approve($reopenedPreview, $actor, false);
            $this->fail('Reopening clothing must not permit retrying an original with a revised child.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('preview', $exception->errors());
        }
        $this->assertSame(2, BulkEmailLog::count());
        Bus::assertDispatchedTimes(SendTeamSelectionInvitationEmailJob::class, 1);
        Mail::assertNothingSent();
    }

    public function test_open_retry_preserves_the_exact_approved_content(): void
    {
        $log = $this->log();
        $this->assertSame($log->payload, app(TeamSelectionClothingMailPolicy::class)->retryPayload($log));
        $this->assertStringContainsString('Regional shirt', $log->payload['rendered_html']);
        $this->assertTrue(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, 1));
    }

    public function test_closing_between_preview_and_queue_requires_new_approval_without_silent_rewrite(): void
    {
        $invitation = $this->invitation();
        DB::table('team_regions')->where('id', 1)->update(['clothing_order' => 0]);
        $queue = new \ReflectionMethod(TeamSelectionInvitationService::class, 'queueMail');
        try {
            $queue->invoke(app(TeamSelectionInvitationService::class), $invitation, 'parent@example.test', 'invitation', $this->campaign());
            $this->fail('Changed clothing ordering must invalidate the previous preview.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('email_preview', $exception->errors());
        }
        $this->assertSame(0, BulkEmailLog::count());
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
    }
}
