<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\CategoryAdjustmentInvitationService;
use App\Http\Controllers\Frontend\RegistrationPaymentController;
use App\Models\CategoryAdjustment;
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, MastersInvitation, MastersInvitationBatch, Player, Registration, RegistrationOrder, RegistrationOrderItems, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryAdjustmentInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $code = 'interprovincial-trials'): array
    {
        $payer = User::factory()->create();
        $type = DB::table('eventtypes')->insertGetId(['name' => $code, 'type' => EventType::INDIVIDUAL, 'code' => $code]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true, 'signUp' => true, 'start_date' => now()->addMonth(), 'deadline' => 1]);
        $source = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true]);
        $target = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true]);
        $player = Player::factory()->create();
        $registration = Registration::create([]);
        $registration->players()->sync([$player->id]);
        $entry = CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $source->id, 'registration_id' => $registration->id, 'user_id' => $payer->id]);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 500, 'pay_status' => true, 'payfast_paid' => true, 'payfast_amount_due' => 500, 'payfast_pf_payment_id' => 'original-receipt']);
        $item = new RegistrationOrderItems;
        $item->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id, 'player_id' => $player->id, 'category_event_id' => $source->id, 'user_id' => $payer->id, 'item_price' => 500])->save();
        $service = app(CategoryAdjustmentInvitationService::class);
        return compact('payer', 'event', 'source', 'target', 'player', 'entry', 'order', 'item', 'service');
    }

    private function trialInvitation(array $f): InterprovincialTrialInvitation
    {
        $nomination = EventNomination::create(['event_id' => $f['event']->id, 'category_event_id' => $f['source']->id, 'player_id' => $f['player']->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $f['event']->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $f['payer']->id]);
        return InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $f['event']->id, 'category_event_id' => $f['source']->id, 'nomination_id' => $nomination->id, 'player_id' => $f['player']->id, 'registration_id' => $f['entry']->registration_id, 'order_id' => $f['order']->id, 'status' => 'paid_confirmed', 'paid_at' => now()->subDay()]);
    }

    private function mastersBatch(array $f): MastersInvitationBatch
    {
        return MastersInvitationBatch::create(['event_id' => $f['event']->id, 'series_id' => 99, 'ranking_run_id' => 'test-run', 'created_by' => $f['payer']->id, 'status' => 'sent', 'public_list_published' => true]);
    }

    public function test_trials_requires_an_exact_published_destination_nomination(): void
    {
        $f = $this->fixture();
        $this->trialInvitation($f);
        $this->expectException(ValidationException::class);
        $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
    }

    public function test_trials_does_not_allow_a_nomination_from_another_category(): void
    {
        $f = $this->fixture();
        $this->trialInvitation($f);
        EventNomination::create(['event_id' => $f['event']->id, 'category_event_id' => CategoryEvent::factory()->create(['event_id' => $f['event']->id])->id, 'player_id' => $f['player']->id]);
        $this->expectException(ValidationException::class);
        $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
    }

    public function test_trials_transfers_participation_without_changing_nominees_or_original_receipt(): void
    {
        $f = $this->fixture();
        $sourceInvitation = $this->trialInvitation($f);
        $nomination = EventNomination::create(['event_id' => $f['event']->id, 'category_event_id' => $f['target']->id, 'player_id' => $f['player']->id]);
        $originalOrder = $f['order']->fresh()->getRawOriginal();
        $originalItem = $f['item']->fresh()->getRawOriginal();
        DB::transaction(function () use ($f) {
            $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
            $f['entry']->update(['category_event_id' => $f['target']->id]);
            $f['service']->apply($f['entry'], $f['source']->id, $f['target'], $f['payer']);
        });
        $destination = InterprovincialTrialInvitation::where('registration_id', $f['entry']->registration_id)->sole();
        $this->assertSame($nomination->id, $destination->nomination_id);
        $this->assertSame($f['target']->id, $destination->category_event_id);
        $this->assertSame($f['order']->id, $destination->order_id);
        $this->assertSame('paid_confirmed', $destination->status);
        $this->assertNull($sourceInvitation->fresh()->registration_id);
        $this->assertSame('withdrawn', $sourceInvitation->fresh()->status);
        $this->assertSame($originalOrder, $f['order']->fresh()->getRawOriginal());
        $this->assertSame($originalItem, $f['item']->fresh()->getRawOriginal());
        $this->assertDatabaseCount('event_nominations', 2);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_trials_admin_decline_cannot_be_bypassed_by_a_category_move(): void
    {
        $f = $this->fixture();
        $source = $this->trialInvitation($f);
        $nomination = EventNomination::create(['event_id' => $f['event']->id, 'category_event_id' => $f['target']->id, 'player_id' => $f['player']->id]);
        InterprovincialTrialInvitation::create(['batch_id' => $source->batch_id, 'event_id' => $f['event']->id, 'category_event_id' => $f['target']->id, 'nomination_id' => $nomination->id, 'player_id' => $f['player']->id, 'status' => 'admin_declined']);
        $this->expectException(ValidationException::class);
        $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
    }

    public function test_trials_rejects_an_older_destination_checkout_hidden_by_a_new_invitation(): void
    {
        $f = $this->fixture();
        $source = $this->trialInvitation($f);
        $nomination = EventNomination::create(['event_id' => $f['event']->id, 'category_event_id' => $f['target']->id, 'player_id' => $f['player']->id]);
        InterprovincialTrialInvitation::create(['batch_id' => $source->batch_id, 'event_id' => $f['event']->id, 'category_event_id' => $f['target']->id, 'nomination_id' => $nomination->id, 'player_id' => $f['player']->id, 'status' => 'accepted_pending_payment']);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $f['event']->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('b', 64), 'created_by_user_id' => $f['payer']->id]);
        InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $f['event']->id, 'category_event_id' => $f['target']->id, 'nomination_id' => $nomination->id, 'player_id' => $f['player']->id, 'status' => 'sent']);
        $this->expectException(ValidationException::class);
        $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
    }

    public function test_masters_reserve_cannot_promote_itself_through_category_change(): void
    {
        $f = $this->fixture('masters');
        $sourceBatch = $this->mastersBatch($f);
        MastersInvitation::create(['event_id' => $f['event']->id, 'batch_id' => $sourceBatch->id, 'category_event_id' => $f['source']->id, 'player_id' => $f['player']->id, 'ranking_position' => 1, 'queue_position' => 1, 'registration_id' => $f['entry']->registration_id, 'order_id' => $f['order']->id, 'status' => 'paid_confirmed']);
        $targetBatch = $this->mastersBatch($f);
        MastersInvitation::create(['event_id' => $f['event']->id, 'batch_id' => $targetBatch->id, 'category_event_id' => $f['target']->id, 'player_id' => $f['player']->id, 'ranking_position' => 12, 'queue_position' => 12, 'status' => 'reserve']);
        $this->expectException(ValidationException::class);
        $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
    }

    public function test_masters_requires_current_invitation_and_preserves_single_paid_participation(): void
    {
        $f = $this->fixture('masters');
        $source = MastersInvitation::create(['event_id' => $f['event']->id, 'batch_id' => $this->mastersBatch($f)->id, 'category_event_id' => $f['source']->id, 'player_id' => $f['player']->id, 'ranking_position' => 1, 'queue_position' => 1, 'registration_id' => $f['entry']->registration_id, 'order_id' => $f['order']->id, 'status' => 'paid_confirmed']);
        $destination = MastersInvitation::create(['event_id' => $f['event']->id, 'batch_id' => $this->mastersBatch($f)->id, 'category_event_id' => $f['target']->id, 'player_id' => $f['player']->id, 'ranking_position' => 2, 'queue_position' => 2, 'status' => 'invited']);
        DB::transaction(function () use ($f) {
            $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
            $f['entry']->update(['category_event_id' => $f['target']->id]);
            $f['service']->apply($f['entry'], $f['source']->id, $f['target'], $f['payer']);
        });
        $this->assertSame('superseded', $source->fresh()->status);
        $this->assertSame('paid_confirmed', $destination->fresh()->status);
        $this->assertSame($f['entry']->registration_id, $destination->fresh()->registration_id);
        $this->assertSame($f['order']->id, $destination->fresh()->order_id);
        $this->assertSame($f['source']->id, $f['item']->fresh()->category_event_id);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_original_trials_callback_can_replay_after_an_audited_move(): void
    {
        $f = $this->fixture();
        $this->trialInvitation($f);
        EventNomination::create(['event_id' => $f['event']->id, 'category_event_id' => $f['target']->id, 'player_id' => $f['player']->id]);
        $this->moveAndRecordProof($f);
        $this->assertOriginalReceiptReplay($f);
    }

    public function test_original_masters_callback_can_replay_after_an_audited_move(): void
    {
        $f = $this->fixture('masters');
        MastersInvitation::create(['event_id' => $f['event']->id, 'batch_id' => $this->mastersBatch($f)->id, 'category_event_id' => $f['source']->id, 'player_id' => $f['player']->id, 'ranking_position' => 1, 'queue_position' => 1, 'registration_id' => $f['entry']->registration_id, 'order_id' => $f['order']->id, 'status' => 'paid_confirmed']);
        MastersInvitation::create(['event_id' => $f['event']->id, 'batch_id' => $this->mastersBatch($f)->id, 'category_event_id' => $f['target']->id, 'player_id' => $f['player']->id, 'ranking_position' => 2, 'queue_position' => 2, 'status' => 'invited']);
        $this->moveAndRecordProof($f);
        $this->assertOriginalReceiptReplay($f);
    }

    private function moveAndRecordProof(array $f): void
    {
        DB::transaction(function () use ($f) {
            $f['service']->assertEligible($f['entry'], $f['target'], $f['payer']);
            $f['entry']->update(['category_event_id' => $f['target']->id]);
            $f['service']->apply($f['entry'], $f['source']->id, $f['target'], $f['payer']);
            CategoryAdjustment::create([
                'entry_id' => $f['entry']->id, 'event_id' => $f['event']->id, 'player_id' => $f['player']->id,
                'user_id' => $f['payer']->id, 'actor_id' => $f['payer']->id, 'source_category_id' => $f['source']->id,
                'target_category_id' => $f['target']->id, 'original_order_item_id' => $f['item']->id,
                'request_token' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'completed',
                'payment_before' => ['total_paid' => 500], 'payment_after' => ['total_paid' => 500],
                'target_fee' => 500, 'difference' => 0, 'expires_at' => now()->addMinutes(5), 'applied_at' => now(),
            ]);
        });
    }

    private function assertOriginalReceiptReplay(array $f): void
    {
        $data = ['custom_int1' => $f['source']->id, 'custom_int2' => $f['player']->id,
            'custom_int3' => $f['event']->id, 'custom_int4' => $f['payer']->id,
            'custom_int5' => $f['order']->id, 'custom_str5' => '',
            'pf_payment_id' => 'original-receipt', 'payment_status' => 'COMPLETE', 'amount_gross' => '500.00'];
        $controller = app(RegistrationPaymentController::class);
        $this->assertTrue($controller->handlePayfastSuccess($data));
        $this->assertTrue($controller->handlePayfastSuccess($data));
        $this->assertSame($f['target']->id, $f['entry']->fresh()->category_event_id);
        $this->assertSame($f['source']->id, $f['item']->fresh()->category_event_id);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $f['entry']->fresh()->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $invitationClass = $f['event']->isMasters() ? MastersInvitation::class : InterprovincialTrialInvitation::class;
        $invitationClass::where('registration_id', $f['entry']->registration_id)->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->assertTrue($controller->handlePayfastSuccess($data));
        $this->assertSame('withdrawn', $f['entry']->fresh()->status);
        $this->assertSame('withdrawn', $invitationClass::where('registration_id', $f['entry']->registration_id)->sole()->status);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
    }
}
