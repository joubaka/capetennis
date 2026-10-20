<?php

namespace Tests\Feature;

use App\Jobs\SendTeamSelectionInvitationEmailJob;
use App\Models\{BulkEmailLog, CategoryEvent, Event, EventCommunicationBatch, EventRegion, EventRegionManager, EventType, Player, Team, TeamPlayer, TeamRegion, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\{EventCommunicationService, InvitationMailSecurity};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamMailWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $actor;
    private EventRegion $region;
    private Team $team;
    private TeamSelectionImport $import;
    private TeamSelectionInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake(); Mail::fake();
        Role::findOrCreate('super-user', 'web');
        $this->actor = User::factory()->create()->assignRole('super-user');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Mail teams', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['name' => 'Synthetic Mail Teams', 'eventType' => $type, 'start_date' => now()->addDays(10), 'end_date' => now()->addDays(11), 'signUp' => 1, 'published' => 1]);
        $region = TeamRegion::create(['region_name' => 'Mail North', 'clothing_admin' => false]);
        $this->region = EventRegion::forceCreate(['event_id' => $this->event->id, 'region_id' => $region->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id, 'num_team_members' => 3]);
        $this->import = TeamSelectionImport::create(['source_id' => 1, 'event_id' => $this->event->id, 'region_id' => $region->id, 'series_id' => 1,
            'ranking_run_id' => 'mail-review-run', 'status' => 'sent', 'imported_by' => $this->actor->id,
            'response_deadline' => now()->addDays(2), 'payment_deadline' => now()->addDays(3), 'replacement_payment_deadline' => now()->addDays(4),
            'email_subject' => 'Saved invitation subject', 'email_message' => 'The saved campaign message.', 'include_clothing' => false,
            'communication_snapshot' => ['subject' => 'Saved invitation subject', 'message' => 'The saved campaign message.', 'include_clothing' => false,
                'from_name' => 'Regional convenor', 'reply_to' => 'convenor@example.test', 'hash' => str_repeat('a', 64)]]);
        $this->invitation = $this->addInvitation();
        $this->actingAs($this->actor);
    }

    private function addInvitation(array $overrides = [], ?Team $team = null): TeamSelectionInvitation
    {
        $team ??= $this->team;
        $rank = TeamSelectionInvitation::count() + 1;
        $player = Player::factory()->create(['email' => 'child'.$rank.'@example.test', 'userId' => null]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank, 'pay_status' => 0]);

        return TeamSelectionInvitation::create($overrides + ['import_id' => $this->import->id, 'event_id' => $this->event->id,
            'region_id' => $this->region->region_id, 'team_id' => $team->id, 'player_id' => $player->id, 'ranking_list_id' => 1,
            'ranking_position' => $rank, 'queue_position' => $rank, 'roster_rank' => $rank, 'status' => TeamSelectionInvitation::INVITED,
            'snapshot_json' => ['activation' => ['pending_manual_invitation' => true]]]);
    }

    private function preview(string $action, array $options = []): EventCommunicationBatch
    {
        return app(EventCommunicationService::class)->preview($this->event, $this->actor,
            $options + ['source' => 'team_selection_lifecycle', 'lifecycle_action' => $action,
                'selection_import_id' => $this->import->id, 'invitation_id' => $this->invitation->id], 'Lifecycle review', '');
    }

    public function test_pending_preview_preserves_saved_campaign_and_approval_stamps_once_with_signed_visible_history(): void
    {
        $batch = $this->preview('pending');
        $this->assertSame('Replacement: Saved invitation subject', $batch->recipients[0]['subject']);
        $this->assertStringContainsString('team-registration-'.$this->team->id.'-'.$this->invitation->player_id, $batch->recipients[0]['html']);
        $this->assertNull($this->invitation->fresh()->invited_at);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
        $service = app(EventCommunicationService::class);
        $this->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])
            ->assertRedirect()->assertSessionHas('success', fn ($message) => str_contains($message, '1 emails queued'));
        $this->assertSame(0, $service->approve($batch, $this->actor, false)['queued']);
        $log = $batch->logs()->sole();
        $this->assertSame('The saved campaign message.', $log->payload['campaign']['message']);
        $this->assertSame($batch->recipients[0]['html'], $log->payload['rendered_html']);
        $this->assertTrue(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $this->event->id));
        $this->assertNotNull($this->invitation->fresh()->invited_at);
        $this->assertFalse(data_get($this->invitation->fresh()->snapshot_json, 'activation.pending_manual_invitation'));
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertDatabaseCount('team_payment_orders', 0);
    }

    public function test_individual_pending_invitation_uses_canonical_delivery_and_leaves_other_pending_players(): void
    {
        $other = $this->addInvitation();
        $this->post(route('backend.team-selection.invitations.email.resend', [$this->event, $this->import, $this->invitation]))->assertOk();
        $batch = EventCommunicationBatch::latest('id')->firstOrFail();
        $this->assertSame('pending', $batch->options['lifecycle_action']);
        $this->assertCount(1, $batch->recipients);
        app(EventCommunicationService::class)->approve($batch, $this->actor, false);
        $this->assertNotNull($this->invitation->fresh()->invited_at);
        $this->assertNull($other->fresh()->invited_at);
        $bulk = $this->preview('pending', ['invitation_id' => null]);
        $this->assertSame($other->player->email, $bulk->recipients[0]['email']);
    }

    public function test_changed_contact_or_campaign_requires_fresh_review_and_rolls_back_approval(): void
    {
        $batch = $this->preview('pending');
        $this->invitation->player->update(['email' => 'changed@example.test']);
        try { app(EventCommunicationService::class)->approve($batch, $this->actor, false); $this->fail('Stale contact was approved'); }
        catch (ValidationException) {}
        $this->assertNull($batch->fresh()->approved_at);
        $batch = $this->preview('pending');
        $this->import->update(['communication_snapshot' => ['subject' => 'Changed campaign', 'message' => 'Changed message', 'include_clothing' => false]]);
        try { app(EventCommunicationService::class)->approve($batch, $this->actor, false); $this->fail('Stale campaign was approved'); }
        catch (ValidationException) {}
        $this->assertNull($batch->fresh()->approved_at);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertNull($this->invitation->fresh()->invited_at);
    }

    public function test_resend_accepted_unpaid_has_signed_snapshot_and_reaches_array_transport(): void
    {
        $this->invitation->update(['status' => TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, 'invited_at' => now()->subHour(), 'snapshot_json' => []]);
        $batch = $this->preview('resend');
        app(EventCommunicationService::class)->approve($batch, $this->actor, false);
        $log = $batch->logs()->sole();
        $this->assertTrue(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $this->event->id));
        $this->assertSame($batch->recipients[0]['html'], $log->payload['rendered_html']);
        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        $this->mock(\App\Services\MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        (new SendTeamSelectionInvitationEmailJob($log->id, $this->event->id))->handle();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame('Regional convenor', $message->getFrom()[0]->getName());
        $this->assertSame('convenor@example.test', $message->getReplyTo()[0]->getAddress());
        $this->assertSame(TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, $this->invitation->fresh()->status);
    }

    public function test_restored_review_queues_exact_signed_invitation_and_retains_decline_history(): void
    {
        $this->invitation->update(['declined_at' => now()->subDay(), 'snapshot_json' => []]);
        $batch = $this->preview('restored');
        app(EventCommunicationService::class)->approve($batch, $this->actor, false);
        $log = $batch->logs()->sole();
        $this->assertTrue(app(InvitationMailSecurity::class)->logMatchesSignedSnapshot($log, $this->event->id));
        $this->assertSame($batch->recipients[0]['html'], $log->payload['rendered_html']);
        $this->assertNotNull($this->invitation->fresh()->declined_at);
        $this->assertNotNull($this->invitation->fresh()->invited_at);
        $this->assertSame(0, app(EventCommunicationService::class)->approve($batch, $this->actor, false)['queued']);
    }

    public function test_registration_reminder_deduplicates_parent_preserves_child_links_and_excludes_inactive_players(): void
    {
        $second = $this->addInvitation();
        $second->player->update(['email' => $this->invitation->player->email]);
        $this->addInvitation(['status' => TeamSelectionInvitation::PAID_CONFIRMED]);
        $this->addInvitation(['status' => TeamSelectionInvitation::DECLINED]);
        $this->addInvitation(['status' => TeamSelectionInvitation::RESERVE]);
        $options = ['event_region_id' => $this->region->id, 'reminder_kind' => 'registration_clothing', 'reminder_audience' => 'unregistered'];
        $batch = $this->preview('reminder', $options);
        $this->assertCount(1, $batch->recipients);
        $this->assertCount(2, $batch->recipients[0]['player_keys']);
        foreach ([$this->invitation, $second] as $invitation) $this->assertStringContainsString(route('team-selection.invitations.show', ['invitation' => $invitation, 'action' => 'pay']), html_entity_decode($batch->recipients[0]['html']));
        $this->assertSame(1, app(EventCommunicationService::class)->approve($batch, $this->actor, false)['queued']);
        $this->assertSame(1, $batch->logs()->count());
        $this->assertNull($this->invitation->fresh()->invited_at);
    }

    public function test_reminder_team_context_and_incomplete_clothing_cohort_are_resolved_exactly(): void
    {
        $this->region->region->update(['clothing_admin' => true]);
        $this->invitation->update(['status' => TeamSelectionInvitation::PAID_CONFIRMED, 'clothing_decision' => 'not_required']);
        $wanted = $this->addInvitation(['status' => TeamSelectionInvitation::PAID_CONFIRMED]);
        $otherTeam = Team::factory()->create(['category_event_id' => $this->team->category_event_id, 'region_id' => $this->region->region_id]);
        $this->addInvitation(['status' => TeamSelectionInvitation::PAID_CONFIRMED], $otherTeam);
        $batch = $this->preview('reminder', ['event_region_id' => $this->region->id, 'reminder_kind' => 'incomplete_clothing', 'reminder_audience' => 'registered', 'team_id' => $this->team->id]);
        $this->assertCount(1, $batch->recipients);
        $this->assertSame($wanted->player->email, $batch->recipients[0]['email']);
    }

    public function test_lifecycle_rejects_regional_only_actor_foreign_import_and_different_approver(): void
    {
        $regional = User::factory()->create();
        EventRegionManager::create(['event_id' => $this->event->id, 'region_id' => $this->region->region_id, 'event_region_id' => $this->region->id, 'user_id' => $regional->id]);
        $route = route('backend.team-selection.invitations.email.send-pending-activated', [$this->event, $this->import]);
        $this->actingAs($regional)->post($route)->assertForbidden();
        $foreign = Event::factory()->create();
        $this->actingAs($this->actor)->post(route('backend.team-selection.invitations.email.send-pending-activated', [$foreign, $this->import]))->assertNotFound();
        $batch = $this->preview('pending');
        $this->actingAs($regional)->post(route('backend.event-communications.send', $this->event), ['token' => $batch->token, 'confirm_send' => 1])->assertForbidden();
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_custom_checked_email_is_authored_only_and_does_not_stamp_pending_invitation(): void
    {
        $this->invitation->update(['status' => TeamSelectionInvitation::PAID_CONFIRMED]);
        $this->invitation->player->update(['userId' => User::factory()->create(['email' => 'secondary-parent@example.test'])->id]);
        $response = $this->post(route('backend.team-selection.invitations.email.custom-preview', [$this->event, $this->import]), [
            'invitation_ids' => [$this->invitation->id], 'email_subject' => 'Custom subject', 'email_message' => 'Only my authored message.',
        ])->assertOk();
        $batch = EventCommunicationBatch::latest('id')->firstOrFail();
        $this->assertCount(1, $batch->recipients);
        $this->assertSame($this->invitation->player->email, $batch->recipients[0]['email']);
        $this->assertSame('Only my authored message.', $batch->recipients[0]['html']);
        $this->assertStringNotContainsString('clothing', $batch->recipients[0]['html']);
        app(EventCommunicationService::class)->approve($batch, $this->actor, false);
        $this->assertNull($this->invitation->fresh()->invited_at);
        $this->assertTrue(data_get($this->invitation->fresh()->snapshot_json, 'activation.pending_manual_invitation'));
        $this->assertSame('team_email', $batch->logs()->sole()->mail_type);
    }

    public function test_workspace_email_chooser_retains_campaign_controls_and_blank_custom_with_pending_contacts(): void
    {
        $series = \App\Models\Series::factory()->create();
        $source = \App\Models\EventRegionRankingSource::create(['event_id' => $this->event->id, 'event_region_id' => $this->region->id,
            'region_id' => $this->region->region_id, 'series_id' => $series->id, 'reserve_count' => 2, 'linked_by' => $this->actor->id]);
        $this->import->update(['source_id' => $source->id, 'series_id' => $series->id]);
        $missing = $this->addInvitation(); $missing->player->update(['email' => null]);
        $page = $this->get(route('backend.team-selection.index', [$this->event, 'selected_region' => $this->region->region_id, 'selected_team' => $this->team->id]))->assertOk();
        $page->assertSee('data-team-email-open', false)->assertSee('data-team-email-purpose="custom"', false)
            ->assertSee('data-team-email-purpose="invitation"', false)->assertSee('data-team-email-purpose="reminder"', false)
            ->assertSee('data-team-email-purpose="pending"', false)->assertSee('2 newly activated invitation(s) pending.');
        $this->assertMatchesRegularExpression('/name="email_subject"[^>]*value=""/', $page->getContent());
        $this->assertMatchesRegularExpression('/name="email_message"[^>]*><\/textarea>/', $page->getContent());
        $this->assertDatabaseCount('bulk_email_logs', 0);
        if (getenv('CT_CENTRAL_TEAMS_QA') === '1') file_put_contents(storage_path('framework/testing/qa-central-mail.html'), $page->getContent());
        $this->import->update(['status' => 'draft']);
        $draft = $this->get(route('backend.team-selection.index', [$this->event, 'selected_region' => $this->region->region_id, 'selected_team' => $this->team->id]))->assertOk();
        $draft->assertSee('Prepare regional invitations')->assertSee(route('backend.team-selection.email.preview', [$this->event, $this->import]), false);
        if (getenv('CT_CENTRAL_TEAMS_QA') === '1') file_put_contents(storage_path('framework/testing/qa-central-mail-draft.html'), $draft->getContent());
    }
}
