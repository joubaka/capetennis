<?php
namespace Tests\Feature;
use App\Models\{Event,EventType,CategoryEvent,EventNomination,Player,User,InterprovincialTrialInvitation,InterprovincialTrialInvitationBatch,TrialMailSchedule,BulkEmailLog};
use App\Services\InterprovincialTrials\TrialCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Bus};
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class TrialCommunicationsTest extends TestCase {
    use RefreshDatabase;
    private Event $event; private User $admin; private CategoryEvent $category; private Player $player; private EventNomination $nomination;
    protected function setUp(): void {
        parent::setUp(); Bus::fake(); Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $this->admin=User::factory()->create()->assignRole('admin');
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Trials','type'=>EventType::INDIVIDUAL,'code'=>EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $this->event=Event::factory()->create(['eventType'=>$type,'name'=>'Regional Trials','entryFee'=>125,'start_date'=>now()->addMonth(),'deadline'=>7]);
        DB::table('event_admins')->insert(['event_id'=>$this->event->id,'user_id'=>$this->admin->id]);
        $this->category=CategoryEvent::factory()->create(['event_id'=>$this->event->id,'nominations_published'=>true]);
        $parent=User::factory()->create(['email'=>'family@example.test']);
        $this->player=Player::factory()->create(['email'=>'family@example.test','userId'=>$parent->id]);
        $this->player->users()->attach($parent->id);
        $this->nomination=EventNomination::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'player_id'=>$this->player->id]);
    }
    private function preview(string $filter='all') {
        return app(TrialCommunicationService::class)->preview($this->event,$this->admin,['audience'=>'nominations','filter'=>$filter],'Editable {event}','Only this text: {players} <script>alert(1)</script> {fee} {url}');
    }
    public function test_combined_family_preview_has_exact_editable_content_and_commit_is_idempotent(): void {
        $child=Player::factory()->create(['email'=>'FAMILY@example.test']);
        EventNomination::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'player_id'=>$child->id]);
        $preview=$this->preview(); $this->assertCount(1,$preview->recipients); $this->assertCount(2,$preview->recipients[0]['players']);
        $this->assertSame('Editable Regional Trials',$preview->recipients[0]['subject']);
        $service=app(TrialCommunicationService::class); $this->assertSame(1,$service->commit($preview,$this->admin)['queued']);
        $this->assertSame(0,$service->commit($preview,$this->admin)['queued']); $this->assertDatabaseCount('bulk_email_logs',1);
        $log=BulkEmailLog::sole(); $this->assertStringContainsString('&lt;script&gt;',$log->payload['body']); $this->assertStringNotContainsString('<script>',$log->payload['body']);
        $this->assertSame(0,(new \App\Jobs\SendBulkEmailJob($log->id,true))->tries);
    }
    public function test_changed_contacts_and_expired_preview_cannot_send(): void {
        $service=app(TrialCommunicationService::class); $preview=$this->preview(); $this->player->update(['email'=>'changed@example.test']);
        try {$service->commit($preview,$this->admin); $this->fail('Expected stale rejection');} catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('preview',$e->errors());}
        $preview=$this->preview(); $this->travel(16)->minutes();
        try {$service->commit($preview,$this->admin); $this->fail('Expected expiry');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
        $this->assertDatabaseCount('bulk_email_logs',0);
    }
    public function test_other_admin_cannot_commit_or_preview_region(): void {
        $other=User::factory()->create()->assignRole('admin');
        try {app(TrialCommunicationService::class)->preview($this->event,$other,[],'Subject','Body'); $this->fail('Expected denial');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $preview=$this->preview(); DB::table('event_admins')->insert(['event_id'=>$this->event->id,'user_id'=>$other->id]);
        try {app(TrialCommunicationService::class)->commit($preview,$other); $this->fail('Expected denial');} catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }
    public function test_missing_contacts_are_reported_even_for_unpublished_nominees(): void {
        $this->player->update(['email'=>null,'userId'=>null]); $this->player->users()->detach();
        $preview=$this->preview(); $this->assertCount(0,$preview->recipients); $this->assertCount(1,$preview->excluded);
        $this->category->update(['nominations_published'=>false]); $preview=$this->preview(); $this->assertCount(1,$preview->excluded);
    }
    public function test_due_reminders_never_queue_without_a_fresh_manual_review(): void {
        $service=app(TrialCommunicationService::class); $preview=$this->preview('not_registered');
        $schedule=$service->schedule($preview,$this->admin,now()->addHour(),null,null);
        $batch=InterprovincialTrialInvitationBatch::create(['event_id'=>$this->event->id,'status'=>'draft','created_by_user_id'=>$this->admin->id,'snapshot_hash'=>str_repeat('a',64)]);
        InterprovincialTrialInvitation::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'nomination_id'=>$this->nomination->id,'batch_id'=>$batch->id,'player_id'=>$this->player->id,'status'=>'paid_confirmed']);
        $this->travel(2)->hours(); $this->assertSame(1,$service->runDue()); $this->assertDatabaseCount('bulk_email_logs',0); $this->assertTrue($schedule->fresh()->active);
        $review=$service->preview($this->event,$this->admin,array_merge($schedule->options,['_schedule_id'=>$schedule->id]),$schedule->subject,$schedule->body);
        $this->assertCount(0,$review->recipients); $service->commit($review,$this->admin);
        $this->assertFalse($schedule->fresh()->active); $this->assertSame(0,$service->runDue());
    }
    public function test_failed_retry_reuses_log_and_requires_admin(): void {
        $service=app(TrialCommunicationService::class); $service->commit($this->preview(),$this->admin); $log=BulkEmailLog::sole(); $log->markAsFailed('Temporary transport error');
        $this->assertTrue($service->retry($log,$this->admin)); $this->assertFalse($service->retry($log,$this->admin)); $this->assertDatabaseCount('bulk_email_logs',1);
        $this->assertSame('queued',$log->fresh()->status);
    }
    public function test_everyone_includes_nominees_and_current_squad_with_individual_filters(): void {
        $squadPlayer=Player::factory()->create(['email'=>'squad@example.test','userId'=>null]);
        $run=\App\Models\TrialRankingRun::create(['event_id'=>$this->event->id,'signature'=>str_repeat('a',64),'positions'=>[]]);
        $draft=\App\Models\TrialSquadDraft::create(['event_id'=>$this->event->id,'ranking_run_id'=>$run->id,'tiers'=>['A'],'created_by'=>$this->admin->id]);
        \App\Models\TrialSquadSlot::create(['draft_id'=>$draft->id,'category_event_id'=>$this->category->id,'tier'=>'A','slot'=>1,'player_id'=>$squadPlayer->id]);
        $service=app(TrialCommunicationService::class);
        $all=$service->preview($this->event,$this->admin,['audience'=>'all','filter'=>'all'],'Update','{players}');
        $this->assertEqualsCanonicalizing(['family@example.test','squad@example.test'],array_column($all->recipients,'email'));
        $one=$service->preview($this->event,$this->admin,['audience'=>'all','filter'=>'all','nominee_ids'=>[$this->nomination->id]],'Update','{players}');
        $this->assertSame(['family@example.test'],array_column($one->recipients,'email'));
        $this->assertDatabaseCount('bulk_email_logs',0);
    }
    public function test_saved_template_remains_fully_editable(): void {
        $template=app(TrialCommunicationService::class)->saveTemplate($this->event,$this->admin,'My invitation','My exact subject','My whole body');
        $this->assertSame('My whole body',$template->body); $this->assertSame($this->event->id,$template->event_id);
    }
    public function test_team_messages_work_before_finalisation_and_respect_tier_filters(): void {
        $run=\App\Models\TrialRankingRun::create(['event_id'=>$this->event->id,'signature'=>str_repeat('a',64),'positions'=>[]]);
        $draft=\App\Models\TrialSquadDraft::create(['event_id'=>$this->event->id,'ranking_run_id'=>$run->id,'tiers'=>['A'],'created_by'=>$this->admin->id]);
        \App\Models\TrialSquadSlot::create(['draft_id'=>$draft->id,'category_event_id'=>$this->category->id,'tier'=>'A','slot'=>1,'player_id'=>$this->player->id]);
        $service=app(TrialCommunicationService::class);
        $preview=$service->preview($this->event,$this->admin,['audience'=>'teams','filter'=>'all'],'Subject','{players}');
        $this->assertCount(1,$preview->recipients);
        $draft->update(['status'=>'finalised','finalised_at'=>now()]);
        $preview=$service->preview($this->event,$this->admin,['audience'=>'teams','filter'=>'all','tiers'=>['A']],'Subject','{players}');
        $this->assertCount(1,$preview->recipients);
        $preview=$service->preview($this->event,$this->admin,['audience'=>'teams','filter'=>'all','tiers'=>['B']],'Subject','{players}');
        $this->assertCount(0,$preview->recipients);
    }
    public function test_selected_other_event_category_never_includes_its_players(): void {
        $other=CategoryEvent::factory()->create(['nominations_published'=>true]);
        $preview=app(TrialCommunicationService::class)->preview($this->event,$this->admin,['audience'=>'nominations','filter'=>'all','category_ids'=>[$other->id]],'Subject','Body');
        $this->assertCount(0,$preview->recipients);
    }
    public function test_failed_retry_http_requires_explicit_approval_of_the_reviewed_email(): void {
        $service=app(TrialCommunicationService::class); $service->commit($this->preview(),$this->admin);
        $log=BulkEmailLog::sole(); $log->markAsFailed('Temporary transport error');
        $url=route('backend.interprovincial-trials.communications.retry',[$this->event,$log]);
        $this->actingAs($this->admin)->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('approved_retry');
        $this->assertSame('failed',$log->fresh()->status);
        $this->actingAs($this->admin)->post($url,['approved_retry'=>1])->assertRedirect();
        $this->assertSame('queued',$log->fresh()->status);
    }
    public function test_legacy_invitation_retry_endpoints_require_new_review_without_queuing(): void {
        $batch=InterprovincialTrialInvitationBatch::create(['event_id'=>$this->event->id,'status'=>'draft','created_by_user_id'=>$this->admin->id,'snapshot_hash'=>str_repeat('a',64)]);
        $invitation=InterprovincialTrialInvitation::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'nomination_id'=>$this->nomination->id,'batch_id'=>$batch->id,'player_id'=>$this->player->id,'status'=>'failed']);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.retry',[$this->event,$batch,$invitation]))->assertRedirect(route('backend.interprovincial-trials.communications.index',$this->event));
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.retry-follow-up',[$this->event,$invitation]))->assertRedirect(route('backend.interprovincial-trials.communications.index',$this->event));
        $this->assertSame('failed',$invitation->fresh()->status); $this->assertDatabaseCount('bulk_email_logs',0);
        Bus::assertNothingDispatched();
    }
    public function test_system_message_drafts_are_event_scoped_and_reviewable(): void {
        $draft=\App\Models\EventCommunicationBatch::create(['event_id'=>$this->event->id,'token'=>(string)\Illuminate\Support\Str::uuid(),'subject'=>'Registration confirmation','body'=>'','options'=>['source'=>'transaction'],'recipients'=>[],'issues'=>[],'fingerprint'=>str_repeat('a',64),'status'=>'draft']);
        $other=Event::factory()->create();
        \App\Models\EventCommunicationBatch::create(['event_id'=>$other->id,'token'=>(string)\Illuminate\Support\Str::uuid(),'subject'=>'Other event','body'=>'','options'=>['source'=>'transaction'],'recipients'=>[],'issues'=>[],'fingerprint'=>str_repeat('b',64),'status'=>'draft']);
        $response=$this->actingAs($this->admin)->get(route('backend.interprovincial-trials.communications.index',$this->event))->assertOk();
        $this->assertSame([$draft->id],$response->viewData('pendingDrafts')->pluck('id')->all());
        $response->assertSee(route('backend.event-communications.drafts.preview',[$this->event,$draft]),false);
    }
    public function test_unpublished_nominees_can_receive_announcements_after_registration_closes(): void {
        $this->category->update(['nominations_published'=>false]);
        $this->event->update(['start_date'=>now()->subMonth()]);
        $service=app(TrialCommunicationService::class);
        $preview=$this->preview('not_registered');
        $this->assertCount(1,$preview->recipients);
        $this->assertSame(1,$service->commit($preview,$this->admin)['queued']);
    }
    public function test_regional_manager_summary_is_event_scoped_and_includes_selected_roster_only(): void {
        $manager=User::factory()->create(['email'=>'manager@example.test']);
        $otherManager=User::factory()->create(['email'=>'other-manager@example.test']);
        $region=DB::table('event_regions')->insertGetId(['event_id'=>$this->event->id,'region_id'=>1]);
        DB::table('event_region_managers')->insert(['event_id'=>$this->event->id,'region_id'=>1,'event_region_id'=>$region,'user_id'=>$manager->id]);
        $other=Event::factory()->create();
        $otherRegion=DB::table('event_regions')->insertGetId(['event_id'=>$other->id,'region_id'=>1]);
        DB::table('event_region_managers')->insert(['event_id'=>$other->id,'region_id'=>1,'event_region_id'=>$otherRegion,'user_id'=>$otherManager->id]);
        $service=app(TrialCommunicationService::class);
        $preview=$service->preview($this->event,$this->admin,['audience'=>'nominations','recipients'=>'both','individual_ids'=>[$this->nomination->id]],'Summary','{players}');
        $this->assertSame(['family@example.test','manager@example.test'],array_column($preview->recipients,'email'));
        $this->assertStringContainsString('not_registered',$preview->recipients[1]['body']);
        $preview=$service->preview($this->event,$this->admin,['audience'=>'nominations','recipients'=>'managers'],'Summary','{players}');
        $this->assertSame(['manager@example.test'],array_column($preview->recipients,'email'));
        $this->assertSame(1,$service->commit($preview,$this->admin)['queued']);
    }
    public function test_each_repeating_reminder_occurrence_requires_manual_approval(): void {
        $service=app(TrialCommunicationService::class);
        $schedule=$service->schedule($this->preview(),$this->admin,now()->addHour(),1,null);
        $this->travel(2)->hours();
        $service->runDue(); $this->assertDatabaseCount('bulk_email_logs',0);
        $preview=$service->preview($this->event,$this->admin,array_merge($schedule->options,['_schedule_id'=>$schedule->id]),$schedule->subject,$schedule->body);
        $this->assertSame(1,$service->commit($preview,$this->admin)['queued']);
        $this->assertTrue($schedule->fresh()->next_send_at->isFuture());
        $this->travel(2)->hours(); $service->runDue(); $this->assertDatabaseCount('bulk_email_logs',1);
    }
    public function test_admin_selector_datasets_are_bounded(): void {
        foreach (Player::factory()->count(505)->create() as $player) EventNomination::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'player_id'=>$player->id]);
        $response=$this->actingAs($this->admin)->get(route('backend.interprovincial-trials.communications.index',$this->event))->assertOk();
        $this->assertCount(500,$response->viewData('nominees'));
    }
    public function test_nomination_lifecycle_resolution_uses_one_bounded_query(): void {
        $batch=InterprovincialTrialInvitationBatch::create(['event_id'=>$this->event->id,'status'=>'draft','created_by_user_id'=>$this->admin->id,'snapshot_hash'=>str_repeat('a',64)]);
        foreach (Player::factory()->count(3)->create() as $player) {
            $nomination=EventNomination::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'player_id'=>$player->id,'nominee_email'=>'family-'.$player->id.'@example.test']);
            InterprovincialTrialInvitation::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'nomination_id'=>$nomination->id,'batch_id'=>$batch->id,'player_id'=>$player->id,'status'=>'sent']);
        }
        DB::flushQueryLog();DB::enableQueryLog();$this->preview();
        $queries=collect(DB::getQueryLog())->filter(fn(array $query)=>str_contains(strtolower($query['query']),'interprovincial_trial_invitations'));
        $this->assertCount(1,$queries);DB::disableQueryLog();
    }
    public function test_team_participation_resolution_uses_one_bounded_query(): void {
        $run=\App\Models\TrialRankingRun::create(['event_id'=>$this->event->id,'signature'=>str_repeat('b',64),'positions'=>[]]);
        $draft=\App\Models\TrialSquadDraft::create(['event_id'=>$this->event->id,'ranking_run_id'=>$run->id,'tiers'=>['A'],'created_by'=>$this->admin->id,'status'=>'finalised','finalised_at'=>now()]);
        foreach (Player::factory()->count(3)->create() as $index=>$player) {
            $slot=\App\Models\TrialSquadSlot::create(['draft_id'=>$draft->id,'category_event_id'=>$this->category->id,'tier'=>'A','slot'=>$index+1,'player_id'=>$player->id]);
            \App\Models\TrialParticipation::create(['event_id'=>$this->event->id,'player_id'=>$player->id,'slot_id'=>$slot->id]);
        }
        DB::flushQueryLog();DB::enableQueryLog();
        app(TrialCommunicationService::class)->preview($this->event,$this->admin,['audience'=>'teams','filter'=>'all'],'Subject','{players}');
        $queries=collect(DB::getQueryLog())->filter(fn(array $query)=>str_contains(strtolower($query['query']),'from "trial_participations"') || str_contains(strtolower($query['query']),'from `trial_participations`'));
        $this->assertCount(1,$queries);DB::disableQueryLog();
    }
}
