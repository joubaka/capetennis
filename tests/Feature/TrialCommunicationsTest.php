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
    public function test_missing_contacts_are_reported_and_unpublished_nominees_excluded(): void {
        $this->player->update(['email'=>null,'userId'=>null]); $this->player->users()->detach();
        $preview=$this->preview(); $this->assertCount(0,$preview->recipients); $this->assertCount(1,$preview->excluded);
        $this->category->update(['nominations_published'=>false]); $preview=$this->preview(); $this->assertCount(0,$preview->excluded);
    }
    public function test_due_reminders_recheck_status_and_stop_after_one_run(): void {
        $service=app(TrialCommunicationService::class); $preview=$this->preview('not_registered');
        $schedule=$service->schedule($preview,$this->admin,now()->addHour(),null,null);
        $batch=InterprovincialTrialInvitationBatch::create(['event_id'=>$this->event->id,'status'=>'draft','created_by_user_id'=>$this->admin->id,'snapshot_hash'=>str_repeat('a',64)]);
        InterprovincialTrialInvitation::create(['event_id'=>$this->event->id,'category_event_id'=>$this->category->id,'nomination_id'=>$this->nomination->id,'batch_id'=>$batch->id,'player_id'=>$this->player->id,'status'=>'paid_confirmed']);
        $this->travel(2)->hours(); $this->assertSame(1,$service->runDue()); $this->assertDatabaseCount('bulk_email_logs',0); $this->assertFalse($schedule->fresh()->active); $this->assertSame(0,$service->runDue());
    }
    public function test_failed_retry_reuses_log_and_requires_admin(): void {
        $service=app(TrialCommunicationService::class); $service->commit($this->preview(),$this->admin); $log=BulkEmailLog::sole(); $log->markAsFailed('Temporary transport error');
        $this->assertTrue($service->retry($log,$this->admin)); $this->assertFalse($service->retry($log,$this->admin)); $this->assertDatabaseCount('bulk_email_logs',1);
        $this->assertSame('queued',$log->fresh()->status);
    }
    public function test_saved_template_remains_fully_editable(): void {
        $template=app(TrialCommunicationService::class)->saveTemplate($this->event,$this->admin,'My invitation','My exact subject','My whole body');
        $this->assertSame('My whole body',$template->body); $this->assertSame($this->event->id,$template->event_id);
    }
    public function test_team_messages_require_finalisation_and_respect_tier_filters(): void {
        $run=\App\Models\TrialRankingRun::create(['event_id'=>$this->event->id,'signature'=>str_repeat('a',64),'positions'=>[]]);
        $draft=\App\Models\TrialSquadDraft::create(['event_id'=>$this->event->id,'ranking_run_id'=>$run->id,'tiers'=>['A'],'created_by'=>$this->admin->id]);
        \App\Models\TrialSquadSlot::create(['draft_id'=>$draft->id,'category_event_id'=>$this->category->id,'tier'=>'A','slot'=>1,'player_id'=>$this->player->id]);
        $service=app(TrialCommunicationService::class);
        $preview=$service->preview($this->event,$this->admin,['audience'=>'teams','filter'=>'all'],'Subject','{players}');
        $this->assertCount(0,$preview->recipients);
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
}
