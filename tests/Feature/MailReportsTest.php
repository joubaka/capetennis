<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, Event, EventCommunicationBatch, EventType, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Illuminate\Support\Str;
use Tests\TestCase;

class MailReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); Queue::fake();
        \Spatie\Permission\Models\Role::findOrCreate('admin','web');
        \Spatie\Permission\Models\Role::findOrCreate('super-user','web');
    }

    private function log(Event $event,string $subject,string $status,array $evidence=[]): BulkEmailLog
    {
        return BulkEmailLog::create($evidence+['mail_type'=>'event_email','recipient_name'=>'Searchable Recipient','recipient_email'=>'recipient@example.test','status'=>$status,'payload'=>['event_id'=>$event->id,'subject'=>$subject,'body'=>'PRIVATE_BODY']]);
    }

    public function test_sent_failed_pending_and_excluded_filters_work_for_every_event_workflow(): void
    {
        foreach ([1,2,3,4,5,6,7,'masters','interprovincial-trials'] as $kind) {
            $type = DB::table('eventtypes')->insertGetId(['name'=>'Workflow '.$kind,'type'=>is_int($kind)?$kind:1,'code'=>is_string($kind)?$kind:null]);
            $event = Event::factory()->create(['eventType'=>$type]);
            $actor = User::factory()->create()->assignRole('admin');
            DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$actor->id]);
            $accepted=$this->log($event,'Confirmed send','sent',['evidence_status'=>'server_accepted','accepted_at'=>now()]);
            $legacy=$this->log($event,'Historical send','sent');
            $failed=$this->log($event,'Failed send','failed');
            $this->log($event,'Sandbox send','sent',['evidence_status'=>'sandbox_accepted','accepted_at'=>now()]);
            $this->log($event,'Uncertain send','acceptance_unknown');
            $this->log($event,'Pending send','queued');
            $this->log($event,'Excluded send','skipped');
            $this->actingAs($actor)->get(route('backend.event-communications.index',['event'=>$event,'history_outcome'=>'sent_complete']))
                ->assertOk()->assertSee('Confirmed send')->assertSee('Historical send')->assertDontSee('Failed send')->assertDontSee('Sandbox send')->assertDontSee('Uncertain send')
                ->assertViewHas('historyReport',fn($report)=>$report['logs']->total()===2 && $report['counts']['failed']===1);
            $this->get(route('backend.event-communications.index',['event'=>$event,'history_outcome'=>'failed']))->assertOk()->assertSee('Failed send')->assertDontSee('Historical send');
            if ($event->isInterprovincialTrials()) $this->get(route('backend.interprovincial-trials.communications.index',['event'=>$event,'history_outcome'=>'failed']))->assertOk()->assertSee('Failed send')->assertDontSee('Historical send');
        }
        Mail::assertNothingSent();
    }

    public function test_filters_preserve_selected_batch_pagination_and_do_not_expose_other_events(): void
    {
        $event=Event::factory()->create(); $other=Event::factory()->create();
        $actor=User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$actor->id]);
        $batch=EventCommunicationBatch::create(['event_id'=>$event->id,'created_by'=>$actor->id,'token'=>(string)Str::uuid(),'subject'=>'Batch','body'=>'Message','options'=>['scope'=>'all'],'recipients'=>[],'issues'=>[],'fingerprint'=>str_repeat('a',64)]);
        for($i=0;$i<27;$i++) {
            $log=$this->log($event,'Clothing '.$i,'failed');
            $log->update(['payload'=>[...$log->payload,'event_communication_batch_id'=>$batch->id]]);
        }
        $this->log($other,'FOREIGN_PRIVATE','failed');
        $url=route('backend.event-communications.index',['event'=>$event,'batch'=>$batch->id,'report_scope'=>'batch','history_outcome'=>'failed','history_search'=>'Searchable','history_from'=>now()->toDateString(),'history_until'=>now()->toDateString()]);
        $this->actingAs($actor)->get($url)->assertOk()->assertDontSee('FOREIGN_PRIVATE')->assertViewHas('historyReport',function($report)use($batch){
            $next=$report['logs']->nextPageUrl();
            return $report['logs']->total()===27 && str_contains($next,'batch='.$batch->id) && str_contains($next,'history_outcome=failed') && str_contains($next,'history_page=2');
        });
        $this->get(route('backend.event-communications.index',['event'=>$event,'history_from'=>'2026-10-05','history_until'=>'2026-10-04']))->assertSessionHasErrors('history_until');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_superadmin_uses_same_sent_semantics_without_loading_bodies_or_private_errors(): void
    {
        $event=Event::factory()->create();
        $this->log($event,'Confirmed send','sent',['evidence_status'=>'server_accepted','accepted_at'=>now()]);
        $this->log($event,'Historical send','sent');
        $this->log($event,'Sandbox send','sent',['evidence_status'=>'sandbox_accepted','accepted_at'=>now()]);
        $this->log($event,'Failed send','failed',['error_message'=>'SECRET_ERROR']);
        $actor=User::factory()->create()->assignRole('super-user');
        $this->actingAs($actor)->get(route('backend.superadmin.mail-history',['mail_outcome'=>'sent_complete']))
            ->assertOk()->assertSee('Confirmed send')->assertSee('Historical send')->assertDontSee('Sandbox send')->assertDontSee('PRIVATE_BODY')->assertDontSee('SECRET_ERROR')
            ->assertViewHas('mailLogs',fn($logs)=>$logs->total()===2 && $logs->first()->payload===null);
        $this->get(route('backend.superadmin.mail-history',['mail_outcome'=>'failed']))->assertOk()->assertSee('Failed send')->assertDontSee('SECRET_ERROR');
    }
}
