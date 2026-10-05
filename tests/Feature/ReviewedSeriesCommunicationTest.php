<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\{BulkEmailLog, CategoryEvent, CategoryEventRegistration, Event, EventCommunicationBatch, EventType, Player, Registration, Series, User};
use App\Services\SeriesCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ReviewedSeriesCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        \Spatie\Permission\Models\Role::findOrCreate('admin','web');
    }

    private function fixture(bool $includeTrials = true): array
    {
        $series = Series::factory()->create();
        $actor = User::factory()->create()->assignRole('admin');
        $player = Player::factory()->create(['email'=>'shared@example.test','userId'=>null]);
        $events = collect();
        foreach ($includeTrials ? [[1,null],[2,null],[1,EventType::INTERPROVINCIAL_TRIALS_CODE]] : [[1,null],[2,null]] as [$kind,$code]) {
            $type = DB::table('eventtypes')->insertGetId(['name'=>'Review '.$kind,'type'=>$kind,'code'=>$code]);
            $event = Event::factory()->create(['series_id'=>$series->id,'eventType'=>$type]);
            DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$actor->id]);
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['registration_id'=>$registration->id,'category_event_id'=>CategoryEvent::factory()->create(['event_id'=>$event->id])->id]);
            $events->push($event);
        }

        return [$series,$actor,$events,$player];
    }

    private function preview(Series $series, User $actor, string $intent): void
    {
        $this->actingAs($actor)->postJson(route('series.email.players',$series),['campaign_key'=>$intent,'emailSubject'=>'Clothing','message'=>'Collect clothing','fromName'=>'Event organiser','replyTo'=>$actor->email])
            ->assertOk()->assertJsonPath('review_required',true);
    }

    public function test_mixed_series_never_queues_before_exact_review_and_overlap_is_explicit(): void
    {
        [$series,$actor,$events] = $this->fixture();
        $intent = (string) Str::uuid();
        $this->preview($series,$actor,$intent);
        $this->assertDatabaseCount('bulk_email_logs',0);
        Queue::assertNothingPushed();
        $this->get(route('series.email.review',['series'=>$series,'intent'=>$intent]))->assertOk()->assertSee('3 event messages')->assertSee('1 unique email addresses')->assertSee('separate message for each event');
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertRedirect();
        Queue::assertPushed(SendBulkEmailJob::class,3);
        $this->assertDatabaseCount('bulk_email_logs',3);
        foreach (BulkEmailLog::all() as $log) {
            $this->assertSame('enqueued',data_get($log->payload,'queue_state'));
            $batch = EventCommunicationBatch::findOrFail($log->payload['event_communication_batch_id']);
            $this->assertNotNull($batch->approved_at);
            $this->assertSame($batch->event_id,$log->payload['event_id']);
        }
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHas('info');
        Queue::assertPushed(SendBulkEmailJob::class,3);
        $this->assertDatabaseCount('bulk_email_logs',3);
        Mail::assertNothingSent();
    }

    public function test_every_event_authorization_and_contact_snapshot_are_checked_before_any_queue(): void
    {
        [$series,$actor,$events,$player] = $this->fixture();
        $intent = (string) Str::uuid();
        $this->preview($series,$actor,$intent);
        $player->update(['email'=>'changed@example.test']);
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs',0);
        Queue::assertNothingPushed();
        DB::table('event_admins')->where('event_id',$events->last()->id)->where('user_id',$actor->id)->delete();
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertForbidden();
        $this->previewDenied($series,$actor);
        $this->assertDatabaseCount('bulk_email_logs',0);
    }

    private function previewDenied(Series $series, User $actor): void
    {
        $this->actingAs($actor)->postJson(route('series.email.players',$series),['campaign_key'=>(string)Str::uuid(),'emailSubject'=>'Private','message'=>'Body'])->assertForbidden();
    }

    public function test_after_commit_queue_failure_does_not_stop_later_callbacks_and_fresh_intent_is_independent(): void
    {
        [$series,$actor] = $this->fixture(false);
        $intent = (string) Str::uuid();
        $this->preview($series,$actor,$intent);
        $attempts = 0;
        $queue = Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->andReturnUsing(function ($job) use (&$attempts) {
            $attempts++;
            if ($attempts===1) throw new \RuntimeException('Queue unavailable');
            return 'job-'.$attempts;
        });
        $factory = Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->andReturn($queue);
        $this->app->instance(\Illuminate\Contracts\Queue\Factory::class,$factory);
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHas('warning');
        $this->assertSame(2,$attempts);
        $this->assertSame(1,BulkEmailLog::where('status','failed')->count());
        $this->assertSame(1,BulkEmailLog::where('payload->queue_state','enqueued')->count());
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHas('info');
        $this->assertSame(2,$attempts);
        $fresh = (string)Str::uuid();
        $this->preview($series,$actor,$fresh);
        $this->post(route('series.email.approve',$series),['intent'=>$fresh,'confirmed'=>1])->assertSessionHas('success');
        $this->assertSame(4,$attempts);
        $this->assertDatabaseCount('bulk_email_logs',4);
        Mail::assertNothingSent();
    }

    public function test_review_cannot_be_approved_from_one_event_endpoint_or_changed_under_same_intent(): void
    {
        [$series,$actor,$events] = $this->fixture(false);
        $intent = (string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $batch = EventCommunicationBatch::where('event_id',$events->first()->id)->sole();
        $this->post(route('backend.event-communications.send',$events->first()),['token'=>$batch->token,'confirm_send'=>1])->assertStatus(422);
        $this->postJson(route('series.email.players',$series),['campaign_key'=>$intent,'emailSubject'=>'Changed','message'=>'Changed'])->assertUnprocessable();
        $this->assertDatabaseCount('bulk_email_logs',0);
        Queue::assertNothingPushed();
    }

    public function test_fast_worker_outcomes_do_not_change_successful_queue_submission_counts(): void
    {
        [$series,$actor] = $this->fixture();
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $outcomes=['sent','acceptance_unknown','failed'];
        $queue=Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->andReturnUsing(function($job)use(&$outcomes){
            BulkEmailLog::whereKey($job->logId)->update(['status'=>array_shift($outcomes)]);
            return 'accepted-job';
        });
        $factory=Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->andReturn($queue);
        $this->app->instance(\Illuminate\Contracts\Queue\Factory::class,$factory);
        $stats=app(SeriesCommunicationService::class)->approve($series,$actor,$intent,false);
        $this->assertSame(3,$stats['queued']);
        $this->assertSame(0,$stats['failed']);
        $this->assertSame(1,BulkEmailLog::where('status','failed')->count());
        $this->assertSame(1,BulkEmailLog::where('status','acceptance_unknown')->count());
        Mail::assertNothingSent();
    }

    public function test_confirmation_marker_failure_keeps_accepted_job_pending_and_continues_callbacks(): void
    {
        [$series,$actor] = $this->fixture(false);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $failMarker=true;
        DB::connection()->beforeExecuting(function($query)use(&$failMarker){
            if ($failMarker && str_contains(strtolower($query),'json_set') && str_contains($query,'queue_state')) {
                $failMarker=false;
                throw new \RuntimeException('Marker storage unavailable');
            }
        });
        $stats=app(SeriesCommunicationService::class)->approve($series,$actor,$intent,false);
        Queue::assertPushed(SendBulkEmailJob::class,2);
        $this->assertSame(1,$stats['queued']);
        $this->assertSame(1,$stats['pending']);
        $this->assertSame(0,$stats['failed']);
        $this->assertSame(2,BulkEmailLog::where('status','queued')->count());
        Mail::assertNothingSent();
    }

    public function test_removed_series_event_blocks_saved_review_disclosure(): void
    {
        [$series,$actor,$events] = $this->fixture(false);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $events->last()->update(['series_id'=>null]);
        $this->get(route('series.email.review',['series'=>$series,'intent'=>$intent]))->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_queue_exception_cannot_overwrite_worker_claim_and_later_callbacks_continue(): void
    {
        [$series,$actor] = $this->fixture(false);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $calls=0;
        $queue=Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->andReturnUsing(function($job)use(&$calls){
            $calls++;
            if ($calls===1) {
                BulkEmailLog::whereKey($job->logId)->update(['status'=>'sending']);
                throw new \RuntimeException('Submission response unavailable');
            }
            return 'accepted-job';
        });
        $factory=Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->andReturn($queue);
        $this->app->instance(\Illuminate\Contracts\Queue\Factory::class,$factory);
        $stats=app(SeriesCommunicationService::class)->approve($series,$actor,$intent,false);
        $this->assertSame(2,$calls);
        $this->assertSame(1,$stats['pending']);
        $this->assertSame(1,$stats['queued']);
        $this->assertSame(0,$stats['failed']);
        $this->assertSame(1,BulkEmailLog::where('status','sending')->count());
        Mail::assertNothingSent();
    }

    public function test_marker_failure_after_fast_worker_is_still_unconfirmed_submission(): void
    {
        [$series,$actor] = $this->fixture(false);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $calls=0;
        $queue=Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('push')->andReturnUsing(function($job)use(&$calls){
            BulkEmailLog::whereKey($job->logId)->update(['status'=>++$calls===1 ? 'sent' : 'acceptance_unknown']);
            return 'accepted-job';
        });
        $factory=Mockery::mock(\Illuminate\Contracts\Queue\Factory::class);
        $factory->shouldReceive('connection')->andReturn($queue);
        $this->app->instance(\Illuminate\Contracts\Queue\Factory::class,$factory);
        $failMarker=true;
        DB::connection()->beforeExecuting(function($query)use(&$failMarker){
            if ($failMarker && str_contains(strtolower($query),'json_set') && str_contains($query,'queue_state')) {
                $failMarker=false;
                throw new \RuntimeException('Marker storage unavailable');
            }
        });
        $stats=app(SeriesCommunicationService::class)->approve($series,$actor,$intent,false);
        $this->assertSame(1,$stats['pending']);
        $this->assertSame(1,$stats['queued']);
        $this->assertSame(0,$stats['failed']);
        Mail::assertNothingSent();
    }

}
