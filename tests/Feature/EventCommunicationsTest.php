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

    public function test_closed_unpublished_unpaid_rosters_can_be_emailed_only_after_exact_approval(): void
    {
        $service = app(EventCommunicationService::class);
        $batch = $this->preview(['filter' => 'not_registered']);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertStringContainsString('Child One', $batch->recipients[0]['html']);
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

    public function test_shared_parent_contact_keeps_each_childs_personalised_details(): void
    {
        $parent = User::factory()->create(['email' => 'parent@example.test']);
        $this->player->users()->attach($parent);
        $sibling = Player::factory()->create(['name' => 'Sibling', 'surname' => 'Two', 'email' => 'parent@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $this->team->id, 'player_id' => $sibling->id, 'rank' => 2, 'pay_status' => 1]);
        $batch = $this->preview();
        $this->assertCount(2, $batch->recipients);
        $family = collect($batch->recipients)->firstWhere('email', 'parent@example.test');
        $this->assertStringContainsString('Child One', $family['html']);
        $this->assertStringContainsString('Sibling Two', $family['html']);
    }

    public function test_manager_summary_contains_only_the_selected_team(): void
    {
        $manager = User::factory()->create(['email' => 'manager@example.test']);
        EventRegionManager::create(['event_id' => $this->event->id, 'region_id' => $this->team->region_id, 'event_region_id' => $this->eventRegion->id, 'user_id' => $manager->id]);
        $other = Team::factory()->create(['category_event_id' => $this->team->category_event_id, 'region_id' => $this->team->region_id, 'name' => 'Other team']);
        $otherPlayer = Player::factory()->create(['name' => 'Other', 'surname' => 'Player']);
        TeamPlayer::create(['team_id' => $other->id, 'player_id' => $otherPlayer->id, 'rank' => 1, 'pay_status' => 0]);
        $batch = $this->preview(['scope' => 'team', 'team_id' => $this->team->id, 'recipients' => 'both']);
        $summary = collect($batch->recipients)->firstWhere('kind', 'manager');
        $this->assertSame('manager@example.test', $summary['email']);
        $this->assertStringContainsString('Child One', $summary['html']);
        $this->assertStringNotContainsString('Other Player', $summary['html']);
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
}
