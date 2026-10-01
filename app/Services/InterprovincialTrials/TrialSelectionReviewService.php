<?php

namespace App\Services\InterprovincialTrials;

use App\Models\{Event, Player, TrialProgramme, TrialReplacementProposal, TrialSquadDraft, TrialSquadSlot, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialSelectionReviewService
{
    public function respond(TrialSquadSlot $slot, User $actor, string $response, ?string $adminReason = null): void
    {
        abort_unless(in_array($response, ['pending', 'confirmed', 'declined'], true), 422);
        DB::transaction(function () use ($slot, $actor, $response, $adminReason) {
            $draft = TrialSquadDraft::lockForUpdate()->findOrFail($slot->draft_id);
            $event = $draft->event;
            abort_unless($event->isInterprovincialTrials() && $draft->status === 'finalised', 404);
            abort_unless((int) TrialSquadDraft::where('event_id', $event->id)->where('status', 'finalised')->max('id') === $draft->id, 409);
            if ($adminReason !== null) {
                app(TrialProgrammeService::class)->authorize($event, $actor);
                abort_unless(trim($adminReason) !== '', 422);
            } else {
                abort_unless($event->published && $response !== 'pending', 404);
            }
            $slot = TrialSquadSlot::lockForUpdate()->findOrFail($slot->id);
            abort_unless($slot->player_id && !$slot->reserve, 422);
            $this->assertPaymentResolved($draft->event_id, $slot->player_id);
            if ($slot->response === $response) { return; }
            $before = $slot->response;
            $slot->update(['response' => $response, 'responded_by' => $actor->id, 'responded_at' => now()]);
            activity('interprovincial-trials')->performedOn($slot)->causedBy($actor)
                ->withProperties(['player_id' => $slot->player_id, 'before' => $before, 'after' => $response, 'reason' => $adminReason])
                ->log($response === 'declined' ? 'Participation declined; replacement review required' : 'Participation response recorded');
        });
    }

    public function colour(Event $event, Player $player, User $actor, bool $value, string $reason): void
    {
        app(TrialProgrammeService::class)->authorize($event, $actor);
        abort_unless($event->nominations()->where('player_id', $player->id)->exists(), 404);
        abort_unless(trim($reason) !== '', 422);
        DB::transaction(function () use ($event, $player, $actor, $value, $reason) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $player = Player::lockForUpdate()->findOrFail($player->id);
            $before = $player->is_player_of_colour;
            $player->update(['is_player_of_colour' => $value]);
            TrialSquadDraft::whereHas('slots', fn ($q) => $q->where('player_id', $player->id))->update(['needs_review' => true]);
            activity('interprovincial-trials')->performedOn($player)->causedBy($actor)
                ->withProperties(['event_id' => $event->id, 'before' => $before, 'after' => $value, 'reason' => $reason])->log('Player colour declaration updated');
        });
    }

    public function remove(TrialSquadSlot $slot, User $actor, string $reason): void
    {
        app(TrialProgrammeService::class)->authorize($slot->draft->event, $actor);
        DB::transaction(function () use ($slot, $actor, $reason) {
            $draft = TrialSquadDraft::lockForUpdate()->findOrFail($slot->draft_id);
            $slot = TrialSquadSlot::lockForUpdate()->findOrFail($slot->id);
            abort_unless($slot->player_id && trim($reason) !== '', 422);
            $this->assertPaymentResolved($draft->event_id, $slot->player_id);
            $playerId = $slot->player_id;
            $participation = \App\Models\TrialParticipation::where('event_id', $draft->event_id)->where('player_id', $playerId)->first();
            if ($participation?->order_id) { app(TrialParticipationService::class)->withdraw($participation, $actor); }
            $slot->update(['player_id' => null, 'requires_colour' => $slot->requires_colour || (!$slot->reserve && $slot->player?->is_player_of_colour), 'response' => 'pending', 'responded_by' => null, 'responded_at' => null]);
            activity('interprovincial-trials')->performedOn($slot)->causedBy($actor)->withProperties(['removed_player_id' => $playerId, 'reason' => $reason])->log('Selected player removed; vacancy retained for review');
        });
    }

    public function propose(TrialSquadSlot $target, User $actor, ?int $chosenSourceId = null): TrialReplacementProposal
    {
        app(TrialProgrammeService::class)->authorize($target->draft->event, $actor);
        return DB::transaction(function () use ($target, $actor, $chosenSourceId) {
            $draft = TrialSquadDraft::lockForUpdate()->findOrFail($target->draft_id);
            $target = TrialSquadSlot::lockForUpdate()->findOrFail($target->id);
            abort_unless($draft->status === 'finalised' && !$draft->needs_review && !$target->reserve && (!$target->player_id || $target->response === 'declined'), 422);
            $existing = TrialReplacementProposal::where('target_slot_id', $target->id)->where('status', 'pending')->first();
            if ($existing && !$chosenSourceId && $existing->source_player_id) { return $existing; }
            $tiers = $draft->tiers;
            $next = $tiers[array_search($target->tier, $tiers, true) + 1] ?? null;
            $currentColour = $draft->slots()->where('category_event_id', $target->category_event_id)->where('tier', $target->tier)
                ->where('reserve', false)->where('id', '!=', $target->id)->with('player')->get()
                ->filter(fn ($s) => $s->player?->is_player_of_colour || (!$s->player_id && $s->requires_colour))->count();
            $needsColour = $currentColour < 2;
            $ranking = collect($draft->rankingRun->positions)->keyBy('player_id');
            $source = $draft->slots()->where('category_event_id', $target->category_event_id)
                ->where('tier', $next ?? $target->tier)->where('reserve', $next === null)->whereNotNull('player_id')
                ->where('response', '!=', 'declined')->with('player')->get()
                ->filter(fn ($s) => !$needsColour || $s->player?->is_player_of_colour)
                ->when($chosenSourceId, fn ($rows) => $rows->where('id', $chosenSourceId))
                ->sortBy(fn ($s) => $ranking[$s->player_id]['position'] ?? PHP_INT_MAX)->first();
            if ($chosenSourceId && !$source) { throw ValidationException::withMessages(['source_slot' => 'Choose an eligible replacement from the next team or the lowest team reserves.']); }
            if ($existing) { $existing->update(['status' => 'superseded']); }
            return TrialReplacementProposal::create(['draft_id' => $draft->id, 'target_slot_id' => $target->id,
                'source_slot_id' => $source?->id, 'target_player_id' => $target->player_id, 'source_player_id' => $source?->player_id, 'created_by' => $actor->id]);
        });
    }

    public function approve(TrialReplacementProposal $proposal, User $actor, string $reason): void
    {
        app(TrialProgrammeService::class)->authorize($proposal->draft->event, $actor);
        DB::transaction(function () use ($proposal, $actor, $reason) {
            $draft = TrialSquadDraft::lockForUpdate()->findOrFail($proposal->draft_id);
            $proposal = TrialReplacementProposal::lockForUpdate()->findOrFail($proposal->id);
            if ($proposal->status === 'approved') { return; }
            abort_unless($proposal->status === 'pending' && $draft->status === 'finalised' && !$draft->needs_review && trim($reason) !== '', 422);
            $slots = TrialSquadSlot::whereIn('id', [$proposal->target_slot_id, $proposal->source_slot_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $slots->get($proposal->target_slot_id); $source = $slots->get($proposal->source_slot_id);
            if (!$source || !$target || $source->player_id !== $proposal->source_player_id || $target->player_id !== $proposal->target_player_id
                || $source->response === 'declined' || ($target->player_id && $target->response !== 'declined')) {
                throw ValidationException::withMessages(['replacement' => 'The proposal is unavailable or stale. Review the current roster.']);
            }
            $playerId = $source->player_id;
            $this->assertPaymentResolved($draft->event_id, $playerId);
            if ($target->player_id) { $this->assertPaymentResolved($draft->event_id, $target->player_id); }
            $displaced = $target->player_id ? \App\Models\TrialParticipation::where('event_id', $draft->event_id)->where('player_id', $target->player_id)->first() : null;
            if ($displaced?->order_id) { app(TrialParticipationService::class)->withdraw($displaced, $actor); }
            $response = $source->reserve ? 'pending' : $source->response;
            $respondedBy = $source->reserve ? null : $source->responded_by;
            $respondedAt = $source->reserve ? null : $source->responded_at;
            if ($source->player?->is_player_of_colour) { $source->requires_colour = true; }
            $source->update(['player_id' => null, 'response' => 'pending', 'responded_by' => null, 'responded_at' => null]);
            $target->update(['player_id' => $playerId, 'response' => $response, 'responded_by' => $respondedBy, 'responded_at' => $respondedAt]);
            app(TrialSquadService::class)->validate($draft);
            app(TrialParticipationService::class)->relocate($target, $actor);
            $proposal->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'reason' => $reason]);
            activity('interprovincial-trials')->performedOn($proposal)->causedBy($actor)
                ->withProperties(['from' => $source->id, 'to' => $target->id, 'player_id' => $playerId, 'displaced_player_id' => $proposal->target_player_id, 'reason' => $reason])->log('Replacement approved; lower team vacancy requires review');
        });
    }

    private function assertPaymentResolved(int $eventId, int $playerId): void
    {
        if (\App\Models\TrialParticipation::where('event_id', $eventId)->where('player_id', $playerId)
            ->whereHas('order', fn ($q) => $q->whereNotNull('payfast_handed_off_at')->where('pay_status', false))->exists()) {
            throw ValidationException::withMessages(['payment' => 'Wait for the outstanding PayFast checkout to resolve before changing participation.']);
        }
    }
}
