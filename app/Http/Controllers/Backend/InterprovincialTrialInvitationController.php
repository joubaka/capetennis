<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Player;
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Http\Request;

class InterprovincialTrialInvitationController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $batch = InterprovincialTrialInvitationBatch::with(['invitations.player', 'invitations.categoryEvent.category'])
            ->where('event_id', $event->id)->latest('id')->first();
        $event->load(['categoryEvents' => fn ($query) => $query->with(['category', 'nominations.player'])->orderBy('ordering')->orderBy('id')]);
        $search = trim((string) $request->query('player_search', ''));
        $players = collect();
        if (mb_strlen($search) >= 2) {
            $literalSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $pattern = "%{$literalSearch}%";
            $players = Player::query()
                ->select('id', 'name', 'surname')
                ->where(function ($query) use ($pattern): void {
                    $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("surname LIKE ? ESCAPE '!'", [$pattern]);
                })
                ->orderBy('surname')->orderBy('name')->limit(30)->get();
        }

        return view('backend.interprovincial-trials.invitations', compact('event', 'batch', 'search', 'players'));
    }

    public function nominate(Request $request, Event $event, CategoryEvent $categoryEvent)
    {
        $this->authorizeEvent($event);
        abort_unless((int) $categoryEvent->event_id === (int) $event->id, 404);
        $data = $request->validate(['player_id' => ['required', 'integer', 'exists:players,id']]);
        EventNomination::firstOrCreate([
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
            'player_id' => $data['player_id'],
        ]);

        return redirect()->route('backend.interprovincial-trials.invitations.index', $event)
            ->with('success', 'Player nominated. Prepare a new invitation snapshot when the list is complete.');
    }

    public function removeNomination(Event $event, CategoryEvent $categoryEvent, EventNomination $nomination)
    {
        $this->authorizeEvent($event);
        abort_unless((int) $categoryEvent->event_id === (int) $event->id
            && (int) $nomination->event_id === (int) $event->id
            && (int) $nomination->category_event_id === (int) $categoryEvent->id, 404);
        $nomination->delete();

        return redirect()->route('backend.interprovincial-trials.invitations.index', $event)
            ->with('success', 'Nomination removed.');
    }
    public function prepare(Request $request,Event $event,InvitationService $service) { $this->authorizeEvent($event); $service->prepare($event,$request->user()); return back()->with('success','Invitation list prepared for review.'); }
    public function review(Request $request,Event $event,InterprovincialTrialInvitationBatch $batch,InvitationService $service) { $this->authorizeBatch($event,$batch); $data=$request->validate(['snapshot_hash'=>['required','string','size:64']]); $service->review($batch,$request->user(),$data['snapshot_hash']); return back()->with('success','The exact recipient list has been reviewed.'); }
    public function send(Event $event,InterprovincialTrialInvitationBatch $batch,InvitationService $service) { $this->authorizeBatch($event,$batch); $service->queue($batch); return back()->with('success','Invitations were queued once through the managed mail service.'); }
    public function retry(Event $event,InterprovincialTrialInvitationBatch $batch,\App\Models\InterprovincialTrialInvitation $invitation,InvitationService $service) { $this->authorizeBatch($event,$batch); abort_unless((int)$invitation->batch_id===(int)$batch->id && (int)$invitation->event_id===(int)$event->id,404); $retried=$service->retryFailed($batch,$invitation); return back()->with('success',$retried?'The failed invitation was queued for retry.':'The invitation is already queued, sending, or sent.'); }
    private function authorizeBatch(Event $event,InterprovincialTrialInvitationBatch $batch): void { $this->authorizeEvent($event); abort_unless((int)$batch->event_id===(int)$event->id,404); }
    private function authorizeEvent(Event $event): void { abort_unless($event->isInterprovincialTrials(),404); $u=request()->user(); abort_unless($u&&($u->hasRole('super-user')||($u->hasRole('admin')&&$u->is_event_admin($event->id))),403); }
}
