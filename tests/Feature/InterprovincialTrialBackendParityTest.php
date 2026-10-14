<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\EventConvenor;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialBackendParityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    private CategoryEvent $category;

    public function test_nominee_can_be_declined_without_email_and_history_is_retained(): void
    {
        $player = Player::factory()->create();
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $url = route('backend.interprovincial-trials.nominations.administer', [$this->event, $this->category, $nomination]);
        $this->actingAs($this->admin)->post($url, ['action' => 'decline'])->assertRedirect();
        $declined = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->firstOrFail();
        $draftBatch = $declined->batch->replicate();
        $draftBatch->save();
        $draft = $declined->replicate();
        $draft->batch_id = $draftBatch->id;
        $draft->status = 'prepared';
        $draft->save();
        $this->actingAs($this->admin)->postJson($url, ['action' => 'paid_privately'])->assertUnprocessable();
        $this->actingAs($this->admin)->post($url, ['action' => 'decline'])->assertRedirect();
        $this->assertDatabaseHas('event_nominations', ['id' => $nomination->id]);
        $this->assertDatabaseHas('interprovincial_trial_invitations', ['nomination_id' => $nomination->id, 'status' => 'admin_declined']);
        $this->assertSame(2, InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->count());
        $this->assertDatabaseCount('registration_orders', 0);
        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))->assertOk()->assertSee('Declined');
        $this->category->update(['nominations_published' => true]);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs(User::factory()->create())->postJson(route('interprovincial-trials.nominations.register', [$this->event, $this->category, $nomination]))->assertUnprocessable();
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertSame(2, InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->count());
    }

    public function test_manual_private_collection_uses_admin_entry_once_and_blocks_removal(): void
    {
        $player = Player::factory()->create();
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $url = route('backend.interprovincial-trials.nominations.administer', [$this->event, $this->category, $nomination]);
        $this->actingAs($this->admin)->post($url, ['action' => 'paid_privately'])->assertRedirect();
        $paid = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->firstOrFail();
        $draftBatch = $paid->batch->replicate();
        $draftBatch->save();
        $draft = $paid->replicate();
        $draft->batch_id = $draftBatch->id;
        $draft->status = 'prepared';
        $draft->registration_id = null;
        $draft->order_id = null;
        $draft->paid_at = null;
        $draft->save();
        $this->actingAs($this->admin)->post($url, ['action' => 'paid_privately'])->assertRedirect();
        $this->assertSame(1, $this->category->categoryEventRegistrations()->count());
        $this->assertDatabaseHas('category_event_registrations', ['category_event_id' => $this->category->id, 'admin_payment_status' => 'paid', 'payment_status_id' => 1]);
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertSame(0.0, (float) DB::table('transactions_pf')->value('amount_gross'));
        $this->assertDatabaseCount('user_players', 0);
        $this->actingAs($this->admin)->delete(route('backend.interprovincial-trials.nominations.destroy', [$this->event, $this->category, $nomination]))->assertStatus(422);
        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))->assertOk()->assertSee('Paid privately')->assertSee('Private collection; not reconciled');
    }

    public function test_nomination_admin_actions_deny_unassigned_admin_and_cross_event_category(): void
    {
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => Player::factory()->create()->id]);
        $url = route('backend.interprovincial-trials.nominations.administer', [$this->event, $this->category, $nomination]);
        $this->actingAs(User::factory()->create()->assignRole('admin'))->post($url, ['action' => 'paid_privately'])->assertForbidden();
        $otherCategory = CategoryEvent::factory()->create();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.nominations.administer', [$this->event, $otherCategory, $nomination]), ['action' => 'decline'])->assertNotFound();
        $this->assertDatabaseCount('interprovincial_trial_invitations', 0);
        $this->assertDatabaseCount('category_event_registrations', 0);
    }

    public function test_admin_nomination_actions_preserve_pending_checkout_and_queued_delivery(): void
    {
        foreach (['accepted_pending_payment', 'queued', 'sending'] as $status) {
            $player = Player::factory()->create();
            $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
            $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $this->event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $this->admin->id]);
            $payer = User::factory()->create();
            $order = $status === 'accepted_pending_payment' ? \App\Models\RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 100, 'wallet_reserved' => 25, 'payfast_amount_due' => 75, 'pay_status' => false, 'status' => 'pending']) : null;
            $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'nomination_id' => $nomination->id, 'player_id' => $player->id, 'status' => $status, 'order_id' => $order?->id]);
            $before = $invitation->fresh()->getAttributes();
            foreach (['decline', 'paid_privately'] as $action) {
                $this->actingAs($this->admin)->postJson(route('backend.interprovincial-trials.nominations.administer', [$this->event, $this->category, $nomination]), ['action' => $action])->assertUnprocessable();
            }
            $this->assertSame($before, $invitation->fresh()->getAttributes());
            if ($order) {
                $this->assertEquals(25, $order->fresh()->wallet_reserved);
                $this->assertSame($payer->id, $order->fresh()->user_id);
            }
        }
        $this->assertDatabaseCount('interprovincial_trial_invitations', 3);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('registration_manual_receipts', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $typeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Interpro Trials',
            'type' => EventType::INDIVIDUAL,
            'code' => EventType::INTERPROVINCIAL_TRIALS_CODE,
        ]);
        $this->event = Event::factory()->create([
            'eventType' => $typeId,
            'published' => true,
            'signUp' => true,
            'status' => 'open',
            'start_date' => now()->addMonth(),
            'deadline' => 7,
        ]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    public function test_shared_workspace_navigation_links_and_marks_the_invitation_page_active(): void
    {
        $url = route('backend.interprovincial-trials.invitations.index', $this->event);

        $this->actingAs($this->admin)->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertSee('Interpro Dashboard')
            ->assertSee('Operations')
            ->assertSee('Event setup')
            ->assertSee('Quick Stats')
            ->assertSee('Nominations &amp; invitations', false)
            ->assertSee($url, false);

        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('Tournament workspace')
            ->assertSee('Event overview')
            ->assertSee('Nominations &amp; invitations', false)
            ->assertSee('href="'.$url.'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_unassigned_admin_is_denied_and_does_not_receive_the_interprovincial_tool_link(): void
    {
        $unassigned = User::factory()->create()->assignRole('admin');

        $this->actingAs($unassigned)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertForbidden();

        $ordinaryTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Ordinary singles',
            'type' => EventType::INDIVIDUAL,
            'code' => 'ordinary-singles-parity',
        ]);
        $ordinaryEvent = Event::factory()->create(['eventType' => $ordinaryTypeId]);
        DB::table('event_admins')->insert(['event_id' => $ordinaryEvent->id, 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('admin.events.overview', $ordinaryEvent))
            ->assertOk()
            ->assertDontSee('Nominations &amp; invitations', false);
    }

    public function test_interpro_overview_is_limited_to_assigned_admins_and_super_users(): void
    {
        $overview = route('admin.events.overview', $this->event);
        $unassigned = User::factory()->create()->assignRole('admin');
        $super = User::factory()->create()->assignRole('super-user');
        $convenor = User::factory()->create();
        EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $convenor->id]);

        $this->actingAs($unassigned)->get($overview)->assertForbidden();
        $this->actingAs($convenor)->get($overview)->assertForbidden();
        $this->actingAs($this->admin)->get($overview)->assertOk();
        $this->actingAs($super)->get($overview)->assertOk();
    }

    public function test_interpro_overview_registration_state_honours_lifecycle_and_deadline(): void
    {
        $overview = route('admin.events.overview', $this->event);

        $this->event->update(['status' => 'closed', 'signUp' => false]);
        $this->actingAs($this->admin)->get($overview)
            ->assertOk()
            ->assertSeeInOrder(['Registration:', '>Closed<'], false);

        $this->event->update([
            'status' => 'open',
            'signUp' => true,
            'start_date' => now()->subDays(2),
            'deadline' => 1,
        ]);
        $this->actingAs($this->admin)->get($overview)
            ->assertOk()
            ->assertSeeInOrder(['Registration:', '>Closed<'], false);
    }

    public function test_interpro_overview_stats_are_read_only_and_event_scoped(): void
    {
        $player = Player::factory()->create();
        $nomination = EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $player->id,
        ]);
        $batch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $this->event->id,
            'status' => InterprovincialTrialInvitationBatch::QUEUED,
            'snapshot_hash' => str_repeat('a', 64),
            'created_by_user_id' => $this->admin->id,
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'nomination_id' => $nomination->id,
            'player_id' => $player->id,
            'recipient_email' => 'interpro@example.test',
            'status' => 'sent',
        ]);

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id, 'nominations_published' => true]);
        $otherPlayer = Player::factory()->create();
        $otherNomination = EventNomination::create([
            'event_id' => $other->id,
            'category_event_id' => $otherCategory->id,
            'player_id' => $otherPlayer->id,
        ]);
        $otherBatch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $other->id,
            'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => str_repeat('b', 64),
            'created_by_user_id' => $this->admin->id,
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $otherBatch->id,
            'event_id' => $other->id,
            'category_event_id' => $otherCategory->id,
            'nomination_id' => $otherNomination->id,
            'player_id' => $otherPlayer->id,
            'status' => 'failed',
        ]);

        $before = [
            'nominations' => EventNomination::count(),
            'batches' => InterprovincialTrialInvitationBatch::count(),
            'invitations' => InterprovincialTrialInvitation::count(),
        ];

        $this->actingAs($this->admin)
            ->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertSeeInOrder(['Categories:', '>1<', 'Nominations:', '>1<'], false)
            ->assertSeeInOrder(['Queued / sent:', '>0 / 1<'], false)
            ->assertSeeInOrder(['Failed delivery:', '>0<'], false)
            ->assertSeeInOrder(['Nomination list:', '>Unpublished<'], false)
            ->assertSeeInOrder(['Registration:', '>Open<'], false);

        $this->assertSame($before['nominations'], EventNomination::count());
        $this->assertSame($before['batches'], InterprovincialTrialInvitationBatch::count());
        $this->assertSame($before['invitations'], InterprovincialTrialInvitation::count());
    }

    public function test_current_nomination_lifecycle_survives_newer_email_batches_in_full_and_ajax_views(): void
    {
        $originalBatch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $this->event->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $this->admin->id,
        ]);
        $expected = [];
        foreach ([
            InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT => 'payment-pending',
            InterprovincialTrialInvitation::PAID_CONFIRMED => 'registered',
            'failed' => 'delivery-failed',
        ] as $status => $statusKey) {
            $player = Player::factory()->create();
            $nomination = EventNomination::create([
                'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id,
            ]);
            $invitation = InterprovincialTrialInvitation::create([
                'batch_id' => $originalBatch->id, 'event_id' => $this->event->id,
                'category_event_id' => $this->category->id, 'nomination_id' => $nomination->id,
                'player_id' => $player->id, 'status' => $status, 'accepted_at' => now()->subDay(),
            ]);
            $expected[$nomination->id] = ['invitation' => $invitation, 'status_key' => $statusKey, 'player' => $player];
        }
        $newBatch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $this->event->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => str_repeat('b', 64), 'created_by_user_id' => $this->admin->id,
        ]);
        foreach ($expected as $nominationId => $details) {
            InterprovincialTrialInvitation::create([
                'batch_id' => $newBatch->id, 'event_id' => $this->event->id,
                'category_event_id' => $this->category->id, 'nomination_id' => $nominationId,
                'player_id' => $details['player']->id, 'status' => 'prepared',
            ]);
        }
        // A malformed invitation from another event must not replace or expose
        // a valid lifecycle for this event's nomination.
        $otherEvent = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        $otherBatch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $otherEvent->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => str_repeat('c', 64), 'created_by_user_id' => $this->admin->id,
        ]);
        $firstNominationId = array_key_first($expected);
        InterprovincialTrialInvitation::create([
            'batch_id' => $otherBatch->id, 'event_id' => $otherEvent->id,
            'category_event_id' => $otherCategory->id, 'nomination_id' => $firstNominationId,
            'player_id' => $expected[$firstNominationId]['player']->id, 'status' => 'sent',
            'recipient_email' => 'private-other-event@example.test',
        ]);
        $before = collect([
            'registrations', 'registration_orders', 'registration_order_items', 'wallet_transactions',
            'event_nominations', 'interprovincial_trial_invitations', 'interprovincial_trial_invitation_batches',
        ])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $response = $this->actingAs($this->admin)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()->assertDontSee('private-other-event@example.test');
        foreach ($expected as $nominationId => $details) {
            $presentation = $response->viewData('nominationPresentations')->get($nominationId);
            $this->assertSame($details['invitation']->id, $presentation['invitation']->id);
            $this->assertSame($details['status_key'], $presentation['filter_key']);
            $this->assertSame(in_array($details['invitation']->status, ['sent', 'failed', 'declined'], true), $presentation['can_remove']);
        }
        $failedInvitation = $expected[array_key_last($expected)]['invitation'];
        $response->assertSee(route('backend.interprovincial-trials.invitations.retry', [
            $this->event, $originalBatch, $failedInvitation,
        ]), false);

        $ajax = $this->postJson(route('backend.interprovincial-trials.nominations.bulk-store', $this->event), [
            'category_event_id' => $this->category->id,
            'player_ids' => [$expected[$firstNominationId]['player']->id],
        ])->assertOk()->assertJsonPath('added', 0);
        $rows = collect($ajax->json('nominations'))->keyBy('id');
        foreach ($expected as $nominationId => $details) {
            $this->assertSame($details['status_key'], $rows[$nominationId]['status_key']);
            $this->assertSame(in_array($details['invitation']->status, ['sent', 'failed', 'declined'], true), $rows[$nominationId]['can_remove']);
        }
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
    }

    public function test_readiness_is_observational_bounded_and_event_scoped(): void
    {
        $financialTables = ['registrations', 'registration_orders', 'registration_order_items', 'wallet_transactions'];
        $before = collect($financialTables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $this->actingAs($this->admin)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()
            ->assertSee('Invitation readiness')
            ->assertSee('Add nominations')
            ->assertSee('No recipients ready')
            ->assertSee('Message ready to edit below')
            ->assertSee('Registration open');

        $player = Player::factory()->create();
        $nomination = EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $player->id,
        ]);
        $hash = str_repeat('a', 64);
        $batch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $this->event->id,
            'status' => InterprovincialTrialInvitationBatch::REVIEWED,
            'snapshot_hash' => str_repeat('b', 64),
            'created_by_user_id' => $this->admin->id,
            'reviewed_by_user_id' => $this->admin->id,
            'reviewed_at' => now(),
            'email_subject' => 'Trials invitation',
            'email_body' => 'Please review your invitation.',
            'message_hash' => $hash,
            'reviewed_message_hash' => $hash,
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'nomination_id' => $nomination->id,
            'player_id' => $player->id,
            'recipient_email' => 'ready@example.test',
            'status' => 'sent',
        ]);

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $otherPlayer = Player::factory()->create();
        $otherNomination = EventNomination::create([
            'event_id' => $other->id,
            'category_event_id' => $otherCategory->id,
            'player_id' => $otherPlayer->id,
        ]);
        $otherBatch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $other->id,
            'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => str_repeat('c', 64),
            'created_by_user_id' => $this->admin->id,
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $otherBatch->id,
            'event_id' => $other->id,
            'category_event_id' => $otherCategory->id,
            'nomination_id' => $otherNomination->id,
            'player_id' => $otherPlayer->id,
            'recipient_email' => null,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()
            ->assertSee('Nominations ready')
            ->assertSee('Valid recipients can be emailed')
            ->assertSee('Message stored')
            ->assertSee('Batch Reviewed')
            ->assertSee('Sent: 1')
            ->assertDontSee('Failed: 1');

        $response->assertSeeInOrder(['Ready recipients', '>1<', 'Missing email', '>0<'], false);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "The readiness page must not write {$table}.");
        }
    }
}
