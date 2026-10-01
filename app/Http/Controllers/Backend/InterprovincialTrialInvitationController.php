<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Player;
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterprovincialTrialInvitationController extends Controller
{
    private const NOMINATIONS_PER_PAGE = 100;
    private const HISTORICAL_INVITATIONS_PER_PAGE = 50;

    public function index(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $batch = $this->latestBatchForEvent($event);
        $event->load(['categoryEvents' => fn ($query) => $query
            ->with('category')
            ->withCount('nominations')
            ->orderBy('ordering')
            ->orderBy('id')]);
        $categoryIds = $event->categoryEvents->pluck('id');
        $nominations = EventNomination::query()
            ->with(['player:id,name,surname', 'categoryEvent.category:id,name'])
            ->where('event_id', $event->id)
            ->whereIn('category_event_id', $categoryIds)
            ->orderBy('category_event_id')
            ->orderBy('id')
            ->paginate(self::NOMINATIONS_PER_PAGE, ['*'], 'nominations_page')
            ->withQueryString();
        $invitations = $this->nominationInvitations($event, $nominations->getCollection());
        $successfulInitialIds = $this->successfulInitialInvitationIds($invitations);
        $failedFollowUpIds = $this->failedFollowUpInvitationIds($invitations);
        $latestInvitationsByNomination = $invitations->keyBy(fn (InterprovincialTrialInvitation $invitation): int => (int) $invitation->nomination_id);
        $nominationPresentations = $nominations->getCollection()->mapWithKeys(function (EventNomination $nomination) use ($latestInvitationsByNomination, $successfulInitialIds, $failedFollowUpIds): array {
            $invitation = $latestInvitationsByNomination->get((int) $nomination->id);

            return [$nomination->id => $this->invitationPresentation($invitation, $successfulInitialIds->contains((int) $invitation?->id), $failedFollowUpIds->contains((int) $invitation?->id))];
        });
        $historicalInvitations = $batch
            ? $this->batchInvitationQuery($batch, $event)
                ->with([
                    'player:id,name,surname',
                    'categoryEvent' => fn ($query) => $query->where('event_id', $event->id)->select(['id', 'event_id', 'category_id']),
                    'categoryEvent.category:id,name',
                ])
                ->whereNotIn('nomination_id', EventNomination::query()
                    ->select('id')
                    ->where('event_id', $event->id)
                    ->whereIn('category_event_id', $categoryIds))
                ->orderBy('id')
                ->paginate(self::HISTORICAL_INVITATIONS_PER_PAGE, ['*'], 'history_page')
                ->withQueryString()
            : null;
        if ($historicalInvitations) {
            $historicalInvitations->setCollection(
                $historicalInvitations->getCollection()
                    ->map(fn (InterprovincialTrialInvitation $invitation): array => $this->invitationPresentation($invitation))
            );
        }
        $nominationFilterGroups = collect([
            'all' => 'All',
            'nominated' => 'Nominated - not sent',
            'invited' => 'Awaiting response',
            'payment-pending' => 'Payment pending',
            'registered' => 'Registered',
            'declined-withdrawn' => 'Declined / withdrawn',
            'delivery-failed' => 'Delivery failed',
            'cancelled' => 'Cancelled',
        ])->map(fn (string $label, string $key): array => [
            'key' => $key,
            'label' => $label,
            'count' => $key === 'all'
                ? $nominationPresentations->count()
                : $nominationPresentations->where('filter_key', $key)->count(),
        ]);
        $registrationClosesAt = $event->registrationClosesAt()?->endOfDay();
        $registrationOpen = (bool) $event->published
            && (int) $event->signUp === 1
            && $event->hasOpenRegistrationLifecycle()
            && (! $registrationClosesAt || now()->lte($registrationClosesAt));
        $knownInvitationStates = [
            'prepared', 'queued', 'sending', 'sent', 'failed',
            'accepted_pending_payment', 'paid_confirmed', 'declined', 'withdrawn', 'cancelled',
        ];
        $invitationCounts = $batch
            ? $this->batchInvitationQuery($batch, $event)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
            : collect();
        $stateCounts = collect($knownInvitationStates)
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($invitationCounts[$status] ?? 0)])
            ->filter(fn (int $count): bool => $count > 0);
        $invitationCount = (int) $invitationCounts->sum();
        $readyRecipientCount = $batch ? $this->batchInvitationQuery($batch, $event)->whereNotNull('recipient_email')->count() : 0;
        $readiness = [
            'category_count' => $event->categoryEvents->count(),
            'nomination_count' => $nominations->total(),
            'invitation_count' => $invitationCount,
            'ready_recipient_count' => $readyRecipientCount,
            'blocked_recipient_count' => $invitationCount - $readyRecipientCount,
            'message_saved' => (bool) ($batch?->message_hash && filled($batch->email_subject) && filled($batch->email_body)),
            'message_reviewed' => (bool) ($batch?->message_hash && $batch?->reviewed_message_hash
                && hash_equals((string) $batch->message_hash, (string) $batch->reviewed_message_hash)),
            'registration_open' => $registrationOpen,
            'registration_closes_at' => $registrationClosesAt,
            'batch_status' => $batch?->status,
            'state_counts' => $stateCounts,
        ];
        return view('backend.interprovincial-trials.invitations', compact(
            'event', 'batch', 'readiness', 'latestInvitationsByNomination', 'nominationPresentations',
            'nominationFilterGroups', 'historicalInvitations', 'nominations'
        ));
    }

    public function players(Request $request, Event $event)
    {
        $this->authorizeEvent($event);
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $terms = preg_split('/\s+/u', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $search = implode(' ', $terms);
        abort_if(mb_strlen($search) < 2, 422, 'Enter at least two characters.');

        $players = Player::query()
            ->select('id', 'name', 'surname')
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $literalTerm = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
                    $pattern = "%{$literalTerm}%";
                    $query->where(function ($termQuery) use ($pattern): void {
                        $termQuery->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("surname LIKE ? ESCAPE '!'", [$pattern]);
                    });
                }
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
        $message = "{$added} nomination(s) added; {$alreadyNominated} already nominated. Send invitations when the list is complete.";

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
        $nominationQuery = EventNomination::query()
            ->with('player:id,name,surname')
            ->where('event_id', $categoryEvent->event_id)
            ->where('category_event_id', $categoryEvent->id)
            ->orderBy('id');
        $nominationCount = (clone $nominationQuery)->count();
        $nominations = $nominationQuery
            ->paginate(self::NOMINATIONS_PER_PAGE, ['*'], 'nominations_page', max(1, request()->integer('nominations_page', 1)));
        $event = $categoryEvent->event()->firstOrFail();
        $batch = $this->latestBatchForEvent($event);
        $batchInvitations = $this->nominationInvitations($event, $nominations->getCollection());
        $latestInvitations = $batchInvitations
            ->keyBy(fn (InterprovincialTrialInvitation $invitation): int => (int) $invitation->nomination_id);
        $successfulInitialIds = $this->successfulInitialInvitationIds($batchInvitations);
        $failedFollowUpIds = $this->failedFollowUpInvitationIds($batchInvitations);
        $presentations = $nominations->getCollection()->mapWithKeys(function (EventNomination $nomination) use ($latestInvitations, $successfulInitialIds, $failedFollowUpIds): array {
            $invitation = $latestInvitations->get((int) $nomination->id);
            return [$nomination->id => $this->invitationPresentation($invitation, $successfulInitialIds->contains((int) $invitation?->id), $failedFollowUpIds->contains((int) $invitation?->id))];
        });
        $rows = $nominations->getCollection()->map(fn (EventNomination $nomination): string => view(
            'backend.interprovincial-trials._nomination-row',
            [
                'event' => $event,
                'batch' => $batch,
                'nomination' => $nomination,
                'categoryEvent' => $categoryEvent,
                'presentation' => $presentations->get($nomination->id),
            ]
        )->render())->implode('');

        return [
            'message' => $message,
            'added' => $added,
            'already_nominated' => $alreadyNominated,
            'category_event_id' => $categoryEvent->id,
            'count' => $nominationCount,
            'html' => $rows,
            'pagination' => [
                'current_page' => $nominations->currentPage(),
                'last_page' => $nominations->lastPage(),
                'per_page' => $nominations->perPage(),
            ],
            'nominations' => $nominations->getCollection()->map(fn (EventNomination $nomination): array => [
                'id' => $nomination->id,
                'player_name' => trim(($nomination->player?->name ?? '').' '.($nomination->player?->surname ?? '')),
                'status_label' => $presentations->get($nomination->id)['label'],
                'status_key' => $presentations->get($nomination->id)['key'],
                'recipient_email' => $presentations->get($nomination->id)['invitation']?->recipient_email,
                'can_remove' => $presentations->get($nomination->id)['can_remove'],
                'destroy_url' => route('backend.interprovincial-trials.nominations.destroy', [
                    $categoryEvent->event_id,
                    $categoryEvent,
                    $nomination,
                ]),
            ])->values(),
        ];
    }

    private function latestBatchForEvent(Event $event): ?InterprovincialTrialInvitationBatch
    {
        return InterprovincialTrialInvitationBatch::query()
            ->where('event_id', $event->id)
            ->latest('id')
            ->first();
    }

    private function batchInvitationQuery(InterprovincialTrialInvitationBatch $batch, Event $event)
    {
        return InterprovincialTrialInvitation::query()
            ->where('batch_id', $batch->id)
            ->where('event_id', $event->id);
    }

    private function nominationInvitations(Event $event, \Illuminate\Support\Collection $nominations)
    {
        $query = InterprovincialTrialInvitation::query()
            ->where('event_id', $event->id)
            ->whereIn('nomination_id', $nominations->pluck('id'))
            ->whereHas('nomination', function ($query) use ($event): void {
                $query->where('event_id', $event->id)
                    ->whereColumn('category_event_id', 'interprovincial_trial_invitations.category_event_id')
                    ->whereColumn('player_id', 'interprovincial_trial_invitations.player_id');
            });

        // An email draft must not hide an existing checkout or paid registration.
        // Resolve one current lifecycle per visible nominee across every batch.
        $currentIds = (clone $query)
            ->selectRaw('COALESCE(MAX(CASE WHEN status != ? THEN id END), MAX(id))', ['prepared'])
            ->groupBy('nomination_id');

        return $query->select($this->invitationColumns())->whereIn('id', $currentIds)->get();
    }

    private function invitationColumns(): array
    {
        return [
            'id', 'batch_id', 'event_id', 'category_event_id', 'nomination_id', 'player_id',
            'registration_id', 'order_id', 'recipient_email', 'status',
            'queued_at', 'sent_at', 'accepted_at', 'paid_at', 'withdrawn_at',
        ];
    }

    private function invitationPresentation(?InterprovincialTrialInvitation $invitation, bool $hasSuccessfulInitial = false, bool $hasFailedFollowUp = false): array
    {
        $status = $invitation?->status;
        [$key, $filterKey, $label, $tone] = match ($status) {
            'queued', 'sending', 'sent' => ['invited', 'invited', 'Invited - awaiting response', 'primary'],
            InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT => ['payment-pending', 'payment-pending', 'Payment pending', 'warning'],
            InterprovincialTrialInvitation::PAID_CONFIRMED => ['registered', 'registered', 'Registered', 'success'],
            InterprovincialTrialInvitation::DECLINED => ['declined', 'declined-withdrawn', 'Declined', 'secondary'],
            InterprovincialTrialInvitation::WITHDRAWN => ['withdrawn', 'declined-withdrawn', 'Withdrawn', 'secondary'],
            'failed' => ['delivery-failed', 'delivery-failed', 'Delivery failed', 'danger'],
            'cancelled' => ['cancelled', 'cancelled', 'Cancelled', 'dark'],
            default => ['nominated', 'nominated', 'Nominated - not sent', 'info'],
        };

        return [
            'invitation' => $invitation,
            'key' => $key,
            'filter_key' => $filterKey,
            'label' => $label,
            'tone' => $tone,
            'can_remove' => ! $invitation || $status === 'prepared',
            'can_resend' => $hasSuccessfulInitial && in_array($status, ['sent', InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT], true),
            'can_retry_follow_up' => $hasFailedFollowUp && in_array($status, ['sent', InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT], true),
        ];
    }

    private function successfulInitialInvitationIds($invitations)
    {
        $ids = $invitations->pluck('id')->filter()->values();
        if ($ids->isEmpty()) return collect();

        return DB::table('interprovincial_trial_mail_dispatches as dispatches')
            ->join('bulk_email_logs as logs', 'logs.id', '=', 'dispatches.bulk_email_log_id')
            ->whereIn('dispatches.invitation_id', $ids)
            ->where('dispatches.kind', 'initial')
            ->where('logs.status', 'sent')
            ->whereNotNull('logs.sent_at')
            ->pluck('dispatches.invitation_id')
            ->map(fn ($id): int => (int) $id);
    }

    private function failedFollowUpInvitationIds($invitations)
    {
        $ids = $invitations->pluck('id')->filter()->values();
        if ($ids->isEmpty()) return collect();

        return DB::table('interprovincial_trial_mail_dispatches as dispatches')
            ->join('bulk_email_logs as logs', 'logs.id', '=', 'dispatches.bulk_email_log_id')
            ->whereIn('dispatches.invitation_id', $ids)
            ->where('dispatches.kind', 'follow_up')
            ->where('logs.status', 'failed')
            ->whereNull('logs.sent_at')
            ->pluck('dispatches.invitation_id')
            ->map(fn ($id): int => (int) $id);
    }
    public function prepare(Request $request,Event $event,InvitationService $service) { abort(404); }
    public function sendCurrent(Request $request, Event $event, InvitationService $service)
    {
        abort(404);
    }
    public function previewSend(Request $request, Event $event, InvitationService $service)
    {
        $this->authorizeEvent($event);
        $data = $request->validate([
            'mode' => ['required', 'in:new,not_registered,individual'],
            'invitation_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:150', 'not_regex:/[\r\n]/'],
            'body' => ['nullable', 'string', 'max:5000'],
            'from_address' => ['nullable', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'from_name' => ['nullable', 'string', 'max:100', 'not_regex:/[\r\n]/'],
            'reply_to' => ['nullable', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
        ]);

        return response()->json($service->previewAttempt($event, $request->user(), $data['mode'], $data['invitation_id'] ?? null, $data));
    }

    public function queuePreviewedSend(Request $request, Event $event, InvitationService $service)
    {
        $this->authorizeEvent($event);
        $notBlank = function (string $attribute, mixed $value, \Closure $fail): void {
            if (trim((string) $value) === '') $fail('The '.$attribute.' field must contain text.');
        };
        $data = $request->validate([
            'mode' => ['required', 'in:new,not_registered,individual'],
            'invitation_id' => ['nullable', 'integer'],
            'request_token' => ['required', 'uuid'],
            'recipient_hash' => ['required', 'string', 'size:64'],
            'composition_hash' => ['required', 'string', 'size:64'],
            'review_expires_at' => ['required', 'integer'], 'review_proof' => ['required', 'string', 'size:64'],
            'subject' => ['required', 'string', 'max:150', 'not_regex:/[\r\n]/', $notBlank],
            'body' => ['required', 'string', 'max:5000', $notBlank],
            'from_address' => ['required', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'from_name' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/', $notBlank],
            'reply_to' => ['required', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
        ]);
        $result = $service->queueAttempt($event, $request->user(), $data);

        return response()->json([
            'message' => $result['already_queued']
                ? 'This exact send request was already queued.'
                : $result['queued_count'].' invitation email(s) queued through the managed mail service; '.($result['skipped_count'] ?? 0).' nominee(s) without email were skipped.',
            'queued_count' => $result['queued_count'],
            'skipped_count' => $result['skipped_count'] ?? 0,
            'already_queued' => $result['already_queued'],
        ]);
    }
    public function saveMessage(Request $request, Event $event, InterprovincialTrialInvitationBatch $batch, InvitationService $service) { abort(404); }
    public function review(Request $request,Event $event,InterprovincialTrialInvitationBatch $batch,InvitationService $service) { abort(404); }
    public function send(Request $request, Event $event,InterprovincialTrialInvitationBatch $batch,InvitationService $service) { abort(404); }
    public function retry(Event $event,InterprovincialTrialInvitationBatch $batch,\App\Models\InterprovincialTrialInvitation $invitation,InvitationService $service) { $this->authorizeBatch($event,$batch); abort_unless((int)$invitation->batch_id===(int)$batch->id && (int)$invitation->event_id===(int)$event->id,404); $retried=$service->retryFailed($batch,$invitation); return back()->with('success',$retried?'The failed invitation was queued for retry.':'The invitation is already queued, sending, or sent.'); }
    public function retryFollowUp(Event $event, InterprovincialTrialInvitation $invitation, InvitationService $service)
    {
        $this->authorizeEvent($event);
        abort_unless((int) $invitation->event_id === (int) $event->id, 404);
        $retried = $service->retryFailedFollowUp($event, $invitation);

        return back()->with('success', $retried
            ? 'The failed follow-up was queued again with its original recipient and message.'
            : 'There is no failed follow-up to retry.');
    }
    private function authorizeBatch(Event $event,InterprovincialTrialInvitationBatch $batch): void { $this->authorizeEvent($event); abort_unless((int)$batch->event_id===(int)$event->id,404); }
    private function authorizeEvent(Event $event): void { abort_unless($event->isInterprovincialTrials(),404); $u=request()->user(); abort_unless($u&&($u->hasRole('super-user')||($u->hasRole('admin')&&$u->is_event_admin($event->id))),403); }
}
