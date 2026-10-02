<?php
namespace Tests\Feature;
use App\Models\{Event, EventType, CategoryEvent, Player, User, Registration, RegistrationOrder, RegistrationOrderItems, EventNomination, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, RegistrationManualReceipt, CategoryEventRegistration};
use App\Services\InterprovincialTrials\ManualCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class TrialManualCollectionTest extends TestCase {
    use RefreshDatabase;
    private User $admin; private User $payer; private RegistrationOrder $order; private Event $event; private CategoryEventRegistration $entry; private InterprovincialTrialInvitation $invitation;
    protected function setUp(): void {
        parent::setUp();
        Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);
        $this->admin=User::factory()->create()->assignRole('admin'); $this->payer=User::factory()->create();
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Trials','type'=>EventType::INDIVIDUAL,'code'=>EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $this->event=Event::factory()->create(['eventType'=>$type,'cape_tennis_fee'=>10]);
        DB::table('event_admins')->insert(['event_id'=>$this->event->id,'user_id'=>$this->admin->id]);
        $category=CategoryEvent::factory()->create(['event_id'=>$this->event->id]); $player=Player::factory()->create();
        $registration=Registration::factory()->create(); $registration->players()->attach($player->id);
        $this->order=RegistrationOrder::create(['user_id'=>$this->payer->id,'total_fee'=>123.45,'wallet_reserved'=>0,'payfast_amount_due'=>123.45,'pay_status'=>0,'status'=>'pending']);
        RegistrationOrderItems::forceCreate(['order_id'=>$this->order->id,'registration_id'=>$registration->id,'category_event_id'=>$category->id,'player_id'=>$player->id,'user_id'=>$this->payer->id,'item_price'=>123.45]);
        $this->entry=CategoryEventRegistration::factory()->create(['registration_id'=>$registration->id,'category_event_id'=>$category->id,'user_id'=>$this->payer->id,'payment_status_id'=>null]);
        $nomination=EventNomination::create(['event_id'=>$this->event->id,'category_event_id'=>$category->id,'player_id'=>$player->id]);
        $batch=InterprovincialTrialInvitationBatch::create(['event_id'=>$this->event->id,'status'=>'draft','snapshot_hash'=>str_repeat('a',64),'created_by_user_id'=>$this->admin->id]);
        $this->invitation=InterprovincialTrialInvitation::create(['event_id'=>$this->event->id,'category_event_id'=>$category->id,'nomination_id'=>$nomination->id,'batch_id'=>$batch->id,'player_id'=>$player->id,'registration_id'=>$registration->id,'order_id'=>$this->order->id,'status'=>'accepted_pending_payment']);
    }
    public function test_manual_collection_is_exact_idempotent_and_provider_neutral(): void {
        $service=app(ManualCollectionService::class); $receipt=$service->markPaid($this->order,$this->admin,'BANK-123');
        $repeat=$service->markPaid($this->order,$this->admin,'BANK-123');
        $this->assertSame($receipt->id,$repeat->id); $this->assertSame('123.45',$receipt->amount);
        $this->assertDatabaseCount('registration_manual_receipts',1); $this->assertDatabaseCount('transactions_pf',0); $this->assertDatabaseCount('wallet_transactions',0);
        $this->assertTrue($this->order->fresh()->pay_status); $this->assertFalse($this->order->fresh()->payfast_paid); $this->assertFalse($this->order->fresh()->wallet_debited);
        $this->assertSame('paid_confirmed',$this->invitation->fresh()->status);
        $this->assertSame(123.45,$this->entry->fresh()->paymentInfo()['total_paid']);
        $this->assertSame(123.45,$this->entry->fresh()->maxRefundableAmount());
        $ledger=app(\App\Domain\Finance\Services\FinancialLedgerService::class)->buildForEvent($this->event);
        $this->assertSame(123.45,$ledger['totals']['gross_payments']);
        $this->assertSame(113.45,$ledger['totals']['net_revenue']);
    }
    public function test_eft_upload_does_not_mark_paid_and_requires_payer_then_admin_verification(): void {
        Storage::fake('local'); $service=app(ManualCollectionService::class);
        \App\Models\TrialProgramme::create(['event_id'=>$this->event->id,'bank_details'=>'Example regional account']);
        $proof=$service->uploadProof($this->order,$this->payer,UploadedFile::fake()->create('proof.pdf',20,'application/pdf'));
        Storage::disk('local')->assertExists($proof->path); $this->assertFalse($this->order->fresh()->pay_status); $this->assertArrayNotHasKey('path',$proof->toArray());
        $receipt=$service->verifyProof($proof,$this->admin,'BANK-321');
        $this->assertSame('eft',$receipt->method); $this->assertSame('verified',$proof->fresh()->status);
    }
    public function test_registration_proof_identity_is_immutable_but_review_lifecycle_remains_supported(): void {
        Storage::fake('local');$service=app(ManualCollectionService::class);
        \App\Models\TrialProgramme::create(['event_id'=>$this->event->id,'bank_details'=>'Example regional account']);
        $proof=$service->uploadProof($this->order,$this->payer,UploadedFile::fake()->create('proof.pdf',20,'application/pdf'));
        foreach ([['order_id'=>$this->order->id+1],['event_id'=>$this->event->id+1],['payer_id'=>$this->payer->id+1],['path'=>'retargeted.pdf']] as $changes) {
            try {$proof->fresh()->update($changes);$this->fail('Proof audit evidence changed.');}catch(\RuntimeException $e){$this->assertStringContainsString('immutable',$e->getMessage());}
        }
        $originalProofUpdatedAt=$proof->updated_at;$this->travel(1)->minute();
        $service->rejectProof($proof,$this->admin,'Payment could not be verified.');
        $this->assertSame('rejected',$proof->fresh()->status);
        $this->assertTrue($proof->fresh()->updated_at->gt($originalProofUpdatedAt));
        try {$proof->fresh()->update(['reviewed_by_user_id'=>User::factory()->create()->id]);$this->fail('Completed review audit changed.');}catch(\RuntimeException $e){$this->assertStringContainsString('immutable',$e->getMessage());}
        try {$proof->fresh()->delete();$this->fail('Proof audit evidence deleted.');}catch(\RuntimeException $e){$this->assertStringContainsString('retained',$e->getMessage());}
        $this->assertDatabaseCount('trial_payment_proofs',1);$this->assertDatabaseCount('registration_manual_receipts',0);
        $this->assertFalse((bool)$this->order->fresh()->pay_status);$this->assertDatabaseCount('wallet_transactions',0);$this->assertDatabaseCount('transactions_pf',0);
    }
    public function test_unassigned_admin_cannot_settle_another_regions_order(): void {
        $stranger=User::factory()->create()->assignRole('admin');
        try { app(ManualCollectionService::class)->markPaid($this->order,$stranger,'BANK-123'); $this->fail('Expected denial'); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $this->assertDatabaseCount('registration_manual_receipts',0); $this->assertFalse($this->order->fresh()->pay_status);
    }
    public function test_reserved_unresolved_and_mismatched_orders_are_rejected(): void {
        foreach ([['wallet_reserved'=>1],['wallet_reserved'=>0,'payfast_handed_off_at'=>now()],['payfast_handed_off_at'=>null,'total_fee'=>99]] as $change) {
            $this->order->update($change);
            try { app(ManualCollectionService::class)->markPaid($this->order,$this->admin,'BANK-123'); $this->fail('Expected rejection'); } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('payment',$e->errors()); }
        }
        $this->assertDatabaseCount('registration_manual_receipts',0); $this->assertFalse($this->order->fresh()->pay_status);
    }
    public function test_proof_access_is_payer_or_assigned_manager_only(): void {
        Storage::fake('local');
        \App\Models\TrialProgramme::create(['event_id'=>$this->event->id,'bank_details'=>'Example regional account']);
        $service=app(ManualCollectionService::class);
        $stranger=User::factory()->create();
        try { $service->uploadProof($this->order,$stranger,UploadedFile::fake()->create('proof.pdf',20,'application/pdf')); $this->fail('Expected denial'); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $proof=$service->uploadProof($this->order,$this->payer,UploadedFile::fake()->create('proof.pdf',20,'application/pdf'));
        $service->authorizeProof($proof,$this->payer); $service->authorizeProof($proof,$this->admin);
        try { $service->authorizeProof($proof,$stranger); $this->fail('Expected denial'); } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $this->assertDatabaseCount('trial_payment_proofs',1);
    }
    public function test_malformed_tuple_rolls_back_receipt_and_paid_state(): void {
        EventNomination::where('id',$this->invitation->nomination_id)->delete();
        try { app(ManualCollectionService::class)->markPaid($this->order,$this->admin,'BANK-123'); $this->fail('Expected rejection'); } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('payment',$e->errors()); }
        $this->assertDatabaseCount('registration_manual_receipts',0); $this->assertFalse($this->order->fresh()->pay_status); $this->assertFalse($this->entry->fresh()->is_paid);
    }
    public function test_prior_provider_evidence_refuses_manual_collection(): void {
        $this->order->update(['payfast_pf_payment_id'=>'provider-existing']);
        try { app(ManualCollectionService::class)->markPaid($this->order,$this->admin,'BANK-123'); $this->fail('Expected rejection'); } catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('payment',$e->errors()); }
        $this->assertDatabaseCount('registration_manual_receipts',0);
    }
    public function test_assigned_region_manager_can_receive_manual_payment(): void {
        $manager=User::factory()->create();
        $region=DB::table('event_regions')->insertGetId(['event_id'=>$this->event->id,'region_id'=>1]);
        DB::table('event_region_managers')->insert(['event_id'=>$this->event->id,'region_id'=>1,'event_region_id'=>$region,'user_id'=>$manager->id]);
        $receipt=app(ManualCollectionService::class)->markPaid($this->order,$manager,'BANK-REGION');
        $this->assertSame($manager->id,$receipt->verified_by_user_id);
        $this->assertTrue($this->entry->fresh()->is_paid);
    }
    public function test_scoped_manual_refund_requires_withdrawal_then_preserves_receipt_and_original_paid_state(): void {
        $this->event->update(['withdrawal_deadline'=>now()->addDay()]);
        app(ManualCollectionService::class)->markPaid($this->order,$this->admin,'BANK-REFUND');
        $requests=app(\App\Domain\Finance\Services\RefundRequestService::class);
        try { $requests->requestTrialManualRefund($this->entry->fresh(),$this->admin); $this->fail('Active entries cannot request refunds'); }
        catch(\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('refund',$e->errors()); }
        $this->entry->update(['status'=>'withdrawn','withdrawn_at'=>now()]);
        $pending=$requests->requestTrialManualRefund($this->entry->fresh(),$this->admin);
        $this->assertSame(123.45,(float)$pending->refund_gross); $this->assertSame(111.10,(float)$pending->refund_net);
        $repeat=$requests->requestTrialManualRefund($this->entry->fresh(),$this->admin); $this->assertSame($pending->id,$repeat->id);
        \Illuminate\Support\Facades\Event::fake([\App\Events\RefundCompleted::class]);
        $execution=app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        $execution->completeTrialManualRefund($pending,$this->admin,'REFUND-123');
        $execution->completeTrialManualRefund($pending,$this->admin,'REFUND-123');
        $this->assertSame('completed',$this->entry->fresh()->refund_status); $this->assertTrue($this->entry->fresh()->is_paid);
        $this->assertTrue($this->order->fresh()->pay_status); $this->assertDatabaseCount('registration_manual_receipts',1);
        $this->assertDatabaseCount('transactions_pf',0); $this->assertDatabaseCount('wallet_transactions',0);
        \Illuminate\Support\Facades\Event::assertDispatchedTimes(\App\Events\RefundCompleted::class,1);
    }
    public function test_unassigned_admin_cannot_request_or_complete_trial_manual_refund(): void {
        $outsider=User::factory()->create()->assignRole('admin');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTrialManualRefund($this->entry,$outsider);
    }
}

