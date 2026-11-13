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

    private function fixture(bool $includeTrials = true, bool $distinctContacts = false): array
    {
        $series = Series::factory()->create();
        $actor = User::factory()->create()->assignRole('admin');
        $player = Player::factory()->create(['email'=>'shared@example.test','userId'=>null]);
        $events = collect();
        foreach ($includeTrials ? [[1,null],[2,null],[1,EventType::INTERPROVINCIAL_TRIALS_CODE]] : [[1,null],[2,null]] as [$kind,$code]) {
            $type = DB::table('eventtypes')->insertGetId(['name'=>'Review '.$kind,'type'=>$kind,'code'=>$code]);
            $event = Event::factory()->create(['series_id'=>$series->id,'eventType'=>$type]);
            DB::table('event_admins')->insert(['event_id'=>$event->id,'user_id'=>$actor->id]);
            $entryPlayer = $distinctContacts ? Player::factory()->create(['email'=>'event'.$event->id.'@example.test','userId'=>null]) : $player;
            if ($event->isTeam()) {
                $team = \App\Models\Team::factory()->create(['category_event_id'=>CategoryEvent::factory()->create(['event_id'=>$event->id])->id]);
                \App\Models\TeamPlayer::create(['team_id'=>$team->id,'player_id'=>$entryPlayer->id,'rank'=>1,'pay_status'=>1]);
            }
            $registration = Registration::factory()->create();
            $registration->players()->attach($entryPlayer);
            CategoryEventRegistration::factory()->create(['payment_status_id'=>1,'registration_id'=>$registration->id,'category_event_id'=>CategoryEvent::factory()->create(['event_id'=>$event->id])->id]);
            $events->push($event);
        }

        return [$series,$actor,$events,$player];
    }

    private function preview(Series $series, User $actor, string $intent, string $cc = ''): void
    {
        $this->actingAs($actor)->postJson(route('series.email.players',$series),['campaign_key'=>$intent,'emailSubject'=>'Clothing','message'=>'Collect clothing','fromName'=>'Event organiser','replyTo'=>$actor->email,'cc'=>$cc])
            ->assertOk()->assertJsonPath('review_required',true);
    }

    public function test_mixed_series_queues_only_once_per_address_after_exact_review(): void
    {
        [$series,$actor,$events] = $this->fixture();
        $intent = (string) Str::uuid();
        $this->preview($series,$actor,$intent);
        $this->assertDatabaseCount('bulk_email_logs',0);
        Queue::assertNothingPushed();
        $this->get(route('series.email.review',['series'=>$series,'intent'=>$intent]))->assertOk()->assertSee('1 emails')->assertSee('1 unique email addresses')->assertSee('Each email address receives one message');
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertRedirect();
        Queue::assertPushed(SendBulkEmailJob::class,1);
        $this->assertDatabaseCount('bulk_email_logs',1);
        foreach (BulkEmailLog::all() as $log) {
            $this->assertSame('enqueued',data_get($log->payload,'queue_state'));
            $batch = EventCommunicationBatch::findOrFail($log->payload['event_communication_batch_id']);
            $this->assertNotNull($batch->approved_at);
            $this->assertSame($batch->event_id,$log->payload['event_id']);
        }
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHas('info');
        Queue::assertPushed(SendBulkEmailJob::class,1);
        $this->assertDatabaseCount('bulk_email_logs',1);
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
        [$series,$actor] = $this->fixture(false, true);
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
        [$series,$actor] = $this->fixture(true, true);
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
        [$series,$actor] = $this->fixture(false, true);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $failMarker=true;
        DB::connection()->beforeExecuting(function($query, $bindings)use(&$failMarker){
            if ($failMarker && str_starts_with(strtolower($query), 'update') && str_contains($query, 'bulk_email_logs')
                && str_contains($query, 'payload') && (str_contains($query, 'queue_state') || collect($bindings)->contains(fn ($value) => is_string($value) && str_contains($value, '"queue_state":"enqueued"')))) {
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
        $this->assertFalse($failMarker, 'The submission confirmation storage failure must be exercised.');
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
        [$series,$actor] = $this->fixture(false, true);
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
        [$series,$actor] = $this->fixture(false, true);
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
        DB::connection()->beforeExecuting(function($query, $bindings)use(&$failMarker){
            if ($failMarker && str_starts_with(strtolower($query), 'update') && str_contains($query, 'bulk_email_logs')
                && str_contains($query, 'payload') && (str_contains($query, 'queue_state') || collect($bindings)->contains(fn ($value) => is_string($value) && str_contains($value, '"queue_state":"enqueued"')))) {
                $failMarker=false;
                throw new \RuntimeException('Marker storage unavailable');
            }
        });
        $stats=app(SeriesCommunicationService::class)->approve($series,$actor,$intent,false);
        $this->assertSame(1,$stats['pending']);
        $this->assertSame(1,$stats['queued']);
        $this->assertSame(0,$stats['failed']);
        $this->assertFalse($failMarker, 'The submission confirmation storage failure must be exercised.');
        Mail::assertNothingSent();
    }

    public function test_active_audience_normalizes_family_contacts_and_excludes_unconfirmed_and_other_series(): void
    {
        [$series,$actor,$events,$player] = $this->fixture(false);
        $player->update(['email'=>' SHARED@example.test ', 'email_2'=>'shared@example.test', 'email_3'=>'extra@example.test']);
        foreach (['withdrawn','pending_checkout','unpaid','deleted','other_series','family'] as $state) {
            $target = $state === 'other_series' ? Event::factory()->create(['series_id'=>Series::factory()->create(['year'=>$series->year - 1])->id]) : $events->first();
            $person = Player::factory()->create(['email'=>$state === 'family' ? 'SHARED@example.test' : $state.'@example.test','userId'=>null]);
            $registration = Registration::factory()->create();
            $registration->players()->attach($person);
            $entry = CategoryEventRegistration::factory()->create(['registration_id'=>$registration->id,
                'category_event_id'=>CategoryEvent::factory()->create(['event_id'=>$target->id])->id,
                'payment_status_id'=>$state === 'unpaid' ? null : 1,
                'status'=>in_array($state,['withdrawn','pending_checkout'],true) ? $state : 'active']);
            if ($state === 'deleted') $entry->delete();
        }
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $batches=app(SeriesCommunicationService::class)->batches($series,$actor,$intent);
        $this->assertSame(['extra@example.test','shared@example.test'], $batches->flatMap(fn($batch)=>array_column($batch->recipients,'email'))->sort()->values()->all());
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertRedirect();
        Queue::assertPushed(SendBulkEmailJob::class,2);
        Mail::assertNothingSent();
    }

    public function test_legacy_review_requires_a_fresh_intent(): void
    {
        [$series,$actor]=$this->fixture(false);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        foreach (EventCommunicationBatch::all() as $batch) {
            $options=$batch->options;
            unset($options['audience']);
            $batch->update(['options'=>$options]);
        }
        $this->get(route('series.email.review',['series'=>$series,'intent'=>$intent]))->assertStatus(422);
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_withdrawal_of_overlap_entry_invalidates_review_even_without_changing_unique_addresses(): void
    {
        [$series,$actor,$events]=$this->fixture();
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $events->last()->registrations()->first()->update(['status'=>'withdrawn']);
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHasErrors('preview');
        Queue::assertNothingPushed();
    }

    public function test_missing_contact_requires_acknowledgement_before_other_contacts_are_queued(): void
    {
        [$series,$actor,$events]=$this->fixture(false);
        $missing=Player::factory()->create(['email'=>null,'email_2'=>null,'email_3'=>null,'userId'=>null]);
        $registration=Registration::factory()->create();
        $registration->players()->attach($missing);
        CategoryEventRegistration::factory()->create(['registration_id'=>$registration->id,'payment_status_id'=>1,
            'category_event_id'=>CategoryEvent::factory()->create(['event_id'=>$events->first()->id])->id]);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent);
        $this->get(route('series.email.review',['series'=>$series,'intent'=>$intent]))->assertOk()->assertSee('no valid contact');
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHasErrors('acknowledge_missing');
        Queue::assertNothingPushed();
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1,'acknowledge_missing'=>1])->assertRedirect();
        Queue::assertPushed(SendBulkEmailJob::class,1);
    }

    public function test_combined_review_shows_one_list_and_one_identical_message_with_cc_once(): void
    {
        [$series,$actor,$events,$player]=$this->fixture(true,true);
        $secondary='secondary@example.test';
        $lastPlayer=\App\Models\Player::where('email','event'.$events->last()->id.'@example.test')->firstOrFail();
        $lastPlayer->update(['email_2'=>$secondary]);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent,' EXTRA@example.test,extra@example.test; '.$secondary.'; EVENT'.$events->last()->id.'@example.test');
        $review=$this->get(route('series.email.review',['series'=>$series,'intent'=>$intent]))->assertOk()
            ->assertSee('All series recipients')->assertSee('CC / additional copies:')->assertSee('extra@example.test');
        $html=$review->getContent();
        $this->assertSame(1,substr_count($html,'<summary>Review all '));
        $this->assertSame(1,substr_count($html,'<iframe '));
        $this->assertSame(1,substr_count($html,'data-series-combined-review'));
        if ($path=getenv('SERIES_REVIEW_FIXTURE_PATH')) file_put_contents($path,$html);
        $batches=app(SeriesCommunicationService::class)->batches($series,$actor,$intent);
        $recipients=$batches->flatMap(fn($batch)=>$batch->recipients);
        $this->assertCount(5,$recipients);
        $this->assertCount(1,$recipients->where('email','extra@example.test'));
        $this->assertCount(1,$recipients->pluck('html')->unique());
        $this->assertSame('cc',$recipients->firstWhere('email','extra@example.test')['kind']);
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs',5);
        Queue::assertPushed(SendBulkEmailJob::class,5);
        $this->post(route('series.email.approve',$series),['intent'=>$intent,'confirmed'=>1])->assertSessionHas('info');
        Queue::assertPushed(SendBulkEmailJob::class,5);
        $this->assertSame(1,BulkEmailLog::where('recipient_email','extra@example.test')->count());
        Mail::assertNothingSent();
    }

    public function test_cc_must_be_valid_and_cannot_change_within_reviewed_intent(): void
    {
        [$series,$actor]=$this->fixture(false);
        foreach (['invalid',"person@example.test\r\nBcc: injected@example.test", implode(',',array_map(fn($i)=>'person'.$i.'@example.test',range(1,21)))] as $cc) {
            $this->actingAs($actor)->postJson(route('series.email.players',$series),['campaign_key'=>(string)Str::uuid(),'emailSubject'=>'Clothing','message'=>'Collect clothing','cc'=>$cc])
                ->assertUnprocessable()->assertJsonValidationErrors('cc');
        }
        $this->assertDatabaseCount('event_communication_batches',0);
        $intent=(string)Str::uuid();
        $this->preview($series,$actor,$intent,'extra@example.test');
        $this->actingAs($actor)->postJson(route('series.email.players',$series),['campaign_key'=>$intent,'emailSubject'=>'Clothing','message'=>'Collect clothing','fromName'=>'Event organiser','replyTo'=>$actor->email,'cc'=>'different@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('intent');
        Queue::assertNothingPushed();
    }

}
