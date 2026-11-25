<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, User};
use App\Services\InterprovincialTrials\NominationHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproNominationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue((DB::getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:')
            || (DB::getDriverName() === 'mysql' && DB::connection()->getDatabaseName() === 'ct_testing'));
    }

    public function test_complete_register_counts_archived_withdrawals_and_removed_history_once(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $active = $this->nominee($event, $category, $batch, 'Active', 'paid_confirmed');
        $archived = $this->nominee($event, $category, $batch, 'Archived', 'withdrawn');
        $archived->forceFill(['archived_at' => now(), 'archive_reason' => 'Withdrawn'])->save();
        $this->nominee($event, $category, $batch, 'Withdrawn', 'withdrawn');
        $this->nominee($event, $category, $batch, 'Declined', 'admin_declined');
        $this->nominee($event, $category, $batch, 'Cancelled', 'cancelled');
        $this->deleted($event, $category, 90001, 'Removed');
        $this->deleted($event, $category, 90001, 'Removed');
        \Illuminate\Support\Facades\Schema::withoutForeignKeyConstraints(function () use ($event, $category, $batch) {
            DB::table('interprovincial_trial_invitations')->insert(['batch_id' => $batch->id, 'event_id' => $event->id,
                'category_event_id' => $category->id, 'nomination_id' => 90001, 'player_id' => null, 'status' => 'sent']);
        });
        $history = app(NominationHistory::class)->build($event);
        $this->assertSame(6, $history['rows']->total());
        $this->assertSame(2, (int) $history['counts']['withdrawn']);
        $this->assertSame(1, (int) $history['counts']['removed']);
        $this->assertSame(6, (int) $history['categoryCounts'][$category->id]);
        $filtered = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', [$event, 'nomination_status' => 'withdrawn']))->assertOk();
        $this->assertSame(2, $filtered->viewData('nominations')->total());
        $filtered->assertSee('Archived nomination')->assertSee('Retained history')->assertDontSee('Active Candidate');
        $groups = $filtered->viewData('nominationFilterGroups');
        $this->assertSame(6, $groups['all']['count']);
        $this->assertSame(2, $groups['withdrawn']['count']);
        $this->assertSame(1, $groups['registered']['count']);
        $this->assertSame(2, $groups['declined-removed']['count']);
        $combined = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', [$event, 'nomination_status' => 'declined-removed']))
            ->assertOk()->assertSee('Declined Candidate')->assertSee('Removed Candidate')->assertSee('Declined / removed');
        $this->assertSame(2, $combined->viewData('nominations')->total());
        $all = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', $event))->assertOk()->assertSee('Removed Candidate')->assertSee('Reason not recorded');
        $this->assertSame(0, $all->viewData('historicalInvitations')->total());
        if (getenv('CT_NOMINATION_HISTORY_QA_HTML')) {
            file_put_contents(storage_path('framework/testing/interpro-nomination-history-qa.html'), $all->getContent());
            file_put_contents(storage_path('framework/testing/interpro-nomination-history-withdrawn-qa.html'), $filtered->getContent());
        }
        $this->assertSame('paid_confirmed', $active->actionableInvitation->status);
    }

    public function test_history_rejects_foreign_or_malformed_audit_snapshots_and_current_ids(): void
    {
        [$event, $category, , $batch] = $this->scenario();
        $other = Event::factory()->create();
        $foreign = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $current = $this->nominee($event, $category, $batch, 'Still current', 'paid_confirmed');
        $this->deleted($event, $category, $current->id, 'Old deletion');
        $this->deleted($event, $foreign, 90002, 'Foreign category');
        $this->deleted($event, $category, 90003, 'Foreign snapshot', ['event_id' => $other->id]);
        $this->deleted($event, $category, 90004, 'Malformed snapshot', ['category_event_id' => $category->id.'garbage']);
        $this->deleted($event, $category, 90005, 'Wrong identity', ['id' => 90006]);
        $this->assertSame(1, app(NominationHistory::class)->build($event)['rows']->total());
        $this->deleted($event, $category, 90007, 'Bad identity', ['player_id' => ['bad'], 'nominee_name' => ['bad'], 'nominee_surname' => ['bad']]);
        $history = app(NominationHistory::class)->build($event);
        $this->assertSame('Removed nominee #90007', $history['rows']->getCollection()->last()->display_name);
        $this->get(route('backend.interprovincial-trials.invitations.index', $event))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('backend.interprovincial-trials.invitations.index', $event))->assertForbidden();
    }

    public function test_global_totals_and_filtering_survive_pagination_and_new_drafts(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        for ($i = 0; $i < 101; $i++) $this->nominee($event, $category, $batch, 'Nominee'.$i, 'sent');
        $withdrawn = $this->nominee($event, $category, $batch, 'Last withdrawn', 'withdrawn');
        $batch = $batch->replicate();
        $batch->save();
        InterprovincialTrialInvitation::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'batch_id' => $batch->id,
            'nomination_id' => $withdrawn->id, 'player_id' => $withdrawn->player_id, 'status' => 'prepared']);
        $response = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', [$event, 'nomination_status' => 'withdrawn']))->assertOk()->assertSee('Last withdrawn Candidate');
        $this->assertSame(1, $response->viewData('nominations')->total());
        $this->assertSame(102, $response->viewData('nominationFilterGroups')['all']['count']);
        $this->assertSame(100, app(NominationHistory::class)->build($event)['rows']->count());
        $this->assertSame(102, app(NominationHistory::class)->build($event)['rows']->total());
    }

    public function test_register_folds_categories_and_history_without_losing_filtered_records(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $second = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $this->nominee($event, $category, $batch, 'First group', 'withdrawn');
        $withdrawn = $this->nominee($event, $second, $batch, 'Second group', 'withdrawn');
        $withdrawn->actionableInvitation->forceFill(['withdrawn_at' => now()])->save();
        $this->actingAs($actor);
        $url = route('backend.interprovincial-trials.invitations.index', $event);
        $response = $this->get($url)->assertOk()->assertSee('First group Candidate')->assertSee('Second group Candidate');
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//div[contains(@class,"interpro-tool-grid")]/details')->length);
        $this->assertSame(1, $xpath->query('//details[@data-nomination-category and @open]')->length);
        $this->assertSame(2, $xpath->query('//details[@data-nomination-category]')->length);
        $this->assertSame(1, $xpath->query('//details[contains(@class,"interpro-history") and not(@open)]')->length);
        $response->assertSee('View history')->assertSee('Withdrawn:')->assertDontSee('No registration activity yet');
        $filtered = $this->get($url.'?nomination_status=withdrawn')->assertOk();
        @$document->loadHTML($filtered->getContent());
        $this->assertSame(2, (new \DOMXPath($document))->query('//details[@data-nomination-category and @open]')->length);
        $this->assertSame(2, $filtered->viewData('nominations')->total());
        if (getenv('CT_NOMINATION_HISTORY_QA_HTML')) {
            file_put_contents(storage_path('framework/testing/interpro-nomination-ordered-qa.html'), $response->getContent());
        }
    }

    public function test_status_order_is_applied_within_categories_before_pagination_without_changing_counts(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        // Create later groups first so ID ordering alone cannot satisfy this test.
        $registered = $this->nominee($event, $category, $batch, 'Registered', 'paid_confirmed');
        $withdrawn = $this->nominee($event, $category, $batch, 'Withdrawn', 'withdrawn');
        $declined = $this->nominee($event, $category, $batch, 'Declined', 'admin_declined');
        $archived = $this->nominee($event, $category, $batch, 'Archived withdrawal', 'withdrawn');
        $archived->forceFill(['archived_at' => now(), 'archive_reason' => 'Withdrawn'])->save();
        $this->deleted($event, $category, 90001, 'Removed');
        $unregistered = [];
        foreach (['prepared', 'sent', 'accepted_pending_payment', 'failed', 'cancelled'] as $status) {
            $unregistered[] = $this->nominee($event, $category, $batch, $status, $status)->id;
        }
        for ($i = 0; $i < 96; $i++) {
            $unregistered[] = $this->nominee($event, $category, $batch, 'Unregistered'.$i, 'sent')->id;
        }
        $second = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $secondNominee = $this->nominee($event, $second, $batch, 'Next category', 'sent');
        $this->actingAs($actor);
        $url = route('backend.interprovincial-trials.invitations.index', $event);
        $first = $this->get($url)->assertOk();
        $secondPage = $this->get($url.'?nominations_page=2')->assertOk();
        $firstRows = $first->viewData('nominations');
        $secondRows = $secondPage->viewData('nominations');
        $this->assertSame(107, $firstRows->total());
        $this->assertSame(array_slice($unregistered, 0, 100), $firstRows->getCollection()->pluck('id')->all());
        $this->assertSame([$unregistered[100], $registered->id, $declined->id, $withdrawn->id, $archived->id, 90001, $secondNominee->id],
            $secondRows->getCollection()->pluck('id')->all());
        $this->assertSame(['invited', 'registered', 'declined', 'withdrawn', 'withdrawn', 'removed', 'invited'],
            $secondRows->getCollection()->pluck('history_state')->all());
        $groups = $first->viewData('nominationFilterGroups');
        $this->assertSame(107, $groups['all']['count']);
        $this->assertSame(1, $groups['registered']['count']);
        $this->assertSame(2, $groups['withdrawn']['count']);
        $this->assertSame(2, $groups['declined-removed']['count']);
        $filtered = $this->get($url.'?nomination_status=withdrawn')->assertOk();
        $this->assertSame([$withdrawn->id, $archived->id], $filtered->viewData('nominations')->getCollection()->pluck('id')->all());
        $this->assertSame(2, $filtered->viewData('nominations')->total());
    }

    public function test_removed_player_can_be_readded_once_with_decline_and_deletion_history_preserved(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $category->update(['nominations_published' => true]);
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'declined');
        $original = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->first()->getAttributes();
        $id = $nomination->id;
        $playerId = $nomination->player_id;
        $nomination->delete();
        $this->deleted($event, $category, $id, 'Returning', ['player_id' => $playerId]);
        $page = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', $event))->assertOk()->assertSee('Re-add nomination')->assertSee('Confirm the player is available again');
        preg_match('/name="revision" value="([a-f0-9]{64})"/', $page->getContent(), $matches);
        $revision = $matches[1];
        if (getenv('CT_NOMINATION_HISTORY_QA_HTML')) file_put_contents(storage_path('framework/testing/interpro-nomination-reactivation-qa.html'), $page->getContent());
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $id]);
        $data = ['revision' => $revision, 'reason' => 'Player is available again', 'confirm' => 1];
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($original, InterprovincialTrialInvitation::find($original['id'])->getAttributes());
        $this->assertSame($id, EventNomination::where('player_id', $playerId)->sole()->id);
        $this->assertSame('open_registration', EventNomination::find($id)->actionableInvitation->status);
        $this->assertSame(1, app(NominationHistory::class)->build($event)['rows']->total());
        $this->assertSame('nominated', app(NominationHistory::class)->build($event)['rows']->first()->history_state);
        $this->assertTrue((bool) $category->fresh()->nominations_published);
        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('registration_order_items', 0);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 0);
        $this->post($url, $data)->assertSessionHasErrors('nomination');
        $this->assertSame(2, InterprovincialTrialInvitation::where('nomination_id', $id)->count());
        $this->assertDatabaseHas('audit_events', ['subject_id' => (string) $id, 'action' => 'event-nomination.deleted']);
    }

    public function test_reactivation_preserves_private_publication_and_requires_current_confirmation(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $category->update(['nominations_published' => false]);
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'admin_declined');
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $revision = $service->revision($event, $category->fresh(), $nomination->id);
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $nomination]);
        $this->actingAs($actor)->post($url, ['revision' => $revision, 'reason' => 'Available again'])->assertSessionHasErrors('confirm');
        $this->post($url, ['revision' => str_repeat('0',64), 'reason' => 'Available again', 'confirm' => 1])->assertSessionHasErrors('nomination');
        $this->post($url, ['revision' => $revision, 'reason' => 'Available again', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $category->fresh()->nominations_published);
        $this->assertSame('admin_declined', InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->oldest('id')->first()->status);
    }

    public function test_reactivation_rejects_foreign_scope_unauthorized_actors_and_withdrawn_or_paid_history(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'declined');
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $revision = $service->revision($event, $category, $nomination->id);
        $data = ['revision' => $revision, 'reason' => 'Available again', 'confirm' => 1];
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $nomination]);
        $this->actingAs(User::factory()->create())->post($url, $data)->assertForbidden();
        $foreign = CategoryEvent::factory()->create();
        $this->actingAs($actor)->post(route('backend.interprovincial-trials.nominations.reactivate', [$event, $foreign, $nomination]), $data)->assertNotFound();
        foreach (['withdrawn', 'paid_confirmed', 'queued', 'sending', 'accepted_pending_payment'] as $status) {
            $invitation = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->first();
            $invitation->update(['status' => $status]);
            $data['revision'] = $service->revision($event, $category, $nomination->id);
            $this->post($url, $data)->assertSessionHasErrors('nomination');
            $this->assertSame($status, $invitation->fresh()->status);
        }
        $this->assertSame(1, InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->count());
    }

    public function test_reactivation_rejects_missing_audit_duplicate_player_and_locked_category(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'declined');
        $id = $nomination->id;
        $playerId = $nomination->player_id;
        EventNomination::whereKey($id)->delete(); // Bypass the observer to test absent evidence.
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $id]);
        $this->actingAs($actor)->post($url, ['revision' => str_repeat('0',64), 'reason' => 'Available again', 'confirm' => 1])->assertNotFound();
        $this->deleted($event, $category, $id, 'Returning', ['player_id' => $playerId]);
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $replacement = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $playerId]);
        $data = ['revision' => $service->revision($event, $category, $id), 'reason' => 'Available again', 'confirm' => 1];
        $this->post($url, $data)->assertSessionHasErrors('nomination');
        $replacement->delete();
        $category->forceFill(['locked_at' => now()])->save();
        $data['revision'] = $service->revision($event, $category, $id);
        $this->post($url, $data)->assertSessionHasErrors('nomination');
        $this->assertNull(EventNomination::find($id));
    }

    public function test_reactivation_rejects_tampered_deletion_evidence_and_mismatched_invitation_identity(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'declined');
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $nomination]);
        $invitation = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->sole();
        $invitation->update(['player_id' => Player::factory()->create()->id]);
        $this->actingAs($actor)->post($url, ['revision' => $service->revision($event, $category, $nomination->id), 'reason' => 'Available again', 'confirm' => 1])->assertSessionHasErrors('nomination');
        $invitation->update(['player_id' => $nomination->player_id]);
        $nomination->delete();
        $this->deleted($event, $category, $nomination->id, 'Returning', ['player_id' => $nomination->player_id]);
        DB::table('audit_events')->where('subject_id', (string) $nomination->id)->where('action', 'event-nomination.deleted')->update(['integrity_hash' => str_repeat('0',64)]);
        $this->post($url, ['revision' => $service->revision($event, $category, $nomination->id), 'reason' => 'Available again', 'confirm' => 1])->assertSessionHasErrors('nomination');
        $this->assertNull(EventNomination::find($nomination->id));
    }

    public function test_reactivation_blocks_unlinked_checkout_items_and_soft_deleted_entries(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $this->actingAs($actor);
        foreach (['checkout', 'deleted-entry'] as $kind) {
            $nomination = $this->nominee($event, $category, $batch, $kind, 'declined');
            $registration = \App\Models\Registration::create();
            $registration->players()->attach($nomination->player_id);
            if ($kind === 'checkout') {
                $order = \App\Models\RegistrationOrder::create(['user_id' => $actor->id, 'status' => 'pending', 'total_fee' => 450, 'pay_status' => false]);
                \App\Models\RegistrationOrderItems::forceCreate(['order_id' => $order->id, 'category_event_id' => $category->id,
                    'player_id' => $nomination->player_id, 'registration_id' => $registration->id, 'user_id' => $actor->id, 'item_price' => 450]);
                $before = $order->fresh()->getAttributes();
            } else {
                $entry = \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'user_id' => $actor->id]);
                $entry->delete();
                $before = $entry->fresh()->getAttributes();
            }
            $this->post(route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $nomination]),
                ['revision' => $service->revision($event, $category, $nomination->id), 'reason' => 'Available again', 'confirm' => 1])->assertSessionHasErrors('nomination');
            $this->assertSame($before, ($kind === 'checkout' ? $order : $entry)->fresh()->getAttributes());
            $this->assertSame(1, InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->count());
        }
    }

    public function test_only_assigned_event_admin_can_reactivate_a_nominee(): void
    {
        [$event, $category, , $batch] = $this->scenario();
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'declined');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $nomination]);
        $data = ['revision' => $service->revision($event, $category, $nomination->id), 'reason' => 'Available again', 'confirm' => 1];
        $this->actingAs($admin)->post($url, $data)->assertForbidden();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->assertSame('open_registration', $nomination->actionableInvitation->status);
    }

    public function test_cancelled_unpaid_checkout_history_is_preserved_and_new_payer_can_register(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $event->update(['entryFee' => 450, 'status' => 'scheduled', 'start_date' => now()->addMonth(), 'deadline' => 1]);
        $category->update(['nominations_published' => true]);
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'sent');
        $payer = User::factory()->create();
        $invitations = app(\App\Services\InterprovincialTrials\InvitationService::class);
        $order = $invitations->acceptPublishedNomination($event, $category, $nomination, $payer);
        app(\App\Domain\Payments\Services\RegistrationPaymentService::class)->cancelPayment($order);
        $invitations->resetCancelledPayment($order->fresh(), $payer);
        $invitation = $nomination->actionableInvitation()->first();
        $invitation->update(['status' => 'declined']);
        $nomination->delete();
        // This same player's paid participation elsewhere is independent and unchanged.
        $otherCategory = CategoryEvent::factory()->create();
        $otherRegistration = \App\Models\Registration::create();
        $otherRegistration->players()->attach($nomination->player_id);
        \App\Models\CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $otherCategory->id, 'registration_id' => $otherRegistration->id, 'user_id' => $payer->id]);
        $otherOrder = \App\Models\RegistrationOrder::create(['user_id' => $payer->id, 'status' => 'completed', 'pay_status' => true, 'payfast_paid' => true, 'total_fee' => 320]);
        \App\Models\RegistrationOrderItems::forceCreate(['order_id' => $otherOrder->id, 'category_event_id' => $otherCategory->id, 'registration_id' => $otherRegistration->id, 'player_id' => $nomination->player_id, 'user_id' => $payer->id, 'item_price' => 320]);
        \App\Models\Transaction::forceCreate(['pf_payment_id' => 'PF-OTHER-EVENT-TEST', 'custom_int5' => $otherOrder->id, 'custom_int4' => $payer->id, 'amount_gross' => 320, 'amount_fee' => 12, 'amount_net' => 308]);
        $before = $this->retainedCheckoutRows();
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $nomination->id]);
        $page = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', $event))->assertOk();
        preg_match('/name="revision" value="([a-f0-9]{64})"/', $page->getContent(), $matches);
        $data = ['revision' => $matches[1], 'reason' => 'Available again', 'confirm' => 1];
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_nominations', ['id' => $nomination->id, 'player_id' => $nomination->player_id]);
        $this->assertSame($before, $this->retainedCheckoutRows());
        $this->post($url, $data)->assertSessionHasErrors('nomination');
        $this->assertSame($before, $this->retainedCheckoutRows());
        $nextPayer = User::factory()->create();
        $freshOrder = $invitations->acceptPublishedNomination($event, $category, EventNomination::findOrFail($nomination->id), $nextPayer);
        $this->assertNotSame($order->id, $freshOrder->id);
        $this->assertSame($nextPayer->id, $freshOrder->user_id);
        $this->assertEquals(450, $freshOrder->total_fee);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('declined', $invitation->fresh()->status);
        $this->assertSame(1, \App\Models\CategoryEventRegistration::where('category_event_id', $category->id)->count());
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_cancelled_history_rejects_stale_financial_review_and_retained_payfast_handoff(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $event->update(['entryFee' => 450, 'status' => 'scheduled', 'start_date' => now()->addMonth(), 'deadline' => 1]);
        $category->update(['nominations_published' => true]);
        $nomination = $this->nominee($event, $category, $batch, 'Returning', 'open_registration');
        $invitations = app(\App\Services\InterprovincialTrials\InvitationService::class);
        $payer = User::factory()->create();
        $order = $invitations->acceptPublishedNomination($event, $category, $nomination, $payer);
        app(\App\Domain\Payments\Services\RegistrationPaymentService::class)->cancelPayment($order);
        $invitations->resetCancelledPayment($order->fresh(), $payer);
        $nomination->actionableInvitation()->first()->update(['status' => 'declined']);
        $service = app(\App\Services\InterprovincialTrials\NominationReactivationService::class);
        $url = route('backend.interprovincial-trials.nominations.reactivate', [$event, $category, $nomination]);
        $data = ['revision' => $service->revision($event, $category, $nomination->id), 'reason' => 'Available again', 'confirm' => 1];
        DB::table('registration_orders')->where('id', $order->id)->update(['total_fee' => 451]);
        $before = $this->retainedCheckoutRows();
        $this->actingAs($actor)->post($url, $data)->assertRedirect()->assertSessionHasErrors('nomination');
        $this->assertSame($before, $this->retainedCheckoutRows());
        DB::table('registration_orders')->where('id', $order->id)->update(['total_fee' => 450]);
        activity('payment')->performedOn($order)->withProperties(['handed_off_at' => now()->subHour()->toIso8601String()])
            ->log('Expired unresolved PayFast handoff retained for reconciliation');
        $data['revision'] = $service->revision($event, $category, $nomination->id);
        $before = $this->retainedCheckoutRows();
        $this->post($url, $data)->assertRedirect()->assertSessionHasErrors('nomination');
        $this->assertSame($before, $this->retainedCheckoutRows());
        $this->assertSame('declined', $nomination->actionableInvitation->status);
    }
    private function retainedCheckoutRows(): array
    {
        $rows = [];
        foreach (['registration_orders', 'registration_order_items', 'category_event_registrations', 'registrations', 'player_registrations', 'wallet_transactions', 'transactions_pf'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        return $rows;
    }
    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $type = EventType::forceCreate(['name' => 'History trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id, 'published' => true, 'signUp' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a',64), 'created_by_user_id' => $actor->id]);
        return [$event, $category, $actor, $batch];
    }

    private function nominee($event, $category, $batch, $name, $status): EventNomination
    {
        $player = Player::factory()->create(['name' => $name, 'surname' => 'Candidate']);
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
        InterprovincialTrialInvitation::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'batch_id' => $batch->id,
            'nomination_id' => $nomination->id, 'player_id' => $player->id, 'status' => $status]);
        return $nomination;
    }

    private function deleted($event, $category, $id, $name, array $overrides = []): void
    {
        $auditId = DB::table('audit_events')->insertGetId(['event_uuid' => (string) \Illuminate\Support\Str::ulid(), 'occurred_at' => now(), 'created_at' => now(),
            'category' => 'data', 'action' => 'event-nomination.deleted', 'outcome' => 'succeeded', 'source' => 'web',
            'subject_type' => EventNomination::class, 'subject_id' => (string) $id, 'event_id' => $event->id,
            'before' => json_encode(array_replace(['id' => $id, 'event_id' => $event->id, 'category_event_id' => $category->id,
                'player_id' => null, 'nominee_name' => $name, 'nominee_surname' => 'Candidate'], $overrides)), 'integrity_hash' => str_repeat('0',64)]);
        $payload = (array) DB::table('audit_events')->find($auditId);
        DB::table('audit_events')->where('id', $auditId)->update(['integrity_hash' => \App\Support\Audit\AuditIntegrity::hash($payload)]);
    }
}
