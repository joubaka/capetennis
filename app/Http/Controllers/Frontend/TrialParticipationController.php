<?php
namespace App\Http\Controllers\Frontend;
use App\Http\Controllers\Controller;
use App\Models\{Event,TrialSquadSlot,TrialParticipation,TrialParticipationProof,TrialProgramme};
use App\Services\InterprovincialTrials\TrialParticipationService;
use App\Domain\Payments\Services\TeamPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class TrialParticipationController extends Controller {
    public function begin(Request $request,Event $event,TrialSquadSlot $slot,TrialParticipationService $service) {
        abort_unless((int)$slot->draft->event_id===(int)$event->id,404);
        $participation=$service->begin($slot,$request->user());
        if($participation->isPaid()) return redirect()->route('events.show',$event)->with('success','Participation is already paid.');
        return redirect()->route('interprovincial-trials.participation.show',[$event,$participation]);
    }
    private function assertAccess(Event $event,TrialParticipation $participation,TrialParticipationService $service): void {
        abort_unless((int)$participation->event_id===(int)$event->id,404);$service->authorizePayer($participation,request()->user());$service->assertOrder($participation->order);
    }
    public function show(Event $event,TrialParticipation $participation,TrialParticipationService $service) {
        $this->assertAccess($event,$participation,$service);
        $order=$participation->order;
        return view('frontend.interprovincial-trials.participation',compact('event','participation','order')+['programme'=>TrialProgramme::where('event_id',$event->id)->first(),'proofs'=>TrialParticipationProof::where('participation_id',$participation->id)->latest()->limit(20)->get(),'payfast'=>null]);
    }
    public function payfast(Event $event,TrialParticipation $participation,TrialParticipationService $service,TeamPaymentService $payments) {
        $this->assertAccess($event,$participation,$service);$order=$participation->order;
        $payfast=app(\App\Services\Payfast::class);$payfast->setMode(config('services.payfast.sandbox')?0:1);
        if (blank($payfast->id) || blank($payfast->key)) { throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'PayFast is temporarily unavailable. Your checkout remains available for EFT or another attempt.']); }
        $payfast->setEvent($event);$payfast->setPlayerInfo($participation->player);$payfast->setPayer(request()->user());
        $payfast->custom_int4=request()->user()->id;$payfast->custom_int5=$order->id;$payfast->custom_str5='TeamOrder';$payfast->amount=number_format((float)$order->payfast_amount_due,2,'.','');
        $payfast->item_name=$event->name.' — regional team participation';$payfast->setTeamNotifyUrl(route('notify.team'));$payfast->setReturnUrl(route('events.show',$event));$payfast->setCancelUrl(route('interprovincial-trials.participation.show',[$event,$participation]));
        $payfast->getForm();
        $order=$payments->recordPayfastHandoff($order,request()->user(),(float)$order->payfast_amount_due);
        return view('frontend.interprovincial-trials.participation',compact('event','participation','order','payfast')+['programme'=>TrialProgramme::where('event_id',$event->id)->first(),'proofs'=>collect()]);
    }
    public function proof(Request $request,Event $event,TrialParticipation $participation,TrialParticipationService $service) {
        $this->assertAccess($event,$participation,$service);$request->validate(['proof'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);$service->uploadProof($participation,$request->user(),$request->file('proof'));return back()->with('success','Proof submitted for admin verification.');
    }
    public function cancel(Event $event,TrialParticipation $participation,TrialParticipationService $service) {
        abort_unless((int)$participation->event_id===(int)$event->id,404);$service->cancel($participation,request()->user());return redirect()->route('events.show',$event)->with('success','Unpaid checkout cancelled.');
    }
    public function download(Event $event,TrialParticipationProof $proof,TrialParticipationService $service) {
        abort_unless((int)$proof->participation->event_id===(int)$event->id,404);$service->authorizeProof($proof,request()->user());return Storage::disk('local')->download($proof->path,'payment-proof.'.match($proof->mime_type){'application/pdf'=>'pdf','image/png'=>'png',default=>'jpg'});
    }
    public function withdraw(Event $event, TrialParticipation $participation, TrialParticipationService $service) {
        abort_unless((int)$participation->event_id===(int)$event->id,404);
        $service->withdraw($participation,request()->user());
        return redirect()->route('interprovincial-trials.participation.refund',[$event,$participation])->with('success','Participation withdrawn. Replacement flagged for admin review.');
    }
    public function refund(Event $event, TrialParticipation $participation) {
        abort_unless((int)$participation->event_id===(int)$event->id,404);
        $actor=request()->user();
        abort_unless((int)$participation->payer_id===(int)$actor->id || app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->canManage($event,$actor),403);
        return view('frontend.interprovincial-trials.participation-refund',compact('event','participation'));
    }
    public function requestRefund(Request $request, Event $event, TrialParticipation $participation, \App\Domain\Finance\Services\RefundRequestService $refunds, \App\Domain\Refunds\Services\RefundExecutionService $execution) {
        abort_unless((int)$participation->event_id===(int)$event->id,404);
        $data=$request->validate(['method'=>'required|in:bank,payfast','refund_account_name'=>'required|string|max:255','refund_bank_name'=>'required|string|max:255','refund_account_number'=>'required|digits_between:5,12','refund_branch_code'=>'required|digits_between:4,6','refund_account_type'=>'required|in:current,savings']);
        $refunds->requestTrialTeamRefund($participation,$request->user(),$data['method'],$data);
        if($data['method']==='payfast') $execution->executeTrialTeamPayfastRefund($participation,$request->user());
        return redirect()->route('interprovincial-trials.participation.refund',[$event,$participation])->with('success',$data['method']==='payfast'?'PayFast refund confirmed.':'Manual refund requested for admin processing.');
    }
}
