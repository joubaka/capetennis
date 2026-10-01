<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, TrialProgramme, TrialSquadDraft, TrialSquadSlot, TrialPaymentProof, RegistrationOrder};
use App\Services\InterprovincialTrials\{TrialProgrammeService, TrialSquadService, ManualCollectionService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrialProgrammeController extends Controller
{
    public function index(Request $request, Event $event, TrialProgrammeService $service)
    {
        $service->authorize($event, $request->user());
        $programme = TrialProgramme::with('currentRun')->where('event_id', $event->id)->first();
        $draft = TrialSquadDraft::where('event_id', $event->id)->latest('id')->first();
        $slots = $draft?->slots()->with(['player', 'categoryEvent.category'])->orderBy('category_event_id')->orderBy('tier')->orderBy('slot')->get() ?? collect();
        $proofs = TrialPaymentProof::with('order')->where('event_id', $event->id)->latest('id')->paginate(25);
        $proposals = $draft ? \App\Models\TrialReplacementProposal::where('draft_id', $draft->id)->with(['source.player', 'target.player'])->latest('id')->limit(50)->get() : collect();
        $orders = RegistrationOrder::whereHas('items.category_event', fn ($q) => $q->where('event_id', $event->id))->where('pay_status', false)->latest('id')->paginate(25, ['*'], 'orders_page');
        $withdrawn = \App\Models\CategoryEventRegistration::whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->where('status', 'withdrawn')->with(['registration.players', 'categoryEvent.category'])->latest('id')->paginate(25, ['*'], 'withdrawn_page');
        $participations = \App\Models\TrialParticipation::where('event_id', $event->id)->with(['player', 'order', 'slot'])->latest('id')->paginate(25, ['*'], 'participations_page');
        $participationProofs = \App\Models\TrialParticipationProof::whereHas('participation', fn ($q) => $q->where('event_id', $event->id))->with(['participation.player', 'order'])->where('status', 'pending')->latest('id')->paginate(25, ['*'], 'participation_proofs_page');
        $paymentByPlayer = \App\Models\TrialParticipation::with('order')->where('event_id', $event->id)->whereIn('player_id', $slots->pluck('player_id')->filter())->get()->keyBy('player_id');
        $declarations = $event->nominations()->with(['player', 'categoryEvent.category'])->whereNotNull('player_id')->orderBy('id')->paginate(25, ['*'], 'declarations_page');
        $categoryReadiness = $event->categoryEvents()->with(['category', 'draws.drawFixtures'])
            ->withCount(['categoryEventRegistrations as paid_count' => fn ($q) => $q->where('payment_status_id', 1)])
            ->get()->map(function ($category) use ($event, $programme) {
                $fixtures = $category->draws->flatMap->drawFixtures;
                $unresolved = $fixtures->filter(fn ($fixture) => !in_array((int) $fixture->match_status, [1, 3, 5], true)
                    || ((int) $fixture->match_status !== 5 && !in_array((int) $fixture->winner_registration, array_filter([(int) $fixture->registration1_id, (int) $fixture->registration2_id]), true)))->count();
                $message = !$category->paid_count ? 'No paid registrations yet.'
                    : ($fixtures->isEmpty() ? 'Draw and matches not ready.'
                    : ($unresolved ? $unresolved.' match(es) still need a result or withdrawal decision.'
                    : ($programme?->concluded_at ? 'Final positions ready.' : 'Matches complete. Check finishing positions and resolve any ties.')));
                return ['name' => $category->category?->name, 'message' => $message,
                    'href' => route($fixtures->isEmpty() || $unresolved ? 'headOffice.show' : 'admin.events.results.individual', $event)];
            });
        $journey = match (true) {
            !$programme?->region_id => ['label' => 'Setup', 'action' => 'Confirm the event region and save regional settings.', 'href' => '#trial-settings'],
            (bool) $draft?->needs_review => ['label' => 'Selection review required', 'action' => 'Review corrected results and affected team places.', 'href' => '#trial-teams'],
            $draft?->status === 'finalised' => ['label' => 'Participation and follow-up', 'action' => 'Follow up responses, payments and replacement places. Invitations are optional.', 'href' => '#trial-participation'],
            (bool) $draft => ['label' => 'Draft teams', 'action' => 'Review selections and vacancies, then finalise all teams together.', 'href' => '#trial-teams'],
            (bool) $programme?->concluded_at => ['label' => 'Team selection', 'action' => 'Check colour declarations and choose the teams to generate.', 'href' => '#trial-declarations'],
            default => ['label' => 'Registration and Trials results', 'action' => 'Publish nominees, verify payments and complete category matches.', 'href' => '#trial-results'],
        };
        $replacementCandidates = collect();
        if ($draft?->status === 'finalised') {
            foreach ($slots->where('reserve', false)->filter(fn ($slot) => !$slot->player_id || $slot->response === 'declined') as $target) {
                $replacementCandidates[$target->id] = app(\App\Services\InterprovincialTrials\TrialSelectionReviewService::class)->eligibleSources($draft, $target, $slots)
                    ->filter(fn ($candidate) => !($paymentByPlayer->get($candidate->player_id)?->order?->payfast_handed_off_at && !$paymentByPlayer->get($candidate->player_id)?->order?->pay_status));
            }
        }
        return view('backend.interprovincial-trials.programme', compact('event', 'programme', 'draft', 'slots', 'proofs', 'proposals', 'orders', 'withdrawn', 'participations', 'participationProofs', 'paymentByPlayer', 'declarations', 'categoryReadiness', 'journey', 'replacementCandidates'));
    }

    public function settings(Request $request, Event $event, TrialProgrammeService $service)
    {
        $service->authorize($event, $request->user());
        $data = $request->validate(['bank_details' => 'nullable|string|max:2000', 'participation_fee' => 'required|decimal:0,2|min:0|max:999999.99',
            'response_deadline' => 'nullable|date', 'payment_deadline' => 'nullable|date', 'withdrawal_deadline' => 'nullable|date']);
        $regions = DB::table('event_regions')->where('event_id', $event->id)->pluck('region_id')->unique();
        if ($regions->count() !== 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['region' => 'A regional Trials programme must have exactly one region.']);
        }
        TrialProgramme::updateOrCreate(['event_id' => $event->id], $data + ['region_id' => $regions->first()]);
        activity('interprovincial-trials')->performedOn($event)->causedBy($request->user())->log('Regional payment details and participation deadlines updated');
        return back()->with('success', 'Regional payment settings saved.');
    }

    public function generate(Request $request, Event $event, TrialSquadService $service)
    {
        $data = $request->validate(['team_count' => 'required|integer|min:1|max:6']);
        $service->generate($event, array_slice(range('A', 'Z'), 0, (int) $data['team_count']), $request->user());
        return back()->with('success', 'Draft teams generated for all age/gender categories. Review before finalising.');
    }

    public function swap(Request $request, Event $event, TrialSquadDraft $draft, TrialSquadService $service)
    {
        abort_unless((int) $draft->event_id === (int) $event->id, 404);
        $data = $request->validate(['first_slot' => 'required|integer', 'second_slot' => 'required|integer|different:first_slot', 'reason' => 'required|string|max:500']);
        $service->swap($draft, TrialSquadSlot::findOrFail($data['first_slot']), TrialSquadSlot::findOrFail($data['second_slot']), $request->user(), $data['reason']);
        return back()->with('success', 'Draft selection updated.');
    }

    public function finalise(Request $request, Event $event, TrialSquadDraft $draft, TrialSquadService $service)
    {
        abort_unless((int) $draft->event_id === (int) $event->id, 404);
        $request->validate(['allow_vacancies' => 'nullable|boolean']);
        $service->finalise($draft, $request->user(), $request->boolean('allow_vacancies'));
        return back()->with('success', 'All teams finalised and published. Sending invitations remains optional.');
    }

    public function review(Request $request, Event $event, TrialSquadDraft $draft, TrialSquadService $service)
    {
        abort_unless((int) $draft->event_id === (int) $event->id, 404);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $service->acknowledgeCorrections($draft, $request->user(), $data['reason']);
        return back()->with('success', 'Corrected results reviewed. Roster retained.');
    }

    public function verify(Request $request, Event $event, TrialPaymentProof $proof, ManualCollectionService $service)
    {
        abort_unless((int) $proof->event_id === (int) $event->id, 404);
        $data = $request->validate(['reference' => 'required|string|min:3|max:120']);
        $service->verifyProof($proof, $request->user(), $data['reference']);
        return back()->with('success', 'EFT receipt verified and registration confirmed.');
    }

    public function markPaid(Request $request, Event $event, RegistrationOrder $order, ManualCollectionService $service)
    {
        abort_unless($order->items()->whereHas('category_event', fn ($query) => $query->where('event_id', $event->id))->exists(), 404);
        $data = $request->validate(['reference' => 'required|string|min:3|max:120']);
        $service->markPaid($order, $request->user(), $data['reference']);
        return back()->with('success', 'Manual payment received and recorded.');
    }

    public function respond(Request $request, Event $event, TrialSquadSlot $slot, \App\Services\InterprovincialTrials\TrialSelectionReviewService $service)
    {
        abort_unless((int) $slot->draft->event_id === (int) $event->id, 404);
        $data = $request->validate(['response' => 'required|in:pending,confirmed,declined', 'reason' => 'required|string|max:500']);
        $service->respond($slot, $request->user(), $data['response'], $data['reason']);
        return back()->with('success', 'Administrative response recorded with its reason.');
    }

    public function propose(Request $request, Event $event, TrialSquadSlot $slot, \App\Services\InterprovincialTrials\TrialSelectionReviewService $service)
    {
        abort_unless((int) $slot->draft->event_id === (int) $event->id, 404);
        $data = $request->validate(['source_slot' => 'nullable|integer']);
        $service->propose($slot, $request->user(), isset($data['source_slot']) ? (int) $data['source_slot'] : null);
        return back()->with('success', 'Replacement proposed for review. The roster is unchanged until approval.');
    }

    public function approve(Request $request, Event $event, \App\Models\TrialReplacementProposal $proposal, \App\Services\InterprovincialTrials\TrialSelectionReviewService $service)
    {
        abort_unless((int) $proposal->draft->event_id === (int) $event->id, 404);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $service->approve($proposal, $request->user(), $data['reason']);
        return back()->with('success', 'Promotion approved. Review the resulting lower team vacancy.');
    }

    public function colour(Request $request, Event $event, \App\Models\Player $player, \App\Services\InterprovincialTrials\TrialSelectionReviewService $service)
    {
        $data = $request->validate(['is_player_of_colour' => 'required|boolean', 'reason' => 'required|string|max:500']);
        $service->colour($event, $player, $request->user(), (bool) $data['is_player_of_colour'], $data['reason']);
        return back()->with('success', 'Colour declaration recorded. Review affected team criteria.');
    }

    public function disposition(Request $request, Event $event, \App\Models\CategoryEventRegistration $entry, TrialProgrammeService $service)
    {
        $service->authorize($event, $request->user());
        abort_unless((int) $entry->categoryEvent->event_id === (int) $event->id && $entry->status === 'withdrawn', 404);
        $data = $request->validate(['disposition' => 'required|in:retain,last,exclude', 'reason' => 'required|string|max:500']);
        DB::transaction(function () use ($event, $entry, $request, $data) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            DB::table('trial_ranking_dispositions')->updateOrInsert(['entry_id' => $entry->id], $data + ['event_id' => $event->id, 'changed_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => now()]);
            activity('interprovincial-trials')->performedOn($entry)->causedBy($request->user())->withProperties($data)->log('Withdrawn Trials ranking decision recorded');
        });
        $service->refresh($event);
        return back()->with('success', 'Withdrawn player ranking decision recorded.');
    }

    public function refundRequest(Request $request, Event $event, \App\Models\CategoryEventRegistration $entry, \App\Domain\Finance\Services\RefundRequestService $service)
    {
        abort_unless((int) $entry->categoryEvent->event_id === (int) $event->id, 404);
        $service->requestTrialManualRefund($entry, $request->user());
        return back()->with('success', 'Manual refund request recorded using the existing withdrawal fee.');
    }

    public function refundComplete(Request $request, Event $event, \App\Models\CategoryEventRegistration $entry, \App\Domain\Refunds\Services\RefundExecutionService $service)
    {
        abort_unless((int) $entry->categoryEvent->event_id === (int) $event->id, 404);
        $data = $request->validate(['reference' => 'required|string|min:3|max:120', 'externally_paid' => 'accepted']);
        $service->completeTrialManualRefund($entry, $request->user(), $data['reference']);
        return back()->with('success', 'Externally paid refund recorded. Original payment history retained.');
    }

    public function participationPaid(Request $request, Event $event, \App\Models\TrialParticipation $participation, \App\Services\InterprovincialTrials\TrialParticipationService $service)
    {
        abort_unless((int) $participation->event_id === (int) $event->id, 404);
        $data = $request->validate(['reference' => 'required|string|min:3|max:120']);
        $service->markPaid($participation, $request->user(), $data['reference']);
        return back()->with('success', 'Participation receipt recorded.');
    }

    public function participationVerify(Request $request, Event $event, \App\Models\TrialParticipationProof $proof, \App\Services\InterprovincialTrials\TrialParticipationService $service)
    {
        abort_unless((int) $proof->participation->event_id === (int) $event->id, 404);
        $data = $request->validate(['reference' => 'required|string|min:3|max:120']);
        $service->verifyProof($proof, $request->user(), $data['reference']);
        return back()->with('success', 'Participation EFT payment verified.');
    }

    public function participationCollect(Request $request, Event $event, TrialSquadSlot $slot, \App\Services\InterprovincialTrials\TrialParticipationService $service)
    {
        app(TrialProgrammeService::class)->authorize($event, $request->user());
        abort_unless((int) $slot->draft->event_id === (int) $event->id, 404);
        $data = $request->validate(['reference' => 'required|string|min:3|max:120']);
        DB::transaction(function () use ($slot, $request, $service, $data) {
            $participation = $service->begin($slot, $request->user());
            $service->markPaid($participation, $request->user(), $data['reference']);
        });
        return back()->with('success', 'Administrative participation payment received and recorded.');
    }

    public function remove(Request $request, Event $event, TrialSquadSlot $slot, \App\Services\InterprovincialTrials\TrialSelectionReviewService $service)
    {
        abort_unless((int) $slot->draft->event_id === (int) $event->id, 404);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $service->remove($slot, $request->user(), $data['reason']);
        return back()->with('success', 'Player removed with an audited reason. Review the retained vacancy.');
    }

    public function rejectProof(Request $request, Event $event, TrialPaymentProof $proof, ManualCollectionService $service)
    {
        abort_unless((int) $proof->event_id === (int) $event->id, 404);
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $service->rejectProof($proof, $request->user(), $data['reason']);
        return back()->with('success', 'Proof rejected with its reason. Payment remains unconfirmed.');
    }

    public function participationRefund(Request $request, Event $event, \App\Models\TrialParticipation $participation)
    {
        abort_unless((int) $participation->event_id === (int) $event->id, 404);
        app(TrialProgrammeService::class)->authorize($event, $request->user());
        $data = $request->validate(['method' => 'required|in:bank,payfast']);
        app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->withdraw($participation, $request->user());
        app(\App\Domain\Finance\Services\RefundRequestService::class)->requestTrialTeamRefund($participation->fresh(), $request->user(), $data['method']);
        if ($data['method'] === 'payfast') {
            app(\App\Domain\Refunds\Services\RefundExecutionService::class)->executeTrialTeamPayfastRefund($participation->fresh(), $request->user());
        }
        return back()->with('success', 'Participation withdrawn and refund processed or flagged for manual payment.');
    }

    public function participationRefundComplete(Request $request, Event $event, \App\Models\TrialParticipation $participation)
    {
        abort_unless((int) $participation->event_id === (int) $event->id, 404);
        $data = $request->validate(['reference' => 'required|string|min:3|max:120', 'externally_paid' => 'accepted']);
        app(\App\Domain\Refunds\Services\RefundExecutionService::class)->completeTrialTeamManualRefund($participation, $request->user(), $data['reference']);
        return back()->with('success', 'Externally paid participation refund recorded.');
    }
}
