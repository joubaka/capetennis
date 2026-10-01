<?php

namespace App\Services\InterprovincialTrials;

use App\Models\{Event, TrialProgramme, TrialRankingRun, TrialSquadDraft, User};
use Illuminate\Support\Facades\DB;

class TrialProgrammeService
{
    public function authorize(Event $event, User $actor): void
    {
        abort_unless($event->isInterprovincialTrials(), 404);
        abort_unless($this->canManage($event, $actor), 403);
    }

    public function canManage(Event $event, User $actor): bool
    {
        if ($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id))) {
            return true;
        }
        $regions = DB::table('event_regions')->where('event_id', $event->id)->pluck('region_id')->unique();
        if ($regions->count() !== 1) {
            return false;
        }
        $programmeRegion = TrialProgramme::where('event_id', $event->id)->value('region_id');
        if ($programmeRegion && (int) $programmeRegion !== (int) $regions->sole()) {
            return false;
        }
        return DB::table('event_region_managers')->join('event_regions', 'event_regions.id', '=', 'event_region_managers.event_region_id')
            ->where('event_regions.event_id', $event->id)->where('event_regions.region_id', $regions->sole())
            ->where('event_region_managers.user_id', $actor->id)->exists();
    }

    /** Only complete, uniquely resolved placement sets can become public rankings. */
    public function refresh(Event $event, bool $scoresChanged = false, array $changedCategoryIds = []): ?TrialRankingRun
    {
        if (! $event->isInterprovincialTrials()) {
            return null;
        }
        return DB::transaction(function () use ($event, $scoresChanged, $changedCategoryIds) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $programme = TrialProgramme::firstOrCreate(['event_id' => $event->id]);
            $categories = $event->categoryEvents()->orderBy('id')->get();
            // Invalidate approvals before any incomplete category can return early.
            if ($scoresChanged) {
                $invalidated = $changedCategoryIds === [] ? $categories->pluck('id')->all() : $changedCategoryIds;
                $programme->update(['manual_position_categories' => array_values(array_diff($programme->manual_position_categories ?? [], $invalidated))]);
            }
            $snapshot = [];
            foreach ($categories as $category) {
                $entries = $category->categoryEventRegistrations()->where('payment_status_id', 1)->with('registration.players')->get();
                if ($entries->isEmpty()) {
                    continue;
                }
                foreach ($entries->where('status', 'withdrawn') as $withdrawn) {
                    $reachable = \App\Models\Fixture::whereHas('draw', fn ($q) => $q->where('category_event_id', $category->id))
                        ->whereIn('match_status', [0, 2])->where('registration1_id', '>', 0)->where('registration2_id', '>', 0)
                        ->where(fn ($q) => $q->where('registration1_id', $withdrawn->registration_id)->orWhere('registration2_id', $withdrawn->registration_id))->exists();
                    if ($reachable) { app(TrialWithdrawalProgressionService::class)->resolve($withdrawn); }
                }
                $draws = $category->draws()->with(['drawFixtures.fixtureResults', 'groups.groupRegistrations', 'registrations', 'flexibleMonrad', 'settings'])->get();
                $fixtures = $draws->flatMap->drawFixtures;
                if ($draws->isEmpty() || $fixtures->isEmpty() || $fixtures->contains(function ($fixture) {
                    if (! in_array((int) $fixture->match_status, [1, 3, 5], true)) {
                        return true;
                    }
                    return (int) $fixture->match_status !== 5 && ! in_array((int) $fixture->winner_registration,
                        array_filter([(int) $fixture->registration1_id, (int) $fixture->registration2_id]), true);
                })) {
                    return $this->invalidate($programme);
                }
                $positions = DB::table('category_results')->where('event_id', $event->id)
                    ->where('category_id', $category->category_id)->orderBy('position')->get();
                $ids = $entries->pluck('registration_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                $hasUnresolvedTie = false;
                foreach ($draws as $draw) {
                    if ($draw->isRoundRobinOnly() && $draw->groups->count() === 1) {
                        $standingRows = app(\App\Domain\Draws\Services\StandingsService::class)->forGroup($draw->groups->sole(), $draw->drawFixtures);
                        $hasUnresolvedTie = $hasUnresolvedTie || collect($standingRows)->contains(fn ($row) => ($row['tiebreak'] ?? '') === '=');
                    }
                }
                $manual = in_array((int) $category->id, $programme->manual_position_categories ?? [], true);
                $derived = $hasUnresolvedTie || $draws->count() !== 1 ? collect() : app(\App\Services\Draw\DrawResultOrderService::class)->forCategory(
                    $category, $draws, collect($ids), collect([$category->id => collect($ids)]));
                if (! $manual && $derived->sort()->values()->all() === $ids) {
                    $positions = $derived->values()->map(fn ($id, $index) => (object) ['registration_id' => $id, 'position' => $index + 1]);
                } elseif (! $manual) {
                    return $this->invalidate($programme);
                }
                if ($positions->pluck('registration_id')->map(fn ($id) => (int) $id)->sort()->values()->all() !== $ids
                    || $positions->pluck('position')->map(fn ($position) => (int) $position)->all() !== range(1, count($ids))) {
                    return $this->invalidate($programme);
                }
                $dispositions = DB::table('trial_ranking_dispositions')->where('event_id', $event->id)->whereIn('entry_id', $entries->pluck('id'))->pluck('disposition', 'entry_id');
                $positions = $positions->reject(function ($position) use ($entries, $dispositions) {
                    $entry = $entries->firstWhere('registration_id', $position->registration_id);
                    return $entry->status === 'withdrawn' && ($dispositions[$entry->id] ?? '') === 'exclude';
                })->sortBy(function ($position) use ($entries, $dispositions) {
                    $entry = $entries->firstWhere('registration_id', $position->registration_id);
                    return $entry->status === 'withdrawn' && ($dispositions[$entry->id] ?? '') === 'last' ? 1000000 + $position->position : $position->position;
                })->values();
                foreach ($positions as $index => $position) {
                    $players = $entries->firstWhere('registration_id', $position->registration_id)?->registration?->players;
                    if (! $players || $players->count() !== 1) {
                        return $this->invalidate($programme);
                    }
                    $player = $players->sole();
                    $snapshot[] = ['category_event_id' => $category->id, 'registration_id' => (int) $position->registration_id,
                        'player_id' => $player->id, 'name' => $player->full_name, 'position' => $index + 1];
                }
            }
            if ($snapshot === []) {
                return $this->invalidate($programme);
            }
            $hash = hash('sha256', json_encode($snapshot));
            $run = TrialRankingRun::firstOrCreate(['event_id' => $event->id, 'signature' => $hash], ['positions' => $snapshot]);
            if ((int) $programme->current_run_id !== (int) $run->id) {
                TrialSquadDraft::where('event_id', $event->id)->where('ranking_run_id', '!=', $run->id)->update(['needs_review' => true]);
                $programme->update(['current_run_id' => $run->id, 'concluded_at' => now()]);
                activity('interprovincial-trials')->performedOn($programme)->withProperties(['ranking_run_id' => $run->id])
                    ->log('Completed Trials finishing positions published');
            }
            if (! $programme->concluded_at) {
                $programme->update(['concluded_at' => now()]);
            }
            return $run;
        });
    }

    private function invalidate(TrialProgramme $programme): ?TrialRankingRun
    {
        if ($programme->concluded_at !== null) {
            $programme->update(['concluded_at' => null]);
            TrialSquadDraft::where('event_id', $programme->event_id)->update(['needs_review' => true]);
        }
        return null;
    }
}
