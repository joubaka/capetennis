<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\StandingsService;
use App\Models\CategoryEvent;
use App\Models\Draw;
use Illuminate\Support\Collection;

/**
 * Read-only fallback order for the event results workspace.
 *
 * Saved category_results remain authoritative. This projection is only used
 * to prefill an unsaved category from results already recorded on its draw.
 */
final class DrawResultOrderService
{
    public function __construct(
        private readonly DrawFinalPlacementService $placements,
        private readonly StandingsService $standings,
        private readonly FlexibleMonradService $flexibleMonrad,
    ) {}

    /**
     * @param  Collection<int, Draw>  $eventDraws
     * @param  Collection<int, int>  $eligibleRegistrationIds
     * @param  Collection<int, Collection<int, int>>  $registrationIdsByCategoryEvent
     * @return Collection<int, int>
     */
    public function forCategory(
        CategoryEvent $categoryEvent,
        Collection $eventDraws,
        Collection $eligibleRegistrationIds,
        Collection $registrationIdsByCategoryEvent,
    ): Collection {
        $eligibleIds = $eligibleRegistrationIds
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($eligibleIds->isEmpty()) {
            return collect();
        }

        $directDraws = $eventDraws->filter(
            fn (Draw $draw) => (int) $draw->category_event_id === (int) $categoryEvent->id
        );

        // Historical draws may pre-date the category_event_id link. Only use
        // an unlinked draw when every participant belongs to this category.
        $draws = $directDraws->isNotEmpty()
            ? $directDraws
            : $eventDraws->filter(function (Draw $draw) use ($categoryEvent, $registrationIdsByCategoryEvent) {
                if ($draw->category_event_id !== null) {
                    return false;
                }

                $participantIds = $this->participantIds($draw);
                $matchingCategoryIds = $registrationIdsByCategoryEvent
                    ->filter(fn (Collection $categoryIds) => $participantIds->isNotEmpty()
                        && $participantIds->diff($categoryIds)->isEmpty())
                    ->keys();

                return $matchingCategoryIds->count() === 1
                    && (int) $matchingCategoryIds->first() === (int) $categoryEvent->id;
            });

        return $draws
            ->sortBy('id')
            ->flatMap(fn (Draw $draw) => $this->forDraw($draw))
            ->filter(fn ($id) => $eligibleIds->contains((int) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** @return Collection<int, int> */
    private function forDraw(Draw $draw): Collection
    {
        if ($draw->flexibleMonrad?->graph) {
            $positions = collect($this->flexibleMonrad->state($draw)['positions'] ?? [])
                ->sortBy(fn (array $row) => (int) $row['position'])
                ->pluck('player')
                ->filter();

            if ($positions->isNotEmpty()) {
                return $positions->values();
            }
        }

        $placements = $this->placements->forDraw($draw)
            ->where('status', 'resolved')
            ->sortBy(fn (array $row) => (int) $row['position'])
            ->pluck('registration_id')
            ->filter();

        if ($placements->isNotEmpty()) {
            return $placements->values();
        }

        $rrFixtures = $draw->drawFixtures->where('stage', 'RR');
        if ($draw->groups->count() !== 1
            || ! $rrFixtures->contains(fn ($fixture) => $fixture->fixtureResults->isNotEmpty())) {
            return collect();
        }

        $group = $draw->groups->first();

        return collect($this->standings->forGroup($group, $draw->drawFixtures))
            ->pluck('reg_id')
            ->filter()
            ->values();
    }

    /** @return Collection<int, int> */
    private function participantIds(Draw $draw): Collection
    {
        return $draw->registrations->pluck('id')
            ->merge($draw->groups->flatMap->groupRegistrations->pluck('registration_id'))
            ->merge($draw->drawFixtures->pluck('registration1_id'))
            ->merge($draw->drawFixtures->pluck('registration2_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
