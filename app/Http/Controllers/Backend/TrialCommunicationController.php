<?php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;
use App\Models\{Event, TrialMailPreview, TrialMailSchedule, TrialMessageTemplate, BulkEmailLog};
use App\Services\InterprovincialTrials\{TrialCommunicationService, TrialProgrammeService};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
class TrialCommunicationController extends Controller {
    public function index(Event $event, TrialProgrammeService $programmes) {
        $programmes->authorize($event, request()->user());
        return view('backend.interprovincial-trials.communications', ['event'=>$event,
            'editing'=>request()->filled('edit_schedule') ? TrialMailSchedule::where('event_id',$event->id)->findOrFail(request()->query('edit_schedule')) : null,
            'categories'=>$event->categoryEvents()->with('category')->get(),
            'nominees'=>\App\Models\EventNomination::with('player')->where('event_id',$event->id)->get(),
            'slots'=>\App\Models\TrialSquadDraft::where('event_id',$event->id)->where('status','finalised')->whereNotNull('finalised_at')->latest('id')->first()?->slots()->with('player')->whereNotNull('player_id')->get() ?? collect(),
            'templates'=>app(TrialCommunicationService::class)->templatesFor($event,request()->user()),
            'schedules'=>TrialMailSchedule::where('event_id',$event->id)->latest()->limit(100)->get(),
            'logs'=>BulkEmailLog::where('mail_type','trial_communication')->where('payload->event_id',$event->id)->latest()->paginate(25)]);
    }
    public function preview(Request $request, Event $event, TrialCommunicationService $service) {
        $request->merge(['individual_ids'=>$request->input($request->input('audience')==='teams' ? 'slot_ids' : 'nominee_ids', $request->input('individual_ids', []))]);
        $data=$request->validate(['subject'=>'required|string|max:255','body'=>'required|string|max:50000','audience'=>'required|in:nominations,teams','filter'=>'required|in:all,not_registered,payment_pending,paid,declined,confirmed','category_ids'=>'nullable|array','category_ids.*'=>'integer','individual_ids'=>'nullable|array','individual_ids.*'=>'integer','tiers'=>'nullable|array','tiers.*'=>'in:A,B,C,D,E,F']);
        $preview=$service->preview($event,$request->user(),array_intersect_key($data,array_flip(['audience','filter','category_ids','individual_ids','tiers'])),$data['subject'],$data['body']);
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
        return redirect()->route('backend.interprovincial-trials.communications.index',$event)->with('success','Reminder schedule approved.');
    }
    public function pause(Request $request, Event $event, TrialMailSchedule $schedule, TrialCommunicationService $service) {
        abort_unless((int)$schedule->event_id===(int)$event->id,404); $service->pause($schedule,$request->user()); return back()->with('success','Schedule paused.');
    }
    public function retry(Request $request, Event $event, BulkEmailLog $log, TrialCommunicationService $service) {
        abort_unless((int)data_get($log->payload,'event_id')===(int)$event->id,404); $service->retry($log,$request->user()); return back()->with('success','Failed message queued for manual retry if eligible.');
    }
}
