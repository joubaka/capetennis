<?php

namespace Tests\Feature;

use App\Domain\Entries\Events\EntryWithdrawn;
use App\Listeners\SyncMastersInvitationWithdrawal;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\EventType;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MastersEntriesAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    public function test_masters_entries_explains_the_two_workspaces_and_hides_conflicting_controls(): void
    {
        [$event, $category] = $this->eventAndCategory('masters');
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 99,
            'ranking_run_id' => 'current-run',
            'created_by' => $this->admin->id,
            'status' => 'sent',
        ]);
        $this->assignEvent($event);
        $this->entry($category, 'active');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.entries.new', $event))
            ->assertOk()
            ->assertSee('Confirmed Masters roster')
            ->assertSee(route('backend.masters.show', $batch), false)
            ->assertDontSee('reinstate-player-btn', false)
            ->assertSee('Withdraw');

        $page = new \DOMDocument();
        @$page->loadHTML($response->getContent());
        $xpath = new \DOMXPath($page);
        $this->assertSame(0, $xpath->query('//*[@data-register-offline-submit]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-from-category]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-url-lock or @data-url-unlock]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-admin-payment-note]')->length);

        $mastersPage = $this->actingAs($this->admin)
            ->get(route('backend.masters.show', $batch))
            ->assertOk()
            ->assertSee('Confirmed Entries')
            ->assertSee(route('admin.events.entries.new', $event), false);

        $mastersPage->assertSee('.masters-entry-table { width:100%; border:1px solid #ebeaf0; border-radius:.35rem; overflow:visible; }', false);
    }

    public function test_generic_masters_roster_mutations_are_blocked_server_side(): void
    {
        [$event, $category] = $this->eventAndCategory('masters');
        $this->assignEvent($event);
        $player = Player::factory()->create();
        $entry = $this->entry($category, 'active');

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.addPlayer', $category), ['registration_id' => $player->id])
            ->assertUnprocessable();
        $this->actingAs($this->admin)
            ->getJson(route('admin.category.availableRegistrations', $category))
            ->assertUnprocessable();
        $this->actingAs($this->admin)
            ->patchJson(route('admin.entry.admin-payment-status', $entry), ['paid' => true])
            ->assertUnprocessable();
        $this->actingAs($this->admin)
            ->postJson(route('admin.category.lock', $category))
            ->assertUnprocessable();
        $this->actingAs($this->admin)
            ->deleteJson(route('admin.category.removePlayer', [$category, $entry->registration]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame('unpaid', $entry->fresh()->admin_payment_status);
        $this->assertSame('active', $entry->fresh()->status);
        $this->assertDatabaseCount('category_event_registrations', 1);
    }

    public function test_move_is_rejected_across_events_and_when_either_category_is_masters(): void
    {
        [$sourceEvent, $source] = $this->eventAndCategory('individual-source');
        [$otherEvent, $other] = $this->eventAndCategory('individual-target');
        [$mastersEvent, $masters] = $this->eventAndCategory('masters');
        foreach ([$sourceEvent, $otherEvent, $mastersEvent] as $event) {
            $this->assignEvent($event);
        }
        $entry = $this->entry($source, 'active');

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.movePlayer', $entry), ['new_category_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Players can only be moved between categories in the same event.');
        $this->actingAs($this->admin)
            ->postJson(route('admin.category.movePlayer', $entry), ['new_category_id' => $masters->id])
            ->assertUnprocessable();

        $mastersEntry = $this->entry($masters, 'active');
        $this->actingAs($this->admin)
            ->postJson(route('admin.category.movePlayer', $mastersEntry), ['new_category_id' => $source->id])
            ->assertUnprocessable();

        $this->assertSame($source->id, $entry->fresh()->category_event_id);
        $this->assertSame($masters->id, $mastersEntry->fresh()->category_event_id);
    }

    public function test_bulk_email_rejects_cross_event_and_withdrawn_recipient_resolution(): void
    {
        [$event] = $this->eventAndCategory('individual-mail');
        [$otherEvent, $otherCategory] = $this->eventAndCategory('individual-other');
        $this->assignEvent($event);
        $this->assignEvent($otherEvent);
        $withdrawn = $this->entry($otherCategory, 'withdrawn');
        $payload = [
            'scope' => 'player',
            'event_id' => $event->id,
            'registration_id' => $withdrawn->registration_id,
            'subject' => 'Schedule',
            'message' => 'Updated schedule',
            'from_name' => 'Cape Tennis',
            'reply_to' => 'admin@example.test',
        ];

        $this->actingAs($this->admin)
            ->postJson(route('admin.events.email.send'), $payload)
            ->assertNotFound();

        $payload['event_id'] = $otherEvent->id;
        $this->actingAs($this->admin)
            ->postJson(route('admin.events.email.send'), $payload)
            ->assertNotFound();
    }

    public function test_removed_reinstate_route_is_not_registered(): void
    {
        $this->assertFalse(app('router')->has('admin.category.registration.reinstate'));
    }

    public function test_unassigned_admin_and_convenor_cannot_withdraw_another_events_entry(): void
    {
        Mail::fake();
        Queue::fake();
        [$event, $category] = $this->eventAndCategory('masters');
        $entry = $this->entry($category, 'active');
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 1,
            'ranking_run_id' => 'authorization-run',
            'created_by' => $this->admin->id,
            'status' => 'sent',
        ]);
        $invitation = MastersInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'player_id' => $entry->registration->players()->firstOrFail()->id,
            'registration_id' => $entry->registration_id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'status' => MastersInvitation::PAID_CONFIRMED,
        ]);

        $unassignedAdmin = User::factory()->create()->assignRole('admin');
        $unassignedConvenor = User::factory()->create()->assignRole('convenor');

        foreach ([$unassignedAdmin, $unassignedConvenor] as $user) {
            $this->actingAs($user)
                ->deleteJson(route('admin.category.removePlayer', [$category, $entry->registration]))
                ->assertForbidden();
        }

        $this->assertSame('active', $entry->fresh()->status);
        $this->assertSame(MastersInvitation::PAID_CONFIRMED, $invitation->fresh()->status);
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public function test_category_manage_is_event_scoped_for_assigned_admin_and_convenor(): void
    {
        [$event, $category] = $this->eventAndCategory('individual-scope');
        $this->assignEvent($event);
        $convenor = User::factory()->create()->assignRole('convenor');
        EventConvenor::create([
            'event_id' => $event->id,
            'user_id' => $convenor->id,
            'role' => 'hoof',
        ]);

        $this->assertTrue($this->admin->can('category.manage', $category));
        $this->assertTrue($convenor->can('category.manage', $category));
    }

    public function test_masters_withdrawal_is_blocked_for_assigned_convenor_and_pivot_only_admin(): void
    {
        Mail::fake();
        Queue::fake();
        [$event, $category] = $this->eventAndCategory('masters');
        $entry = $this->entry($category, 'active');
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 1,
            'ranking_run_id' => 'restricted-withdrawal-run',
            'status' => 'sent',
        ]);
        $invitation = MastersInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'player_id' => $entry->registration->players()->firstOrFail()->id,
            'registration_id' => $entry->registration_id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'status' => MastersInvitation::PAID_CONFIRMED,
        ]);

        $convenor = User::factory()->create()->assignRole('convenor');
        EventConvenor::create([
            'event_id' => $event->id,
            'user_id' => $convenor->id,
            'role' => 'hoof',
        ]);
        $pivotOnlyAdmin = User::factory()->create();
        DB::table('event_admins')->insert([
            'event_id' => $event->id,
            'user_id' => $pivotOnlyAdmin->id,
        ]);

        foreach ([$convenor, $pivotOnlyAdmin] as $user) {
            $pageResponse = $this->actingAs($user)
                ->get(route('admin.events.entries.new', $event))
                ->assertOk();
            $page = new \DOMDocument();
            @$page->loadHTML($pageResponse->getContent());
            $xpath = new \DOMXPath($page);
            $this->assertSame(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " withdraw-player-btn ")]')->length);

            $this->actingAs($user)
                ->postJson(route('admin.category.registration.withdraw', $entry))
                ->assertForbidden();
        }

        $this->assertSame('active', $entry->fresh()->status);
        $this->assertSame(MastersInvitation::PAID_CONFIRMED, $invitation->fresh()->status);
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
    }

    public function test_assigned_admin_withdrawal_uses_canonical_event_and_synchronizes_masters_once(): void
    {
        Mail::fake();
        Queue::fake();
        EventFacade::fake([EntryWithdrawn::class]);
        [$event, $category] = $this->eventAndCategory('masters');
        $this->assignEvent($event);
        $entry = $this->entry($category, 'active');
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 1,
            'ranking_run_id' => 'withdrawal-run',
            'created_by' => $this->admin->id,
            'status' => 'sent',
        ]);
        $invitation = MastersInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'player_id' => $entry->registration->players()->firstOrFail()->id,
            'registration_id' => $entry->registration_id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'status' => MastersInvitation::PAID_CONFIRMED,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.category.registration.withdraw', $entry))
            ->assertOk();

        EventFacade::assertDispatchedTimes(EntryWithdrawn::class, 1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
        $withdrawal = EventFacade::dispatched(EntryWithdrawn::class)->first()[0];
        app(SyncMastersInvitationWithdrawal::class)->handle($withdrawal);

        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame(MastersInvitation::WITHDRAWN, $invitation->fresh()->status);
    }

    public function test_entry_financial_details_are_super_user_only(): void
    {
        [$event, $category] = $this->eventAndCategory('individual-details');
        $this->assignEvent($event);
        $entry = $this->entry($category, 'active');
        $convenor = User::factory()->create()->assignRole('convenor');
        EventConvenor::create([
            'event_id' => $event->id,
            'user_id' => $convenor->id,
            'role' => 'hoof',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.entry.details', $entry))
            ->assertForbidden();
        $this->actingAs($convenor)
            ->getJson(route('admin.entry.details', $entry))
            ->assertForbidden();

        $superUser = User::factory()->create()->assignRole('super-user');
        $this->actingAs($superUser)
            ->getJson(route('admin.entry.details', $entry))
            ->assertOk()
            ->assertJsonPath('entry_id', $entry->id)
            ->assertJsonStructure(['payment_method', 'transactions', 'wallet_payment', 'wallet_refund']);
    }

    public function test_masters_paid_remove_only_sends_super_user_to_refund_chooser(): void
    {
        Mail::fake();
        Queue::fake();
        EventFacade::fake([EntryWithdrawn::class]);

        [$adminInvitation, $adminEntry, $adminEvent] = $this->paidMastersInvitation('admin-refund-route');
        $this->assignEvent($adminEvent);
        $this->actingAs($this->admin)
            ->from('/backend/masters/origin')
            ->post(route('backend.masters.invitation.remove.post', $adminInvitation))
            ->assertRedirect('/backend/masters/origin')
            ->assertSessionHas('success', 'Paid player removed by admin and deactivated (no refund issued).');
        $this->assertSame('withdrawn', $adminEntry->fresh()->status);

        [$superInvitation, $superEntry, $superEvent] = $this->paidMastersInvitation('super-refund-route');
        $superUser = User::factory()->create()->assignRole('super-user');
        $this->actingAs($superUser)
            ->post(route('backend.masters.invitation.remove.post', $superInvitation))
            ->assertRedirect(route('admin.registration.refund.choose', [$superEvent, $superEntry]));
        $this->assertSame('withdrawn', $superEntry->fresh()->status);
    }

    private function eventAndCategory(string $code): array
    {
        $typeId = (int) DB::table('eventtypes')->insertGetId([
            'name' => ucfirst($code),
            'type' => EventType::INDIVIDUAL,
            'code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = Event::factory()->create(['eventType' => $typeId]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);

        return [$event, $category];
    }

    private function assignEvent(Event $event): void
    {
        DB::table('event_admins')->insertOrIgnore([
            'event_id' => $event->id,
            'user_id' => $this->admin->id,
        ]);
    }

    private function entry(CategoryEvent $category, string $status): CategoryEventRegistration
    {
        $registration = Registration::factory()->create();
        $player = Player::factory()->create(['email' => 'player-'.$registration->id.'@example.test']);
        PlayerRegistration::create(['registration_id' => $registration->id, 'player_id' => $player->id]);

        return CategoryEventRegistration::factory()->create([
            'category_event_id' => $category->id,
            'registration_id' => $registration->id,
            'user_id' => $this->admin->id,
            'payment_status_id' => 1,
            'status' => $status,
            'admin_payment_status' => 'unpaid',
        ]);
    }

    private function paidMastersInvitation(string $run): array
    {
        [$event, $category] = $this->eventAndCategory('masters');
        $event->update([
            'start_date' => today()->addDays(30),
            'withdrawal_deadline' => today()->addDays(10),
        ]);
        $entry = $this->entry($category, 'active');
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 1,
            'ranking_run_id' => $run,
            'status' => 'sent',
        ]);
        $invitation = MastersInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'player_id' => $entry->registration->players()->firstOrFail()->id,
            'registration_id' => $entry->id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'status' => MastersInvitation::PAID_CONFIRMED,
        ]);

        return [$invitation, $entry, $event];
    }
}
