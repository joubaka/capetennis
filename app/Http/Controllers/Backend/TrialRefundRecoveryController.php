<?php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;
use App\Models\{Event,TrialParticipation,TrialProviderRefundAttempt};
use App\Services\InterprovincialTrials\TrialProgrammeService;
use App\Domain\Refunds\Services\RefundExecutionService;
use Illuminate\Http\Request;
class TrialRefundRecoveryController extends Controller {
    public function index(Event $event,Request $request,TrialProgrammeService $programmes) {
        $programmes->authorize($event,$request->user());
        $attempts=TrialProviderRefundAttempt::with('order.player')->whereHas('order',fn($q)=>$q->where('event_id',$event->id))->latest('id')->paginate(25);
        return view('backend.interprovincial-trials.refund-recovery',compact('event','attempts'));
    }
    public function rejectProof(Event $event,\App\Models\TrialParticipationProof $proof,Request $request,TrialProgrammeService $programmes,\App\Services\InterprovincialTrials\TrialParticipationService $payments) {
        $programmes->authorize($event,$request->user());abort_unless((int)$proof->participation->event_id===(int)$event->id,404);
        $payments->rejectProof($proof,$request->user(),$request->validate(['reason'=>'required|string|min:10|max:1000'])['reason']);
        return back()->with('success','Proof rejected. The payer may upload corrected evidence.');
    }
    public function recover(Event $event,TrialParticipation $participation,Request $request,TrialProgrammeService $programmes,RefundExecutionService $refunds) {
        $programmes->authorize($event,$request->user());abort_unless((int)$participation->event_id===(int)$event->id,404);
        $mode=$request->validate(['mode'=>'required|in:retry,reconcile'])['mode'];
        $refunds->recoverTrialTeamPayfastRefund($participation,$request->user(),$mode==='reconcile'?$request->only(['pf_payment_id','amount','reference','reason','confirmed_at','externally_confirmed']):null);
        return redirect()->route('backend.interprovincial-trials.refund-recovery.index',$event)->with('success','Confirmed provider refund completed locally.');
    }
}
