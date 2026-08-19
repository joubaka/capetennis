<?php

namespace App\Services\Integrations;

use App\Models\Fixture;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;

class JtaResultExportService
{
    public function paginate(
        Player $player,
        ?CarbonImmutable $updatedSince,
        int $perPage,
        bool $useCursor = false,
    ): LengthAwarePaginator|CursorPaginator {
        $query = Fixture::query()
            ->select('fixtures.*')
            ->with([
                'draw.event',
                'draw.categoryEvent.category',
                'registration1.players' => fn ($query) => $query->orderBy('players.id'),
                'registration2.players' => fn ($query) => $query->orderBy('players.id'),
                'fixtureResults',
                'oop',
            ])
            ->whereIn('fixtures.match_status', [1, 3])
            ->where('fixtures.registration1_id', '>', 0)
            ->where('fixtures.registration2_id', '>', 0)
            ->where(function (Builder $query): void {
                $query->whereColumn('fixtures.winner_registration', 'fixtures.registration1_id')
                    ->orWhereColumn('fixtures.winner_registration', 'fixtures.registration2_id');
            })
            ->whereHas('fixtureResults', fn (Builder $query) => $query
                ->whereNotNull('registration1_score')
                ->whereNotNull('registration2_score'))
            ->whereDoesntHave('fixtureResults', fn (Builder $query) => $query
                ->whereNull('registration1_score')
                ->orWhereNull('registration2_score'))
            ->whereHas('draw', fn (Builder $query) => $query
                ->where('published', true)
                ->whereHas('event', fn (Builder $eventQuery) => $eventQuery->where('published', true)))
            ->where(function (Builder $query): void {
                $query->where(function (Builder $singles): void {
                    $singles->has('registration1.players', '=', 1)
                        ->has('registration2.players', '=', 1);
                })->orWhere(function (Builder $doubles): void {
                    $doubles->has('registration1.players', '=', 2)
                        ->has('registration2.players', '=', 2);
                });
            })
            ->where(function (Builder $query) use ($player): void {
                $query->whereHas('registration1.players', fn (Builder $players) => $players->whereKey($player->getKey()))
                    ->orWhereHas('registration2.players', fn (Builder $players) => $players->whereKey($player->getKey()));
            });

        if ($updatedSince) {
            $this->applyUpdatedSince($query, $updatedSince);
        }

        $query->orderBy('fixtures.id');

        return $useCursor
            ? $query->cursorPaginate($perPage)->withQueryString()
            : $query->paginate($perPage)->withQueryString();
    }

    private function applyUpdatedSince(Builder $query, CarbonImmutable $updatedSince): void
    {
        $query->where(function (Builder $changed) use ($updatedSince): void {
            $changed->where('fixtures.updated_at', '>=', $updatedSince)
                ->orWhereHas('fixtureResults', fn (Builder $results) => $results->where('updated_at', '>=', $updatedSince))
                ->orWhereHas('oop', fn (Builder $oop) => $oop->where('updated_at', '>=', $updatedSince))
                ->orWhereHas('draw', function (Builder $draw) use ($updatedSince): void {
                    $draw->where('updated_at', '>=', $updatedSince)
                        ->orWhereHas('event', fn (Builder $event) => $event->where('updated_at', '>=', $updatedSince))
                        ->orWhereHas('categoryEvent.category', fn (Builder $category) => $category->where('updated_at', '>=', $updatedSince));
                })
                ->orWhereHas('registration1', function (Builder $registration) use ($updatedSince): void {
                    $registration->where('updated_at', '>=', $updatedSince)
                        ->orWhereHas('players', fn (Builder $players) => $players
                            ->where('players.updated_at', '>=', $updatedSince)
                            ->orWhere('player_registrations.updated_at', '>=', $updatedSince));
                })
                ->orWhereHas('registration2', function (Builder $registration) use ($updatedSince): void {
                    $registration->where('updated_at', '>=', $updatedSince)
                        ->orWhereHas('players', fn (Builder $players) => $players
                            ->where('players.updated_at', '>=', $updatedSince)
                            ->orWhere('player_registrations.updated_at', '>=', $updatedSince));
                });
        });
    }
}
