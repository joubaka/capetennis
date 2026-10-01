<?php

namespace App\Services\InterprovincialTrials;

use App\Models\{Event, Player, TrialProgramme, TrialSquadDraft, TrialSquadSlot, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialSquadService
{
    public function generate(Event $event, array $tiers, User $actor): TrialSquadDraft
    {
        app(TrialProgrammeService::class)->authorize($event, $actor);
        if ($tiers === [] || $tiers !== array_slice(range('A', 'Z'), 0, count($tiers))) {
            throw ValidationException::withMessages(['tiers' => 'Choose consecutive teams starting with A.']);
        }
        return DB::transaction(function () use ($event, $tiers, $actor) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $programme = TrialProgramme::where('event_id', $event->id)->lockForUpdate()->first();
            if (! $programme?->concluded_at || ! $programme->currentRun) {
                throw ValidationException::withMessages(['teams' => 'Complete Trials and resolve all finishing positions before generating teams.']);
            }
            if (TrialSquadDraft::where('event_id', $event->id)->exists()) {
                throw ValidationException::withMessages(['teams' => 'A draft already exists. Review it rather than replace its history.']);
            }
            $draft = TrialSquadDraft::create(['event_id' => $event->id, 'ranking_run_id' => $programme->current_run_id,
                'tiers' => $tiers, 'created_by' => $actor->id]);
            foreach (collect($programme->currentRun->positions)->groupBy('category_event_id') as $categoryId => $rows) {
                $pool = $rows->sortBy('position')->pluck('player_id')->map(fn ($id) => (int) $id)->values();
                $players = Player::whereIn('id', $pool)->get()->keyBy('id');
                foreach ($tiers as $tier) {
                    $merit = $pool->take(4)->values();
                    $pool = $pool->diff($merit)->values();
                    $colourCount = $merit->filter(fn ($id) => $players[$id]->is_player_of_colour)->count();
                    for ($slot = 1; $slot <= 6; $slot++) {
                        $required = $slot > 4 && $colourCount < 2;
                        $playerId = $slot <= 4 ? $merit->get($slot - 1)
                            : ($required ? $pool->first(fn ($id) => $players[$id]->is_player_of_colour) : $pool->first());
                        if ($slot > 4 && $playerId) {
                            $pool = $pool->reject(fn ($id) => $id === $playerId)->values();
                            $colourCount += $players[$playerId]->is_player_of_colour ? 1 : 0;
                        } elseif ($required && ! $playerId) {
                            // This explicit vacancy reserves a required colour place.
                            $colourCount++;
                        }
                        $draft->slots()->create(['category_event_id' => $categoryId, 'tier' => $tier, 'slot' => $slot,
                            'player_id' => $playerId, 'requires_colour' => $required]);
                    }
                }
                for ($slot = 7; $slot <= 10; $slot++) {
                    $draft->slots()->create(['category_event_id' => $categoryId, 'tier' => end($tiers), 'slot' => $slot,
                        'reserve' => true, 'player_id' => $pool->shift()]);
                }
            }
            activity('interprovincial-trials')->performedOn($draft)->causedBy($actor)->withProperties(['tiers' => $tiers])
                ->log('All regional squad drafts generated');
            return $draft->fresh();
        });
    }

    public function swap(TrialSquadDraft $draft, TrialSquadSlot $first, TrialSquadSlot $second, User $actor, string $reason): void
    {
        app(TrialProgrammeService::class)->authorize($draft->event, $actor);
        DB::transaction(function () use ($draft, $first, $second, $actor, $reason) {
            $draft = TrialSquadDraft::lockForUpdate()->findOrFail($draft->id);
            abort_unless(in_array($draft->status, ['draft', 'finalised'], true) && $first->id !== $second->id && (int) $first->draft_id === $draft->id && (int) $second->draft_id === $draft->id
                && (int) $first->category_event_id === (int) $second->category_event_id, 422);
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Record a reason for changing the selection.']);
            }
            $slots = TrialSquadSlot::whereIn('id', [$first->id, $second->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $first = $slots[$first->id];
            $second = $slots[$second->id];
            if ($draft->status === 'finalised' && ($first->reserve !== $second->reserve)) {
                throw ValidationException::withMessages(['teams' => 'Use an approved replacement to promote a reserve.']);
            }
            if (\App\Models\TrialParticipation::where('event_id', $draft->event_id)->whereIn('player_id', [$first->player_id, $second->player_id])
                ->whereHas('order', fn ($q) => $q->whereNotNull('payfast_handed_off_at')->where('pay_status', false))->exists()) {
                throw ValidationException::withMessages(['payment' => 'Resolve outstanding PayFast checkouts before moving selected players.']);
            }
            $playerId = $first->player_id;
            $firstResponse = $first->only(['response', 'responded_by', 'responded_at']);
            $secondResponse = $second->only(['response', 'responded_by', 'responded_at']);
            $first->update(['player_id' => null]);
            $secondPlayer = $second->player_id;
            $second->update(['player_id' => $playerId] + $firstResponse);
            $first->update(['player_id' => $secondPlayer] + $secondResponse);
            $this->validate($draft);
            if ($draft->status === 'finalised') {
                if ($first->player_id && !$first->reserve) { app(TrialParticipationService::class)->relocate($first, $actor); }
                if ($second->player_id && !$second->reserve) { app(TrialParticipationService::class)->relocate($second, $actor); }
            }
            activity('interprovincial-trials')->performedOn($draft)->causedBy($actor)
                ->withProperties(['first_slot' => $first->id, 'second_slot' => $second->id, 'reason' => $reason])
                ->log('Draft selection adjusted');
        });
    }

    public function finalise(TrialSquadDraft $draft, User $actor, bool $allowVacancies): TrialSquadDraft
    {
        app(TrialProgrammeService::class)->authorize($draft->event, $actor);
        return DB::transaction(function () use ($draft, $actor, $allowVacancies) {
            Event::whereKey($draft->event_id)->lockForUpdate()->firstOrFail();
            $draft = TrialSquadDraft::lockForUpdate()->findOrFail($draft->id);
            if ($draft->status === 'finalised') {
                if ($draft->needs_review) {
                    throw ValidationException::withMessages(['teams' => 'Review the changed Trials results before confirming the finalised roster.']);
                }
                return $draft;
            }
            $programme = TrialProgramme::where('event_id', $draft->event_id)->firstOrFail();
            if ($draft->needs_review || ! $programme->concluded_at || (int) $programme->current_run_id !== (int) $draft->ranking_run_id) {
                throw ValidationException::withMessages(['teams' => 'Trials positions changed. Review the affected selections before finalising.']);
            }
            $this->validate($draft);
            if (! $allowVacancies && $draft->slots()->where('reserve', false)->whereNull('player_id')->exists()) {
                throw ValidationException::withMessages(['vacancies' => 'Explicitly acknowledge the vacant team places before finalising.']);
            }
            $draft->update(['status' => 'finalised', 'finalised_by' => $actor->id, 'finalised_at' => now()]);
            activity('interprovincial-trials')->performedOn($draft)->causedBy($actor)
                ->withProperties(['vacancies_acknowledged' => $allowVacancies])->log('All regional squads finalised and published');
            return $draft;
        });
    }

    public function acknowledgeCorrections(TrialSquadDraft $draft, User $actor, string $reason): void
    {
        app(TrialProgrammeService::class)->authorize($draft->event, $actor);
        DB::transaction(function () use ($draft, $actor, $reason) {
            Event::whereKey($draft->event_id)->lockForUpdate()->firstOrFail();
            $draft = TrialSquadDraft::lockForUpdate()->findOrFail($draft->id);
            $programme = TrialProgramme::where('event_id', $draft->event_id)->firstOrFail();
            if (! $programme->concluded_at || trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Resolve Trials positions and record your review reason.']);
            }
            $this->validate($draft);
            $eligible = collect($programme->currentRun->positions)->pluck('player_id');
            if ($draft->slots()->whereNotNull('player_id')->whereNotIn('player_id', $eligible)->exists()) {
                throw ValidationException::withMessages(['teams' => 'A selected player was excluded from the corrected rankings. Adjust the selection before retaining it.']);
            }
            $draft->update(['ranking_run_id' => $programme->current_run_id, 'needs_review' => false]);
            activity('interprovincial-trials')->performedOn($draft)->causedBy($actor)->withProperties(['reason' => $reason])
                ->log('Corrected Trials selections reviewed; roster retained');
        });
    }

    public function validate(TrialSquadDraft $draft): void
    {
        foreach ($draft->slots()->where('reserve', false)->with('player')->get()->groupBy(fn ($slot) => $slot->category_event_id.':'.$slot->tier) as $slots) {
            if ($slots->count() !== 6 || $slots->filter(fn ($slot) => $slot->player?->is_player_of_colour || (! $slot->player_id && $slot->requires_colour))->count() < 2) {
                throw ValidationException::withMessages(['teams' => 'Each age/gender team needs six places and at least two players of colour or required vacant places.']);
            }
        }
    }
}
