<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{BulkEmailLog, CampCoachLink, CampCoachSubmission, CampMailReview, CampNominee, Event, Player, PracticeCampPlan};
use App\Services\Camps\{CampAdmission, CampInvitations, CampWorkflow};
use App\Services\PracticeCampPlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampManagementController extends Controller
{
    public function attach(Request $request, Event $event, CampWorkflow $workflow)
    {
        $plan = $workflow->attach($request->user(), $event);
        return redirect()->route('backend.camps.show', $plan)->with('status', 'Camp workspace ready. Start with camp setup.');
    }

    public function show(Request $request, PracticeCampPlan $plan)
    {
        app(PracticeCampPlanner::class)->authorizePlan($request->user(), $plan);
        $config = app(CampAdmission::class)->configuration($plan->event_id);
        $divisions = $plan->event->categoryEvents()->with('category')->orderBy('id')->get();
        $nominees = CampNominee::where('event_id', $plan->event_id)->with('player', 'categoryEvent.category')->orderByDesc('id')->paginate(50, ['*'], 'nominees_page');
        $links = CampCoachLink::where('event_id', $plan->event_id)->latest('id')->paginate(20, ['*'], 'links_page');
        $submissions = CampCoachSubmission::where('event_id', $plan->event_id)->with('coachLink')->latest('id')->paginate(30, ['*'], 'submissions_page');
        $search = $request->validate(['player_search' => 'nullable|string|min:2|max:100'])['player_search'] ?? null;
        $players = $search ? Player::where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('surname', 'like', '%'.$search.'%'))
            ->orderBy('surname')->limit(30)->get(['id', 'name', 'surname', 'dateOfBirth']) : collect();
        $candidateIds = collect($plan->candidates)->take(500)->pluck('player_id')->filter()->unique();
        $playerChoices = Player::whereIn('id', $candidateIds)->limit(500)->get(['id', 'name', 'surname', 'dateOfBirth', 'gender'])
            ->merge($players)->unique('id')->sortBy(fn ($player) => $player->surname.' '.$player->name);
        $coachChoices = [];
        foreach ($submissions->where('status', 'pending') as $submission) {
            $coachChoices[$submission->id] = Player::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($submission->name))])
                ->whereRaw('LOWER(TRIM(surname)) = ?', [mb_strtolower(trim($submission->surname))])->whereDate('dateOfBirth', $submission->date_of_birth)
                ->limit(10)->get(['id', 'name', 'surname', 'dateOfBirth'])->merge($players)->unique('id')->values();
        }
        $logs = BulkEmailLog::where('mail_type', 'camp_invitation')->where('payload->event_id', $plan->event_id)->latest('id')->paginate(30, ['*'], 'mail_page');
        $recorded = CampNominee::where('event_id', $plan->event_id)->whereIn('player_id', $candidateIds)->limit(15000)->get(['player_id', 'category_event_id', 'status']);
        $rosterRows = collect($plan->candidates)->take(500)->map(function ($candidate) use ($playerChoices, $config, $plan, $recorded) {
            $player = $playerChoices->firstWhere('id', $candidate['player_id'] ?? null);
            $matches = collect($config?->divisions ?? [])->filter(function ($rule) use ($player, $plan) {
                if (! $player?->dateOfBirth) return false;
                $age = $plan->event->start_date->year - \Carbon\Carbon::parse($player->dateOfBirth)->year;
                return $age >= $rule['min_age'] && $age <= $rule['max_age'] && (! $rule['gender'] || (int) $rule['gender'] === (int) $player->gender);
            });
            $decision = $recorded->where('player_id', $candidate['player_id'] ?? null)->first(fn ($nominee) => $nominee->status !== 'approved'
                || ($matches->count() === 1 && (int) $nominee->category_event_id === (int) $matches->keys()->first()));
            return ['candidate' => $candidate, 'player' => $player, 'matches' => $matches, 'decision' => $decision?->status];
        });
        return view('backend.practice-camps.manage', compact('plan', 'config', 'divisions', 'nominees', 'links', 'submissions', 'players', 'playerChoices', 'coachChoices', 'logs', 'rosterRows'));
    }

    public function approveRoster(Request $request, PracticeCampPlan $plan, CampWorkflow $workflow)
    {
        app(PracticeCampPlanner::class)->authorizePlan($request->user(), $plan);
        $data = $request->validate(['revision' => 'required|uuid', 'keys' => 'required|array|min:1|max:200',
            'keys.*' => 'required|string|distinct|max:100', 'division_ids' => 'required|array|max:500',
            'division_ids.*' => 'nullable|integer', 'reason' => 'required|string|max:2000', 'identity_confirmed' => 'accepted'], [
                'keys.required' => 'Choose at least one roster player to approve.',
                'keys.max' => 'Choose up to 200 roster players at a time.',
                'identity_confirmed.accepted' => 'Confirm that you reviewed the selected players.',
            ], ['keys' => 'players', 'division_ids' => 'camp divisions', 'reason' => 'review note']);
        DB::transaction(function () use ($request, $plan, $workflow, $data) {
            app(CampAdmission::class)->lockEvents([$plan->event_id]);
            $plan = PracticeCampPlan::lockForUpdate()->findOrFail($plan->id);
            app(PracticeCampPlanner::class)->authorizePlan($request->user(), $plan);
            abort_unless(hash_equals($plan->revision, $data['revision']), 409, 'The draft changed. Reload and review the roster selection again.');
            $rows = [];
            foreach ($data['keys'] as $key) {
                $candidate = collect($plan->candidates)->firstWhere('key', $key);
                abort_unless($candidate && ! empty($candidate['player_id']) && $key === 'player:'.$candidate['player_id'], 422, 'Imported or unresolved players require individual identity review.');
                $division = (int) ($data['division_ids'][$candidate['player_id']] ?? 0);
                abort_unless($division, 422, 'Choose a camp division for each selected player.');
                $rows[] = [$candidate, $division];
            }
            // Stable division order matches the canonical category lock order.
            usort($rows, fn ($a, $b) => [$a[1], $a[0]['player_id']] <=> [$b[1], $b[0]['player_id']]);
            foreach ($rows as [$candidate, $division]) {
                $workflow->approve($request->user(), $plan, (int) $candidate['player_id'], $division,
                    json_encode(['candidate_key' => $candidate['key'], 'sources' => $candidate['sources'], 'review_reason' => $data['reason']], JSON_THROW_ON_ERROR));
            }
        });
        return back()->with('status', count($data['keys']).' roster players approved. No invitations sent.');
    }

    public function settings(Request $request, PracticeCampPlan $plan, CampWorkflow $workflow)
    {
        $data = $request->validate([
            'revision' => 'required|uuid', 'name' => 'required|string|max:255', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date',
            'dates_provisional' => 'sometimes|boolean', 'venue' => 'required|string|max:2000', 'organizer' => 'required|string|max:255', 'email' => 'required|email|max:255',
            'entry_fee' => 'required|numeric|min:0|max:100000', 'capacity' => 'nullable|integer|min:1|max:100000',
            'method' => 'required|in:open,invitation_only,open_with_invitations', 'registration_closes_at' => 'required|date|before_or_equal:start_date',
            'coach_closes_at' => 'nullable|date|before_or_equal:start_date', 'coach_min_age' => 'required|integer|min:0|max:100', 'coach_max_age' => 'required|integer|gte:coach_min_age|max:100',
            'withdrawal_deadline' => 'nullable|date|before_or_equal:start_date',
            'divisions' => 'required|array|min:1|max:30', 'divisions.*.id' => 'nullable|integer|distinct', 'divisions.*.name' => 'required|string|max:100',
            'divisions.*.min_age' => 'required|integer|min:0|max:100', 'divisions.*.max_age' => 'required|integer|gte:divisions.*.min_age|max:100',
            'divisions.*.gender' => 'required|integer|in:0,1,2', 'divisions.*.fee' => 'required|numeric|min:0|max:100000', 'divisions.*.limit' => 'nullable|integer|min:1|max:100000',
        ]);
        $workflow->settings($request->user(), $plan, $data);
        return back()->with('status', 'Camp settings saved. Publication and registration switches were not changed.');
    }

    public function approve(Request $request, PracticeCampPlan $plan, CampWorkflow $workflow)
    {
        app(PracticeCampPlanner::class)->authorizePlan($request->user(), $plan);
        $data = $request->validate(['player_id' => 'required|integer|exists:players,id', 'division_id' => 'required|integer',
            'candidate_key' => 'nullable|string|max:100', 'reason' => 'required|string|max:2000', 'identity_confirmed' => 'accepted']);
        $provenance = 'Manual organiser nomination: '.$data['reason'];
        if (! empty($data['candidate_key'])) {
            $row = collect($plan->candidates)->firstWhere('key', $data['candidate_key']);
            abort_unless($row, 404);
            abort_if($row['player_id'] && (int) $row['player_id'] !== (int) $data['player_id'], 422, 'Use the exact player profile from this candidate.');
            $provenance = json_encode(['candidate_key' => $row['key'], 'sources' => $row['sources'], 'review_reason' => $data['reason']], JSON_THROW_ON_ERROR);
        }
        $workflow->approve($request->user(), $plan, $data['player_id'], $data['division_id'], $provenance);
        return back()->with('status', 'Camp nominee approved. No invitation was sent.');
    }

    public function revokeNominee(Request $request, PracticeCampPlan $plan, CampNominee $nominee)
    {
        app(PracticeCampPlanner::class)->authorizePlan($request->user(), $plan);
        abort_unless((int) $nominee->event_id === (int) $plan->event_id, 404);
        DB::transaction(function () use ($request, $plan, $nominee) {
            app(CampAdmission::class)->lockEvents([$plan->event_id]);
            $nominee = CampNominee::lockForUpdate()->findOrFail($nominee->id);
            abort_if(in_array(app(CampInvitations::class)->participation($nominee), ['paid', 'checkout_pending'], true), 422, 'Resolve the active entry or checkout first.');
            $nominee->update(['status' => 'revoked']);
            activity('camp')->performedOn($nominee)->causedBy($request->user())->log('Revoked camp nomination');
        });
        return back()->with('status', 'Nomination revoked; history retained.');
    }

    public function coach(Request $request, PracticeCampPlan $plan, CampWorkflow $workflow)
    {
        $data = $request->validate(['coach_name' => 'required|string|max:255', 'expires_at' => 'required|date|after:now']);
        return back()->with('coach_link', $workflow->coach($request->user(), $plan, $data['coach_name'], $data['expires_at']))
            ->with('status', 'Private coach link created. Copy it from the panel below.');
    }

    public function revokeCoach(Request $request, PracticeCampPlan $plan, CampCoachLink $link)
    {
        app(PracticeCampPlanner::class)->authorizePlan($request->user(), $plan);
        abort_unless((int) $link->event_id === (int) $plan->event_id, 404);
        $link->update(['revoked_at' => now()]);
        return back()->with('status', 'Coach link revoked.');
    }

    public function reviewCoach(Request $request, PracticeCampPlan $plan, CampCoachSubmission $submission, CampWorkflow $workflow)
    {
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'player_id' => 'nullable|integer|exists:players,id',
            'create_new' => 'sometimes|boolean', 'division_id' => 'required_if:decision,approve|nullable|integer', 'identity_confirmed' => 'accepted_if:decision,approve']);
        $workflow->reviewCoach($request->user(), $plan, $submission, $data);
        return back()->with('status', 'Coach submission reviewed. No invitation was sent.');
    }

    public function mailReview(Request $request, PracticeCampPlan $plan, CampInvitations $invitations)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer|distinct',
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'], 'message' => 'required|string|max:10000']);
        $review = $invitations->review($request->user(), $plan, $data['ids'], $data['subject'], $data['message']);
        return view('backend.practice-camps.mail-review', compact('plan', 'review'));
    }

    public function mailQueue(Request $request, PracticeCampPlan $plan, CampMailReview $review, CampInvitations $invitations)
    {
        $request->validate(['confirmed' => 'accepted']);
        $invitations->queue($request->user(), $plan, $review);
        return redirect()->route('backend.camps.show', $plan)->with('status', 'Reviewed invitations queued through managed mail. Queued does not mean delivered.');
    }
}
