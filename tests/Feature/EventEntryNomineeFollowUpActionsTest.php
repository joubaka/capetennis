<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, CategoryEvent, CategoryEventRegistration, Event, EventCommunicationBatch, EventConvenor, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, Registration, RegistrationOrder, RegistrationOrderItems, User};
use App\Services\NomineeFollowUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventEntryNomineeFollowUpActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Mail::fake();
    }

    public function test_email_composes_one_exact_nominee_and_requires_review_before_idempotent_managed_send(): void
    {
        [$actor, $event, $source, $nomination] = $this->scenario();
        $nomination->player->update(['email' => 'player@example.test', 'email_2' => ' EXTRA@example.test ', 'email_3' => 'extra@example.test', 'userId' => null]);
        $other = $this->nominee($source, 'Other');
        $this->actingAs($actor)->get(route('admin.events.entries.new', $event))->assertOk()
            ->assertSee('Email this player')->assertSee('Move category')->assertSee('Remove nomination');
        $this->get(route('backend.event-communications.index', ['event' => $event, 'compose_scope' => 'individual',
            'individual_key' => 'player:'.$nomination->player_id, 'followup_nomination_id' => $nomination->id]))
            ->assertOk()->assertSee('name="followup_nomination_id" value="'.$nomination->id.'"', false)
            ->assertSee('Selected Candidate and their player contacts only')->assertDontSee('id="communication-scope"', false);
        $this->post(route('backend.event-communications.preview', $event), $this->mailData($nomination))->assertOk();
        $batch = EventCommunicationBatch::sole();
        $this->assertSame(['extra@example.test', 'player@example.test'], array_column($batch->recipients, 'email'));
        foreach ($batch->recipients as $row) $this->assertSame(['player:'.$nomination->player_id], $row['player_keys']);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->post(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->assertFalse(BulkEmailLog::where('recipient_email', $other->player->email)->exists());
        Mail::assertNothingSent();
    }

    public function test_email_preview_rejects_contact_changes_and_removed_or_moved_nominations(): void
    {
        [$actor, $event, $source, $nomination] = $this->scenario();
        $this->actingAs($actor)->post(route('backend.event-communications.preview', $event), $this->mailData($nomination))->assertOk();
        $batch = EventCommunicationBatch::sole();
        $nomination->player->update(['email' => 'changed@example.test']);
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertUnprocessable();
        $this->invitation($nomination, $actor);
        $this->action($actor, $event, $source, $nomination, 'remove')->assertOk();
        $this->postJson(route('backend.event-communications.send', $event), ['token' => $batch->token, 'confirm_send' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertNull($batch->fresh()->approved_at);
        $this->assertDatabaseCount('registration_orders', 0);
    }

    public function test_remove_retains_sent_invitation_and_archives_nomination_without_declining_or_financial_changes(): void
    {
        [$actor, $event, $source, $nomination] = $this->scenario();
        $invitation = $this->invitation($nomination, $actor);
        $before = $invitation->getAttributes();
        $this->action($actor, $event, $source, $nomination, 'remove')->assertOk();
        $this->assertNotNull($nomination->fresh()->archived_at);
        $this->assertEquals($actor->id, $nomination->fresh()->archived_by);
        $this->assertSame($before, $invitation->fresh()->getAttributes());
        try {
            app(\App\Services\InterprovincialTrials\InvitationService::class)->accept($invitation->fresh(), User::factory()->create());
            $this->fail('Archived invitation was accepted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->action($actor, $event, $source, $nomination, 'remove')->assertUnprocessable();
        $this->assertDatabaseCount('event_nominations', 1);
        $this->assertSame(1, DB::table('activity_log')->where('description', 'Unanswered nomination remove')->count());
        $this->get(route('admin.events.entries.new', $event))->assertOk()->assertDontSee('Selected Candidate');
        foreach (['registrations', 'registration_orders', 'wallet_transactions', (new \App\Models\Transaction)->getTable(), 'bulk_email_logs'] as $table) $this->assertDatabaseCount($table, 0);
    }

    public function test_history_free_removal_is_audited_and_move_preserves_profileless_identity_and_invitation_history(): void
    {
        [$actor, $event, $source, $unused, $target] = $this->scenario();
        $this->action($actor, $event, $source, $unused, 'remove')->assertOk();
        $this->assertDatabaseMissing('event_nominations', ['id' => $unused->id]);
        $this->assertSame($unused->id, (int) DB::table('activity_log')->where('description', 'Unanswered nomination remove')->value('subject_id'));
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $source->id,
            'nominee_name' => 'Profileless', 'nominee_surname' => 'Candidate', 'nominee_email' => 'profileless@example.test', 'profileless_key' => str_repeat('c', 64)]);
        $invitation = $this->invitation($nomination, $actor);
        $before = $invitation->getAttributes();
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertOk();
        $this->assertNotNull($nomination->fresh()->archived_at);
        $destination = EventNomination::where('category_event_id', $target->id)->sole();
        $this->assertSame('profileless@example.test', $destination->nominee_email);
        $this->assertNotSame($nomination->profileless_key, $destination->profileless_key);
        $this->assertSame($before, $invitation->fresh()->getAttributes());
        $this->assertFalse($destination->actionableInvitation()->exists());
        $this->assertTrue((bool) $target->fresh()->nominations_published);
        $this->assertSame(0, RegistrationOrder::count());
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertUnprocessable();
        $this->assertDatabaseCount('event_nominations', 2);
        $this->assertDatabaseCount('user_players', 0);
        Mail::assertNothingSent();
    }

    public function test_move_without_invitation_preserves_nomination_and_rejects_foreign_unpublished_locked_duplicate_or_stale_targets(): void
    {
        [$actor, $event, $source, $nomination, $target] = $this->scenario();
        $foreign = CategoryEvent::factory()->create(['nominations_published' => true]);
        $this->action($actor, $event, $source, $nomination, 'move', $foreign)->assertNotFound();
        $target->update(['nominations_published' => false]);
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertUnprocessable();
        $this->assertFalse((bool) $target->fresh()->nominations_published);
        $target->update(['nominations_published' => true, 'locked_at' => now()]);
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertUnprocessable();
        $target->update(['locked_at' => null]);
        $duplicate = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $target->id, 'player_id' => $nomination->player_id]);
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertUnprocessable();
        $duplicate->delete();
        $this->actingAs($actor)->postJson(route('admin.events.nominees.action', [$event, $source, $nomination]), ['action' => 'move', 'revision' => str_repeat('0', 64), 'target_category_id' => $target->id])->assertUnprocessable();
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertOk();
        $this->assertEquals($target->id, $nomination->fresh()->category_event_id);
        $this->assertNull($nomination->fresh()->archived_at);
        $this->assertDatabaseCount('event_nominations', 1);
    }

    public function test_registration_history_and_queued_delivery_or_response_block_actions_without_mutation(): void
    {
        [$actor, $event, $source, $nomination, $target] = $this->scenario();
        $invitation = $this->invitation($nomination, $actor);
        foreach (['queued', 'sending', 'accepted_pending_payment', 'paid_confirmed', 'declined', 'admin_declined', 'withdrawn'] as $status) {
            $invitation->update(['status' => $status]);
            $this->action($actor, $event, $source, $nomination, 'remove')->assertUnprocessable();
        }
        $invitation->update(['status' => 'sent']);
        $registration = Registration::factory()->create();
        $registration->players()->attach($nomination->player_id);
        $entry = CategoryEventRegistration::factory()->create(['category_event_id' => $target->id, 'registration_id' => $registration->id, 'status' => 'withdrawn', 'withdrawn_at' => now(), 'payment_status_id' => 1]);
        $entry->delete();
        $before = $entry->fresh()->getAttributes();
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertUnprocessable();
        $this->assertNull($nomination->fresh()->archived_at);
        $this->assertEquals($source->id, $nomination->fresh()->category_event_id);
        $this->assertSame($before, CategoryEventRegistration::withTrashed()->find($entry->id)->getAttributes());
        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'nominee-follow-up')->count());
    }

    public function test_action_authorization_event_isolation_and_unsupported_nomination_workflows_are_enforced(): void
    {
        [$actor, $event, $source, $nomination, $target] = $this->scenario();
        $outsider = $this->actor('admin');
        $this->action($outsider, $event, $source, $nomination, 'remove')->assertForbidden();
        $scorer = $this->actor('score-keeper');
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper']);
        $this->action($scorer, $event, $source, $nomination, 'remove')->assertForbidden();
        $this->action($actor, Event::factory()->create(['eventType' => $event->eventType]), $source, $nomination, 'remove')->assertNotFound();
        $generic = Event::factory()->create();
        $genericCategory = CategoryEvent::factory()->for($generic)->create(['nominations_published' => true]);
        $genericNominee = $this->nominee($genericCategory);
        $this->action($actor, $generic, $genericCategory, $genericNominee, 'remove')->assertUnprocessable();
        $this->actingAs($actor)->get(route('admin.events.entries.new', $generic))->assertOk()->assertSee('Email this player')->assertDontSee('data-action="remove"', false);
        $this->assertDatabaseCount('event_nominations', 2);
        $assigned = $this->actor('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $assigned->id]);
        $this->action($assigned, $event, $source, $nomination, 'move', $target)->assertOk();
    }

    public function test_original_checkout_item_and_inconsistent_invitation_identity_cannot_be_bypassed(): void
    {
        [$actor, $event, $source, $nomination, $target] = $this->scenario();
        $invitation = $this->invitation($nomination, $actor);
        $invitation->update(['category_event_id' => $target->id]);
        $this->action($actor, $event, $source, $nomination, 'remove')->assertUnprocessable();
        $invitation->update(['category_event_id' => $source->id]);
        $registration = Registration::factory()->create();
        $order = RegistrationOrder::create(['user_id' => $actor->id, 'total_fee' => 450, 'status' => 'cancelled', 'pay_status' => false]);
        $item = RegistrationOrderItems::forceCreate(['order_id' => $order->id, 'registration_id' => $registration->id,
            'category_event_id' => $source->id, 'player_id' => $nomination->player_id, 'user_id' => $actor->id, 'item_price' => 450]);
        $beforeOrder = $order->fresh()->getAttributes();
        $beforeItem = $item->fresh()->getAttributes();
        $this->action($actor, $event, $source, $nomination, 'move', $target)->assertUnprocessable();
        $this->assertSame($beforeOrder, $order->fresh()->getAttributes());
        $this->assertSame($beforeItem, $item->fresh()->getAttributes());
        $this->assertEquals($source->id, $nomination->fresh()->category_event_id);
        $this->assertNull($nomination->fresh()->archived_at);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function scenario(): array
    {
        $actor = $this->actor('super-user');
        $type = EventType::forceCreate(['name' => 'Follow-up Trials', 'type' => 1, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id]);
        $source = CategoryEvent::factory()->for($event)->create(['nominations_published' => true]);
        $target = CategoryEvent::factory()->for($event)->create(['nominations_published' => true]);
        return [$actor, $event, $source, $this->nominee($source), $target];
    }

    private function actor(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        return User::factory()->create()->assignRole($role);
    }

    private function nominee(CategoryEvent $category, string $name = 'Selected'): EventNomination
    {
        $player = Player::factory()->create(['name' => $name, 'surname' => 'Candidate', 'email' => strtolower($name).'@example.test', 'userId' => null]);
        return EventNomination::create(['event_id' => $category->event_id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
    }

    private function invitation(EventNomination $nomination, User $actor): InterprovincialTrialInvitation
    {
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $nomination->event_id, 'created_by_user_id' => $actor->id, 'snapshot_hash' => str_repeat('a', 64)]);
        return InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $nomination->event_id,
            'category_event_id' => $nomination->category_event_id, 'nomination_id' => $nomination->id, 'player_id' => $nomination->player_id, 'status' => 'sent'])->fresh();
    }

    private function action(User $actor, Event $event, CategoryEvent $source, EventNomination $nomination, string $action, ?CategoryEvent $target = null)
    {
        return $this->actingAs($actor)->postJson(route('admin.events.nominees.action', [$event, $source, $nomination]), [
            'action' => $action, 'revision' => app(NomineeFollowUpService::class)->revision($nomination->fresh()), 'target_category_id' => $target?->id]);
    }

    private function mailData(EventNomination $nomination): array
    {
        return ['scope' => 'individual', 'filter' => 'all', 'recipients' => 'players', 'individual_key' => $nomination->player_id ? 'player:'.$nomination->player_id : 'nominee:'.$nomination->id,
            'followup_nomination_id' => $nomination->id, 'subject' => 'Please respond', 'body' => 'Please let us know whether you can participate.'];
    }
}
