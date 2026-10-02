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
