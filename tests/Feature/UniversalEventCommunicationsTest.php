<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, CategoryEvent, CategoryEventRegistration, Event, EventCommunicationBatch, EventNomination, EventRegionManager, EventType, Player, Registration, TeamRegion, User};
use App\Services\{BulkMailDispatcher, EventCommunicationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Tests\TestCase;

class UniversalEventCommunicationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Mail::fake();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function event(int $kind = 1, ?string $code = null): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Workflow '.$kind, 'type' => $kind, 'code' => $code]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => false, 'signUp' => 0]);
        $actor = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);

        return [$event, $actor];
    }

    private function entry(Event $event, Player $player, array $attributes = []): CategoryEventRegistration
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);

        return CategoryEventRegistration::factory()->create($attributes + [
            'category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id,
            'registration_id' => $registration->id,
        ]);
    }

    private function audienceOptions(array $overrides = []): array
    {
        return $overrides + ['scope' => 'all', 'filter' => 'all', 'recipients' => 'players'];
    }

    public function test_inline_individual_search_is_authorized_and_event_scoped(): void
    {
        [$event, $actor] = $this->event();
        [$other] = $this->event();
        $local = Player::factory()->create(['name' => 'Searchable', 'surname' => 'Local']);
        $foreign = Player::factory()->create(['name' => 'Searchable', 'surname' => 'Foreign']);
        $this->entry($event, $local);
        $this->entry($other, $foreign);
        $url = route('backend.event-communications.index', ['event' => $event, 'individual_search' => 'Searchable']);
        $this->actingAs($actor)->getJson($url)->assertOk()
            ->assertExactJson(['individuals' => [['key' => 'player:'.$local->id, 'name' => $local->full_name]]]);
        $this->getJson(route('backend.event-communications.index', ['event' => $event, 'individual_search' => 'No match']))
            ->assertOk()->assertExactJson(['individuals' => []]);
        $this->getJson(route('backend.event-communications.index', ['event' => $event, 'individual_search' => str_repeat('a', 101)]))
            ->assertUnprocessable();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_all_nine_workflow_types_have_a_functional_authorized_composer(): void
    {
        foreach ([1, 2, 3, 4, 5, 6, 7, 'masters', 'interprovincial-trials'] as $kind) {
            [$event, $actor] = $this->event(is_int($kind) ? $kind : 1, is_string($kind) ? $kind : null);
            $this->actingAs($actor)->get(route('backend.event-communications.index', $event))
                ->assertOk()->assertSee('data-mail-compose', false)->assertSee('From name')->assertSee('Reply-to address')->assertSee('Communications');
            if (! $event->isTeam() && ! $event->isInterprovincialTrials()) {
                $player = Player::factory()->create(['email' => 'player'.$event->id.'@example.test', 'userId' => null]);
                $this->entry($event, $player, ['payment_status_id' => 1]);
                $this->post(route('backend.event-communications.preview', $event), $this->audienceOptions(['scope' => 'registrations']) + ['subject' => 'Clothing', 'body' => 'Collect clothing'])
                    ->assertOk()->assertSee($player->email);
            }
        }
        Mail::assertNothingSent();
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_individual_preview_approval_revalidates_and_queues_once_in_exact_event_log(): void
    {
        [$event, $actor] = $this->event();
        [$other] = $this->event();
        $player = Player::factory()->create(['name' => 'Local', 'email' => 'local@example.test', 'userId' => null]);
        $foreign = Player::factory()->create(['name' => 'Foreign', 'email' => 'foreign@example.test', 'userId' => null]);
        $this->entry($event, $player, ['payment_status_id' => 1]);
        $this->entry($other, $foreign, ['payment_status_id' => 1]);
        $this->actingAs($actor)->get(route('backend.event-communications.index', ['event' => $event, 'search' => 'Local']))->assertOk()->assertSee('data-mail-compose', false)->assertDontSee($foreign->full_name);
        $service = app(EventCommunicationService::class);
        $batch = $service->preview($event, $actor, $this->audienceOptions(), 'Clothing', 'Collect clothing');
        $this->assertSame(['local@example.test'], array_column($batch->recipients, 'email'));
        $this->actingAs($actor)->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHas('success');
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHas('info');
        $log = BulkEmailLog::sole();
        $this->assertSame('bulk_event_mail', $log->mail_type);
        $this->assertSame($event->id, $log->payload['event_id']);
        $this->assertSame($actor->id, $log->payload['created_by']);
        $this->assertStringContainsString('Collect clothing', $log->payload['body']);
        $changed = $service->preview($event, $actor, $this->audienceOptions(), 'Changed contact', 'Details');
        $player->update(['email' => 'changed@example.test']);
        $this->post(route('backend.event-communications.send', $event), ['token' => $changed->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Mail::assertNothingSent();
    }

    public function test_category_status_filters_and_nominees_do_not_leak_other_event_status_or_payer(): void
    {
        [$event, $actor] = $this->event();
        [$other] = $this->event();
        $player = Player::factory()->create(['email' => 'family@example.test', 'userId' => null]);
        $paid = $this->entry($event, $player, ['payment_status_id' => 1]);
        $pendingEntry = $this->entry($event, $player, ['payment_status_id' => null]);
        $pendingEntry->categoryEvent->update(['category_id' => $paid->categoryEvent->category_id]);
        $withdrawn = Player::factory()->create(['email' => 'withdrawn@example.test', 'userId' => null]);
        $this->entry($event, $withdrawn, ['payment_status_id' => 1, 'status' => 'withdrawn_refunded']);
        $this->entry($other, $withdrawn, ['payment_status_id' => 1]);
        EventNomination::create(['event_id' => $event->id, 'category_event_id' => $paid->category_event_id, 'player_id' => $player->id]);
        EventNomination::create(['event_id' => $event->id, 'category_event_id' => $paid->category_event_id, 'nominee_name' => 'New', 'nominee_surname' => 'Nominee', 'nominee_email' => 'nominee@example.test']);
        $service = app(EventCommunicationService::class);
        $paidPlan = $service->plan($event, $actor, $this->audienceOptions(['filter' => 'paid']), 'Update', 'Body');
        $this->assertSame(['family@example.test'], array_column($paidPlan['recipients'], 'email'));
        $pending = $service->plan($event, $actor, $this->audienceOptions(['filter' => 'payment_pending']), 'Update', 'Body');
        $this->assertCount(1, $pending['recipients']);
        $this->assertStringNotContainsString('Paid Confirmed', $pending['recipients'][0]['html']);
        $nominees = $service->plan($event, $actor, $this->audienceOptions(['scope' => 'nominations']), 'Update', 'Body');
        $this->assertSame(['family@example.test', 'nominee@example.test'], array_column($nominees['recipients'], 'email'));
        $this->assertStringNotContainsString('Accepted Pending Payment', $nominees['recipients'][0]['html']);
        $withdrawal = $service->plan($event, $actor, $this->audienceOptions(['filter' => 'withdrawn']), 'Update', 'Body');
        $this->assertSame(['withdrawn@example.test'], array_column($withdrawal['recipients'], 'email'));
    }

    public function test_regional_manager_and_outsider_cannot_access_an_individual_roster(): void
    {
        [$event] = $this->event();
        $manager = User::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Region']);
        $eventRegion = DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id]);
        EventRegionManager::create(['event_id' => $event->id, 'region_id' => $region->id, 'event_region_id' => $eventRegion, 'user_id' => $manager->id]);
        foreach ([$manager, User::factory()->create()] as $actor) {
            $this->actingAs($actor)->get(route('backend.event-communications.index', $event))->assertForbidden();
            $this->post(route('backend.event-communications.preview', $event), $this->audienceOptions() + ['subject' => 'Private', 'body' => 'Body'])->assertForbidden();
        }
    }

    public function test_general_composer_rejects_team_manager_ranking_and_foreign_player_requests(): void
    {
        [$event, $actor] = $this->event();
        $this->actingAs($actor);
        foreach ([['scope' => 'region', 'region_id' => 1], ['scope' => 'team', 'team_id' => 1], ['scope' => 'rankings'], ['recipients' => 'managers']] as $options) {
            $this->post(route('backend.event-communications.preview', $event), $this->audienceOptions($options) + ['subject' => 'Private', 'body' => 'Body'])->assertStatus(422);
        }
        $foreign = Player::factory()->create();
        $this->post(route('backend.event-communications.preview', $event), $this->audienceOptions(['scope' => 'individual', 'individual_key' => 'player:'.$foreign->id]) + ['subject' => 'Private', 'body' => 'Body'])->assertNotFound();
        $this->assertDatabaseCount('event_communication_batches', 0);
    }

    public function test_missing_contacts_require_acknowledgement_and_approval_results_are_truthful(): void
    {
        [$event, $actor] = $this->event();
        $this->entry($event, Player::factory()->create(['email' => 'valid@example.test', 'userId' => null]));
        $this->entry($event, Player::factory()->create(['email' => null, 'userId' => null]));
        $service = app(EventCommunicationService::class);
        $batch = $service->preview($event, $actor, $this->audienceOptions(), 'Update', 'Body');
        $this->assertCount(1, $batch->issues);
        $this->actingAs($actor)->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('acknowledge_missing');
        $this->mock(BulkMailDispatcher::class)->shouldReceive('dispatch')->once()->andReturn(['queued' => 0, 'failed' => 1, 'skipped' => 0]);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1, 'acknowledge_missing' => 1])
            ->assertSessionHas('error', '0 emails queued; 1 failed to queue; 0 skipped. Check the send report for mail-server acceptance.');
        Mail::assertNothingSent();
    }

    public function test_masters_native_invitations_are_event_scoped_searchable_and_revalidated(): void
    {
        [$event, $actor] = $this->event(1, EventType::MASTERS_CODE);
        [$other] = $this->event(1, EventType::MASTERS_CODE);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $batch = \App\Models\MastersInvitationBatch::create(['event_id' => $event->id, 'series_id' => 1, 'ranking_run_id' => 'communications', 'created_by' => $actor->id, 'top_x' => 1, 'status' => 'sent']);
        $foreignBatch = \App\Models\MastersInvitationBatch::create(['event_id' => $other->id, 'series_id' => 1, 'ranking_run_id' => 'other', 'created_by' => $actor->id, 'top_x' => 1, 'status' => 'sent']);
        $player = Player::factory()->create(['name' => 'Native', 'surname' => 'Invitee', 'email' => 'native@example.test', 'userId' => null]);
        $foreign = Player::factory()->create(['name' => 'Native', 'surname' => 'Foreign', 'email' => 'foreign@example.test', 'userId' => null]);
        $invitation = \App\Models\MastersInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id, 'ranking_position' => 1, 'queue_position' => 1, 'status' => 'invited']);
        // Even a forged event_id cannot cross the batch/category event boundary.
        \App\Models\MastersInvitation::create(['batch_id' => $foreignBatch->id, 'event_id' => $event->id, 'category_event_id' => $foreignCategory->id, 'player_id' => $foreign->id, 'ranking_position' => 2, 'queue_position' => 2, 'status' => 'invited']);
        $service = app(EventCommunicationService::class);
        $this->actingAs($actor)->get(route('backend.event-communications.index', ['event' => $event, 'search' => 'Native']))
            ->assertOk()->assertDontSee('Native Foreign')->assertSee('Invited players');
        $this->getJson(route('backend.event-communications.index', ['event'=>$event, 'individual_search'=>'Native']))
            ->assertOk()->assertExactJson(['individuals'=>[['key'=>'player:'.$player->id,'name'=>$player->full_name]]]);
        $preview = $service->preview($event, $actor, $this->audienceOptions(['scope' => 'invitations']), 'Clothing', 'Arrangements');
        $this->assertSame(['native@example.test'], array_column($preview->recipients, 'email'));
        $all = $service->plan($event, $actor, $this->audienceOptions(), 'Clothing', 'Arrangements');
        $this->assertCount(1, $all['recipients']);
        $invitation->update(['status' => 'declined']);
        $this->post(route('backend.event-communications.send', $event), ['token' => $preview->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $declined = $service->preview($event, $actor, $this->audienceOptions(['scope' => 'invitations', 'filter' => 'declined']), 'Clothing', 'Arrangements');
        $this->assertCount(1, $declined->recipients);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_partial_queue_result_uses_warning_and_exact_counts(): void
    {
        [$event, $actor] = $this->event();
        foreach (['first@example.test', 'second@example.test'] as $email) {
            $this->entry($event, Player::factory()->create(['email' => $email, 'userId' => null]));
        }
        $batch = app(EventCommunicationService::class)->preview($event, $actor, $this->audienceOptions(), 'Update', 'Body');
        $dispatcher = $this->mock(BulkMailDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andReturn(['queued' => 1, 'failed' => 0, 'skipped' => 0]);
        $dispatcher->shouldReceive('dispatch')->once()->andReturn(['queued' => 0, 'failed' => 1, 'skipped' => 0]);
        $this->actingAs($actor)->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])
            ->assertSessionHas('warning', '1 emails queued; 1 failed to queue; 0 skipped. Check the send report for mail-server acceptance.');
        Mail::assertNothingSent();
    }


    public function test_zero_queue_result_is_an_error_even_without_dispatcher_failure_count(): void
    {
        [$event, $actor] = $this->event();
        $this->entry($event, Player::factory()->create(['email' => 'valid@example.test', 'userId' => null]));
        $batch = app(EventCommunicationService::class)->preview($event, $actor, $this->audienceOptions(), 'Update', 'Body');
        $this->mock(BulkMailDispatcher::class)->shouldReceive('dispatch')->once()->andReturn(['queued' => 0, 'failed' => 0, 'skipped' => 0]);
        $this->actingAs($actor)->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])
            ->assertSessionHas('error')->assertSessionMissing('success');
    }

    public function test_team_and_trials_reject_general_event_only_audience_scopes(): void
    {
        foreach ([[2, null], [1, EventType::INTERPROVINCIAL_TRIALS_CODE]] as [$kind, $code]) {
            [$event, $actor] = $this->event($kind, $code);
            foreach (['registrations', 'invitations'] as $scope) {
                $this->actingAs($actor)->post(route('backend.event-communications.preview', $event), $this->audienceOptions(['scope' => $scope]) + ['subject' => 'Private', 'body' => 'Body'])->assertStatus(422);
            }
        }
        $this->assertDatabaseCount('event_communication_batches', 0);
        Mail::assertNothingSent();
    }

    public function test_legacy_registered_review_excludes_nominees_and_other_registration_targets(): void
    {
        [$event,$actor]=$this->event();
        $selected=Player::factory()->create(['email'=>'selected@example.test','userId'=>null]);
        $other=Player::factory()->create(['email'=>'other@example.test','userId'=>null]);
        $entry=$this->entry($event,$selected);
        $this->entry($event,$other);
        EventNomination::create(['event_id'=>$event->id,'category_event_id'=>$entry->category_event_id,'nominee_name'=>'Unregistered','nominee_surname'=>'Nominee','nominee_email'=>'nominee@example.test']);
        $service=app(EventCommunicationService::class);
        $all=$service->plan($event,$actor,$this->audienceOptions(['scope'=>'legacy_registered']),'Update','Body');
        $this->assertSame(['other@example.test','selected@example.test'],array_column($all['recipients'],'email'));
        foreach (['category_event_id'=>$entry->category_event_id,'registration_id'=>$entry->registration_id] as $field=>$id) {
            $plan=$service->plan($event,$actor,$this->audienceOptions(['scope'=>'legacy_registered',$field=>$id]),'Update','Body');
            $this->assertSame(['selected@example.test'],array_column($plan['recipients'],'email'));
        }
        $this->actingAs($actor)->getJson(route('backend.event-communications.index',['event'=>$event,'individual_search'=>'Selected']))->assertOk();
        Mail::assertNothingSent();
    }

    public function test_masters_invitation_history_shortcut_is_event_and_batch_scoped(): void
    {
        [$event,$actor]=$this->event(1,EventType::MASTERS_CODE);
        [$foreign]=$this->event(1,EventType::MASTERS_CODE);
        foreach ([$event,$foreign] as $item) {
            $category=CategoryEvent::factory()->create(['event_id'=>$item->id]);
            $batch=\App\Models\MastersInvitationBatch::create(['event_id'=>$item->id,'series_id'=>1,'ranking_run_id'=>'report','created_by'=>$actor->id,'top_x'=>1,'status'=>'sent']);
            $invite=\App\Models\MastersInvitation::create(['batch_id'=>$batch->id,'event_id'=>$item->id,'category_event_id'=>$category->id,'player_id'=>Player::factory()->create()->id,'ranking_position'=>1,'queue_position'=>1,'status'=>'invited']);
            BulkEmailLog::create(['mail_type'=>'masters_invitation','related_type'=>\App\Models\MastersInvitation::class,'related_id'=>$invite->id,'recipient_email'=>'invite'.$item->id.'@example.test','status'=>'failed','payload'=>['event_id'=>$item->id,'subject'=>'Invitation '.$item->id]]);
        }
        $this->actingAs($actor)->get(route('backend.event-communications.index',['event'=>$event,'report_scope'=>'invitations','history_outcome'=>'failed']))
            ->assertOk()->assertViewHas('historyReport',fn($report)=>$report['logs']->total()===1 && $report['logs']->first()->recipient_email==='invite'.$event->id.'@example.test');
        Mail::assertNothingSent();
    }

}
