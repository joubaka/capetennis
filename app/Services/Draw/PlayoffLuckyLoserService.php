<?php

namespace App\Services\Draw;

use App\Models\{CategoryEventRegistration, Draw, DrawAuditLog, Fixture, Registration};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Explicit playoff identities; never substitutes round-robin history or paid entries. */
final class PlayoffLuckyLoserService
{
    public function candidates(Draw $draw): Builder
    {
        return CategoryEventRegistration::query()->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $draw->event_id))
            ->where('payment_status_id', 1)->whereNull('withdrawn_at')
            ->where(fn ($query) => $query->whereNull('status')->orWhereNotIn('status', ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'refund_requested', 'refunded', 'cancelled']))
            ->whereHas('registration.players');
    }

    public function revision(Draw $draw): string
    {
        return hash('sha256', json_encode([$draw->fresh()->team_draw_selection,
            DB::table('category_event_registrations as entries')->join('category_events as divisions', 'divisions.id', '=', 'entries.category_event_id')
                ->leftJoin('player_registrations as participants', 'participants.registration_id', '=', 'entries.registration_id')
                ->where('divisions.event_id', $draw->event_id)->orderBy('entries.id')->orderBy('participants.player_id')
                ->get(['entries.id', 'entries.registration_id', 'entries.payment_status_id', 'entries.status', 'entries.withdrawn_at', 'entries.deleted_at', 'participants.player_id'])->all(),
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->all(),
            $draw->drawFixtures()->with(['fixtureResults', 'draw.settings', 'orderOfPlay'])->orderBy('id')->get()
                ->map(fn ($fixture) => [app(IndividualScoreRevision::class)->forFixture($fixture),
                    $fixture->orderOfPlay?->only(['venue_id', 'court', 'time', 'duration_minutes', 'gap_minutes']),
                    $fixture->only(['hint_registration1_id', 'hint_registration2_id'])])->all()]));
    }

    /** Validate durable overrides against their exact original source and target draw. */
    public static function sourceOverride(Draw $draw, Fixture $fixture, int $slot, ?int $source): ?int
    {
        $override = $draw->team_draw_selection['playoff_lucky_losers'][$fixture->id.':'.$slot] ?? null;
        if (!$override) return $source;
        $incoming = Registration::find($override['incoming_registration_id'] ?? null);
        $meta = $incoming?->competition_replacement ?? [];
        abort_unless((int) ($override['outgoing_registration_id'] ?? 0) === (int) $source
            && (int) ($meta['draw_id'] ?? 0) === (int) $draw->id && ($meta['show_lucky_loser'] ?? false)
            && (int) $fixture->draw_id === (int) $draw->id, 409, 'The lucky-loser source changed. Review the playoff replacement.');
        return (int) $incoming->id;
    }

    public function replace(Draw $draw, Fixture $fixture, int $slot, int $entryId, int $outgoing, string $revision): Registration
    {
        return app(IndividualScoreRevision::class)->locked($draw, $fixture, function (Draw $draw, Fixture $fixture) use ($slot, $entryId, $outgoing, $revision) {
            Gate::authorize('replacePlayoffLuckyLoser', $draw);
            abort_if($draw->locked || $draw->isTeamDraw() || $draw->usesFlexibleMonrad(), 409, 'Choose an unlocked round-robin playoff draw.');
            abort_unless(in_array($slot, [1, 2], true) && $fixture->stage !== 'RR' && $fixture->stage !== 'FM'
                && $draw->drawFixtures()->where('stage', 'RR')->exists(), 422, 'Choose a resolved playoff place after the round robin.');
            $key = $fixture->id.':'.$slot;
            $history = $draw->team_draw_selection ?? [];
            $prior = $history['playoff_lucky_losers'][$key] ?? null;
            if ($prior && (int) $prior['source_entry_id'] === $entryId && (int) $prior['outgoing_registration_id'] === $outgoing
                && (int) $fixture->getAttribute("registration{$slot}_id") === (int) $prior['incoming_registration_id']) {
                return Registration::findOrFail($prior['incoming_registration_id']);
            }
            abort_if($prior, 409, 'This playoff place already has a lucky loser. Review its existing replacement.');
            abort_unless(hash_equals($this->revision($draw), $revision), 409, 'The draw changed. Reload and review the playoff replacement.');
            abort_unless($outgoing > 0 && (int) $fixture->getAttribute("registration{$slot}_id") === $outgoing, 409, 'The selected playoff player changed.');
            abort_unless($draw->drawFixtures()->where('stage', 'RR')->where(fn ($query) => $query
                ->where('registration1_id', $outgoing)->orWhere('registration2_id', $outgoing))->exists(), 422,
                'The outgoing player must be a round-robin participant in this draw.');
            $fixtures = $draw->drawFixtures()->where('stage', '!=', 'RR')->with(['fixtureResults', 'orderOfPlay'])->lockForUpdate()->get();
            abort_if(app(AlternateReplacementService::class)->hasStarted($draw, $outgoing, $fixtures, true), 409, 'The outgoing player has already started a playoff match.');
            abort_if($fixture->fixtureResults()->exists() || $fixture->winner_registration || (int) $fixture->match_status !== 0
                || InterproRoundRobinFormat::noMatchRequired($fixture), 409, 'Played, automatic or carried playoff matches cannot receive a lucky loser.');
            abort_if($fixtures->contains(fn ($match) => (int) $match->id !== (int) $fixture->id
                && in_array($outgoing, [(int) $match->registration1_id, (int) $match->registration2_id], true)), 409,
                'The outgoing player has another playoff place. Review that branch before replacing this slot.');
            $entry = $this->candidates($draw)->with(['registration.players', 'categoryEvent.category'])->lockForUpdate()->findOrFail($entryId);
            abort_unless($entry->registration->players->count() === 1, 422, 'Choose an active singles entrant.');
            $player = \App\Models\Player::whereKey($entry->registration->players->first()->id)->lockForUpdate()->firstOrFail();
            try {
                app(\App\Services\PlayerEligibilityService::class)->assertEligible($player, $draw->event);
            } catch (\RuntimeException $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_entry_id' => $exception->getMessage()]);
            }
            abort_if($entry->registration_id === $outgoing, 422, 'Choose a different player.');
            $aliases = DB::table('player_registrations')->where('player_id', $player->id)->pluck('registration_id');
            abort_if($aliases->contains($outgoing), 422, 'Choose a different player.');
            if (InterproRoundRobinFormat::selected($draw)) {
                $opponent = (int) $fixture->getAttribute('registration'.($slot === 1 ? 2 : 1).'_id');
                $opponentIds = $opponent ? DB::table('player_registrations')->whereIn('player_id', DB::table('player_registrations')
                    ->where('registration_id', $opponent)->select('player_id'))->pluck('registration_id') : collect();
                abort_if($draw->drawFixtures()->whereKeyNot($fixture->id)->where(function ($query) use ($aliases, $opponentIds) {
                    $query->where(fn ($pair) => $pair->whereIn('registration1_id', $aliases)->whereIn('registration2_id', $opponentIds))
                        ->orWhere(fn ($pair) => $pair->whereIn('registration2_id', $aliases)->whereIn('registration1_id', $opponentIds));
                })->where(fn ($query) => $query->whereHas('fixtureResults')->orWhereNotNull('winner_registration'))->exists(), 409,
                    'These players have already met in this trial. Review the first-result-stands path before replacing this place.');
            }
            $commitments = Fixture::whereHas('draw', fn ($query) => $query->where('event_id', $draw->event_id))
                ->where('stage', '!=', 'RR')->where(fn ($query) => $query->whereIn('registration1_id', $aliases)->orWhereIn('registration2_id', $aliases)
                    ->orWhereIn('hint_registration1_id', $aliases)->orWhereIn('hint_registration2_id', $aliases))
                ->with(['fixtureResults', 'draw.settings'])->lockForUpdate()->get();
            abort_if($commitments->contains(fn ($match) => $this->hasRemainingRoute($match, $aliases))
                || $this->hasUnresolvedQualificationPlace($draw, $aliases), 422, 'This player already has an active playoff place in this event.');
            $incoming = Registration::create(['competition_replacement' => ['draw_id' => $draw->id, 'category_event_id' => $draw->category_event_id,
                'source_registration_id' => $entry->registration_id, 'source_entry_id' => $entry->id,
                'entry_mode' => 'existing_entry', 'show_lucky_loser' => true]]);
            $incoming->players()->attach($player->id);
            $draw->registrations()->attach($incoming->id);
            $fixture->update(["registration{$slot}_id" => $incoming->id]);
            if ((int) $fixture->getAttribute("hint_registration{$slot}_id") === $outgoing) $fixture->update(["hint_registration{$slot}_id" => $incoming->id]);
            if ($fixture->orderOfPlay?->time) {
                $booking = $fixture->orderOfPlay;
                $conflict = app(\App\Domain\Draws\Services\ScheduleConflictService::class)->conflict($draw, $fixture,
                    (int) $booking->venue_id, (string) $booking->court, (string) $booking->time,
                    (int) ($booking->duration_minutes ?: 75), null, (int) ($booking->gap_minutes ?? 0));
                abort_if($conflict, 409, 'The lucky loser conflicts with the saved match schedule. Review scheduling before replacing the player.');
            }
            $record = ['fixture_id' => $fixture->id, 'slot' => $slot, 'outgoing_registration_id' => $outgoing, 'playoff_withdrawal' => true,
                'incoming_registration_id' => $incoming->id, 'source_entry_id' => $entry->id, 'source_registration_id' => $entry->registration_id];
            $history['playoff_lucky_losers'][$key] = $record;
            $draw->team_draw_selection = $history;
            $draw->save();
            DrawAuditLog::record($draw->id, 'playoff_lucky_loser_replaced', $fixture->id, $record);
            return $incoming;
        });
    }

    /** A completed result can still reserve an unfilled winner/loser destination. */
    private function hasRemainingRoute(Fixture $fixture, \Illuminate\Support\Collection $aliases, array $seen = []): bool
    {
        if (isset($seen[$fixture->id])) return true;
        $seen[$fixture->id] = true;
        $outcomes = app(\App\Services\IndividualMatchOutcomeService::class);
        $winner = $outcomes->winner($fixture);
        if (!$winner) return !$outcomes->isDraw($fixture);
        if (!$aliases->contains((int) $fixture->registration1_id) && !$aliases->contains((int) $fixture->registration2_id)) return false;
        $destination = $aliases->contains((int) $winner) ? $fixture->parent_fixture_id : $fixture->loser_parent_fixture_id;
        if (!$destination) return false;
        $next = Fixture::whereKey($destination)->where('draw_id', $fixture->draw_id)->where('stage', '!=', 'RR')
            ->with(['fixtureResults', 'draw.settings'])->lockForUpdate()->first();
        // An invalid feeder must not be treated as evidence that the place is free.
        return !$next || $this->hasRemainingRoute($next, $aliases, $seen);
    }

    /** Completed RR positions reserve their prepared playoff slots before identity resolution. */
    private function hasUnresolvedQualificationPlace(Draw $draw, \Illuminate\Support\Collection $aliases): bool
    {
        $groups = \App\Models\DrawGroup::whereHas('draw', fn ($query) => $query->where('event_id', $draw->event_id))
            ->whereHas('groupRegistrations', fn ($query) => $query->whereIn('registration_id', $aliases))
            ->with(['draw.settings', 'groupRegistrations.registration'])->get();
        foreach ($groups as $group) {
            $fixtures = $group->draw->drawFixtures()->where('stage', 'RR')->where('draw_group_id', $group->id)->with('fixtureResults')->get();
            if (!app(TwoStageRoundRobinService::class)->complete($group->draw, $group, $fixtures)) continue;
            $rows = app(\App\Domain\Draws\Services\StandingsService::class)->forGroup($group, $fixtures);
            $destinations = $group->draw->drawFixtures()->where('stage', '!=', 'RR')->where(fn ($query) => $query
                ->where('registration1_source_group_id', $group->id)->orWhere('registration2_source_group_id', $group->id))
                ->with(['fixtureResults', 'draw.settings'])->lockForUpdate()->get();
            foreach ($destinations as $fixture) {
                foreach ([1, 2] as $slot) {
                    if ((int) $fixture->getAttribute("registration{$slot}_source_group_id") !== (int) $group->id) continue;
                    $position = (int) $fixture->getAttribute("registration{$slot}_source_position");
                    $source = $rows[$position - 1]['reg_id'] ?? null;
                    if (!$source || !$aliases->contains((int) $source)) continue;
                    $registration = self::sourceOverride($group->draw, $fixture, $slot, (int) $source);
                    if ($aliases->contains($registration) && $this->hasRemainingRoute($fixture, $aliases)) return true;
                }
            }
        }
        return false;
    }
}
