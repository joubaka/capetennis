<?php
namespace App\Services\InterprovincialTrials;
use App\Models\{TrialParticipation,TrialParticipationProof,TrialSquadSlot,TrialSquadDraft,TrialProgramme,TeamPaymentOrder,User,Event};
use App\Domain\Payments\Services\TeamPaymentService;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
class TrialParticipationService {
    public function __construct(private TeamPaymentService $payments,private TrialProgrammeService $programmes) {}
    public function assertSlot(TrialSquadSlot $slot): void {
        $draft=$slot->draft;
        abort_unless($draft && $draft->status==='finalised' && $draft->finalised_at && $slot->player_id && !$slot->reserve && $slot->response!=='declined',422,'Only finalised playing-team members may pay.');
        $current=TrialSquadDraft::where('event_id',$draft->event_id)->where('status','finalised')->whereNotNull('finalised_at')->latest('id')->value('id');
        abort_unless((int)$current===(int)$draft->id,422,'This roster is no longer current.');
        abort_unless($slot->categoryEvent && (int)$slot->categoryEvent->event_id===(int)$draft->event_id && $draft->event?->isInterprovincialTrials(),422);
    }
    public function begin(TrialSquadSlot $slot,User $payer): TrialParticipation {
        return DB::transaction(function() use($slot,$payer){
            $draft=TrialSquadDraft::lockForUpdate()->findOrFail($slot->draft_id);
            $slot=TrialSquadSlot::lockForUpdate()->findOrFail($slot->id); $this->assertSlot($slot);
            abort_unless($draft->event->published || $this->programmes->canManage($draft->event, $payer),404);
            $programme=TrialProgramme::where('event_id',$draft->event_id)->lockForUpdate()->firstOrFail();
            $fee=(int)round((float)$programme->participation_fee*100);
            abort_unless($fee>0,422,'Set a positive regional participation fee before collecting payment.');
            $participation=TrialParticipation::firstOrCreate(['event_id'=>$draft->event_id,'player_id'=>$slot->player_id],['slot_id'=>$slot->id]);
            $participation=TrialParticipation::lockForUpdate()->findOrFail($participation->id);
            $participation->update(['slot_id'=>$slot->id]);
            $order=$this->payments->ensureTrialOrder($participation,$payer);
            $participation->update(['order_id'=>$order->id,'payer_id'=>$order->user_id]);
            activity('interprovincial-trials')->performedOn($participation)->causedBy($payer)->withProperties(['order_id'=>$order->id,'slot_id'=>$slot->id,'amount'=>$fee/100])->log('Regional team participation checkout started');
            return $participation->fresh('order');
        });
    }
    public function authorizePayer(TrialParticipation $participation,User $actor): void {abort_unless((int)$participation->payer_id===(int)$actor->id,403);}
    public function cancel(TrialParticipation $participation,User $payer): void {
        DB::transaction(function() use($participation,$payer){
            $this->lockForOrder((int)$participation->order_id);
            $participation=TrialParticipation::lockForUpdate()->findOrFail($participation->id);$this->authorizePayer($participation,$payer);
            $order=TeamPaymentOrder::lockForUpdate()->findOrFail($participation->order_id);
            abort_unless(!$order->pay_status && !$order->payfast_paid && !$order->wallet_debited && !$order->payfast_handed_off_at,422);
            $this->payments->closeUnpaidLifecycle($order,$payer); $participation->update(['order_id'=>null,'payer_id'=>null]);
            activity('interprovincial-trials')->performedOn($participation)->causedBy($payer)->withProperties(['cancelled_order_id'=>$order->id])->log('Participation checkout cancelled by payer');
        });
    }
    public function lockForOrder(int $orderId, bool $historical = false): ?TrialParticipation {
        abort_unless(DB::transactionLevel() > 0,500,'Participation locks require a transaction.');
        $current=TrialParticipation::where('order_id',$orderId)->first();
        if (!$current) return null;
        $slot=TrialSquadSlot::findOrFail($current->slot_id);
        $draft=TrialSquadDraft::lockForUpdate()->findOrFail($slot->draft_id);
        $slot=TrialSquadSlot::lockForUpdate()->findOrFail($slot->id);
        TrialProgramme::where('event_id',$draft->event_id)->lockForUpdate()->firstOrFail();
        $order=TeamPaymentOrder::lockForUpdate()->findOrFail($orderId);
        $locked=TrialParticipation::lockForUpdate()->findOrFail($current->id);
        abort_unless((int)$locked->order_id===$orderId && (int)$locked->slot_id===(int)$slot->id
            && (int)$slot->draft_id===(int)$draft->id && (int)$draft->event_id===(int)$locked->event_id
            && (int)$locked->event_id===(int)$order->event_id && (int)$locked->player_id===(int)$order->player_id
            && ($historical || (int)$slot->player_id===(int)$order->player_id) && (int)$locked->payer_id===(int)$order->user_id,409,'Participation changed; retry against the current checkout.');
        return $locked;
    }
    public function assertOrder(TeamPaymentOrder $order): TrialParticipation {
        $participation=TrialParticipation::where('order_id',$order->id)->firstOrFail();
        $this->assertSlot($participation->slot);
        abort_unless((int)$order->event_id===(int)$participation->event_id && (int)$order->player_id===(int)$participation->player_id && (int)$order->user_id===(int)$participation->payer_id && !$order->team_id && !$order->withdrawn_at && (int)$participation->slot->player_id===(int)$order->player_id,422,'Participation checkout relationships no longer match.');
        return $participation;
    }
    public function relocate(TrialSquadSlot $slot,User $actor): void {
        $this->programmes->authorize($slot->draft->event,$actor);$this->assertSlot($slot);
        TrialParticipation::where('event_id',$slot->draft->event_id)->where('player_id',$slot->player_id)->update(['slot_id'=>$slot->id]);
    }
    public function uploadProof(TrialParticipation $participation,User $payer,UploadedFile $file): TrialParticipationProof {
        $this->authorizePayer($participation,$payer);
        if(!$file->isValid() || !in_array($file->getMimeType(),['application/pdf','image/jpeg','image/png'],true) || $file->getSize()>5*1024*1024) throw ValidationException::withMessages(['proof'=>'Upload a PDF, JPEG or PNG no larger than 5 MB.']);
        $path=$file->store('trial-participation-proofs','local');
        try{return DB::transaction(function()use($participation,$payer,$file,$path){
            $this->lockForOrder((int)$participation->order_id);
            $participation=TrialParticipation::lockForUpdate()->findOrFail($participation->id);$this->authorizePayer($participation,$payer);
            $order=TeamPaymentOrder::lockForUpdate()->findOrFail($participation->order_id);$this->assertOrder($order);
            abort_unless(!$order->pay_status && !$order->payfast_handed_off_at && (float)$order->wallet_reserved===0.0,422);
            abort_unless(filled(TrialProgramme::where('event_id',$participation->event_id)->first()?->bank_details),422,'The regional EFT account must be configured.');
            return TrialParticipationProof::create(['participation_id'=>$participation->id,'order_id'=>$order->id,'payer_id'=>$payer->id,'path'=>$path,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize()]);
        });}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
    }
    public function authorizeProof(TrialParticipationProof $proof,User $actor): void {
        if((int)$proof->payer_id!==(int)$actor->id) $this->programmes->authorize($proof->participation->event,$actor);
    }
    public function rejectProof(TrialParticipationProof $proof, User $actor, string $reason): void {
        validator(['reason'=>$reason],['reason'=>'required|string|min:10|max:1000'])->validate();
        DB::transaction(function () use ($proof,$actor,$reason) {
            $this->lockForOrder((int)$proof->order_id);
            $proof=TrialParticipationProof::lockForUpdate()->findOrFail($proof->id);
            $participation=$proof->participation;
            $this->programmes->authorize($participation->event,$actor);
            $order=TeamPaymentOrder::lockForUpdate()->findOrFail($proof->order_id);
            $this->assertOrder($order);
            abort_unless((int)$participation->order_id===(int)$order->id && (int)$proof->payer_id===(int)$order->user_id
                && !$order->pay_status && !$order->payfast_paid && !$order->wallet_debited && !$order->payfast_handed_off_at
                && !\App\Models\TrialParticipationReceipt::where('order_id',$order->id)->exists(),422);
            if ($proof->status==='rejected') return;
            abort_unless($proof->status==='pending',422);
            $proof->update(['status'=>'rejected','reviewed_by'=>$actor->id,'reviewed_at'=>now()]);
            activity('interprovincial-trials')->performedOn($proof)->causedBy($actor)->withProperties(['order_id'=>$order->id,'reason'=>$reason])->log('Regional participation EFT proof rejected');
        });
    }
    public function verifyProof(TrialParticipationProof $proof,User $actor,string $reference): \App\Models\TrialParticipationReceipt {return $this->payments->finalizeTrialManualPayment($proof->participation,$actor,'eft',$reference,$proof->id);}
    public function markPaid(TrialParticipation $participation,User $actor,string $reference): \App\Models\TrialParticipationReceipt {return $this->payments->finalizeTrialManualPayment($participation,$actor,'manual',$reference);}
    public function withdraw(TrialParticipation $participation, User $actor): TeamPaymentOrder {
        return DB::transaction(function () use ($participation, $actor) {
            $this->lockForOrder((int)$participation->order_id);
            $order = TeamPaymentOrder::lockForUpdate()->findOrFail($participation->order_id);
            $participation = TrialParticipation::lockForUpdate()->findOrFail($participation->id);
            abort_unless((int) $participation->order_id === (int) $order->id && (int) $participation->payer_id === (int) $order->user_id, 422);
            $manager = $this->programmes->canManage($participation->event, $actor);
            abort_unless($manager || (int) $order->user_id === (int) $actor->id, 403);
            if ($order->withdrawn_at) return $order;
            $withdrawn = $this->payments->recordWithdrawal($order, $actor);
            app(TrialSelectionReviewService::class)->respond($participation->slot, $actor, 'declined', $manager ? 'Participation withdrawn by regional administrator.' : null);
            activity('interprovincial-trials')->performedOn($participation)->causedBy($actor)->withProperties(['order_id' => $order->id])->log('Regional participation withdrawn; replacement review required');
            return $withdrawn;
        });
    }
}
