<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\{CategoryEvent, Event, EventNomination, EventType, Player, Team, TeamPaymentOrder, User};
use App\Services\EventCommunicationService;
use App\Support\EventCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFormatCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private function event(int $id = 1, int $family = 1, string $code = 'individual'): Event
    {
        DB::table('eventtypes')->updateOrInsert(['id' => $id], ['name' => 'Synthetic format '.$id, 'type' => $family, 'code' => $code]);
        return Event::factory()->create(['eventType' => $id, 'name' => 'Synthetic Masters wording', 'published' => false]);
    }

    private function admin(Event $event): User
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('super-user', 'web');
        $actor = User::factory()->create()->assignRole('super-user');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        Bus::fake(); Mail::fake();
        return $actor;
    }

    public function test_workflow_capabilities_do_not_follow_individual_family_alone(): void
    {
        $individual = $this->event();
        $cavaliers = $this->event(5);
        $masters = $this->event(14, 1, 'masters');
        $trials = $this->event(203, 2, 'interprovincial-trials');
        $camp = $this->event(202, 3, 'camp');
        $team = $this->event(3, 2, 'team');
        $legacy = $this->event(13);
        $this->assertFalse(EventCapabilities::nominations($individual));
        $this->assertFalse(EventCapabilities::invitations($individual));
        foreach ([$cavaliers, $trials, $team] as $event) $this->assertTrue(EventCapabilities::nominations($event));
        foreach ([$masters, $camp] as $event) {
            $this->assertFalse(EventCapabilities::nominations($event));
            $this->assertTrue(EventCapabilities::invitations($event));
        }
        $this->assertFalse(EventCapabilities::clothing($trials));
        $this->assertTrue(EventCapabilities::teamPayments($trials));
        $this->assertTrue(EventCapabilities::clothing($legacy));
    }

    public function test_individual_rejects_nomination_actions_and_excludes_stray_nominees_from_mail(): void
    {
        $event = $this->event(); $actor = $this->admin($event);
        $division = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create(['userId' => null]);
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $division->id, 'player_id' => $player->id]);
        $this->actingAs($actor)->postJson(route('nominate.store'), ['event_id' => $event->id, 'category_event_id' => $division->id, 'players' => [$player->id]])->assertNotFound();
        $this->postJson(route('nomination.publish.toggle', $division->id), ['published' => true])->assertNotFound();
        $this->postJson(route('backend.nomination.remove'), ['nomination_id' => $nomination->id])->assertNotFound();
        $this->get(route('nomination.category.players', $division->id))->assertNotFound();
        $this->get(route('backend.event-communications.index', $event))->assertOk()->assertDontSee('Nominated players')->assertDontSee('Invitations only');
        $this->get(route('backend.event-communications.index', ['event' => $event, 'report_scope' => 'invitations']))->assertStatus(422);
        $this->get(route('backend.event-communications.index', ['event' => $event, 'followup_nomination_id' => $nomination->id]))->assertStatus(422);
        $this->postJson(route('backend.event-communications.preview', $event), ['scope' => 'nominations', 'filter' => 'all', 'recipients' => 'players', 'subject' => 'Synthetic', 'body' => 'Synthetic'])->assertStatus(422);
        $service = app(EventCommunicationService::class);
        $this->assertCount(0, $service->entries($event, $actor));
        $this->assertCount(0, $service->individualOptions($event, $actor, $player->name));
        $this->assertDatabaseCount('event_nominations', 1);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertFalse((bool) $division->fresh()->nominations_published);
        Mail::assertNothingSent();
    }

    public function test_format_changes_are_setup_only_and_keep_existing_history(): void
    {
        $event = $this->event(); $target = $this->event(3, 2, 'team');
        $event->load('eventTypeModel');
        $event->update(['eventType' => $target->eventType]);
        $this->assertTrue($event->isTeam());
        $division = CategoryEvent::factory()->create(['event_id' => $event->id]);
        EventNomination::create(['event_id' => $event->id, 'category_event_id' => $division->id, 'player_id' => Player::factory()->create()->id]);
        try { $event->update(['eventType' => 1]); $this->fail('Changed an event with nominations'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('eventType', $exception->errors()); }
        $this->assertSame((int) $target->eventType, (int) $event->fresh()->eventType);
        $this->assertDatabaseCount('event_nominations', 1);
    }

    public function test_individual_finance_hides_team_tools_and_edit_hides_masters_card(): void
    {
        $event = $this->event(); $actor = $this->admin($event);
        $this->actingAs($actor)->get(route('admin.events.finances', $event))->assertOk()->assertDontSee('Regional clothing income and reconciliation')->assertDontSee('Review late team payments');
        $this->get(route('admin.events.finances', ['event' => $event, 'tab' => 'clothing']))->assertNotFound();
        $this->get(route('backend.late-team-payments.index', $event))->assertNotFound();
        $this->get(route('backend.events.edit', $event))->assertOk()->assertDontSee('Masters readiness');
    }

    public function test_team_refunds_include_exact_completed_and_pending_values_once_without_mutation(): void
    {
        $event = $this->event(3, 2, 'team');
        $division = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $division->id]);
        $payer = User::factory()->create(); $player = Player::factory()->create(); $beneficiary = Player::factory()->create();
        $base = ['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id, 'user_id' => $payer->id,
            'pay_status' => true, 'total_amount' => 200, 'refund_gross' => 123.45, 'refund_fee' => 3.45, 'refund_net' => 120,
            'refund_method' => 'bank', 'withdrawn_at' => now()];
        $completed = TeamPaymentOrder::create($base + ['refund_status' => 'completed']);
        $completed->forceFill(['beneficiary_player_id' => $beneficiary->id])->save();
        $pending = TeamPaymentOrder::create(array_replace($base, ['refund_status' => 'pending', 'refund_method' => 'wallet', 'refund_net' => 123.45, 'withdrawn_at' => null]));
        TeamPaymentOrder::create(array_replace($base, ['refund_status' => 'not_refunded', 'refund_gross' => 0, 'refund_net' => 0]));
        $other = $this->event(1);
        TeamPaymentOrder::create(array_replace($base, ['event_id' => $other->id, 'refund_status' => 'completed']));
        $before = TeamPaymentOrder::orderBy('id')->get()->toArray();
        $ledger = app(FinancialLedgerService::class);
        $rows = $ledger->buildRefundRows($event, 10);
        $this->assertCount(3, $rows);
        $this->assertSame($beneficiary->full_name, $rows->firstWhere('source_order_id', $completed->id)->player);
        $this->assertNull($rows->firstWhere('source_order_id', $pending->id)->withdrawal_status);
        $totals = $ledger->buildTotals(collect(), $rows, collect());
        $this->assertSame(123.45, $totals['completed_refunds']);
        $this->assertSame(123.45, $totals['pending_refunds']);
        $this->assertSame(-120.0, $totals['registration_net']);
        $this->assertSame(1, $totals['no_refund_count']);
        $this->assertEquals($rows->toArray(), $ledger->buildRefundRows($event, 10)->toArray());
        $this->assertSame($before, TeamPaymentOrder::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
