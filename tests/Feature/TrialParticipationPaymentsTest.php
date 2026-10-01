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
class TrialParticipationPaymentsTest extends TestCase {
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
    public function test_roster_payment_lock_rejects_changed_entitlement_tuple_before_mutation():void {
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);
        $participation->update(['player_id'=>Player::factory()->create()->id]);
        try { $service->markPaid($participation,$this->admin,'BANK-123');$this->fail('Changed tuple accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {$this->assertSame(409,$e->getStatusCode());}
        $this->assertDatabaseCount('trial_participation_receipts',0);$this->assertFalse((bool)$participation->order->fresh()->pay_status);
    }
    public function test_private_event_payment_begin_requires_regional_manager():void {
        $this->event->update(['published'=>false]);
        try { app(TrialParticipationService::class)->begin($this->slot,$this->payer); $this->fail('Private event exposed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404,$e->getStatusCode()); }
        $this->assertDatabaseCount('team_payment_orders',0);
        $this->assertNotNull(app(TrialParticipationService::class)->begin($this->slot,$this->admin)->order);
    }
    public function test_signed_trials_callback_rejects_wrong_marker_and_each_tuple_field_before_paid_replay():void {
        $participation=app(TrialParticipationService::class)->begin($this->slot,$this->payer);
        config()->set('services.payfast.sandbox',false);
        config()->set('services.payfast.merchant_id','test-merchant');
        config()->set('services.payfast.passphrase_live','test-passphrase');
        $base=['merchant_id'=>'test-merchant','payment_status'=>'COMPLETE','custom_int5'=>$participation->order_id,'custom_str5'=>'TeamOrder','custom_int4'=>$this->payer->id,'custom_int3'=>$this->event->id,'custom_int2'=>$this->slot->player_id,'pf_payment_id'=>'PF-COLLISION','amount_gross'=>'456.78'];
        foreach (['custom_str5'=>'RegistrationOrder','custom_int4'=>$this->payer->id+1000,'custom_int3'=>$this->event->id+1000,'custom_int2'=>$this->slot->player_id+1000] as $field=>$wrong) {
            $payload=array_replace($base,[$field=>$wrong]);
            $payload['signature']=md5(http_build_query($payload).'&passphrase='.urlencode('test-passphrase'));
            $this->call('POST',route('notify.team'),$payload,[],[],['CONTENT_TYPE'=>'application/x-www-form-urlencoded'],http_build_query($payload))->assertStatus(400);
        }
        $this->assertFalse((bool)$participation->order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf',0);
        app(TrialParticipationService::class)->markPaid($participation,$this->admin,'BANK-123');
        $payload=array_replace($base,['custom_str5'=>'ClothingOrder']);
        $payload['signature']=md5(http_build_query($payload).'&passphrase='.urlencode('test-passphrase'));
        $this->call('POST',route('notify.team'),$payload,[],[],['CONTENT_TYPE'=>'application/x-www-form-urlencoded'],http_build_query($payload))->assertStatus(400);
        $this->assertDatabaseCount('transactions_pf',0);
    }
    public function test_another_pending_proof_cannot_be_accepted_after_a_receipt_exists():void {
        Storage::fake('local');$service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);
        $proof=$service->uploadProof($participation,$this->payer,UploadedFile::fake()->create('one.pdf',10,'application/pdf'));
        $other=$service->uploadProof($participation,$this->payer,UploadedFile::fake()->create('two.pdf',10,'application/pdf'));
        $service->verifyProof($proof,$this->admin,'BANK-123');
        $this->assertSame($proof->id,$service->verifyProof($proof,$this->admin,'BANK-123')->proof_id);
        try {$service->verifyProof($other,$this->admin,'BANK-456');$this->fail('Different proof accepted');}
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {$this->assertSame(422,$e->getStatusCode());}
        $this->assertSame('pending',$other->fresh()->status);$this->assertDatabaseCount('trial_participation_receipts',1);
    }
    public function test_any_payer_can_begin_but_only_original_payer_resumes():void {
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);$this->assertSame(456.78,$participation->order->total_amount);$this->assertNull($participation->order->team_id);
        $again=$service->begin($this->slot,$this->payer);$this->assertSame($participation->order_id,$again->order_id);
        $stranger=User::factory()->create();try{$service->begin($this->slot,$stranger);$this->fail('Expected denial');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
        $this->assertSame($this->payer->id,$participation->fresh()->payer_id);$this->assertDatabaseCount('team_payment_orders',1);$this->assertDatabaseCount('user_players',0);
    }
    public function test_paid_b_to_a_player_has_single_entitlement_and_payment():void {
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);
        $receipt=$service->markPaid($participation,$this->admin,'BANK-123');$this->assertSame('456.78',$receipt->amount);$this->assertTrue($participation->fresh()->isPaid());
        $this->slot->update(['tier'=>'A']);$service->relocate($this->slot->fresh(),$this->admin);
        $again=$service->begin($this->slot->fresh(),$this->payer);$this->assertSame($participation->order_id,$again->order_id);$this->assertTrue($again->isPaid());
        $service->markPaid($again,$this->admin,'BANK-123');$this->assertDatabaseCount('team_payment_orders',1);$this->assertDatabaseCount('trial_participation_receipts',1);$this->assertDatabaseCount('transactions_pf',0);$this->assertDatabaseCount('wallet_transactions',0);
    }
    public function test_private_drafts_and_standalone_reserves_cannot_begin():void {
        $service=app(TrialParticipationService::class);$this->slot->update(['reserve'=>true]);
        try{$service->begin($this->slot->fresh(),$this->payer);$this->fail('Expected rejection');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
        $this->slot->update(['reserve'=>false]);$this->slot->draft->update(['finalised_at'=>null]);
        try{$service->begin($this->slot->fresh(),$this->payer);$this->fail('Expected rejection');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
        $this->assertDatabaseCount('team_payment_orders',0);
    }
    public function test_eft_proof_is_private_and_requires_admin_verification():void {
        Storage::fake('local');$service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);
        $proof=$service->uploadProof($participation,$this->payer,UploadedFile::fake()->create('proof.pdf',10,'application/pdf'));$this->assertFalse($participation->fresh()->isPaid());$this->assertArrayNotHasKey('path',$proof->toArray());
        $receipt=$service->verifyProof($proof,$this->admin,'BANK-123');$this->assertSame('eft',$receipt->method);$this->assertSame('verified',$proof->fresh()->status);
        $ledger=app(\App\Domain\Finance\Services\FinancialLedgerService::class)->buildForEvent($this->event);$this->assertSame(456.78,$ledger['totals']['gross_payments']);$this->assertSame(456.78,$ledger['totals']['net_revenue']);
    }
    public function test_handoff_blocks_manual_payment_and_cancellation():void {
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);app(TeamPaymentService::class)->recordPayfastHandoff($participation->order,$this->payer,456.78);
        try{$service->markPaid($participation,$this->admin,'BANK-123');$this->fail('Expected rejection');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('payment',$e->errors());}
        try{$service->cancel($participation,$this->payer);$this->fail('Expected rejection');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(422,$e->getStatusCode());}
        $this->assertDatabaseCount('trial_participation_receipts',0);
    }
    public function test_verified_provider_amount_is_exact_and_updates_entitlement_once():void {
        Events::fake([\App\Events\PaymentCompleted::class]);$service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);$payments=app(TeamPaymentService::class);
        try{$payments->finalizePayment($participation->order,['payment_method'=>'payfast','payfast_amount_received'=>1,'pf_payment_id'=>'PF-TEST']);$this->fail('Expected mismatch');}catch(\RuntimeException $e){$this->assertStringContainsString('mismatch',$e->getMessage());}
        $payments->finalizePayment($participation->order,['payfast_amount_received'=>456.78,'payfast_amount_due'=>456.78,'payment_method'=>'payfast','pf_payment_id'=>'PF-TEST']);
        $this->assertTrue($participation->fresh()->isPaid());$this->assertNotNull($participation->fresh()->paid_at);
        $payments->finalizePayment($participation->order,['payment_method'=>'payfast','payfast_amount_received'=>456.78,'pf_payment_id'=>'PF-TEST']);$this->assertDatabaseCount('team_payment_orders',1);
    }
    public function test_withdraw_and_manual_refund_preserve_paid_history_and_exact_ledger():void {
        Events::fake([\App\Events\RefundCompleted::class]);
        TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->addWeek()]);
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);$service->markPaid($participation,$this->admin,'BANK-123');
        $service->withdraw($participation,$this->admin);$this->assertSame('declined',$this->slot->fresh()->response);
        $request=app(\App\Domain\Finance\Services\RefundRequestService::class);$order=$request->requestTrialTeamRefund($participation,$this->admin,'bank');
        $this->assertSame(456.78,$order->refund_gross);$this->assertSame(45.68,$order->refund_fee);$this->assertSame(411.10,$order->refund_net);
        $execution=app(\App\Domain\Refunds\Services\RefundExecutionService::class);$execution->completeTrialTeamManualRefund($participation,$this->admin,'REFUND-123');$execution->completeTrialTeamManualRefund($participation,$this->admin,'REFUND-123');
        $this->assertTrue($participation->order->fresh()->pay_status);$this->assertSame('completed',$participation->order->fresh()->refund_status);
        $ledger=app(\App\Domain\Finance\Services\FinancialLedgerService::class)->buildForEvent($this->event);$this->assertSame(456.78,$ledger['totals']['gross_payments']);$this->assertSame(45.68,$ledger['totals']['net_revenue']);$this->assertDatabaseCount('wallet_transactions',0);
    }
    public function test_participation_refunds_require_withdrawal_deadline_and_original_method():void {
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);$service->markPaid($participation,$this->admin,'BANK-123');
        $request=app(\App\Domain\Finance\Services\RefundRequestService::class);
        try{$request->requestTrialTeamRefund($participation,$this->admin,'bank');$this->fail('Expected withdrawal gate');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('refund',$e->errors());}
        $service->withdraw($participation,$this->admin);TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->subDay()]);
        try{$request->requestTrialTeamRefund($participation,$this->admin,'bank');$this->fail('Expected deadline gate');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('refund',$e->errors());}
        TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->addDay()]);
        try{$request->requestTrialTeamRefund($participation,$this->admin,'payfast');$this->fail('Expected original method gate');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('refund',$e->errors());}
    }
    public function test_payfast_refund_uses_original_payment_and_runs_once_with_mocked_provider():void {
        Events::fake([\App\Events\PaymentCompleted::class,\App\Events\RefundCompleted::class]);TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->addWeek()]);
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);
        app(TeamPaymentService::class)->finalizePayment($participation->order,['payment_method'=>'payfast','payfast_amount_received'=>456.78,'payfast_amount_due'=>456.78,'pf_payment_id'=>'PF-TEST']);
        $service->withdraw($participation,$this->admin);app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTrialTeamRefund($participation,$this->admin,'payfast');
        $this->mock(\App\Services\Payfast::class,fn($mock)=>$mock->shouldReceive('refundUsingAvailableMethod')->once()->with('PF-TEST',411.10,'Regional team withdrawal refund',\Mockery::type('array'))->andReturn(['success'=>true,'data'=>[]]));
        $this->commitFixtureForProvider();
        $execution=app(\App\Domain\Refunds\Services\RefundExecutionService::class);$execution->executeTrialTeamPayfastRefund($participation,$this->admin);$execution->executeTrialTeamPayfastRefund($participation,$this->admin);
        $this->assertSame('completed',$participation->order->fresh()->refund_status);$this->assertTrue($participation->order->fresh()->payfast_paid);
    }
    public function test_confirmed_provider_refund_survives_local_failure_and_retry_does_not_dispatch_again(): void {
        Events::fake([\App\Events\PaymentCompleted::class,\App\Events\RefundCompleted::class]);
        TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->addWeek()]);
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);
        app(TeamPaymentService::class)->finalizePayment($participation->order,['payment_method'=>'payfast','payfast_amount_received'=>456.78,'payfast_amount_due'=>456.78,'pf_payment_id'=>'PF-TEST']);
        $service->withdraw($participation,$this->admin);
        app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTrialTeamRefund($participation,$this->admin,'payfast');
        $this->mock(\App\Services\Payfast::class,fn($mock)=>$mock->shouldReceive('refundUsingAvailableMethod')->once()->andReturn(['success'=>true]));
        $execution=\Mockery::mock(\App\Domain\Refunds\Services\RefundExecutionService::class,[app(\App\Domain\Payments\Services\LedgerService::class)])->makePartial();
        $execution->shouldReceive('executeSplitRefund')->once()->andThrow(new \RuntimeException('Simulated local completion failure'));
        $this->commitFixtureForProvider();
        try {$execution->executeTrialTeamPayfastRefund($participation,$this->admin);$this->fail('Expected local failure');}
        catch(\RuntimeException $e){$this->assertSame('Simulated local completion failure',$e->getMessage());}
        $this->assertDatabaseHas('trial_provider_refund_attempts',['order_id'=>$participation->order_id,'status'=>'confirmed']);
        $this->assertSame('pending',$participation->order->fresh()->refund_status);
        app(\App\Domain\Refunds\Services\RefundExecutionService::class)->executeTrialTeamPayfastRefund($participation,$this->admin);
        $this->assertSame('completed',$participation->order->fresh()->refund_status);$this->assertDatabaseCount('trial_provider_refund_attempts',1);
    }
    public function test_uncertain_provider_refund_is_flagged_without_a_second_dispatch(): void {
        Events::fake([\App\Events\PaymentCompleted::class]);TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->addWeek()]);
        $service=app(TrialParticipationService::class);$participation=$service->begin($this->slot,$this->payer);
        app(TeamPaymentService::class)->finalizePayment($participation->order,['payment_method'=>'payfast','payfast_amount_received'=>456.78,'payfast_amount_due'=>456.78,'pf_payment_id'=>'PF-TEST']);
        $service->withdraw($participation,$this->admin);app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTrialTeamRefund($participation,$this->admin,'payfast');
        $this->mock(\App\Services\Payfast::class,fn($mock)=>$mock->shouldReceive('refundUsingAvailableMethod')->once()->andThrow(new \RuntimeException('Network result unknown')));
        $this->commitFixtureForProvider();
        $execution=app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        for($i=0;$i<2;$i++){try{$execution->executeTrialTeamPayfastRefund($participation,$this->admin);$this->fail('Expected reconciliation flag');}catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('refund',$e->errors());}}
        $this->assertDatabaseHas('trial_provider_refund_attempts',['order_id'=>$participation->order_id,'status'=>'uncertain']);
        $this->assertSame('pending',$participation->order->fresh()->refund_status);
    }
    public function test_provider_refund_rejects_an_enclosing_transaction_without_contacting_provider(): void {
        $participation=app(TrialParticipationService::class)->begin($this->slot,$this->payer);
        $this->mock(\App\Services\Payfast::class,fn($mock)=>$mock->shouldNotReceive('refundUsingAvailableMethod'));
        $this->expectException(\LogicException::class);
        app(\App\Domain\Refunds\Services\RefundExecutionService::class)->executeTrialTeamPayfastRefund($participation,$this->admin);
    }
    public function test_late_provider_failure_cannot_downgrade_an_admin_reconciled_attempt():void {
        Events::fake([\App\Events\PaymentCompleted::class,\App\Events\RefundCompleted::class]);
        TrialProgramme::where('event_id',$this->event->id)->update(['withdrawal_deadline'=>now()->addWeek()]);
        $service=app(TrialParticipationService::class);$p=$service->begin($this->slot,$this->payer);
        app(TeamPaymentService::class)->finalizePayment($p->order,['payment_method'=>'payfast','payfast_amount_received'=>456.78,'payfast_amount_due'=>456.78,'pf_payment_id'=>'PF-TEST']);
        $service->withdraw($p,$this->admin);app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTrialTeamRefund($p,$this->admin,'payfast');
        $confirmedAt=now()->subMinute()->toDateTimeString();
        $this->mock(\App\Services\Payfast::class,function($mock)use($p,$confirmedAt){
            $mock->shouldReceive('refundUsingAvailableMethod')->once()->andReturnUsing(function()use($p,$confirmedAt){
                app(\App\Domain\Refunds\Services\RefundExecutionService::class)->recoverTrialTeamPayfastRefund($p,$this->admin,[
                    'pf_payment_id'=>'PF-TEST','amount'=>'411.10','reference'=>'PROVIDER-123','reason'=>'Provider history confirms exact completed refund.','confirmed_at'=>$confirmedAt,'externally_confirmed'=>true,
                ]);
                throw new \RuntimeException('Late provider timeout');
            });
        });
        $this->commitFixtureForProvider();
        $execution=app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        try {$execution->executeTrialTeamPayfastRefund($p,$this->admin);$this->fail('Expected provider timeout');}
        catch(\Illuminate\Validation\ValidationException $e){$this->assertArrayHasKey('refund',$e->errors());}
        $attempt=\App\Models\TrialProviderRefundAttempt::where('order_id',$p->order_id)->firstOrFail();
        $this->assertSame('confirmed',$attempt->status);$this->assertSame($confirmedAt,$attempt->confirmed_at->toDateTimeString());
        $this->assertSame('completed',$p->order->fresh()->refund_status);
        $execution->executeTrialTeamPayfastRefund($p,$this->admin);$this->assertDatabaseCount('trial_provider_refund_attempts',1);
    }
    private function commitFixtureForProvider(): void {
        // Exercise the real durable-claim contract, which intentionally refuses test wrapper transactions.
        DB::commit();
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;
    }
    public function test_missing_payfast_configuration_leaves_checkout_available_for_eft(): void {
        $participation=app(TrialParticipationService::class)->begin($this->slot,$this->payer);
        $provider=\Mockery::mock(\App\Services\Payfast::class)->makePartial();
        $provider->id=null;$provider->key=null;$provider->shouldReceive('setMode')->once();$provider->shouldNotReceive('getForm');
        $this->app->instance(\App\Services\Payfast::class,$provider);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class,\App\Http\Middleware\EnsurePlayerProfileUpdated::class])
            ->actingAs($this->payer)->postJson(route('interprovincial-trials.participation.payfast',[$this->event,$participation]))->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertNull($participation->order->fresh()->payfast_handed_off_at);
        app(TrialParticipationService::class)->cancel($participation,$this->payer);
        $this->assertNull($participation->fresh()->order_id);
    }
}
