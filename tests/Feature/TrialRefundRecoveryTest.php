<?php
namespace Tests\Feature;
use App\Models\{Event,EventType,CategoryEvent,Player,User,TrialProgramme,TrialRankingRun,TrialSquadDraft,TrialSquadSlot,TrialParticipation};
use App\Services\InterprovincialTrials\TrialParticipationService;
use App\Domain\Payments\Services\TeamPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage,Event as Events};
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class TrialRefundRecoveryTest extends TestCase {
    use RefreshDatabase;
    private Event $event; private User $admin;private User $payer;private TrialSquadSlot $slot;
    protected function setUp():void {
        parent::setUp();Role::firstOrCreate(['name'=>'admin','guard_name'=>'web']);$this->admin=User::factory()->create()->assignRole('admin');$this->payer=User::factory()->create();
        $type=DB::table('eventtypes')->insertGetId(['name'=>'Trials','type'=>EventType::INDIVIDUAL,'code'=>EventType::INTERPROVINCIAL_TRIALS_CODE]);$this->event=Event::factory()->create(['eventType'=>$type,'published'=>true]);
        DB::table('event_admins')->insert(['event_id'=>$this->event->id,'user_id'=>$this->admin->id]);
        $category=CategoryEvent::factory()->create(['event_id'=>$this->event->id]);$player=Player::factory()->create();
        TrialProgramme::create(['event_id'=>$this->event->id,'participation_fee'=>456.78,'bank_details'=>'Regional bank account']);
        $run=TrialRankingRun::create(['event_id'=>$this->event->id,'signature'=>str_repeat('a',64),'positions'=>[]]);
        $draft=TrialSquadDraft::create(['event_id'=>$this->event->id,'ranking_run_id'=>$run->id,'tiers'=>['A','B'],'created_by'=>$this->admin->id,'status'=>'finalised','finalised_at'=>now()]);
        $this->slot=TrialSquadSlot::create(['draft_id'=>$draft->id,'category_event_id'=>$category->id,'tier'=>'B','slot'=>1,'player_id'=>$player->id]);
    }
    private function pendingRefund(string $status='uncertain'): TrialParticipation {
        Events::fake([\App\Events\PaymentCompleted::class,\App\Events\RefundCompleted::class]);
        TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->addWeek()]);
        $service=app(TrialParticipationService::class);$p=$service->begin($this->slot,$this->payer);
        app(TeamPaymentService::class)->finalizePayment($p->order,['payment_method'=>'payfast','payfast_amount_received'=>456.78,'payfast_amount_due'=>456.78,'pf_payment_id'=>'PF-TEST']);
        $service->withdraw($p,$this->admin);app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTrialTeamRefund($p,$this->admin,'payfast');
        \App\Models\TrialProviderRefundAttempt::create(['order_id'=>$p->order_id,'pf_payment_id'=>'PF-TEST','amount'=>411.10,'requested_by'=>$this->admin->id,'status'=>$status]);
        $this->mock(\App\Services\Payfast::class,fn($m)=>$m->shouldNotReceive('refundUsingAvailableMethod'));
        DB::commit();\Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;return $p;
    }
    private function evidence(): array {return ['pf_payment_id'=>'PF-TEST','amount'=>'411.10','reference'=>'PF-REFUND-123','reason'=>'Verified provider refund history and bank settlement.','confirmed_at'=>now()->toDateTimeString(),'externally_confirmed'=>true];}
    public function test_uncertain_reconciliation_requires_exact_evidence_and_never_redispatches():void {
        $p=$this->pendingRefund();$service=app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        foreach (['pf_payment_id'=>'PF-WRONG','amount'=>'411.11'] as $field=>$value) {
            try {$service->recoverTrialTeamPayfastRefund($p,$this->admin,array_replace($this->evidence(),[$field=>$value]));$this->fail('Wrong evidence accepted');}
            catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
        }
        $this->assertDatabaseHas('trial_provider_refund_attempts',['order_id'=>$p->order_id,'status'=>'uncertain']);
        $service->recoverTrialTeamPayfastRefund($p,$this->admin,$this->evidence());$service->recoverTrialTeamPayfastRefund($p,$this->admin);
        $this->assertSame('completed',$p->order->fresh()->refund_status);$this->assertTrue((bool)$p->order->fresh()->pay_status);$this->assertDatabaseCount('trial_provider_refund_attempts',1);
    }
    public function test_confirmed_retry_is_local_only_and_uncertain_retry_is_blocked():void {
        $p=$this->pendingRefund();$service=app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        try {$service->recoverTrialTeamPayfastRefund($p,$this->admin);$this->fail('Uncertain retry accepted');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
        \App\Models\TrialProviderRefundAttempt::where('order_id',$p->order_id)->update(['status'=>'confirmed','confirmed_at'=>now()]);
        $service->recoverTrialTeamPayfastRefund($p,$this->admin);$this->assertSame('completed',$p->order->fresh()->refund_status);
    }
    public function test_payer_and_unassigned_admin_cannot_reconcile_or_view_attempts():void {
        $p=$this->pendingRefund();$other=User::factory()->create()->assignRole('admin');
        foreach ([$this->payer,$other] as $actor) {
            try {app(\App\Domain\Refunds\Services\RefundExecutionService::class)->recoverTrialTeamPayfastRefund($p,$actor,$this->evidence());$this->fail('Unauthorized reconciliation');}
            catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        }
        $this->assertSame('pending',$p->order->fresh()->refund_status);
    }
    public function test_recovery_page_is_scoped_and_paginated():void {
        $this->actingAs($this->admin)->get(route('backend.interprovincial-trials.refund-recovery.index',$this->event))->assertOk()->assertSee('PayFast refund recovery');
        $other=User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->get(route('backend.interprovincial-trials.refund-recovery.index',$this->event))->assertForbidden();
        $p=app(TrialParticipationService::class)->begin($this->slot,$this->payer);$otherEvent=Event::factory()->create(['eventType'=>$this->event->eventType]);
        DB::table('event_admins')->insert(['event_id'=>$otherEvent->id,'user_id'=>$this->admin->id]);
        $this->actingAs($this->admin)->post(route('backend.interprovincial-trials.refund-recovery.recover',[$otherEvent,$p]),['mode'=>'retry'])->assertNotFound();
    }
    public function test_proof_rejection_is_scoped_unpaid_and_blocks_rejected_proof_verification():void {
        Storage::fake('local');$service=app(TrialParticipationService::class);$p=$service->begin($this->slot,$this->payer);
        $proof=$service->uploadProof($p,$this->payer,UploadedFile::fake()->create('proof.pdf',10,'application/pdf'));
        try {$service->rejectProof($proof,$this->payer,'Account proof does not show payment.');$this->fail('Payer rejected proof');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $service->rejectProof($proof,$this->admin,'Account proof does not show payment.');$service->rejectProof($proof,$this->admin,'Account proof does not show payment.');
        $this->assertSame('rejected',$proof->fresh()->status);$this->assertDatabaseCount('trial_participation_receipts',0);
        try {$service->verifyProof($proof,$this->admin,'BANK-123');$this->fail('Rejected proof settled');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
    }
}
