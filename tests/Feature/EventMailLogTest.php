<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, Event, EventMailIssue, TeamRegion, User};
use App\Services\EventMailLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventMailLogTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Event $event): User
    {
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        return $actor;
    }

    public function test_http_retry_after_acknowledgement_alerts_retry_actor_and_preserves_original_attempt(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $event = Event::factory()->create();
        $original = $this->manager($event);
        $retryActor = $this->manager($event);
        $log = $this->log($event, ['created_by' => $original->id], 'failed');
        $log->update(['failed_at' => now()]);
        $issue = EventMailIssue::where('log_id', $log->id)->firstOrFail();
        $this->actingAs($original)->post(route('backend.event-mail-log.acknowledge', [$event, $issue]))->assertRedirect();
        $this->actingAs($retryActor)->get(route('backend.event-mail-log.retry-preview', [$event, $log]))->assertOk();
        $this->post(route('backend.event-mail-log.retry', [$event, $log]), ['confirmed' => 1])->assertRedirect();
        $log->refresh()->markAsFailed('Second failure');
        $this->assertSame($original->id, data_get($log->payload, 'created_by'));
        $this->assertDatabaseHas('event_mail_issues', ['log_id' => $log->id, 'user_id' => $retryActor->id, 'attempt_number' => 2, 'read_at' => null]);
        $this->get(route('backend.event-mail-log.show', [$event, $log]))->assertOk()->assertSee('Attempt 1')->assertSee('Attempt 2');
    }

    public function test_payer_without_manager_access_can_see_and_acknowledge_only_generic_own_issue(): void
    {
        $event = Event::factory()->create(['name' => 'Private event name']);
        $payer = User::factory()->create();
        $other = User::factory()->create();
        $log = $this->log($event, ['created_by' => $payer->id], 'failed');
        $issue = EventMailIssue::where('log_id', $log->id)->firstOrFail();
        $this->actingAs($payer)->get(route('backend.email-issues'))->assertOk()->assertSee('Your email attempt')->assertDontSee('Private event name')->assertDontSee('parent@example.test')->assertDontSee('Clothing collection');
        $this->get(route('backend.event-mail-log.show', [$event, $log]))->assertForbidden();
        $this->post(route('backend.event-mail-log.acknowledge', [$event, $issue]))->assertRedirect();
        $this->actingAs($other)->post(route('backend.event-mail-log.acknowledge', [$event, $issue]))->assertNotFound();
    }

    private function log(Event $event, array $payload = [], string $status = 'queued'): BulkEmailLog
    {
        return BulkEmailLog::create(['mail_type' => 'event_email', 'recipient_email' => 'parent@example.test', 'status' => $status, 'payload' => $payload + ['event_id' => $event->id, 'subject' => 'Event arrangements', 'message' => '<p>Clothing collection</p>']]);
    }

    public function test_log_supports_every_existing_event_type_and_hides_other_events(): void
    {
        $types = collect(range(1, 7))->map(fn ($kind) => DB::table('eventtypes')->insertGetId(['name'=>'Type '.$kind,'type'=>$kind]));
        $types->push(DB::table('eventtypes')->insertGetId(['name'=>'Masters','type'=>1,'code'=>\App\Models\EventType::MASTERS_CODE]));
        $types->push(DB::table('eventtypes')->insertGetId(['name'=>'Trials','type'=>1,'code'=>\App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]));
        foreach ($types as $type) {
            $event = Event::factory()->create(['eventType' => $type]);
            $actor = $this->manager($event);
            $log = $this->log($event);
            $other = $this->log(Event::factory()->create(), ['subject' => 'Private other event']);
            $this->actingAs($actor)->get(route('backend.event-mail-log.index', $event))->assertOk()->assertSee('Event arrangements')->assertDontSee('Private other event');
            $this->get(route('backend.event-mail-log.show', [$event, $log]))->assertOk()->assertSee('Clothing collection');
            $this->get(route('backend.event-mail-log.show', [$event, $other]))->assertNotFound();
        }
    }

    public function test_shared_region_legacy_logs_are_not_guessed_into_an_event(): void
    {
        $event = Event::factory()->create();
        $actor = $this->manager($event);
        $region = TeamRegion::create(['region_name' => 'Shared region']);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id]);
        $log = BulkEmailLog::create(['mail_type' => 'region_email', 'related_type' => TeamRegion::class, 'related_id' => $region->id, 'recipient_email' => 'private@example.test', 'status' => 'sent', 'payload' => ['subject' => 'Ambiguous history']]);
        $announcement=\App\Models\Announcement::create(['event_id'=>$event->id,'title'=>'Historical event notice','message'=>'Saved historical message']);
        BulkEmailLog::create(['mail_type'=>'event_announcement','related_type'=>\App\Models\Announcement::class,'related_id'=>$announcement->id,'recipient_email'=>'parent@example.test','status'=>'sent','payload'=>['title'=>'Historical event notice','message'=>'Saved historical message']]);
        $this->actingAs($actor)->get(route('backend.event-mail-log.index',$event))->assertOk()->assertDontSee('Ambiguous history')->assertSee('Historical event notice');
        $this->get(route('backend.event-mail-log.show',[$event,$log]))->assertNotFound();
    }

    public function test_regional_manager_sees_only_proven_region_and_revocation_blocks_access(): void
    {
        $event = Event::factory()->create();
        $this->manager($event);
        $actor = User::factory()->create();
        $region = TeamRegion::create(['region_name' => 'One region']);
        $eventRegion = DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id]);
        DB::table('event_region_managers')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'event_region_id' => $eventRegion, 'user_id' => $actor->id]);
        $own = $this->log($event,['region_id' => $region->id]);
        $hidden = $this->log($event,['created_by' => $actor->id,'subject'=>'Whole event private']);
        $this->actingAs($actor)->get(route('backend.event-mail-log.show',[$event,$own]))->assertOk();
        $this->get(route('backend.event-mail-log.show',[$event,$hidden]))->assertNotFound();
        DB::table('event_region_managers')->where('user_id',$actor->id)->delete();
        $this->get(route('backend.event-mail-log.index',$event))->assertForbidden();
    }

    public function test_issues_are_durable_unique_sender_scoped_and_never_expose_transport_secrets(): void
    {
        $event = Event::factory()->create();
        $actor = $this->manager($event);
        $log = $this->log($event,['created_by'=>$actor->id],'failed');
        $log->update(['error_message'=>'SMTP password=private-secret@example.test']);
        $service = app(EventMailLogService::class);
        $service->recordIssue($log); $service->recordIssue($log);
        $this->assertDatabaseCount('event_mail_issues',1);
        $this->actingAs($actor)->get(route('backend.email-issues'))->assertOk()->assertSee('Failed');
        $this->get(route('backend.event-mail-log.show',[$event,$log]))->assertOk()->assertDontSee('private-secret');
        $issue=EventMailIssue::firstOrFail();
        $this->post(route('backend.event-mail-log.acknowledge',[$event,$issue]))->assertRedirect();
        $this->assertNotNull($issue->fresh()->read_at);
        $this->actingAs(User::factory()->create())->post(route('backend.event-mail-log.acknowledge',[$event,$issue]))->assertNotFound();
    }

    public function test_stalled_queue_creates_an_alert_without_resending(): void
    {
        $event=Event::factory()->create(); $actor=$this->manager($event);
        $log=$this->log($event,['created_by'=>$actor->id]);
        DB::table('bulk_email_logs')->where('id',$log->id)->update(['updated_at'=>now()->subHours(2)]);
        $this->artisan('mail:record-event-issues')->assertSuccessful();
        $this->assertDatabaseHas('event_mail_issues',['log_id'=>$log->id,'kind'=>'stalled']);
        $this->assertSame('queued',$log->fresh()->status);
    }

    public function test_guest_and_unrelated_user_cannot_read_event_email_log(): void
    {
        $event=Event::factory()->create();
        $this->get(route('backend.event-mail-log.index',$event))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('backend.event-mail-log.index',$event))->assertForbidden();
    }

    public function test_retry_requires_review_and_queues_exactly_one_job_without_duplicate_submission(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $event=Event::factory()->create(); $actor=$this->manager($event);
        $log=$this->log($event,['created_by'=>$actor->id],'failed');
        $this->actingAs($actor)->post(route('backend.event-mail-log.retry',[$event,$log]),['confirmed'=>1])->assertStatus(422);
        $response = $this->get(route('backend.event-mail-log.retry-preview',[$event,$log]))->assertOk()->assertSee('Clothing collection')
            ->assertSee('Send reviewed retry')->assertSee('data-mail-record-details', false);
        $this->assertLessThan(strpos($response->getContent(), 'data-mail-record-details'), strpos($response->getContent(), 'Saved message text'));
        if (getenv('CT_BATCHES151617_QA')) { file_put_contents(storage_path('app/batches151617-qa/mail.html'), $response->getContent()); }
        $this->post(route('backend.event-mail-log.retry',[$event,$log]),['confirmed'=>1])->assertRedirect();
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\SendBulkEmailJob::class,1);
        $this->assertSame('queued',$log->fresh()->status);
        $this->assertSame('<p>Clothing collection</p>',data_get($log->fresh()->payload,'message'));
        $this->assertDatabaseCount('event_communication_batches',1);
        $this->post(route('backend.event-mail-log.retry',[$event,$log]),['confirmed'=>1])->assertStatus(422);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\SendBulkEmailJob::class,1);
    }

    public function test_stale_retry_and_accepted_or_unknown_emails_cannot_be_resent(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $event=Event::factory()->create(); $actor=$this->manager($event);
        $log=$this->log($event,[],'failed');
        $this->actingAs($actor)->get(route('backend.event-mail-log.retry-preview',[$event,$log]))->assertOk();
        $log->update(['payload'=>[...$log->payload,'message'=>'Changed content']]);
        $this->post(route('backend.event-mail-log.retry',[$event,$log]),['confirmed'=>1])->assertStatus(422);
        foreach(['sent','acceptance_unknown','sending'] as $status){
            $log->update(['status'=>$status]);
            $this->get(route('backend.event-mail-log.retry-preview',[$event,$log]))->assertStatus(422);
        }
        $log->update(['status'=>'failed','accepted_at'=>now()]);
        $this->get(route('backend.event-mail-log.retry-preview',[$event,$log]))->assertStatus(422);
        \Illuminate\Support\Facades\Bus::assertNothingDispatched();
    }

    public function test_trials_log_uses_canonical_programme_access_for_regional_manager(): void
    {
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Trials','type'=>1,'code'=>\App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event=Event::factory()->create(['eventType'=>$type]);
        $actor=User::factory()->create();
        $region=TeamRegion::create(['region_name'=>'Trials region']);
        $eventRegion=DB::table('event_regions')->insertGetId(['event_id'=>$event->id,'region_id'=>$region->id]);
        DB::table('event_region_managers')->insert(['event_id'=>$event->id,'region_id'=>$region->id,'event_region_id'=>$eventRegion,'user_id'=>$actor->id]);
        $log=$this->log($event);
        $this->actingAs($actor)->get(route('backend.event-mail-log.show',[$event,$log]))->assertOk();
        DB::table('event_region_managers')->where('user_id',$actor->id)->delete();
        $this->get(route('backend.event-mail-log.show',[$event,$log]))->assertForbidden();
    }

    public function test_existing_reviewed_campaign_cannot_bypass_its_canonical_retry_checks(): void
    {
        $event=Event::factory()->create(); $actor=$this->manager($event);
        $batch=\App\Models\EventCommunicationBatch::create(['event_id'=>$event->id,'created_by'=>$actor->id,'token'=>(string)\Illuminate\Support\Str::uuid(),'subject'=>'Reviewed subject','body'=>'Saved body','options'=>['scope'=>'rankings'],'recipients'=>[],'issues'=>[],'fingerprint'=>str_repeat('a',64),'status'=>'approved','approved_at'=>now()]);
        $log=$this->log($event,['event_communication_batch_id'=>$batch->id],'failed');
        $this->actingAs($actor)->get(route('backend.event-mail-log.retry-preview',[$event,$log]))->assertStatus(422);
        $this->assertSame('failed',$log->fresh()->status);
        $log->update(['payload'=>[...$log->payload,'event_communication_batch_id'=>null,'preview_id'=>99999]]);
        $this->get(route('backend.event-mail-log.retry-preview',[$event,$log]))->assertStatus(422);
    }

    public function test_sender_copies_do_not_inflate_recipient_outcomes_and_feedback_is_visible(): void
    {
        $event=Event::factory()->create(); $actor=$this->manager($event);
        $this->log($event,['recipient_kind'=>'players','campaign_key'=>'campaign-one'],'skipped');
        $this->log($event,['recipient_kind'=>'sender_copy','campaign_key'=>'campaign-one']);
        $this->actingAs($actor)->withSession(['error'=>'No recipient emails were queued.'])->get(route('backend.event-mail-log.index',['event'=>$event,'campaign'=>'campaign-one']))
            ->assertOk()->assertSee('No recipient emails were queued.')
            ->assertViewHas('summary',fn ($summary)=>$summary['total']===1 && $summary['queued']===0 && $summary['skipped']===1)
            ->assertViewHas('copySummary',fn ($summary)=>$summary['total']===1 && $summary['queued']===1);
    }
    public function test_outcome_filters_distinguish_acceptance_unverified_and_uncertain_with_honest_facets(): void
    {
        $event = Event::factory()->create();
        $actor = $this->manager($event);
        $accepted = $this->log($event, [], 'sent');
        $accepted->update(['accepted_at'=>now(), 'evidence_status'=>'server_accepted']);
        $unverified = $this->log($event, [], 'sent');
        $uncertain = $this->log($event, [], 'acceptance_unknown');
        $sandbox = $this->log($event, [], 'sent');
        $sandbox->update(['accepted_at'=>now(), 'evidence_status'=>'sandbox_accepted']);
        $this->actingAs($actor);
        foreach (['accepted'=>$accepted, 'unverified'=>$unverified, 'uncertain'=>$uncertain, 'sandbox'=>$sandbox] as $outcome=>$expected) {
            $this->get(route('backend.event-mail-log.index', ['event'=>$event, 'outcome'=>$outcome]))->assertOk()
                ->assertViewHas('logs', fn ($logs) => $logs->total()===1 && $logs->first()->id===$expected->id)
                ->assertViewHas('facets', fn ($facets) => $facets['total']===4 && $facets['server_accepted']===1 && $facets['unverified']===1 && $facets['uncertain']===1);
        }
        $this->get(route('backend.event-mail-log.index', $event))->assertOk()->assertSee('this does not mean they failed')->assertDontSee('Some emails need attention');
    }

    public function test_combined_name_date_type_audience_filters_and_pagination_preserve_campaign_and_event_isolation(): void
    {
        $event = Event::factory()->create();
        $actor = $this->manager($event);
        for ($i=0; $i<26; $i++) {
            $log = $this->log($event, ['campaign_key'=>'clothing-campaign', 'recipient_kind'=>'players'], 'failed');
            $log->forceFill(['recipient_name'=>'Alice Example', 'created_at'=>'2026-10-05 12:00:00'])->save();
        }
        $copy = $this->log($event, ['campaign_key'=>'clothing-campaign', 'recipient_kind'=>'sender_copy'], 'failed');
        $copy->forceFill(['recipient_name'=>'Alice Example', 'created_at'=>'2026-10-05 13:00:00'])->save();
        $later = $this->log($event, ['campaign_key'=>'clothing-campaign'], 'failed');
        $later->forceFill(['recipient_name'=>'Alice Example', 'created_at'=>'2026-10-06 00:00:00'])->save();
        $foreign = $this->log(Event::factory()->create(), ['campaign_key'=>'clothing-campaign'], 'failed');
        $foreign->forceFill(['recipient_name'=>'Alice Example', 'mail_type'=>'private_foreign_mail', 'created_at'=>'2026-10-05 12:00:00'])->save();
        $filters = ['search'=>'Alice', 'from'=>'2026-10-05', 'until'=>'2026-10-05', 'mail_type'=>'event_email', 'audience'=>'recipients', 'outcome'=>'failed', 'campaign'=>'clothing-campaign'];
        $this->actingAs($actor)->get(route('backend.event-mail-log.index', ['event'=>$event,...$filters]))->assertOk()
            ->assertViewHas('logs', function ($logs) use ($filters) {
                parse_str(parse_url($logs->nextPageUrl(), PHP_URL_QUERY), $next);
                return $logs->total()===26 && $logs->count()===25 && count(array_diff_assoc($filters,$next))===0;
            })
            ->assertViewHas('facets', fn ($facets) => $facets['total']===26)
            ->assertViewHas('types', fn ($types) => !$types->has('private_foreign_mail'))
            ->assertSee('Campaign: clothing-campaign')->assertSee('View all event emails');
        $this->get(route('backend.event-mail-log.index', ['event'=>$event,...$filters,'page'=>2]))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->count()===1 && $logs->total()===26);
        $this->get(route('backend.event-mail-log.index', ['event'=>$event,...$filters,'audience'=>'copies']))->assertOk()
            ->assertViewHas('facets', fn ($facets) => $facets['total']===1)
            ->assertViewHas('copySummary', fn ($summary) => $summary['failed']===1);
        $this->actingAs(User::factory()->create())->get(route('backend.event-mail-log.index', ['event'=>$event,...$filters]))->assertForbidden();
    }

    public function test_date_validation_and_scoped_type_options_are_bounded(): void
    {
        $event = Event::factory()->create();
        $actor = $this->manager($event);
        $this->actingAs($actor)->getJson(route('backend.event-mail-log.index',['event'=>$event,'from'=>'2026-10-06','until'=>'2026-10-05']))->assertUnprocessable()->assertJsonValidationErrors('until');
        $indexUrl = route('backend.event-mail-log.index',$event);
        $this->from($indexUrl)->get(route('backend.event-mail-log.index',['event'=>$event,'from'=>'2026-10-06','until'=>'2026-10-05']))->assertRedirect($indexUrl)->assertSessionHasErrors('until');
        $this->get($indexUrl)->assertOk()->assertSee('Check email log filters')->assertSee('aria-invalid="true"',false);
        for ($i=0; $i<101; $i++) $this->log($event)->update(['mail_type'=>'type_'.str_pad((string)$i,3,'0',STR_PAD_LEFT)]);
        $this->get(route('backend.event-mail-log.index',$event))->assertOk()
            ->assertViewHas('types', fn ($types) => $types->count()===100)
            ->assertViewHas('typesLimited',true)->assertSee('first 100 permitted email types');
        $this->get(route('backend.event-mail-log.index',['event'=>$event,'mail_type'=>'type_100']))->assertOk()
            ->assertViewHas('types',fn ($types) => $types->count()===101 && $types->has('type_100'))
            ->assertViewHas('logs',fn ($logs) => $logs->total()===1);
        $this->get(route('backend.event-mail-log.index',['event'=>$event,'mail_type'=>'no_visible_records']))->assertOk()
            ->assertSee('Selected type (no permitted records): no_visible_records')
            ->assertViewHas('logs',fn ($logs) => $logs->total()===0);

    }

    public function test_sent_shortcut_includes_confirmed_and_historical_sends_but_excludes_sandbox_and_unsent_attempts(): void
    {
        $event = Event::factory()->create();
        $actor = $this->manager($event);
        $accepted = $this->log($event, [], 'sent');
        $accepted->update(['accepted_at'=>now(),'evidence_status'=>'server_accepted']);
        $historical = $this->log($event, [], 'sent');
        foreach (['failed','skipped','acceptance_unknown','queued','sending'] as $status) $this->log($event, [], $status);
        $sandbox = $this->log($event, [], 'sent');
        $sandbox->update(['accepted_at'=>now(),'evidence_status'=>'sandbox_accepted']);
        $this->actingAs($actor)->get(route('backend.event-mail-log.index',['event'=>$event,'outcome'=>'sent_complete']))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total()===2 && $logs->pluck('id')->sort()->values()->all()===collect([$accepted->id,$historical->id])->sort()->values()->all())
            ->assertViewHas('facets', fn ($facets) => $facets['sent_complete']===2 && $facets['server_accepted']===1)
            ->assertSee('Server accepted: 1')->assertSee('Sent includes completed and historical sends');
    }

}
