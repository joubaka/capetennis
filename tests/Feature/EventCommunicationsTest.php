<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, CategoryEvent, Event, EventCommunicationBatch, EventNomination, EventRegion, EventRegionManager, EventType, Player, Team, TeamPlayer, TeamRegion, User};
use App\Services\EventCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCommunicationsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;
    private Team $team;
    private EventRegion $eventRegion;
    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Mail::fake();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Team tournament', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['eventType' => $type, 'published' => false, 'signUp' => 0, 'status' => 'closed']);
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $region = TeamRegion::create(['region_name' => 'Region one', 'short_name' => 'ONE']);
        $this->eventRegion = EventRegion::findOrFail(DB::table('event_regions')->insertGetId(['event_id' => $this->event->id, 'region_id' => $region->id]));
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id, 'published' => false, 'name' => 'Team one']);
        $this->player = Player::factory()->create(['name' => 'Child', 'surname' => 'One', 'email' => 'child@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $this->player->id, 'rank' => 1, 'pay_status' => 0]);
        $this->actingAs($this->admin);
    }

    private function audienceOptions(array $overrides = []): array
    {
        return $overrides + ['scope' => 'all', 'filter' => 'all', 'recipients' => 'players'];
    }

    private function preview(array $options = []): EventCommunicationBatch
    {
        return app(EventCommunicationService::class)->preview($this->event, $this->admin, $this->audienceOptions($options), 'Event update', 'Please review the arrangements.');
    }

    public function test_review_groups_recipients_message_and_approval_without_sending(): void
    {
        $batch = $this->preview();
        $response = $this->get(route('backend.event-communications.review', [$this->event, $batch]))->assertOk();
        $response->assertSeeInOrder(['1. Recipients', '2. Message preview', '3. Approve'])
            ->assertSee('confirm_send')->assertSee('Approve and queue');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
        if (getenv('CT_BATCHES_QA') === '1') {
            file_put_contents(storage_path('app/batches345-qa/email-review.html'), str_replace('http://localhost', 'http://127.0.0.1:8775/ct/public', $response->getContent()));
        }
    }

    public function test_closed_unpublished_unpaid_rosters_can_be_emailed_only_after_exact_approval(): void
    {
        $service = app(EventCommunicationService::class);
        $batch = $this->preview(['filter' => 'not_registered']);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertSame(nl2br(e($batch->body)), $batch->recipients[0]['html']);
        $this->assertSame(1, $service->approve($batch, $this->admin, false)['queued']);
        $this->assertSame(0, $service->approve($batch, $this->admin, false)['queued']);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertSame('queued', BulkEmailLog::sole()->status);
        Mail::assertNothingSent();
    }

    public function test_linked_imported_roster_uses_only_profile_contacts_and_unlinked_uses_imported_email(): void
    {
        $linked = Player::factory()->create(['name' => 'Linked', 'surname' => 'Player', 'email' => 'linked@example.test', 'userId' => null]);
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'name' => 'Imported', 'surname' => 'Linked', 'rank' => 2, 'pay_status' => 0, 'player_profile' => $linked->id, 'email' => 'discard@example.test']);
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'name' => 'Unlinked', 'surname' => 'Player', 'rank' => 3, 'pay_status' => 0, 'email' => 'imported@example.test']);
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'name' => 'Duplicate', 'surname' => 'Normal', 'rank' => 4, 'pay_status' => 0, 'player_profile' => $this->player->id, 'email' => 'discard-normal@example.test']);
        $batch = $this->preview(['scope' => 'region', 'region_id' => $this->team->region_id]);
        $this->assertEqualsCanonicalizing(['child@example.test', 'linked@example.test', 'imported@example.test'], array_column($batch->recipients, 'email'));
        $this->assertSame(3, collect($batch->recipients)->flatMap(fn ($recipient) => $recipient['player_keys'])->unique()->count());
        $snapshot = app(\App\Services\EventAnnouncementService::class)->audienceSnapshot($this->event);
        $this->assertEqualsCanonicalizing(array_column($batch->recipients, 'email'), $snapshot['recipients']->pluck('email')->all());
    }

    public function test_linked_profile_without_contacts_does_not_fall_back_to_imported_email(): void
    {
        $linked = Player::factory()->create(['name' => 'Missing', 'surname' => 'Contact', 'email' => null, 'userId' => null]);
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'name' => 'Missing', 'surname' => 'Contact', 'rank' => 2, 'pay_status' => 0, 'player_profile' => $linked->id, 'email' => 'discard@example.test']);
        $batch = $this->preview();
        $this->assertSame(['child@example.test'], array_column($batch->recipients, 'email'));
        $this->assertCount(1, $batch->issues);
        $snapshot = app(\App\Services\EventAnnouncementService::class)->audienceSnapshot($this->event);
        $this->assertSame(['child@example.test'], $snapshot['recipients']->pluck('email')->all());
        $this->assertCount(1, $snapshot['excluded']);
    }

    public function test_selected_roster_without_import_preserves_team_player_selection(): void
    {
        $other = Player::factory()->create(['email' => 'other@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $other->id, 'rank' => 2, 'pay_status' => 0]);
        $filters = ['event_region_ids' => [$this->eventRegion->id], 'team_ids' => [$this->team->id], 'roster_keys' => [$this->team->id.':player:'.$other->id], 'gender' => 'any', 'audience_status' => 'active'];
        foreach ([3, 4] as $rank) {
            $missing = Player::factory()->create(['name' => 'Same', 'surname' => 'Name', 'email' => null, 'userId' => null]);
            TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $missing->id, 'rank' => $rank, 'pay_status' => 0]);
        }
        $service = app(EventCommunicationService::class);
        $options = app(\App\Services\TeamSelection\TeamSelectionEmailAudienceService::class)->eventSelectionOptions($this->event, ['event_region_ids' => [$this->eventRegion->id]]);
        $this->assertCount(1, $options);
        $this->assertCount(4, $options->first()['players']);
        $batch = $service->preview($this->event, $this->admin, ['source' => 'roster_selection', 'selection' => $filters], 'Selected update', 'Message');
        $this->assertSame(['other@example.test'], array_column($batch->recipients, 'email'));
        $this->assertCount(2, $batch->issues);
        $this->assertSame(1, $service->approve($batch, $this->admin, true)['queued']);
        $this->assertSame(['player:'.$other->id], BulkEmailLog::sole()->payload['player_keys']);
    }

    public function test_selected_region_counts_distinct_missing_players_even_when_one_is_on_two_teams(): void
    {
        $otherTeam = Team::factory()->create(['category_event_id' => $this->team->category_event_id, 'region_id' => $this->team->region_id]);
        foreach ([1, 2] as $number) {
            $missing = Player::factory()->create(['name' => 'Same', 'surname' => 'Name', 'email' => null, 'userId' => null]);
            TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $missing->id, 'rank' => $number + 1, 'pay_status' => 0]);
            if ($number === 1) TeamPlayer::create(['team_id' => $otherTeam->id, 'player_id' => $missing->id, 'rank' => 1, 'pay_status' => 0]);
        }
        $batch = app(EventCommunicationService::class)->preview($this->event, $this->admin, [
            'source' => 'roster_region_selection', 'event_region_id' => $this->eventRegion->id,
            'selection' => ['category_event_ids' => [$this->team->category_event_id], 'gender' => 'any', 'audience_status' => 'active'],
        ], 'Regional message', 'Message');

        $this->assertCount(1, $batch->recipients);
        $this->assertCount(2, $batch->issues);
    }

    public function test_regional_announcement_history_and_retry_preserve_failed_only_snapshot(): void
    {
        $announcement = \App\Models\TeamSelectionRegionAnnouncement::create(['event_id' => $this->event->id, 'event_region_id' => $this->eventRegion->id, 'region_id' => $this->team->region_id, 'created_by' => $this->admin->id, 'title' => 'Regional update', 'message' => '<p>Original message</p>']);
        $other = Player::factory()->create(['email' => 'other@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $other->id, 'rank' => 2, 'pay_status' => 0]);
        $another = Player::factory()->create(['email' => 'another@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $another->id, 'rank' => 3, 'pay_status' => 0]);
        $service = app(EventCommunicationService::class);
        $batch = $service->preview($this->event, $this->admin, $this->audienceOptions(['scope' => 'region', 'region_id' => $this->team->region_id, 'regional_announcement_id' => $announcement->id]), $announcement->title, 'Original message');
        $this->assertNull($announcement->fresh()->emailed_at);
        $service->approve($batch, $this->admin, false);
        $this->assertNotNull($announcement->fresh()->emailed_at);
        $this->assertSame(3, $announcement->emailLogs()->count());
        $announcement->emailLogs()->where('recipient_email', $this->player->email)->sole()->update(['status' => 'sent', 'sent_at' => now(), 'accepted_at' => now(), 'evidence_status' => 'server_accepted']);
        $failed = $announcement->emailLogs()->where('recipient_email', $other->email)->firstOrFail();
        $failed->markAsFailed('Transport failed');
        $announcement->emailLogs()->where('recipient_email', $another->email)->sole()->markAsFailed('Transport failed');
        $announcement->update(['message' => '<p>Edited message</p>']);
        $retry = $service->previewAnnouncementRetry($this->event, $announcement, $this->admin);
        $this->assertCount(2, $retry->recipients);
        $this->assertStringContainsString('Original message', $retry->recipients[0]['html']);
        $this->assertSame(2, $service->approve($retry, $this->admin, false)['queued']);
        $this->assertSame(0, $service->approve($retry, $this->admin, false)['queued']);
        $this->assertDatabaseCount('bulk_email_logs', 3);
    }

    public function test_regional_announcement_retry_rejects_cross_event_and_unauthorized_region(): void
    {
        $announcement = \App\Models\TeamSelectionRegionAnnouncement::create(['event_id' => $this->event->id, 'event_region_id' => $this->eventRegion->id, 'region_id' => $this->team->region_id, 'created_by' => $this->admin->id, 'title' => 'Regional update', 'message' => '<p>Message</p>']);
        $service = app(EventCommunicationService::class);
        $batch = $service->preview($this->event, $this->admin, $this->audienceOptions(['scope' => 'region', 'region_id' => $this->team->region_id, 'regional_announcement_id' => $announcement->id]), 'Regional update', 'Message');
        $service->approve($batch, $this->admin, false);
        $announcement->emailLogs()->firstOrFail()->markAsFailed('Transport failed');
        $retry = $service->previewAnnouncementRetry($this->event, $announcement, $this->admin);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post(route('backend.team-selection.announcements.retry', [$this->event, $this->eventRegion, $announcement]))->assertForbidden();
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $retry->token, 'confirm_send' => 1])->assertForbidden();
        $foreign = \App\Models\TeamSelectionRegionAnnouncement::create(['event_id' => Event::factory()->create()->id, 'event_region_id' => $this->eventRegion->id, 'region_id' => $this->team->region_id, 'created_by' => $this->admin->id, 'title' => 'Foreign announcement', 'message' => '<p>Foreign message</p>']);
        $this->actingAs($this->admin)->post(route('backend.team-selection.announcements.retry', [$this->event, $this->eventRegion, $foreign]))->assertNotFound();
        $this->assertSame('failed', $announcement->emailLogs()->firstOrFail()->status);
        $this->assertNull($retry->fresh()->approved_at);
        $this->assertDatabaseCount('bulk_email_logs', 1);
    }

    public function test_all_nominations_includes_profileless_unpublished_nominees(): void
    {
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->team->category_event_id, 'nominee_name' => 'Nominee', 'nominee_surname' => 'Two', 'nominee_email' => 'nominee@example.test']);
        $batch = $this->preview(['scope' => 'nominations']);
        $this->assertSame(['nominee@example.test'], array_column($batch->recipients, 'email'));
    }

    public function test_shared_parent_receives_only_authored_message_and_keeps_each_childs_audit_key(): void
    {
        $parent = User::factory()->create(['email' => 'parent@example.test']);
        $this->player->users()->attach($parent);
        $sibling = Player::factory()->create(['name' => 'Sibling', 'surname' => 'Two', 'email' => 'parent@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $sibling->id, 'rank' => 2, 'pay_status' => 1]);
        $body = "Hello <parents> & players,\nPlease check the arrangements.";
        $batch = app(EventCommunicationService::class)->preview($this->event, $this->admin, $this->audienceOptions(), 'Family update', $body);
        $this->assertCount(2, $batch->recipients);
        $family = collect($batch->recipients)->firstWhere('email', 'parent@example.test');
        $this->assertSame(nl2br(e($body)), $family['html']);
        $this->assertSame('Family update', $family['subject']);
        $this->assertEqualsCanonicalizing(['player:'.$this->player->id, 'player:'.$sibling->id], $family['player_keys']);
    }

    public function test_regional_manager_receives_only_authored_message_for_selected_team(): void
    {
        $manager = User::factory()->create(['email' => 'manager@example.test']);
        EventRegionManager::create(['event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'event_region_id' => $this->eventRegion->id, 'user_id' => $manager->id]);
        $other = Team::factory()->create(['category_event_id' => $this->team->category_event_id, 'region_id' => $this->team->region_id, 'name' => 'Other team']);
        $otherPlayer = Player::factory()->create(['name' => 'Other', 'surname' => 'Player']);
        TeamPlayer::create(['team_id' => $other->id, 'player_id' => $otherPlayer->id, 'rank' => 1, 'pay_status' => 0]);
        $batch = $this->preview(['scope' => 'team', 'team_id' => $this->team->id, 'recipients' => 'both']);
        $summary = collect($batch->recipients)->firstWhere('kind', 'manager');
        $this->assertCount(2, $batch->recipients);
        $this->assertSame('manager@example.test', $summary['email']);
        $this->assertSame(nl2br(e($batch->body)), $summary['html']);
        $this->assertSame($batch->subject, $summary['subject']);
    }

    public function test_regional_manager_cannot_see_unassigned_nominees_in_shared_category(): void
    {
        $manager = User::factory()->create();
        EventRegionManager::create(['event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'event_region_id' => $this->eventRegion->id, 'user_id' => $manager->id]);
        $otherRegion = TeamRegion::create(['region_name' => 'Other region']);
        DB::table('event_regions')->insert(['event_id' => $this->event->id, 'region_id' => $otherRegion->id]);
        Team::factory()->create(['category_event_id' => $this->team->category_event_id, 'region_id' => $otherRegion->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->team->category_event_id, 'nominee_name' => 'Private', 'nominee_surname' => 'Other', 'nominee_email' => 'private@example.test']);
        $rows = app(EventCommunicationService::class)->entries($this->event, $manager);
        $this->assertSame(['Child One'], $rows->pluck('name')->all());
        $this->assertCount(0, app(EventCommunicationService::class)->individualOptions($this->event, $manager, 'Private'));
    }

    public function test_cross_event_team_and_other_actors_preview_are_rejected(): void
    {
        $other = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create()->id, 'region_id' => $this->team->region_id]);
        $this->post(route('backend.event-communications.preview', $this->event), $this->audienceOptions(['scope' => 'team', 'team_id' => $other->id]) + ['subject' => 'Subject', 'body' => 'Body'])->assertNotFound();
        $batch = $this->preview();
        $this->actingAs(User::factory()->create())->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertForbidden();
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_changed_contact_and_changed_content_require_a_new_preview(): void
    {
        $batch = $this->preview();
        $this->player->update(['email' => 'changed@example.test']);
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $batch = $this->preview();
        $batch->update(['body' => 'Unreviewed replacement text']);
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_player_status_change_with_same_contact_requires_a_new_preview(): void
    {
        $batch = $this->preview();
        TeamPlayer::where('team_id', $this->team->id)->where('player_id', $this->player->id)->update(['pay_status' => 1]);

        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertNull($batch->fresh()->approved_at);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_manager_audience_membership_change_requires_a_new_preview(): void
    {
        $manager = User::factory()->create(['email' => 'manager@example.test']);
        EventRegionManager::create(['event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'event_region_id' => $this->eventRegion->id, 'user_id' => $manager->id]);
        $batch = $this->preview(['scope' => 'team', 'team_id' => $this->team->id, 'recipients' => 'managers']);
        $newPlayer = Player::factory()->create(['email' => 'child@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $newPlayer->id, 'rank' => 2, 'pay_status' => 0]);

        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertNull($batch->fresh()->approved_at);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_team_composer_region_and_team_previews_include_paid_and_unpaid_roster_players(): void
    {
        $paid = Player::factory()->create(['email' => 'paid@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $paid->id, 'rank' => 2, 'pay_status' => 1]);
        $series = \App\Models\Series::factory()->create();
        $rankingList = \App\Models\RankingList::create(['series_id' => $series->id, 'category_id' => $this->team->category->category_id, 'best_num_of_scores' => 3]);
        $import = \App\Models\TeamSelectionImport::create(['source_id' => 1, 'event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'series_id' => $series->id, 'ranking_run_id' => 'composer-roster', 'status' => 'sent']);
        foreach (['reserve', 'withdrawn'] as $status) {
            $outside = Player::factory()->create(['email' => $status.'@example.test', 'userId' => null]);
            \App\Models\TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'team_id' => $this->team->id, 'player_id' => $outside->id, 'ranking_list_id' => $rankingList->id, 'ranking_position' => 3, 'queue_position' => 3, 'status' => $status]);
        }

        foreach ([['scope' => 'all'], ['scope' => 'region', 'region_id' => $this->team->region_id], ['scope' => 'team', 'team_id' => $this->team->id]] as $selection) {
            foreach (['paid', 'not_registered'] as $oldFilter) {
                $this->post(route('backend.event-communications.preview', $this->event), $this->audienceOptions($selection + ['filter' => $oldFilter]) + ['subject' => 'Roster update', 'body' => 'Hello roster'])
                    ->assertOk()->assertViewHas('batch', function ($batch) {
                        $this->assertSame('all', $batch->options['filter']);
                        $this->assertEqualsCanonicalizing(['child@example.test', 'paid@example.test'], array_column($batch->recipients, 'email'));
                        return true;
                    });
            }
        }
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
    }

    public function test_stored_preview_with_old_automatic_details_requires_fresh_review(): void
    {
        $batch = $this->preview();
        $recipients = $batch->recipients;
        $recipients[0]['html'] = nl2br(e($batch->body."\n\nPlayer details:\nChild One — Team one — Not Registered"));
        $legacyPlan = ['recipients' => $recipients, 'issues' => $batch->issues];
        $fingerprint = new \ReflectionMethod(EventCommunicationService::class, 'planFingerprint');
        $batch->update(['recipients' => $recipients, 'fingerprint' => $fingerprint->invoke(app(EventCommunicationService::class), [$this->event->id, $batch->options, $batch->subject, $batch->body, $legacyPlan])]);

        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertNull($batch->fresh()->approved_at);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_missing_contacts_require_explicit_acknowledgement(): void
    {
        $missing = Player::factory()->create(['email' => null, 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $missing->id, 'rank' => 2, 'pay_status' => 0]);
        $batch = $this->preview();
        $this->assertCount(1, $batch->issues);
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertSessionHasErrors('acknowledge_missing');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1, 'acknowledge_missing' => 1])->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 1);
    }

    public function test_no_approval_checkbox_means_no_queue(): void
    {
        $batch = $this->preview();
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token])->assertSessionHasErrors('confirm_send');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_only_failed_recipient_is_retried_after_a_new_exact_preview(): void
    {
        $service = app(EventCommunicationService::class);
        $batch = $this->preview();
        $service->approve($batch, $this->admin, false);
        $log = BulkEmailLog::sole();
        $log->markAsFailed('Transport error');
        $retry = $service->previewRetry($this->event, $log->fresh(), $this->admin);
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame(1, $service->approve($retry, $this->admin, false)['queued']);
        $this->assertSame(0, $service->approve($retry, $this->admin, false)['queued']);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertSame(1, $batch->logs()->count());
        $this->assertSame('queued', $log->fresh()->status);
    }

    public function test_report_never_calls_historical_or_queued_messages_server_accepted(): void
    {
        $batch = $this->preview();
        app(EventCommunicationService::class)->approve($batch, $this->admin, false);
        $this->get(route('backend.event-communications.index', ['event' => $this->event, 'batch' => $batch->id]))->assertOk()->assertSee('Mail-server acceptance is not yet confirmed')->assertDontSee('Every approved email was accepted');
        $this->assertFalse(BulkEmailLog::deliverySummary($batch->logs())['all_server_accepted']);
    }

    public function test_automatic_transactional_draft_needs_exact_review_and_cannot_be_approved_twice(): void
    {
        $service = app(EventCommunicationService::class);
        $draft = $service->draftFixed($this->event, 'test-transaction', [['email' => 'payer@example.test', 'name' => 'Payer', 'kind' => 'players', 'subject' => 'Confirmed', 'html' => '<p>Confirmed registration</p>']], 'Confirmed');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $preview = $service->previewDraft($draft, $this->admin);
        $second = $service->previewDraft($draft, $this->admin);
        $this->assertSame(1, $service->approve($preview, $this->admin, false)['queued']);
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $second->token, 'confirm_send' => 1])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('bulk_email_logs', 1);
    }
    public function test_manual_composer_previews_one_sample_and_queues_saved_sender_once(): void
    {
        $second = Player::factory()->create(['email' => 'second@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $second->id, 'rank' => 2, 'pay_status' => 0]);
        $response = $this->post(route('backend.event-communications.preview', $this->event), $this->audienceOptions() + [
            'subject' => 'Team update', 'body' => 'Arrive at 8am', 'from_name' => 'Overberg Tennis', 'reply_to' => 'organiser@example.test',
        ])->assertOk()->assertSee('Example email')->assertSee('Overberg Tennis')->assertSee('second@example.test');
        $this->assertSame(1, substr_count($response->getContent(), '<iframe'));
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $batch = EventCommunicationBatch::sole();
        $payload = ['token' => $batch->token, 'confirm_send' => 1];
        $this->post(route('backend.event-communications.send', $this->event), $payload)->assertRedirect();
        $this->post(route('backend.event-communications.send', $this->event), $payload)->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 2);
        foreach (BulkEmailLog::all() as $log) {
            $this->assertSame('Overberg Tennis', $log->payload['from_name']);
            $this->assertSame('organiser@example.test', $log->payload['reply_to']);
        }
        Mail::assertNothingSent();
    }

    public function test_manual_sender_rejects_header_injection_before_creating_preview(): void
    {
        foreach ([['from_name' => "Manager\r\nBcc: outsider@example.test"], ['reply_to' => "manager@example.test\r\nBcc: outsider@example.test"]] as $bad) {
            $this->postJson(route('backend.event-communications.preview', $this->event), $this->audienceOptions() + $bad + [
                'subject' => 'Update', 'body' => 'Hello', 'from_name' => 'Manager', 'reply_to' => 'manager@example.test',
            ])->assertUnprocessable();
        }
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_roster_popup_preserves_unlinked_cohort_without_adding_managers(): void
    {
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $this->team->id, 'name' => 'Unlinked', 'surname' => 'Child', 'rank' => 2, 'pay_status' => 0, 'email' => 'unlinked@example.test']);
        $this->post(route('backend.team-selection.roster-email.send', [$this->event, $this->eventRegion]), [
            'target_type' => 'unlinked_imported', 'subject' => 'Roster update', 'message' => 'Please respond',
            'from_name' => 'Regional manager', 'reply_to' => 'regional@example.test',
        ])->assertOk()->assertSee('Example email');
        $batch = EventCommunicationBatch::sole();
        $this->assertSame(['unlinked@example.test'], array_column($batch->recipients, 'email'));
        $this->assertDatabaseCount('bulk_email_logs', 0);
        app(EventCommunicationService::class)->approve($batch, $this->admin, false);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertSame($this->team->region_id, BulkEmailLog::sole()->payload['region_id']);
        $this->assertSame('Regional manager', BulkEmailLog::sole()->payload['from_name']);
    }

    public function test_saved_review_is_private_to_actor_and_event(): void
    {
        $batch = $this->preview();
        $this->get(route('backend.event-communications.review', [$this->event, $batch]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $foreign = Event::factory()->create();
        $this->get(route('backend.event-communications.review', [$foreign, $batch]))->assertNotFound();
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->get(route('backend.event-communications.review', [$this->event, $batch]))->assertNotFound();
    }

    public function test_roster_preview_excludes_superseded_imports_and_tracks_shared_address_players(): void
    {
        $series = \App\Models\Series::factory()->create();
        $list = \App\Models\RankingList::create(['series_id' => $series->id, 'category_id' => $this->team->category->category_id, 'best_num_of_scores' => 3]);
        $imports = collect(['older', 'current'])->map(fn ($run) => \App\Models\TeamSelectionImport::create(['source_id' => 1, 'event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'series_id' => $series->id, 'ranking_run_id' => $run, 'status' => 'sent']));
        $old = Player::factory()->create(['email' => 'old@example.test', 'userId' => null]);
        $shared = Player::factory()->create(['email' => $this->player->email, 'userId' => null]);
        foreach ([[$imports[0], $old, 1], [$imports[1], $this->player, 1], [$imports[1], $shared, 2]] as [$import, $player, $rank]) {
            \App\Models\TeamSelectionInvitation::create(['import_id' => $import->id, 'event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'team_id' => $this->team->id, 'player_id' => $player->id, 'ranking_list_id' => $list->id, 'ranking_position' => $rank, 'queue_position' => $rank, 'status' => 'invited']);
        }
        $this->post(route('backend.team-selection.roster-email.send', [$this->event, $this->eventRegion]), ['target_type' => 'region', 'subject' => 'Update', 'message' => 'Hello'])->assertOk();
        $batch = EventCommunicationBatch::sole();
        $this->assertSame([$this->player->email], array_column($batch->recipients, 'email'));
        $this->assertEqualsCanonicalizing(['player:'.$this->player->id, 'player:'.$shared->id], $batch->recipients[0]['player_keys']);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_reminder_popup_retains_selected_unpaid_audience_at_sample_review(): void
    {
        $paid = Player::factory()->create(['email' => 'paid@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $paid->id, 'rank' => 2, 'pay_status' => 1]);
        $this->post(route('backend.team-selection.final-reminders.send', [$this->event, $this->eventRegion]), ['kind' => 'registration_clothing', 'audience' => 'unregistered'])->assertOk()
            ->assertViewHas('options', fn ($options) => $options['filter'] === 'not_registered' && $options['recipients'] === 'players' && $options['respect_status'] === 1);
        $this->post(route('backend.event-communications.preview', $this->event), $this->audienceOptions(['scope' => 'region', 'region_id' => $this->team->region_id, 'filter' => 'not_registered', 'respect_status' => 1]) + ['subject' => 'Reminder', 'body' => 'Complete registration'])->assertOk();
        $this->assertSame([$this->player->email], array_column(EventCommunicationBatch::sole()->recipients, 'email'));
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

}
