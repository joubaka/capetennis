<?php

namespace Tests\Feature;

use App\Jobs\SendInterprovincialTrialInvitationEmailJob;
use App\Mail\InterprovincialTrialInvitationMail;
use App\Models\CategoryEvent;
use App\Models\Category;
use App\Models\BulkEmailLog;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Player;
use App\Models\User;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\Wallet;
use App\Http\Middleware\EnsureAgreementAccepted;
use App\Http\Middleware\EnsurePlayerProfileUpdated;
use App\Services\MailAccountManager;
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialInvitationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;
    private CategoryEvent $category;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $typeId = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => EventType::INDIVIDUAL,
            'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $this->event = Event::factory()->create(['eventType' => $typeId]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    public function test_registration_lifecycle_accepts_only_scheduled_open_and_active(): void
    {
        foreach (['scheduled', 'open', 'active'] as $status) {
            $this->event->status = $status;
            $this->assertTrue($this->event->hasOpenRegistrationLifecycle(), $status);
        }
        foreach (['draft', 'closed', 'cancelled'] as $status) {
            $this->event->status = $status;
            $this->assertFalse($this->event->hasOpenRegistrationLifecycle(), $status);
        }
    }

    public function test_nomination_update_is_authorized_event_scoped_and_preserves_unchanged_rows(): void
    {
        $first = Player::factory()->create(); $second = Player::factory()->create();
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $first->id]);
        $this->actingAs($this->admin)->post('/backend/nomination/save', ['category_event_id' => $this->category->id, 'player_ids' => [$first->id, $second->id]])->assertOk();
        $this->assertDatabaseHas('event_nominations', ['id' => $nomination->id]);
        $this->assertSame(2, EventNomination::where('event_id', $this->event->id)->count());

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $this->actingAs($this->admin)->post('/backend/nomination/save', ['category_event_id' => $otherCategory->id, 'player_ids' => [$first->id]])->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/backend/nomination/selected/{$this->category->id}")->assertForbidden();
    }

    public function test_current_event_workspace_links_to_a_usable_nomination_flow(): void
    {
        $player = Player::factory()->create(['name' => 'Reachable', 'surname' => 'Nominee']);

        $this->actingAs($this->admin)->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertSee('Nominations &amp; invitations', false)
            ->assertSee(route('backend.interprovincial-trials.invitations.index', $this->event), false);

        $ordinaryTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Ordinary Singles Test',
            'type' => EventType::INDIVIDUAL,
            'code' => 'ordinary-singles-test',
        ]);
        $ordinaryEvent = Event::factory()->create(['eventType' => $ordinaryTypeId]);
        DB::table('event_admins')->insert(['event_id' => $ordinaryEvent->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->get(route('admin.events.overview', $ordinaryEvent))
            ->assertOk()
            ->assertDontSee('Nominations &amp; invitations', false);

        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()
            ->assertSee('Nominate an existing player')
            ->assertSee('id="nomination-player"', false)
            ->assertSee('id="nomination-category"', false)
            ->assertSee('name="player_ids[]"', false)
            ->assertSee('multiple', false)
            ->assertSee(json_encode(route('backend.interprovincial-trials.players.index', $this->event)), false)
            ->assertSee('select2({', false)
            ->assertSee('closeOnSelect: false', false)
            ->assertSee("player.on('select2:select'", false)
            ->assertSee("searchFields.val('').trigger('input')", false)
            ->assertSee("trigger('focus')", false)
            ->assertSee('textContent = nomination.player_name', false)
            ->assertSee("fetch(form.action", false)
            ->assertDontSee('player_search', false)
            ->assertDontSee('Reachable Nominee')
            ->assertDontSee($player->email);

        $store = route('backend.interprovincial-trials.nominations.store', [$this->event, $this->category]);
        $this->actingAs($this->admin)->post($store, ['player_ids' => [$player->id]])
            ->assertRedirect()->assertSessionHas('success', '1 nomination(s) added; 0 already nominated. Prepare a new invitation snapshot when the list is complete.');
        $this->actingAs($this->admin)->post($store, ['player_ids' => [$player->id]])
            ->assertRedirect()->assertSessionHas('success', '0 nomination(s) added; 1 already nominated. Prepare a new invitation snapshot when the list is complete.');
        $this->assertDatabaseCount('event_nominations', 1);
    }

    public function test_interprovincial_trial_invitation_acceptance_is_owned_exact_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $this->event->update([
            'published' => true, 'status' => 'active', 'signUp' => true,
            'start_date' => now()->addDays(20)->toDateString(), 'deadline' => 2,
        ]);
        $this->category->update(['entry_fee' => 275.50]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $invitation = InterprovincialTrialInvitation::sole();
        $invitation->update(['status' => 'queued']);

        $unrelated = User::factory()->create();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            app(InvitationService::class)->accept($invitation, $unrelated);
        } finally {
            $first = app(InvitationService::class)->accept($invitation->fresh(), $owner);
            $second = app(InvitationService::class)->accept($invitation->fresh(), $owner);
            $this->assertSame($first->id, $second->id);
            $this->assertSame(275.50, (float) $first->total_fee);
            $this->assertSame(1, DB::table('registrations')->count());
            $this->assertSame(1, DB::table('registration_orders')->count());
            $this->assertSame(1, DB::table('registration_order_items')->count());
            $this->assertDatabaseHas('registration_order_items', [
                'order_id' => $first->id, 'player_id' => $player->id,
                'category_event_id' => $this->category->id, 'item_price' => 275.50,
            ]);
            $this->assertSame(InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, $invitation->fresh()->status);

            $fallbackPlayer = Player::factory()->create(['userId' => $owner->id]);
            $fallbackNomination = EventNomination::create(['event_id' => $this->event->id,
                'category_event_id' => $this->category->id, 'player_id' => $fallbackPlayer->id]);
            $this->category->update(['entry_fee' => null]);
            $this->event->update(['entryFee' => 88.75]);
            $fallbackInvitation = InterprovincialTrialInvitation::create([
                'batch_id' => $invitation->batch_id, 'event_id' => $this->event->id,
                'category_event_id' => $this->category->id, 'nomination_id' => $fallbackNomination->id,
                'player_id' => $fallbackPlayer->id, 'status' => 'sent',
            ]);
            $fallbackOrder = app(InvitationService::class)->accept($fallbackInvitation, $owner);
            $this->assertSame(88.75, (float) $fallbackOrder->total_fee);
            $this->assertSame(88.75, (float) $fallbackOrder->items->sole()->item_price);
        }
    }

    public function test_cancel_releases_reservation_cleans_unpaid_entry_and_allows_one_fresh_checkout(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $this->event->update(['published' => true, 'status' => 'open', 'signUp' => true,
            'start_date' => now()->addDays(20)->toDateString(), 'deadline' => 2]);
        $this->category->update(['entry_fee' => 150]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $invitation = InterprovincialTrialInvitation::sole();
        $invitation->update(['status' => 'queued']);
        $first = app(InvitationService::class)->accept($invitation, $owner);
        app(\App\Domain\Payments\Services\RegistrationPaymentService::class)->reservePayment($first, 25, 125);
        app(\App\Domain\Payments\Services\RegistrationPaymentService::class)->cancelPayment($first);
        app(InvitationService::class)->resetCancelledPayment($first->fresh(), $owner);

        $this->assertSame('cancelled', $first->fresh()->status);
        $this->assertSame(0.0, (float) $first->fresh()->wallet_reserved);
        $this->assertDatabaseMissing('category_event_registrations', [
            'registration_id' => $first->items()->value('registration_id'), 'deleted_at' => null,
        ]);
        $second = app(InvitationService::class)->accept($invitation->fresh(), $owner);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, DB::table('registration_orders')->count());
        $this->assertSame(2, DB::table('registration_order_items')->count());
        $this->assertSame(1, DB::table('category_event_registrations')->whereNull('deleted_at')->count());
    }

    public function test_unlinked_interprovincial_order_is_blocked_at_every_payment_entry_point(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $registration = Registration::create([]);
        $registration->players()->sync([$player->id]);
        $registration->categoryEvents()->attach($this->category->id, ['user_id' => $owner->id, 'payment_status_id' => 0]);
        $order = RegistrationOrder::create(['user_id' => $owner->id, 'total_fee' => 100,
            'payfast_amount_due' => 100, 'pay_status' => false, 'status' => 'pending']);
        (new RegistrationOrderItems())->forceFill(['order_id' => $order->id,
            'registration_id' => $registration->id, 'category_event_id' => $this->category->id,
            'player_id' => $player->id, 'user_id' => $owner->id, 'item_price' => 100])->save();
        Wallet::factory()->forUser($owner)->create();
        $this->withoutMiddleware([EnsureAgreementAccepted::class, EnsurePlayerProfileUpdated::class]);
        $this->actingAs($owner);

        $this->get(route('registration.checkout', $order))->assertRedirect()->assertSessionHasErrors('registration');
        $this->post(route('registration.payfast-only', $order))->assertRedirect()->assertSessionHasErrors('registration');
        $this->post(route('registration.hybrid.pay'), ['type' => 'registration', 'custom_int5' => $order->id])
            ->assertRedirect()->assertSessionHasErrors('registration');
        $this->postJson(route('registration.hybrid.apply-wallet'), ['order_id' => $order->id])
            ->assertUnprocessable()->assertJsonValidationErrors('registration');
        $this->post(route('registration.hybrid.complete', ['orderId' => $order->id]))
            ->assertRedirect()->assertSessionHasErrors('registration');
        $this->get(route('registration.hybrid.cancel', ['orderId' => $order->id]))
            ->assertRedirect()->assertSessionHasErrors('registration');
        $this->assertFalse((bool) $order->fresh()->pay_status);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_free_invitation_registration_uses_canonical_paid_entry_and_closed_gate_writes_nothing(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $this->event->update([
            'published' => true, 'status' => 'active', 'signUp' => true,
            'start_date' => now()->addDays(20)->toDateString(), 'deadline' => 2,
        ]);
        $this->category->update(['entry_fee' => 0]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $invitation = InterprovincialTrialInvitation::sole();
        $invitation->update(['status' => 'sent']);

        $order = app(InvitationService::class)->accept($invitation, $owner);
        $this->assertTrue((bool) $order->pay_status);
        $this->assertSame('free', $order->payment_method);
        $this->assertSame(InterprovincialTrialInvitation::PAID_CONFIRMED, $invitation->fresh()->status);
        $invitation->update(['status' => InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, 'paid_at' => null]);
        app(InvitationService::class)->confirmPaidOrder($order->fresh());
        $this->assertSame(InterprovincialTrialInvitation::PAID_CONFIRMED, $invitation->fresh()->status);
        $this->assertDatabaseHas('category_event_registrations', [
            'registration_id' => $invitation->fresh()->registration_id,
            'category_event_id' => $this->category->id, 'payment_status_id' => 1,
        ]);

        $secondPlayer = Player::factory()->create(['userId' => $owner->id]);
        $secondNomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $secondPlayer->id]);
        $batch = InterprovincialTrialInvitationBatch::sole();
        $closedInvitation = InterprovincialTrialInvitation::create([
            'batch_id' => $batch->id, 'event_id' => $this->event->id,
            'category_event_id' => $this->category->id, 'nomination_id' => $secondNomination->id,
            'player_id' => $secondPlayer->id, 'status' => 'queued',
        ]);
        $this->event->update(['signUp' => false]);
        $before = DB::table('registration_orders')->count();
        try {
            app(InvitationService::class)->accept($closedInvitation, $owner);
            $this->fail('Closed applications should reject invitation acceptance.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('invitation', $exception->errors());
        }
        $this->assertSame($before, DB::table('registration_orders')->count());
    }

    public function test_new_prepared_snapshot_does_not_shadow_latest_actionable_invitation(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create(['event_id' => $this->event->id,
            'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $actionable = InterprovincialTrialInvitation::sole();
        $actionable->update(['status' => 'queued']);
        $actionable->batch->update(['status' => InterprovincialTrialInvitationBatch::QUEUED]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));

        $this->assertSame(2, InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->count());
        $this->assertSame($actionable->id, $nomination->fresh()->actionableInvitation?->id);
    }

    public function test_player_picker_is_authorized_event_scoped_private_literal_safe_and_paginated(): void
    {
        $endpoint = route('backend.interprovincial-trials.players.index', $this->event);
        $unassigned = User::factory()->create()->assignRole('admin');

        $this->getJson($endpoint.'?q=Search')->assertUnauthorized();
        $this->actingAs($unassigned)->getJson($endpoint.'?q=Search')->assertForbidden();

        $ordinaryTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Ordinary Event Picker Test',
            'type' => EventType::INDIVIDUAL,
            'code' => 'ordinary-event-picker-test',
        ]);
        $ordinaryEvent = Event::factory()->create(['eventType' => $ordinaryTypeId]);
        DB::table('event_admins')->insert(['event_id' => $ordinaryEvent->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)
            ->getJson(route('backend.interprovincial-trials.players.index', $ordinaryEvent).'?q=Search')
            ->assertNotFound();

        $this->actingAs($this->admin)->getJson($endpoint.'?q=a')->assertUnprocessable();

        $literal = Player::factory()->create([
            'name' => 'Literal%_Token',
            'surname' => 'Picker',
            'email' => 'private-picker@example.test',
        ]);
        Player::factory()->create(['name' => 'LiteralXXToken', 'surname' => 'NotAMatch']);

        $literalResponse = $this->actingAs($this->admin)->getJson($endpoint.'?q='.urlencode('%_'));
        $literalResponse->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $literal->id)
            ->assertJsonPath('results.0.text', 'Literal%_Token Picker — Profile #'.$literal->id)
            ->assertJsonMissing(['email' => 'private-picker@example.test']);
        $this->assertSame(['id', 'text'], array_keys($literalResponse->json('results.0')));

        $numericProfile = Player::factory()->create(['id' => 387, 'name' => 'Exact', 'surname' => 'Profile']);
        $this->actingAs($this->admin)->getJson($endpoint.'?q=387')
            ->assertOk()
            ->assertJsonCount(0, 'results')
            ->assertJsonMissing(['id' => $numericProfile->id]);

        Player::factory()->count(21)->create(['name' => 'BoundedPicker']);
        $firstPage = $this->actingAs($this->admin)->getJson($endpoint.'?q=BoundedPicker&page=1');
        $firstPage->assertOk()->assertJsonCount(20, 'results')->assertJsonPath('pagination.more', true);
        $this->actingAs($this->admin)->getJson($endpoint.'?q=BoundedPicker&page=2')
            ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('pagination.more', false);
        $this->actingAs($this->admin)->getJson($endpoint.'?q=BoundedPicker&page=11')
            ->assertUnprocessable()->assertJsonValidationErrors('page');

        Player::factory()->count(201)->create(['name' => 'WindowCappedPicker']);
        $this->actingAs($this->admin)->getJson($endpoint.'?q=WindowCappedPicker&page=10')
            ->assertOk()->assertJsonCount(20, 'results')->assertJsonPath('pagination.more', false);
    }

    public function test_player_picker_is_throttled_per_authenticated_user(): void
    {
        $searcher = User::factory()->create()->assignRole('super-user');
        $endpoint = route('backend.interprovincial-trials.players.index', $this->event).'?q=Throttle';

        for ($request = 1; $request <= 30; $request++) {
            $this->actingAs($searcher)->getJson($endpoint)->assertOk();
        }

        $this->actingAs($searcher)->getJson($endpoint)->assertTooManyRequests();
    }

    public function test_multiple_nomination_submission_is_atomic_deduplicated_and_reports_counts(): void
    {
        $existing = Player::factory()->create();
        $first = Player::factory()->create();
        $second = Player::factory()->create();
        EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $existing->id,
        ]);
        $store = route('backend.interprovincial-trials.nominations.store', [$this->event, $this->category]);

        $this->actingAs($this->admin)->post($store, [
            'player_ids' => [$existing->id, $first->id, $first->id, $second->id],
        ])->assertRedirect()
            ->assertSessionHas('success', '2 nomination(s) added; 1 already nominated. Prepare a new invitation snapshot when the list is complete.');
        $this->assertDatabaseCount('event_nominations', 3);

        $third = Player::factory()->create();
        $this->actingAs($this->admin)->post($store, [
            'player_ids' => [$third->id, PHP_INT_MAX],
        ])->assertSessionHasErrors('player_ids.1');
        $this->assertDatabaseMissing('event_nominations', [
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $third->id,
        ]);
        $this->assertDatabaseCount('event_nominations', 3);
    }

    public function test_bulk_nomination_rejects_more_than_fifty_players_without_writes(): void
    {
        $players = Player::factory()->count(51)->create();
        $store = route('backend.interprovincial-trials.nominations.bulk-store', $this->event);

        $this->actingAs($this->admin)->post($store, [
            'category_event_id' => $this->category->id,
            'player_ids' => $players->pluck('id')->all(),
        ])->assertSessionHasErrors('player_ids');

        $this->assertDatabaseCount('event_nominations', 0);
    }

    public function test_ajax_bulk_add_and_remove_return_authoritative_safe_dom_data(): void
    {
        $player = Player::factory()->create(['name' => '<img src=x onerror=alert(1)>', 'surname' => 'Safe']);
        $bulkStore = route('backend.interprovincial-trials.nominations.bulk-store', $this->event);

        $response = $this->actingAs($this->admin)->postJson($bulkStore, [
            'category_event_id' => $this->category->id,
            'player_ids' => [$player->id],
        ]);
        $response->assertOk()
            ->assertJsonPath('added', 1)
            ->assertJsonPath('already_nominated', 0)
            ->assertJsonPath('category_event_id', $this->category->id)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('nominations.0.player_name', '<img src=x onerror=alert(1)> Safe');
        $nomination = EventNomination::sole();
        $response->assertJsonPath(
            'nominations.0.destroy_url',
            route('backend.interprovincial-trials.nominations.destroy', [$this->event, $this->category, $nomination])
        );

        $this->actingAs($this->admin)
            ->deleteJson(route('backend.interprovincial-trials.nominations.destroy', [$this->event, $this->category, $nomination]))
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonCount(0, 'nominations');
        $this->assertDatabaseCount('event_nominations', 0);

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $this->actingAs($this->admin)->postJson($bulkStore, [
            'category_event_id' => $otherCategory->id,
            'player_ids' => [$player->id],
        ])->assertNotFound();
        $this->assertDatabaseCount('event_nominations', 0);
    }

    public function test_publication_is_deliberate_event_scoped_and_public_page_only_shows_published_names(): void
    {
        $this->category->category->update(['name' => 'Published Category']);
        $privateCategory = CategoryEvent::factory()->create([
            'event_id' => $this->event->id,
            'category_id' => Category::factory()->create(['name' => 'Private Category'])->id,
            'nominations_published' => false,
        ]);
        $publishedPlayer = Player::factory()->create(['name' => 'Public', 'surname' => 'Nominee']);
        $privatePlayer = Player::factory()->create(['name' => 'Private', 'surname' => 'Nominee']);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $publishedPlayer->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $privateCategory->id, 'player_id' => $privatePlayer->id]);
        $this->category->update(['nominations_published' => true]);

        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()->assertSee('Public nomination list: Mixed');

        $this->get(route('events.show', $this->event))->assertOk()
            ->assertSee('Published Category')->assertSee('Public Nominee')
            ->assertDontSee('Private Nominee')
            ->assertDontSee('Profile #')->assertDontSee($publishedPlayer->email);

        $unassigned = User::factory()->create()->assignRole('admin');
        $publication = route('backend.interprovincial-trials.nominations.publication', $this->event);
        $this->actingAs($unassigned)->put($publication, ['published' => 1])->assertForbidden();
        $this->actingAs($this->admin)->put($publication, ['published' => 1])->assertRedirect();
        $this->assertSame(2, CategoryEvent::where('event_id', $this->event->id)->where('nominations_published', true)->count());
        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()->assertSee('Public nomination list: Published');
        $this->actingAs($this->admin)->put($publication, ['published' => 1])->assertRedirect();
        $this->actingAs($this->admin)->put($publication, ['published' => 0])->assertRedirect();
        $this->assertSame(0, CategoryEvent::where('event_id', $this->event->id)->where('nominations_published', true)->count());
        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()->assertSee('Public nomination list: Private');
        $this->get(route('events.show', $this->event))->assertOk()->assertDontSee('Public Nominee')->assertDontSee('Private Nominee');
        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_message_save_preview_review_and_queue_are_bound_to_stored_content(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole();

        $this->actingAs($this->admin)->put(route('backend.interprovincial-trials.batches.message', [$this->event, $batch]), [
            'email_subject' => "Unsafe\nSubject",
            'email_body' => 'Body',
        ])->assertSessionHasErrors('email_subject');
        $this->actingAs($this->admin)->put(route('backend.interprovincial-trials.batches.message', [$this->event, $batch]), [
            'email_subject' => '   ',
            'email_body' => '   ',
        ])->assertSessionHasErrors(['email_subject', 'email_body']);
        $this->saveMessage($batch, 'Stored trials subject', "Stored body\nSecond line <script>alert(1)</script>");
        $batch->refresh();
        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()->assertSee('Stored trials subject')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee($owner->email);

        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), $this->reviewData($batch))->assertRedirect();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]))->assertSessionHasErrors('confirm_exact_recipients_and_message');
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]), $this->sendData())->assertRedirect();
        $log = BulkEmailLog::sole();
        $this->assertSame('Stored trials subject', $log->payload['subject']);
        $this->assertSame("Stored body\nSecond line <script>alert(1)</script>", $log->payload['body']);
        $this->assertSame($batch->message_hash, $log->payload['message_hash']);
        $this->assertSame($batch->snapshot_hash, $log->payload['snapshot_hash']);
        $invitation = InterprovincialTrialInvitation::sole();
        $batch->update(['email_subject' => 'Mutated subject', 'email_body' => 'Mutated body']);
        $rendered = (new InterprovincialTrialInvitationMail($invitation, $log->payload))->render();
        $this->assertStringContainsString('Stored body', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rendered);
        $this->assertStringNotContainsString('Mutated body', $rendered);
        Bus::assertDispatchedTimes(SendInterprovincialTrialInvitationEmailJob::class, 1);
    }

    public function test_missing_recipient_and_changed_message_block_review_or_queue(): void
    {
        $player = Player::factory()->create(['userId' => null]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->saveMessage($batch); $batch->refresh();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), $this->reviewData($batch))
            ->assertSessionHasErrors('batch');

        $owner = User::factory()->create();
        $player->update(['userId' => $owner->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch->refresh();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), $this->reviewData($batch))->assertRedirect();
        $batch->update(['email_body' => 'Tampered after review']);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]), $this->sendData())
            ->assertSessionHasErrors('message');
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_nested_nomination_actions_reject_cross_event_identifiers(): void
    {
        $player = Player::factory()->create();
        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->post(route('backend.interprovincial-trials.nominations.store', [$this->event, $otherCategory]), ['player_ids' => [$player->id]])
            ->assertNotFound();

        $nomination = EventNomination::create(['event_id' => $other->id, 'category_event_id' => $otherCategory->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)
            ->delete(route('backend.interprovincial-trials.nominations.destroy', [$this->event, $this->category, $nomination]))
            ->assertNotFound();
        $this->assertDatabaseHas('event_nominations', ['id' => $nomination->id]);

        $unassigned = User::factory()->create()->assignRole('admin');
        $this->actingAs($unassigned)
            ->post(route('backend.interprovincial-trials.nominations.store', [$this->event, $this->category]), ['player_ids' => [$player->id]])
            ->assertForbidden();
        $this->actingAs($unassigned)
            ->delete(route('backend.interprovincial-trials.nominations.destroy', [$other, $otherCategory, $nomination]))
            ->assertForbidden();
        $this->assertDatabaseHas('event_nominations', ['id' => $nomination->id]);
    }

    public function test_prepare_review_and_send_are_exact_idempotent_and_create_no_financial_records(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id, 'email' => 'stale-player@example.test']);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $url = route('backend.interprovincial-trials.invitations.prepare', $this->event);
        $this->actingAs($this->admin)->post($url)->assertRedirect();
        $this->actingAs($this->admin)->post($url)->assertRedirect();
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->assertSame(1, $batch->invitations()->count());

        $this->assertSame($owner->email, $batch->invitations()->sole()->recipient_email);
        $this->saveMessage($batch);
        $batch->refresh();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), $this->reviewData($batch))->assertRedirect();
        $send = route('backend.interprovincial-trials.batches.send', [$this->event, $batch]);
        $this->actingAs($this->admin)->post($send, $this->sendData())->assertRedirect();
        $this->actingAs($this->admin)->post($send, $this->sendData())->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Bus::assertDispatchedTimes(SendInterprovincialTrialInvitationEmailJob::class, 1);
        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_send_rejects_a_nomination_added_after_review_without_queueing_mail(): void
    {
        Bus::fake();
        [$batch] = $this->reviewedBatch();
        $addedOwner = User::factory()->create();
        $addedPlayer = Player::factory()->create(['userId' => $addedOwner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $addedPlayer->id]);

        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]), $this->sendData())
            ->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_send_rejects_a_nomination_removed_after_review_without_queueing_mail(): void
    {
        Bus::fake();
        [$batch, , $nomination] = $this->reviewedBatch();
        $nomination->delete();

        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]), $this->sendData())
            ->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_send_rejects_a_linked_recipient_change_after_review_without_queueing_mail(): void
    {
        Bus::fake();
        [$batch, $owner] = $this->reviewedBatch();
        $owner->update(['email' => 'changed-after-review@example.test']);

        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]), $this->sendData())
            ->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_batch_and_player_access_reject_cross_event_and_unlinked_accounts(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole(); $invitation = InterprovincialTrialInvitation::sole();
        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$other, $batch]), ['snapshot_hash' => $batch->snapshot_hash, 'message_hash' => str_repeat('a', 64)])->assertNotFound();
        $signed = URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->addMinute(), ['invitation' => $invitation]);
        $this->actingAs(User::factory()->create())->get($signed)->assertForbidden();

        $this->actingAs($owner)->get($signed)->assertNotFound();
        $invitation->update(['status' => 'queued']);
        $this->actingAs($owner)->get($signed)->assertOk()->assertSee('has been invited');
        $this->actingAs($owner)->get($signed.'&tampered=1')->assertForbidden();
        $expired = URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->subMinute(), ['invitation' => $invitation]);
        $this->actingAs($owner)->get($expired)->assertForbidden();
    }

    public function test_recipient_requires_an_authorized_account_and_is_deterministic(): void
    {
        $direct = User::factory()->create(['email' => 'direct@example.test']);
        $firstLink = User::factory()->create(['email' => 'first@example.test']);
        $secondLink = User::factory()->create(['email' => 'second@example.test']);
        $player = Player::factory()->create(['userId' => $direct->id, 'email' => 'stale@example.test']);
        DB::table('user_players')->insert([
            ['user_id' => $secondLink->id, 'player_id' => $player->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $firstLink->id, 'player_id' => $player->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $this->assertSame('direct@example.test', InterprovincialTrialInvitation::sole()->recipient_email);

        $player->update(['userId' => null]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $expected = $firstLink->id < $secondLink->id ? $firstLink->email : $secondLink->email;
        $this->assertSame($expected, InterprovincialTrialInvitation::sole()->recipient_email);

        DB::table('user_players')->where('player_id', $player->id)->delete();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $this->assertNull(InterprovincialTrialInvitation::sole()->recipient_email);
    }

    public function test_stale_snapshot_unassigned_admin_super_user_and_malformed_nomination_boundaries(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $unassigned = User::factory()->create()->assignRole('admin');
        $this->actingAs($unassigned)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event))->assertForbidden();
        $super = User::factory()->create()->assignRole('super-user');
        $this->actingAs($super)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event))->assertRedirect();
        $batch = InterprovincialTrialInvitationBatch::sole();
        $oldHash = $batch->snapshot_hash;
        $owner->update(['email' => 'changed@example.test']);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $this->assertNotSame($oldHash, $batch->fresh()->snapshot_hash);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), ['snapshot_hash' => $oldHash, 'message_hash' => str_repeat('a', 64)])->assertSessionHasErrors('batch');

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $nomination->update(['event_id' => $other->id]);
        $this->actingAs($this->admin)->post(route('backend.nomination.remove'), ['nomination_id' => $nomination->id])->assertNotFound();
    }

    public function test_terminal_mail_failure_can_retry_without_a_second_dispatch_record_or_duplicate_send(): void
    {
        Bus::fake();
        config(['mail.default' => 'array']);
        $this->mock(MailAccountManager::class, fn ($mock) => $mock->shouldReceive('getMailer')->andReturn('array'));
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->saveMessage($batch); $batch->refresh();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), $this->reviewData($batch));
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]), $this->sendData());
        $log = BulkEmailLog::sole();
        $job = new SendInterprovincialTrialInvitationEmailJob($log->id, $this->event->id);
        $job->failed(new \RuntimeException('Transport unavailable'));
        $this->assertSame('failed', InterprovincialTrialInvitation::sole()->status);

        $job->handle();
        $job->handle();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('sent', InterprovincialTrialInvitation::sole()->status);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertCount(1, \Illuminate\Support\Facades\Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_authorized_retry_reuses_the_failed_log_and_dispatch_with_nested_isolation(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->saveMessage($batch); $batch->refresh();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]), $this->reviewData($batch));
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]), $this->sendData());
        $invitation = InterprovincialTrialInvitation::sole();
        $log = BulkEmailLog::sole();
        (new SendInterprovincialTrialInvitationEmailJob($log->id, $this->event->id))->failed(new \RuntimeException('Terminal transport failure'));

        $retry = route('backend.interprovincial-trials.invitations.retry', [$this->event, $batch, $invitation]);
        $unassigned = User::factory()->create()->assignRole('admin');
        $this->actingAs($unassigned)->post($retry)->assertForbidden();
        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.retry', [$other, $batch, $invitation]))->assertNotFound();

        $this->actingAs($this->admin)->post($retry)->assertRedirect()->assertSessionHas('success', 'The failed invitation was queued for retry.');
        $this->actingAs($this->admin)->post($retry)->assertRedirect()->assertSessionHas('success', 'The invitation is already queued, sending, or sent.');
        $this->assertSame('queued', $invitation->fresh()->status);
        $this->assertSame('queued', $log->fresh()->status);
        $this->assertDatabaseCount('interprovincial_trial_invitations', 1);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Bus::assertDispatchedTimes(SendInterprovincialTrialInvitationEmailJob::class, 2);
    }

    private function reviewedBatch(): array
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $player->id,
        ]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event));
        $batch = InterprovincialTrialInvitationBatch::sole();
        $this->saveMessage($batch);
        $batch->refresh();
        $this->actingAs($this->admin)->post(
            route('backend.interprovincial-trials.batches.review', [$this->event, $batch]),
            $this->reviewData($batch)
        );

        return [$batch->fresh(), $owner, $nomination];
    }

    private function saveMessage(InterprovincialTrialInvitationBatch $batch, string $subject = 'Trials invitation', string $body = 'You are invited to participate in the Interprovincial Trials.'): void
    {
        $this->actingAs($this->admin)->put(route('backend.interprovincial-trials.batches.message', [$this->event, $batch]), [
            'email_subject' => $subject,
            'email_body' => $body,
        ])->assertRedirect();
    }

    private function reviewData(InterprovincialTrialInvitationBatch $batch): array
    {
        return ['snapshot_hash' => $batch->snapshot_hash, 'message_hash' => $batch->message_hash];
    }

    private function sendData(): array
    {
        return ['confirm_exact_recipients_and_message' => '1'];
    }
}
