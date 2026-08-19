<?php

namespace App\Services\Integrations;

use App\Models\CategoryResult;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class JtaEventResultExportService
{
    public function paginate(
        Player $player,
        ?CarbonImmutable $updatedSince,
        int $perPage,
        bool $useCursor = false,
    ): LengthAwarePaginator|CursorPaginator {
        $registrationIds = DB::table('player_registrations')
            ->where('player_id', $player->getKey())
            ->pluck('registration_id');

        $query = CategoryResult::query()
            ->select('category_results.*')
            ->with([
                'event',
                'category',
                'registration.players' => fn ($players) => $players->orderBy('players.id'),
            ])
            ->selectSub(function ($fieldSize) {
                $fieldSize->from('category_results as field_results')
                    ->selectRaw('COUNT(DISTINCT field_results.registration_id)')
                    ->whereColumn('field_results.event_id', 'category_results.event_id')
                    ->whereColumn('field_results.category_id', 'category_results.category_id');
            }, 'field_size')
            ->where('category_results.position', '>', 0)
            ->whereHas('event', fn (Builder $event) => $event
                ->where('published', true)
                ->where('results_published', true))
            ->whereHas('category')
            ->whereIn('category_results.registration_id', $registrationIds)
            ->where(function (Builder $query): void {
                $query->has('registration.players', '=', 1)
                    ->orHas('registration.players', '=', 2);
            })
            ->whereNotExists(function ($newer): void {
                $newer->selectRaw('1')
                    ->from('category_results as newer_results')
                    ->whereColumn('newer_results.event_id', 'category_results.event_id')
                    ->whereColumn('newer_results.category_id', 'category_results.category_id')
                    ->whereColumn('newer_results.registration_id', 'category_results.registration_id')
                    ->whereColumn('newer_results.id', '>', 'category_results.id');
            });

        if ($updatedSince) {
            $this->applyUpdatedSince($query, $updatedSince);
        }

        $query->orderBy('category_results.event_id')
            ->orderBy('category_results.category_id')
            ->orderBy('category_results.registration_id')
            ->orderBy('category_results.id');

        return $useCursor
            ? $query->cursorPaginate($perPage)->withQueryString()
            : $query->paginate($perPage)->withQueryString();
    }

    private function applyUpdatedSince(Builder $query, CarbonImmutable $updatedSince): void
    {
        $query->where(function (Builder $changed) use ($updatedSince): void {
            $changed->where('category_results.updated_at', '>=', $updatedSince)
                ->orWhereHas('event', fn (Builder $event) => $event->where('updated_at', '>=', $updatedSince))
                ->orWhereHas('category', fn (Builder $category) => $category->where('updated_at', '>=', $updatedSince))
                ->orWhereHas('registration', function (Builder $registration) use ($updatedSince): void {
                    $registration->where('updated_at', '>=', $updatedSince)
                        ->orWhereHas('players', fn (Builder $players) => $players
                            ->where('players.updated_at', '>=', $updatedSince)
                            ->orWhere('player_registrations.updated_at', '>=', $updatedSince));
                });
        });
    }
}
