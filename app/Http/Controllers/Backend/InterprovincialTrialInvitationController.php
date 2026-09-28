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
use Illuminate\Support\Facades\DB;

class InterprovincialTrialInvitationController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $batch = InterprovincialTrialInvitationBatch::with(['invitations.player', 'invitations.categoryEvent.category'])
            ->where('event_id', $event->id)->latest('id')->first();
        $event->load(['categoryEvents' => fn ($query) => $query->with(['category', 'nominations.player'])->orderBy('ordering')->orderBy('id')]);

        $invitations = $batch?->invitations ?? collect();
        $registrationClosesAt = $event->registrationClosesAt()?->endOfDay();
        $registrationOpen = (bool) $event->published
            && (int) $event->signUp === 1
            && $event->hasOpenRegistrationLifecycle()
            && (! $registrationClosesAt || now()->lte($registrationClosesAt));
        $knownInvitationStates = [
            'prepared', 'queued', 'sending', 'sent', 'failed',
            'accepted_pending_payment', 'paid_confirmed', 'withdrawn', 'cancelled',
        ];
        $stateCounts = collect($knownInvitationStates)
            ->mapWithKeys(fn (string $status): array => [$status => $invitations->where('status', $status)->count()])
            ->filter(fn (int $count): bool => $count > 0);
        $readiness = [
            'category_count' => $event->categoryEvents->count(),
            'nomination_count' => $event->categoryEvents->sum(fn (CategoryEvent $category): int => $category->nominations->count()),
            'invitation_count' => $invitations->count(),
            'ready_recipient_count' => $invitations->whereNotNull('recipient_email')->count(),
            'blocked_recipient_count' => $invitations->whereNull('recipient_email')->count(),
            'message_saved' => (bool) ($batch?->message_hash && filled($batch->email_subject) && filled($batch->email_body)),
            'message_reviewed' => (bool) ($batch?->message_hash && $batch?->reviewed_message_hash
                && hash_equals((string) $batch->message_hash, (string) $batch->reviewed_message_hash)),
            'registration_open' => $registrationOpen,
            'registration_closes_at' => $registrationClosesAt,
            'batch_status' => $batch?->status,
            'state_counts' => $stateCounts,
        ];

        return view('backend.interprovincial-trials.invitations', compact('event', 'batch', 'readiness'));
    }

    public function players(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $search = trim($data['q']);
        abort_if(mb_strlen($search) < 2, 422, 'Enter at least two characters.');

        $literalSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
        $pattern = "%{$literalSearch}%";
        $players = Player::query()
            ->select('id', 'name', 'surname')
            ->where(function ($query) use ($pattern): void {
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("surname LIKE ? ESCAPE '!'", [$pattern]);
            })
            ->orderBy('surname')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20, ['id', 'name', 'surname'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'results' => $players->getCollection()->map(fn (Player $player): array => [
                'id' => $player->id,
                'text' => trim($player->name.' '.$player->surname).' — Profile #'.$player->id,
            ])->values(),
            'pagination' => ['more' => $players->currentPage() < 10 && $players->hasMorePages()],
        ]);
    }

    public function nominate(Request $request, Event $event, CategoryEvent $categoryEvent)
    {
        $this->authorizeEvent($event);
        abort_unless((int) $categoryEvent->event_id === (int) $event->id, 404);
        $data = $request->validate([
            'player_ids' => ['required', 'array', 'min:1', 'max:50'],
            'player_ids.*' => ['required', 'integer', 'exists:players,id'],
        ]);
        $playerIds = collect($data['player_ids'])->map(fn ($id): int => (int) $id)->unique()->values();
        $added = DB::transaction(function () use ($event, $categoryEvent, $playerIds): int {
            $added = 0;
            foreach ($playerIds as $playerId) {
                $nomination = EventNomination::firstOrCreate([
                    'event_id' => $event->id,
                    'category_event_id' => $categoryEvent->id,
                    'player_id' => $playerId,
                ]);
                $added += $nomination->wasRecentlyCreated ? 1 : 0;
            }

            return $added;
        });
        $alreadyNominated = $playerIds->count() - $added;
        $message = "{$added} nomination(s) added; {$alreadyNominated} already nominated. Prepare a new invitation snapshot when the list is complete.";

        if ($request->expectsJson()) {
            return response()->json($this->categoryNominationPayload($categoryEvent, $message, $added, $alreadyNominated));
        }

        return redirect()->route('backend.interprovincial-trials.invitations.index', $event)
            ->with('success', $message);
    }

    public function nominateForCategory(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $data = $request->validate(['category_event_id' => ['required', 'integer', 'exists:category_events,id']]);

        return $this->nominate($request, $event, CategoryEvent::findOrFail($data['category_event_id']));
    }

    public function removeNomination(Event $event, CategoryEvent $categoryEvent, EventNomination $nomination)
    {
        $this->authorizeEvent($event);
        abort_unless((int) $categoryEvent->event_id === (int) $event->id
            && (int) $nomination->event_id === (int) $event->id
            && (int) $nomination->category_event_id === (int) $categoryEvent->id, 404);
        $nomination->delete();

        if (request()->expectsJson()) {
            return response()->json($this->categoryNominationPayload($categoryEvent, 'Nomination removed.'));
        }

        return redirect()->route('backend.interprovincial-trials.invitations.index', $event)
            ->with('success', 'Nomination removed.');
    }

    public function publication(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $data = $request->validate(['published' => ['required', 'boolean']]);
        DB::transaction(function () use ($event, $data): void {
            CategoryEvent::query()->where('event_id', $event->id)->lockForUpdate()->get();
            CategoryEvent::query()->where('event_id', $event->id)
                ->update(['nominations_published' => (bool) $data['published']]);
        });

        return back()->with('success', $data['published']
            ? 'All nomination categories are now public.'
            : 'All nomination categories are now private.');
    }

    private function categoryNominationPayload(CategoryEvent $categoryEvent, string $message, int $added = 0, int $alreadyNominated = 0): array
    {
        $nominations = EventNomination::query()
            ->with('player:id,name,surname')
            ->where('event_id', $categoryEvent->event_id)
            ->where('category_event_id', $categoryEvent->id)
            ->orderBy('id')
            ->get();

        return [
            'message' => $message,
            'added' => $added,
            'already_nominated' => $alreadyNominated,
            'category_event_id' => $categoryEvent->id,
            'count' => $nominations->count(),
            'nominations' => $nominations->map(fn (EventNomination $nomination): array => [
                'id' => $nomination->id,
                'player_name' => trim(($nomination->player?->name ?? '').' '.($nomination->player?->surname ?? '')),
                'destroy_url' => route('backend.interprovincial-trials.nominations.destroy', [
                    $categoryEvent->event_id,
                    $categoryEvent,
                    $nomination,
                ]),
            ])->values(),
        ];
    }
    public function prepare(Request $request,Event $event,InvitationService $service) { $this->authorizeEvent($event); $service->prepare($event,$request->user()); return back()->with('success','Invitation list prepared for review.'); }
    public function saveMessage(Request $request, Event $event, InterprovincialTrialInvitationBatch $batch, InvitationService $service) { $this->authorizeBatch($event, $batch); $notBlank = function (string $attribute, mixed $value, \Closure $fail): void { if (trim((string) $value) === '') { $fail('The '.$attribute.' field must contain text.'); } }; $data = $request->validate(['email_subject' => ['required', 'string', 'max:150', 'not_regex:/[\r\n]/', $notBlank], 'email_body' => ['required', 'string', 'max:5000', $notBlank]]); $service->saveMessage($batch, trim($data['email_subject']), trim($data['email_body'])); return back()->with('success', 'Invitation subject and message saved. Review the exact message and recipients before queueing.'); }
    public function review(Request $request,Event $event,InterprovincialTrialInvitationBatch $batch,InvitationService $service) { $this->authorizeBatch($event,$batch); $data=$request->validate(['snapshot_hash'=>['required','string','size:64'], 'message_hash'=>['required','string','size:64']]); $service->review($batch,$request->user(),$data['snapshot_hash'],$data['message_hash']); return back()->with('success','The exact recipients and stored message have been reviewed.'); }
    public function send(Request $request, Event $event,InterprovincialTrialInvitationBatch $batch,InvitationService $service) { $this->authorizeBatch($event,$batch); $request->validate(['confirm_exact_recipients_and_message' => ['accepted']]); $service->queue($batch); return back()->with('success','Invitations were queued once through the managed mail service.'); }
    public function retry(Event $event,InterprovincialTrialInvitationBatch $batch,\App\Models\InterprovincialTrialInvitation $invitation,InvitationService $service) { $this->authorizeBatch($event,$batch); abort_unless((int)$invitation->batch_id===(int)$batch->id && (int)$invitation->event_id===(int)$event->id,404); $retried=$service->retryFailed($batch,$invitation); return back()->with('success',$retried?'The failed invitation was queued for retry.':'The invitation is already queued, sending, or sent.'); }
    private function authorizeBatch(Event $event,InterprovincialTrialInvitationBatch $batch): void { $this->authorizeEvent($event); abort_unless((int)$batch->event_id===(int)$event->id,404); }
    private function authorizeEvent(Event $event): void { abort_unless($event->isInterprovincialTrials(),404); $u=request()->user(); abort_unless($u&&($u->hasRole('super-user')||($u->hasRole('admin')&&$u->is_event_admin($event->id))),403); }
}
