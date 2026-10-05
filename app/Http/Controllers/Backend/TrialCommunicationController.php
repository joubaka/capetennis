<?php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;
use App\Models\{Event, TrialMailPreview, TrialMailSchedule, TrialMessageTemplate, BulkEmailLog};
use App\Services\InterprovincialTrials\{TrialCommunicationService, TrialProgrammeService};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
class TrialCommunicationController extends Controller {
    private const SELECTOR_LIMIT = 500;

    public function index(Event $event, TrialProgrammeService $programmes) {
        $programmes->authorize($event, request()->user());
        $logQuery=BulkEmailLog::where('mail_type','trial_communication')->where('payload->event_id',$event->id);
        $batch=request()->filled('batch') ? \App\Models\TrialMailPreview::where('event_id',$event->id)->whereNotNull('committed_at')->findOrFail(request()->query('batch')) : null;
        if ($batch) $logQuery->where('payload->preview_id',$batch->id);
        return view('backend.interprovincial-trials.communications', ['event'=>$event,
            'batch'=>$batch,
            'pendingDrafts'=>\App\Models\EventCommunicationBatch::where('event_id',$event->id)->where('status','draft')->where('options->source','transaction')->latest()->limit(100)->get(),
            'transactionBatches'=>\App\Models\EventCommunicationBatch::where('event_id',$event->id)->where('created_by',request()->user()->id)->whereNotNull('approved_at')->latest()->limit(100)->get(),
            'batches'=>\App\Models\TrialMailPreview::where('event_id',$event->id)->whereNotNull('committed_at')->latest('id')->limit(100)->get(),
            'delivery'=>BulkEmailLog::deliverySummary(clone $logQuery),
            'editing'=>request()->filled('edit_schedule') ? TrialMailSchedule::where('event_id',$event->id)->findOrFail(request()->query('edit_schedule')) : null,
            'regions'=>$event->regions()->get(),
            'categories'=>$event->categoryEvents()->with('category')->orderBy('id')->limit(self::SELECTOR_LIMIT)->get(),
            'nominees'=>\App\Models\EventNomination::with('player')->where('event_id',$event->id)->when(request()->filled('person_search'),fn($q)=>$q->where(fn($q)=>$q->where('nominee_name','like','%'.mb_substr(request('person_search'),0,100).'%')->orWhere('nominee_surname','like','%'.mb_substr(request('person_search'),0,100).'%')->orWhereHas('player',fn($p)=>$p->where('name','like','%'.mb_substr(request('person_search'),0,100).'%')->orWhere('surname','like','%'.mb_substr(request('person_search'),0,100).'%'))))->orderBy('id')->limit(self::SELECTOR_LIMIT)->get(),
            'slots'=>\App\Models\TrialSquadDraft::where('event_id',$event->id)->latest('id')->first()?->slots()->with('player')->whereNotNull('player_id')->when(request()->filled('person_search'),fn($q)=>$q->whereHas('player',fn($p)=>$p->where('name','like','%'.mb_substr(request('person_search'),0,100).'%')->orWhere('surname','like','%'.mb_substr(request('person_search'),0,100).'%')))->orderBy('id')->limit(self::SELECTOR_LIMIT)->get() ?? collect(),
            'templates'=>app(TrialCommunicationService::class)->templatesFor($event,request()->user()),
            'schedules'=>TrialMailSchedule::where('event_id',$event->id)->latest()->limit(100)->get(),
            'logs'=>$logQuery->latest()->paginate(25)->withQueryString()]);
    }
    public function preview(Request $request, Event $event, TrialCommunicationService $service) {
        $request->merge(['individual_ids'=>$request->input($request->input('audience')==='teams' ? 'slot_ids' : 'nominee_ids', $request->input('individual_ids', []))]);
        $data=$request->validate(['subject'=>'required|string|max:255','body'=>'required|string|max:50000','audience'=>'required|in:all,nominations,teams','recipients'=>'nullable|in:players,managers,both','region_id'=>'nullable|integer','nominee_ids'=>'nullable|array','nominee_ids.*'=>'integer','slot_ids'=>'nullable|array','slot_ids.*'=>'integer','filter'=>'required|in:all,not_registered,payment_pending,paid,declined,confirmed','category_ids'=>'nullable|array','category_ids.*'=>'integer','individual_ids'=>'nullable|array','individual_ids.*'=>'integer','tiers'=>'nullable|array','tiers.*'=>'in:A,B,C,D,E,F']);
        $preview=$service->preview($event,$request->user(),array_intersect_key($data,array_flip(['audience','recipients','region_id','nominee_ids','slot_ids','filter','category_ids','individual_ids','tiers'])),$data['subject'],$data['body']);
        if ($request->filled('schedule_id')) {
            TrialMailSchedule::where('event_id',$event->id)->findOrFail($request->input('schedule_id'));
            $preview->update(['options'=>array_merge($preview->options,['_schedule_id'=>(int)$request->input('schedule_id')])]);
        }
        return view('backend.interprovincial-trials.communication-preview',compact('event','preview'));
    }
    private function previewFor(Event $event, Request $request): TrialMailPreview {
        return TrialMailPreview::where('event_id',$event->id)->where('token',$request->input('token'))->firstOrFail();
    }
    public function send(Request $request, Event $event, TrialCommunicationService $service) {
        $stats=$service->commit($this->previewFor($event,$request),$request->user());
        return redirect()->route('backend.interprovincial-trials.communications.index',$event)->with('success',$stats['queued'].' combined messages queued.');
    }
    public function templates(Request $request, Event $event, TrialCommunicationService $service) {
        $data=$request->validate(['name'=>'required|string|max:255','subject'=>'required|string|max:255','body'=>'required|string|max:50000']);
        $service->saveTemplate($event,$request->user(),$data['name'],$data['subject'],$data['body']); return back()->with('success','Template saved.');
    }
    public function schedules(Request $request, Event $event, TrialCommunicationService $service) {
        $data=$request->validate(['start'=>'required|date|after:now','repeat_hours'=>'nullable|integer|min:1|max:8760','stop'=>'nullable|date|after_or_equal:start']);
        $service->schedule($this->previewFor($event,$request),$request->user(),Carbon::parse($data['start']),$data['repeat_hours']??null,isset($data['stop'])?Carbon::parse($data['stop']):null);
        return redirect()->route('backend.interprovincial-trials.communications.index',$event)->with('success','Reminder saved. Each occurrence requires a fresh preview and approval before sending.');
    }
    public function pause(Request $request, Event $event, TrialMailSchedule $schedule, TrialCommunicationService $service) {
        abort_unless((int)$schedule->event_id===(int)$event->id,404); $service->pause($schedule,$request->user()); return back()->with('success','Schedule paused.');
    }
    public function retry(Request $request, Event $event, BulkEmailLog $log, TrialCommunicationService $service) {
        app(TrialProgrammeService::class)->authorize($event,$request->user());
        $request->validate(['approved_retry'=>'required|accepted']);
        abort_unless((int)data_get($log->payload,'event_id')===(int)$event->id,404); $queued=$service->retry($log,$request->user()); return back()->with($queued?'success':'warning',$queued?'One failed message queued for manual retry. Check the Email Log for acceptance.':'No email queued. This message is no longer eligible for retry.');
    }
}
