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
            ->assertSee("fetch(form.action", false)
            ->assertSee('list.innerHTML = data.html', false)
            ->assertSee('data-send-preview-mode="new"', false)
            ->assertSee('id="interpro-send-preview-modal"', false)
            ->assertSee(json_encode(route('backend.interprovincial-trials.invitations.send-preview', $this->event)), false)
            ->assertSee(json_encode(route('backend.interprovincial-trials.invitations.send-preview.queue', $this->event)), false)
            ->assertDontSee('textContent = nomination.player_name', false)
            ->assertDontSee('data-send-invitations', false)
            ->assertDontSee('refreshSendButton', false)
            ->assertDontSee(route('backend.interprovincial-trials.invitations.send-current', $this->event), false)
            ->assertDontSee('Prepare invitations from current nominations')
            ->assertDontSee('I reviewed these exact recipients and message')
            ->assertDontSee('player_search', false)
            ->assertDontSee('Reachable Nominee')
            ->assertDontSee($player->email);

        $store = route('backend.interprovincial-trials.nominations.store', [$this->event, $this->category]);
        $this->actingAs($this->admin)->post($store, ['player_ids' => [$player->id]])
            ->assertRedirect()->assertSessionHas('success', '1 nomination(s) added; 0 already nominated. Send invitations when the list is complete.');
        $this->actingAs($this->admin)->post($store, ['player_ids' => [$player->id]])
            ->assertRedirect()->assertSessionHas('success', '0 nomination(s) added; 1 already nominated. Send invitations when the list is complete.');
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
        $this->prepareInvitationFixtures();
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
        $this->prepareInvitationFixtures();
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
        $this->prepareInvitationFixtures();
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
        $this->prepareInvitationFixtures();
        $actionable = InterprovincialTrialInvitation::sole();
        $actionable->update(['status' => 'queued']);
        $actionable->batch->update(['status' => InterprovincialTrialInvitationBatch::QUEUED]);
        $this->prepareInvitationFixtures();

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

        $fullName = Player::factory()->create([
            'name' => 'Jean',
            'surname' => 'Joubert',
        ]);
        foreach (['jean joubert', 'joubert jean', '  jean   joubert  '] as $query) {
            $this->actingAs($this->admin)->getJson($endpoint.'?q='.urlencode($query))
                ->assertOk()
                ->assertJsonCount(1, 'results')
                ->assertJsonPath('results.0.id', $fullName->id)
                ->assertJsonPath('results.0.text', 'Jean Joubert — Profile #'.$fullName->id);
        }
        $this->actingAs($this->admin)->getJson($endpoint.'?q='.urlencode('jean missing'))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $literal = Player::factory()->create([
            'name' => 'Literal%_Token',
            'surname' => 'Picker',
            'email' => 'private-picker@example.test',
        ]);
        $literalBang = Player::factory()->create(['name' => 'Literal!Bang', 'surname' => 'Picker']);
        Player::factory()->create(['name' => 'LiteralXXToken', 'surname' => 'NotAMatch']);

        $literalResponse = $this->actingAs($this->admin)->getJson($endpoint.'?q='.urlencode('%_'));
        $literalResponse->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $literal->id)
            ->assertJsonPath('results.0.text', 'Literal%_Token Picker — Profile #'.$literal->id)
            ->assertJsonMissing(['email' => 'private-picker@example.test']);
        $this->assertSame(['id', 'text'], array_keys($literalResponse->json('results.0')));
        $this->actingAs($this->admin)->getJson($endpoint.'?q='.urlencode('!Bang'))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $literalBang->id);

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
            ->assertSessionHas('success', '2 nomination(s) added; 1 already nominated. Send invitations when the list is complete.');
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

    public function test_signed_invitation_view_preserves_owner_gate_and_allows_read_only_super_user_preview(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $this->event->update([
            'published' => true,
            'status' => 'open',
            'signUp' => true,
            'start_date' => now()->addDays(20)->toDateString(),
            'deadline' => 2,
        ]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->prepareInvitationFixtures();
        $batch = InterprovincialTrialInvitationBatch::sole(); $invitation = InterprovincialTrialInvitation::sole();
        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$other, $batch]), ['snapshot_hash' => $batch->snapshot_hash, 'message_hash' => str_repeat('a', 64)])->assertNotFound();
        $signed = URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->addMinute(), ['invitation' => $invitation]);
        $this->actingAs(User::factory()->create())->get($signed)->assertForbidden();
        $this->actingAs($this->admin)->get($signed)->assertForbidden();

        $this->actingAs($owner)->get($signed)->assertNotFound();
        $super = User::factory()->create()->assignRole('super-user');
        $this->actingAs($super)->get($signed)
            ->assertOk()
            ->assertSee('Super-user preview:')
            ->assertSee('read-only preview')
            ->assertDontSee('Register '.$player->name)
            ->assertDontSee(route('interprovincial-trials.invitations.register', $invitation), false);

        $this->actingAs($super)->get($signed.'&tampered=1')->assertForbidden();
        $expired = URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->subMinute(), ['invitation' => $invitation]);
        $this->actingAs($super)->get($expired)->assertForbidden();

        foreach (['queued', 'sent'] as $status) {
            $invitation->update(['status' => $status]);
            $this->actingAs($owner)->get($signed)
                ->assertOk()
                ->assertSee('has been invited')
                ->assertSee('Register '.$player->name)
                ->assertSee(route('interprovincial-trials.invitations.register', $invitation), false)
                ->assertDontSee('Super-user preview:');
        }
    }

    public function test_invitation_email_targets_the_public_event_nomination_anchor(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $player->id,
        ]);
        $this->prepareInvitationFixtures();
        $invitation = InterprovincialTrialInvitation::sole();
        $url = route('events.show', [
            'event' => $this->event->id,
            'player' => $player->id,
            'nomination' => $nomination->id,
        ]).'#trial-nomination-'.$nomination->id;

        $rendered = (new InterprovincialTrialInvitationMail($invitation, [
            'subject' => 'Trials invitation', 'body' => 'Please respond', 'recipient_name' => 'Player Owner',
        ]))->render();
        $this->assertStringContainsString(e($url), $rendered);
        $this->assertStringContainsString('View event and respond', $rendered);
        $this->assertStringNotContainsString('interprovincial-trials/invitations/'.$invitation->id.'?', $rendered);
    }

    public function test_published_event_focuses_exact_nomination_and_shows_only_owner_status_actions(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id, 'name' => 'Focused', 'surname' => 'Player']);
        $nomination = EventNomination::create([
            'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id,
        ]);
        $this->category->update(['nominations_published' => true]);
        $this->event->update(['published' => true, 'signUp' => 1, 'status' => 'open']);
        $this->prepareInvitationFixtures();
        $invitation = InterprovincialTrialInvitation::sole();
        $invitation->update(['status' => 'queued']);
        $url = route('events.show', ['event' => $this->event->id, 'player' => $player->id, 'nomination' => $nomination->id]);

        $this->actingAs($owner)->get($url)->assertOk()
            ->assertSee('id="trial-nomination-'.$nomination->id.'"', false)
            ->assertSee('border border-primary', false)
            ->assertSee(route('interprovincial-trials.invitations.register', $invitation), false)
            ->assertSee(route('interprovincial-trials.invitations.decline', $invitation), false);
        $this->actingAs(User::factory()->create())->get($url)->assertOk()
            ->assertSee('Focused Player')
            ->assertDontSee(route('interprovincial-trials.invitations.register', $invitation), false)
            ->assertDontSee(route('interprovincial-trials.invitations.decline', $invitation), false);

        $order = RegistrationOrder::create([
            'user_id' => $owner->id, 'wallet_reserved' => 0, 'wallet_debited' => false,
            'payfast_paid' => false, 'payfast_amount_due' => 100, 'pay_status' => false,
            'payment_method' => 'payfast', 'total_fee' => 100, 'status' => 'pending',
        ]);
        $invitation->update(['status' => InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, 'order_id' => $order->id]);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Complete payment')
            ->assertSee(route('registration.checkout', $order), false)->assertDontSee('>Decline<', false);
        $invitation->update(['status' => InterprovincialTrialInvitation::PAID_CONFIRMED]);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Registered');
        $invitation->update(['status' => InterprovincialTrialInvitation::DECLINED]);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Declined');
        $invitation->update(['status' => InterprovincialTrialInvitation::WITHDRAWN]);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Withdrawn');
    }

    public function test_owner_can_decline_once_without_financial_writes_and_declined_cannot_register(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create([
            'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id,
        ]);
        $this->prepareInvitationFixtures();
        $invitation = InterprovincialTrialInvitation::sole();
        $invitation->update(['status' => 'sent']);
        $decline = route('interprovincial-trials.invitations.decline', $invitation);

        $this->actingAs($owner)->post($decline)->assertRedirect();
        $this->actingAs($owner)->post($decline)->assertRedirect();
        $this->assertSame(InterprovincialTrialInvitation::DECLINED, $invitation->fresh()->status);
        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('registration_order_items', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->withoutMiddleware([EnsureAgreementAccepted::class, EnsurePlayerProfileUpdated::class]);
        $this->actingAs($owner)->post(route('interprovincial-trials.invitations.register', $invitation))
            ->assertSessionHasErrors('invitation');
    }

    public function test_decline_rejects_unrelated_and_tampered_tuples_and_is_preserved_by_new_preparation(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create([
            'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id,
        ]);
        $this->prepareInvitationFixtures();
        $first = InterprovincialTrialInvitation::sole();
        $first->update(['status' => 'queued']);
        $this->actingAs(User::factory()->create())->post(route('interprovincial-trials.invitations.decline', $first))->assertForbidden();
        $this->actingAs($owner)->post(route('interprovincial-trials.invitations.decline', $first))->assertRedirect();

        $preview = $this->actingAs($this->admin)->postJson(
            route('backend.interprovincial-trials.invitations.send-preview', $this->event),
            ['mode' => 'new']
        )->assertOk()->assertJsonCount(0, 'recipients')->json();
        $this->assertSame([], $preview['blockers']);
        $this->assertDatabaseCount('interprovincial_trial_invitations', 1);
        $this->assertSame(InterprovincialTrialInvitation::DECLINED, $first->fresh()->status);

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $first->update(['category_event_id' => $otherCategory->id, 'status' => 'queued']);
        $this->actingAs($owner)->post(route('interprovincial-trials.invitations.decline', $first))->assertNotFound();
        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('registration_orders', 0);
    }

    public function test_unlinked_super_user_cannot_register_from_preview_or_create_records(): void
    {
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $player->id,
        ]);
        $this->prepareInvitationFixtures();
        $invitation = InterprovincialTrialInvitation::sole();
        $invitation->update(['status' => 'queued']);
        $super = User::factory()->create()->assignRole('super-user');

        $this->withoutMiddleware([EnsureAgreementAccepted::class, EnsurePlayerProfileUpdated::class]);
        $this->actingAs($super)
            ->post(route('interprovincial-trials.invitations.register', $invitation))
            ->assertForbidden();

        $this->assertDatabaseCount('registrations', 0);
        $this->assertDatabaseCount('registration_orders', 0);
        $this->assertDatabaseCount('registration_order_items', 0);
        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseHas('interprovincial_trial_invitations', [
            'id' => $invitation->id,
            'status' => 'queued',
            'registration_id' => null,
            'order_id' => null,
        ]);
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
        $this->prepareInvitationFixtures();
        $this->assertSame('direct@example.test', InterprovincialTrialInvitation::sole()->recipient_email);

        $player->update(['userId' => null]);
        $this->prepareInvitationFixtures();
        $expected = $firstLink->id < $secondLink->id ? $firstLink->email : $secondLink->email;
        $this->assertSame($expected, InterprovincialTrialInvitation::sole()->recipient_email);

        DB::table('user_players')->where('player_id', $player->id)->delete();
        $this->prepareInvitationFixtures();
        $this->assertNull(InterprovincialTrialInvitation::sole()->recipient_email);
    }

    public function test_latest_batch_list_groups_human_statuses_shows_safe_details_and_is_read_only_event_scoped(): void
    {
        $batch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $this->event->id,
            'status' => InterprovincialTrialInvitationBatch::QUEUED,
            'snapshot_hash' => str_repeat('a', 64),
            'created_by_user_id' => $this->admin->id,
        ]);
        $registration = Registration::factory()->create();
        $orderOwner = User::factory()->create();
        $order = RegistrationOrder::create([
            'user_id' => $orderOwner->id,
            'wallet_reserved' => 0,
            'wallet_debited' => false,
            'payfast_paid' => true,
            'payfast_amount_due' => 0,
            'pay_status' => true,
            'payment_method' => 'payfast',
            'total_fee' => 100,
            'status' => 'paid',
        ]);
        $registration->categoryEvents()->syncWithoutDetaching([
            $this->category->id => ['payment_status_id' => 1, 'user_id' => $orderOwner->id],
        ]);
        $orderPlayer = Player::factory()->create();
        $orderItem = new RegistrationOrderItems();
        $orderItem->order_id = $order->id;
        $orderItem->category_event_id = $this->category->id;
        $orderItem->registration_id = $registration->id;
        $orderItem->player_id = $orderPlayer->id;
        $orderItem->user_id = $orderOwner->id;
        $orderItem->item_price = 100;
        $orderItem->save();

        $statuses = [
            'prepared', 'queued', 'sending', 'sent', 'failed',
            InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT,
            InterprovincialTrialInvitation::PAID_CONFIRMED,
            InterprovincialTrialInvitation::DECLINED,
            InterprovincialTrialInvitation::WITHDRAWN,
            'cancelled',
        ];
        $invitations = collect();
        foreach ($statuses as $index => $status) {
            $player = $status === InterprovincialTrialInvitation::PAID_CONFIRMED
                ? $orderPlayer
                : Player::factory()->create(['name' => 'Lifecycle', 'surname' => str($status)->studly()->toString()]);
            $nomination = EventNomination::create([
                'event_id' => $this->event->id,
                'category_event_id' => $this->category->id,
                'player_id' => $player->id,
            ]);
            $invitations[$status] = InterprovincialTrialInvitation::create([
                'batch_id' => $batch->id,
                'event_id' => $this->event->id,
                'category_event_id' => $this->category->id,
                'nomination_id' => $nomination->id,
                'player_id' => $player->id,
                'registration_id' => $status === InterprovincialTrialInvitation::PAID_CONFIRMED ? $registration->id : null,
                'order_id' => $status === InterprovincialTrialInvitation::PAID_CONFIRMED ? $order->id : null,
                'recipient_email' => 'lifecycle.'.$index.'@example.test',
                'status' => $status,
                'queued_at' => in_array($status, ['queued', 'sending', 'sent'], true) ? now()->subHours(3) : null,
                'sent_at' => $status === 'sent' ? now()->subHours(2) : null,
                'accepted_at' => in_array($status, [InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, InterprovincialTrialInvitation::PAID_CONFIRMED], true) ? now()->subHour() : null,
                'paid_at' => $status === InterprovincialTrialInvitation::PAID_CONFIRMED ? now()->subMinutes(30) : null,
                'withdrawn_at' => $status === InterprovincialTrialInvitation::WITHDRAWN ? now()->subMinutes(15) : null,
            ]);
        }

        $otherEvent = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        Category::query()->whereKey($otherCategory->category_id)->update(['name' => 'Foreign Secret Category']);
        $otherPlayer = Player::factory()->create(['name' => 'OtherEventSecret', 'surname' => 'Player']);
        $otherNomination = EventNomination::create(['event_id' => $otherEvent->id, 'category_event_id' => $otherCategory->id, 'player_id' => $otherPlayer->id]);
        $otherBatch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $otherEvent->id,
            'status' => InterprovincialTrialInvitationBatch::QUEUED,
            'snapshot_hash' => str_repeat('b', 64),
            'created_by_user_id' => $this->admin->id,
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $otherBatch->id,
            'event_id' => $otherEvent->id,
            'category_event_id' => $otherCategory->id,
            'nomination_id' => $otherNomination->id,
            'player_id' => $otherPlayer->id,
            'recipient_email' => 'other-event@example.test',
            'status' => 'failed',
        ]);
        $malformedPlayer = Player::factory()->create(['name' => 'MalformedTuple', 'surname' => 'Player']);
        $malformedNomination = EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $otherCategory->id,
            'player_id' => $malformedPlayer->id,
        ]);
        $unvalidatedRegistration = Registration::factory()->create();
        $unvalidatedOrder = RegistrationOrder::create([
            'user_id' => $orderOwner->id,
            'wallet_reserved' => 0,
            'wallet_debited' => false,
            'payfast_paid' => false,
            'payfast_amount_due' => 0,
            'pay_status' => false,
            'payment_method' => 'free',
            'total_fee' => 0,
            'status' => 'pending',
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $this->event->id,
            'category_event_id' => $otherCategory->id,
            'nomination_id' => $malformedNomination->id,
            'player_id' => $malformedPlayer->id,
            'registration_id' => $unvalidatedRegistration->id,
            'order_id' => $unvalidatedOrder->id,
            'recipient_email' => 'malformed@example.test',
            'status' => 'prepared',
        ]);

        $before = collect([
            'interprovincial_trial_invitation_batches', 'interprovincial_trial_invitations',
            'interprovincial_trial_mail_dispatches', 'bulk_email_logs', 'registrations',
            'registration_orders', 'wallet_transactions', 'transactions_pf',
        ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $response = $this->actingAs($this->admin)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()
            ->assertSee('Invited - awaiting response')
            ->assertSee('Payment pending')
            ->assertSee('Registered')
            ->assertSee('Declined')
            ->assertSee('Withdrawn')
            ->assertSee('Delivery failed')
            ->assertSee('Nominated - not sent')
            ->assertSee('Cancelled')
            ->assertSee('data-nomination-state="invited"', false)
            ->assertSee('mailto:lifecycle.3%40example.test', false)
            ->assertSee('Queued:')
            ->assertSee('Sent:')
            ->assertSee('Accepted:')
            ->assertSee('Paid:')
            ->assertSee('Withdrawn:')
            ->assertSee('MalformedTuple')
            ->assertSee('Category unavailable')
            ->assertDontSee('Foreign Secret Category')
            ->assertSee(route('backend.interprovincial-trials.invitations.retry', [$this->event, $batch, $invitations['failed']]), false)
            ->assertDontSee(route('backend.interprovincial-trials.invitations.retry', [$this->event, $batch, $invitations['sent']]), false)
            ->assertDontSee('OtherEventSecret')
            ->assertDontSee('other-event@example.test');

        $response->assertSee('Send to newly nominated')->assertSee('Send to players not registered')
            ->assertSee('interpro-send-preview-modal')->assertDontSee('<table', false)
            ->assertDontSee(route('backend.interprovincial-trials.invitations.send-current', $this->event), false)
            ->assertDontSee('data-send-invitations', false);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "The invitation list GET must not write {$table}.");
        }
    }

    public function test_previewed_new_send_is_read_only_exact_audited_and_same_token_idempotent(): void
    {
        Bus::fake();
        $owner = User::factory()->create(['email' => 'preview-owner@example.test']);
        $player = Player::factory()->create(['userId' => $owner->id, 'name' => 'Preview', 'surname' => 'Player']);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $previewUrl = route('backend.interprovincial-trials.invitations.send-preview', $this->event);
        $queueUrl = route('backend.interprovincial-trials.invitations.send-preview.queue', $this->event);
        $before = [
            'batches' => InterprovincialTrialInvitationBatch::count(),
            'invitations' => InterprovincialTrialInvitation::count(),
            'dispatches' => DB::table('interprovincial_trial_mail_dispatches')->count(),
            'logs' => BulkEmailLog::count(),
        ];

        $unassigned = User::factory()->create()->assignRole('admin');
        $this->actingAs($unassigned)->postJson($previewUrl, ['mode' => 'new'])->assertForbidden();
        $preview = $this->actingAs($this->admin)->postJson($previewUrl, ['mode' => 'new'])
            ->assertOk()->assertJsonCount(1, 'recipients')->assertJsonCount(0, 'blockers')
            ->assertJsonPath('recipients.0.email', 'preview-owner@example.test')->json();
        $this->assertSame($before['batches'], InterprovincialTrialInvitationBatch::count());
        $this->assertSame($before['invitations'], InterprovincialTrialInvitation::count());
        $this->assertSame($before['dispatches'], DB::table('interprovincial_trial_mail_dispatches')->count());
        $this->assertSame($before['logs'], BulkEmailLog::count());

        $payload = [
            'mode' => 'new',
            'request_token' => $preview['request_token'],
            'recipient_hash' => $preview['recipient_hash'],
            'subject' => 'Exact trials invitation',
            'body' => 'Please use your secure invitation link.',
        ];
        $this->actingAs($this->admin)->postJson($queueUrl, $payload)->assertOk()->assertJsonPath('queued_count', 1);
        $this->actingAs($this->admin)->postJson($queueUrl, $payload)->assertOk()->assertJsonPath('already_queued', true);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertDatabaseHas('interprovincial_trial_mail_dispatches', [
            'request_token' => $preview['request_token'], 'kind' => 'initial', 'requested_by_user_id' => $this->admin->id,
        ]);
        $log = BulkEmailLog::sole();
        $this->assertSame('new', $log->payload['mode']);
        $this->assertSame($preview['request_token'], $log->payload['request_token']);
        $this->assertSame($this->admin->id, $log->payload['requested_by_user_id']);
        $log->update(['status' => 'sent', 'sent_at' => now()]);
        InterprovincialTrialInvitation::sole()->update(['status' => 'sent', 'sent_at' => now()]);
        $owner->update(['email' => 'preview-owner-updated@example.test']);

        $reminderOne = $this->actingAs($this->admin)->postJson($previewUrl, ['mode' => 'not_registered'])
            ->assertOk()->assertJsonCount(1, 'recipients')
            ->assertJsonPath('recipients.0.email', 'preview-owner-updated@example.test')->json();
        $reminderPayload = [
            'mode' => 'not_registered',
            'request_token' => $reminderOne['request_token'],
            'recipient_hash' => $reminderOne['recipient_hash'],
            'subject' => 'Registration reminder',
            'body' => 'Please complete your registration.',
        ];
        $this->actingAs($this->admin)->postJson($queueUrl, $reminderPayload)->assertOk()->assertJsonPath('queued_count', 1);
        $this->assertSame('preview-owner-updated@example.test', InterprovincialTrialInvitation::sole()->recipient_email);
        $this->assertSame('preview-owner@example.test', $log->fresh()->recipient_email);
        $this->assertSame('preview-owner-updated@example.test', BulkEmailLog::query()->latest('id')->value('recipient_email'));
        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()->assertSee('preview-owner-updated@example.test')->assertDontSee('mailto:preview-owner%40example.test', false);
        $reminderTwo = $this->actingAs($this->admin)->postJson($previewUrl, ['mode' => 'not_registered'])->assertOk()->json();
        $reminderPayload['request_token'] = $reminderTwo['request_token'];
        $reminderPayload['recipient_hash'] = $reminderTwo['recipient_hash'];
        $this->actingAs($this->admin)->postJson($queueUrl, $reminderPayload)->assertOk()->assertJsonPath('queued_count', 1);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 3);
        $this->assertSame(2, DB::table('interprovincial_trial_mail_dispatches')->where('kind', 'follow_up')->count());
        $this->assertSame('sent', InterprovincialTrialInvitation::sole()->status);
        Bus::assertDispatchedTimes(SendInterprovincialTrialInvitationEmailJob::class, 3);
    }

    public function test_terminal_mail_failure_can_retry_without_a_second_dispatch_record_or_duplicate_send(): void
    {
        Bus::fake();
        config(['mail.default' => 'array']);
        $this->mock(MailAccountManager::class, fn ($mock) => $mock->shouldReceive('getMailer')->andReturn('array'));
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->queuePreviewedNewInvitation();
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

    public function test_follow_up_job_never_downgrades_lifecycle_and_skips_after_terminal_change(): void
    {
        config(['mail.default' => 'array']);
        $this->mock(MailAccountManager::class, fn ($mock) => $mock->shouldReceive('getMailer')->andReturn('array'));
        $owner = User::factory()->create(['email' => 'follow-up@example.test']);
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $this->event->id, 'status' => InterprovincialTrialInvitationBatch::QUEUED, 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $this->admin->id]);
        $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'nomination_id' => $nomination->id, 'player_id' => $player->id, 'recipient_email' => $owner->email, 'status' => InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT]);
        $payload = ['kind' => 'follow_up', 'subject' => 'Reminder', 'body' => 'Please complete registration.', 'recipient_name' => 'Follow Up'];
        $log = BulkEmailLog::create(['mail_type' => 'interprovincial_trial_invitation', 'related_type' => InterprovincialTrialInvitation::class, 'related_id' => $invitation->id, 'recipient_email' => $owner->email, 'status' => 'queued', 'payload' => $payload, 'queued_at' => now()]);

        (new SendInterprovincialTrialInvitationEmailJob($log->id, $this->event->id))->handle();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame(InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, $invitation->fresh()->status);

        $invitation->update(['status' => InterprovincialTrialInvitation::PAID_CONFIRMED]);
        $terminalLog = BulkEmailLog::create(['mail_type' => 'interprovincial_trial_invitation', 'related_type' => InterprovincialTrialInvitation::class, 'related_id' => $invitation->id, 'recipient_email' => $owner->email, 'status' => 'queued', 'payload' => $payload, 'queued_at' => now()]);
        (new SendInterprovincialTrialInvitationEmailJob($terminalLog->id, $this->event->id))->handle();
        $this->assertSame('skipped', $terminalLog->fresh()->status);
        $this->assertSame(InterprovincialTrialInvitation::PAID_CONFIRMED, $invitation->fresh()->status);
    }

    public function test_authorized_retry_reuses_the_failed_log_and_dispatch_with_nested_isolation(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $this->queuePreviewedNewInvitation();
        $batch = InterprovincialTrialInvitationBatch::sole();
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

    public function test_failed_follow_up_retry_reuses_exact_log_without_changing_invitation_state(): void
    {
        Bus::fake();
        $owner = User::factory()->create(['email' => 'retry-follow-up@example.test']);
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $this->event->id, 'status' => InterprovincialTrialInvitationBatch::QUEUED, 'snapshot_hash' => str_repeat('b', 64), 'created_by_user_id' => $this->admin->id]);
        $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'nomination_id' => $nomination->id, 'player_id' => $player->id, 'recipient_email' => $owner->email, 'status' => InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT]);
        $token = (string) \Illuminate\Support\Str::uuid();
        $log = BulkEmailLog::create([
            'mail_type' => 'interprovincial_trial_invitation', 'related_type' => InterprovincialTrialInvitation::class,
            'related_id' => $invitation->id, 'recipient_email' => $owner->email, 'status' => 'failed',
            'payload' => ['kind' => 'follow_up', 'request_token' => $token, 'subject' => 'Original reminder', 'body' => 'Original exact body'],
            'queued_at' => now()->subMinute(), 'failed_at' => now(), 'error_message' => 'Transport unavailable',
        ]);
        DB::table('interprovincial_trial_mail_dispatches')->insert([
            'invitation_id' => $invitation->id, 'bulk_email_log_id' => $log->id, 'request_token' => $token,
            'kind' => 'follow_up', 'requested_by_user_id' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $route = route('backend.interprovincial-trials.invitations.retry-follow-up', [$this->event, $invitation]);
        $this->actingAs(User::factory()->create()->assignRole('admin'))->post($route)->assertForbidden();
        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $this->admin->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.retry-follow-up', [$other, $invitation]))->assertNotFound();
        $this->actingAs($this->admin)->post($route)->assertRedirect()->assertSessionHas('success');

        $this->assertSame(InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, $invitation->fresh()->status);
        $this->assertSame('queued', $log->fresh()->status);
        $this->assertSame('Original exact body', $log->fresh()->payload['body']);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Bus::assertDispatchedTimes(SendInterprovincialTrialInvitationEmailJob::class, 1);
    }

    public function test_legacy_send_endpoints_are_hard_disabled_without_writes(): void
    {
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $this->event->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT, 'snapshot_hash' => str_repeat('c', 64), 'created_by_user_id' => $this->admin->id]);
        $before = [InterprovincialTrialInvitation::count(), BulkEmailLog::count(), DB::table('interprovincial_trial_mail_dispatches')->count()];

        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.prepare', $this->event))->assertNotFound();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.invitations.send-current', $this->event), ['email_subject' => 'Bypass', 'email_body' => 'Bypass'])->assertNotFound();
        $this->actingAs($this->admin)->put(route('backend.interprovincial-trials.batches.message', [$this->event, $batch]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.review', [$this->event, $batch]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.batches.send', [$this->event, $batch]))->assertNotFound();

        $this->assertSame($before, [InterprovincialTrialInvitation::count(), BulkEmailLog::count(), DB::table('interprovincial_trial_mail_dispatches')->count()]);
    }

    public function test_legacy_initial_claim_excludes_every_non_prepared_lifecycle_state(): void
    {
        Bus::fake();
        $service = app(\App\Services\InterprovincialTrials\InvitationService::class);
        $claim = new \ReflectionMethod($service, 'claimAndQueueInvitations');
        $states = ['queued', 'sending', 'sent', InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, InterprovincialTrialInvitation::PAID_CONFIRMED, InterprovincialTrialInvitation::DECLINED, InterprovincialTrialInvitation::WITHDRAWN, 'cancelled', 'failed'];

        foreach ($states as $index => $status) {
            $owner = User::factory()->create(['email' => "excluded-{$index}@example.test"]);
            $player = Player::factory()->create(['userId' => $owner->id]);
            $nomination = EventNomination::create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'player_id' => $player->id]);
            $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $this->event->id, 'status' => InterprovincialTrialInvitationBatch::REVIEWED, 'snapshot_hash' => str_repeat('d', 64), 'created_by_user_id' => $this->admin->id]);
            $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $this->event->id, 'category_event_id' => $this->category->id, 'nomination_id' => $nomination->id, 'player_id' => $player->id, 'recipient_email' => $owner->email, 'status' => $status]);

            $this->assertSame(0, $claim->invoke($service, $batch->fresh('event')), "State {$status} must not be initially claimed.");
            $this->assertSame($status, $invitation->fresh()->status);
        }

        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_dispatch_attempt_migration_refuses_to_delete_follow_up_audit_on_rollback(): void
    {
        DB::table('interprovincial_trial_mail_dispatches')->insert([
            'invitation_id' => 999999, 'request_token' => (string) \Illuminate\Support\Str::uuid(),
            'kind' => 'follow_up', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_09_28_000004_expand_interprovincial_trial_mail_dispatch_attempts.php');

        try {
            $migration->down();
            $this->fail('Rollback should refuse to delete follow-up audit rows.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('follow-up invitation audit rows exist', $exception->getMessage());
        }
        $this->assertDatabaseHas('interprovincial_trial_mail_dispatches', ['invitation_id' => 999999, 'kind' => 'follow_up']);
    }

    private function queuePreviewedNewInvitation(): void
    {
        $preview = $this->actingAs($this->admin)->postJson(
            route('backend.interprovincial-trials.invitations.send-preview', $this->event),
            ['mode' => 'new']
        )->assertOk()->json();
        $this->actingAs($this->admin)->postJson(
            route('backend.interprovincial-trials.invitations.send-preview.queue', $this->event),
            [
                'mode' => 'new',
                'request_token' => $preview['request_token'],
                'recipient_hash' => $preview['recipient_hash'],
                'subject' => 'Trials invitation',
                'body' => 'You are invited to participate in the Interprovincial Trials.',
            ]
        )->assertOk();
    }

    private function prepareInvitationFixtures(): InterprovincialTrialInvitationBatch
    {
        $service = app(\App\Services\InterprovincialTrials\InvitationService::class);
        $snapshot = new \ReflectionMethod($service, 'snapshotRows');
        $nominations = $this->event->nominations()->with(['player.user', 'player.users', 'categoryEvent.category'])->orderBy('id')->get();
        $rows = $snapshot->invoke($service, $this->event, $nominations);
        $batch = InterprovincialTrialInvitationBatch::query()
            ->where('event_id', $this->event->id)
            ->where('status', InterprovincialTrialInvitationBatch::DRAFT)
            ->latest('id')->first();
        $batch ??= InterprovincialTrialInvitationBatch::create([
            'event_id' => $this->event->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => hash('sha256', json_encode($rows)), 'created_by_user_id' => $this->admin->id,
        ]);
        $batch->update(['snapshot_hash' => hash('sha256', json_encode($rows))]);
        foreach ($rows as $row) {
            InterprovincialTrialInvitation::updateOrCreate([
                'batch_id' => $batch->id, 'nomination_id' => $row['nomination_id'],
            ], [
                'event_id' => $this->event->id,
                'category_event_id' => $row['category_event_id'],
                'player_id' => $row['player_id'],
                'recipient_email' => $row['recipient_email'],
                'recipient_name' => $row['recipient_name'],
                'status' => 'prepared',
            ]);
        }

        return $batch;
    }

}
