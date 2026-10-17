<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;

final class ImportedRosterProfileMatchService
{
    public function participants(Event $source): Builder
    {
        return Player::query()->where(function (Builder $query) use ($source): void {
            $query->whereHas('teams.category', fn ($q) => $q->where('event_id', $source->id))
                ->orWhereIn('players.id', NoProfileTeamPlayer::query()->select('player_profile')
                    ->whereHas('team.category', fn ($q) => $q->where('event_id', $source->id)))
                ->orWhereHas('registrations.categoryEvents', fn ($q) => $q->where('event_id', $source->id)
                    ->whereNull('category_event_registrations.deleted_at')
                    ->whereNull('category_event_registrations.withdrawn_at')
                    ->where(fn ($status) => $status->whereNull('category_event_registrations.status')
                        ->orWhere('category_event_registrations.status', '!=', 'pending_checkout')));
        });
    }

    public function preview(Team $team, Event $source, int $page): array
    {
        $slots = NoProfileTeamPlayer::query()->where('team_id', $team->id)->orderBy('rank')->orderBy('id')
            ->paginate(25, ['*'], 'roster_page', $page)->withQueryString();
        $rows = $slots->getCollection()->map(function (NoProfileTeamPlayer $slot) use ($source): array {
            $candidates = collect();
            if (! $slot->player_profile) {
                $normalize = fn ($name) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
                $candidates = $this->participants($source)
                    ->where(function ($query) use ($slot, $normalize): void {
                        $query->where('identity_name_hash', hash('sha256', $normalize($slot->name).'|'.$normalize($slot->surname)))
                            ->orWhere(fn ($q) => $q->whereRaw('LOWER(TRIM(players.name)) = ?', [$normalize($slot->name)])
                                ->whereRaw('LOWER(TRIM(players.surname)) = ?', [$normalize($slot->surname)]));
                    })
                    ->orderBy('players.id')->limit(21)->get(['players.id', 'players.name', 'players.surname', 'players.dateOfBirth', 'players.email', 'players.gender'])
                    ->filter(fn ($player) => $normalize($player->name) === $normalize($slot->name) && $normalize($player->surname) === $normalize($slot->surname))
                    ->each(function (Player $player) use ($source): void {
                        $player->setAttribute('source_teams', $player->teams()->whereHas('category', fn ($q) => $q->where('event_id', $source->id))
                            ->with('category.category')->limit(10)->get()->map(fn ($team) => $team->name.' · '.$team->category?->category?->name)->all());
                        $player->setAttribute('source_categories', $player->registrations()->whereHas('categoryEvents', fn ($q) => $q->where('event_id', $source->id))
                            ->with(['categoryEvents' => fn ($q) => $q->where('event_id', $source->id)->with('category')])->limit(10)->get()
                            ->flatMap(fn ($registration) => $registration->categoryEvents->map(fn ($category) => $category->category?->name))->filter()->unique()->values()->all());
                        $importedContexts = NoProfileTeamPlayer::query()->where('player_profile', $player->id)
                            ->whereHas('team.category', fn ($q) => $q->where('event_id', $source->id))
                            ->with('team.category.category')->limit(10)->get()
                            ->map(fn ($slot) => $slot->team->name.' · '.$slot->team->category?->category?->name)->all();
                        $player->setAttribute('source_teams', array_values(array_unique(array_merge($player->source_teams, $importedContexts))));
                    });
            }

            return ['slot' => $slot, 'candidates' => $candidates->take(20), 'overflow' => $candidates->count() > 20];
        });

        return compact('slots', 'rows');
    }

    public function fingerprint(array $preview, Event $event, Event $source, Team $team, int $actorId, int $page): string
    {
        $snapshot = $preview['rows']->map(fn ($row) => [
            $row['slot']->only(['id', 'team_id', 'name', 'surname', 'rank', 'player_profile', 'pay_status']),
            $row['candidates']->map->getAttributes()->all(), $row['overflow'],
        ])->all();

        return hash_hmac('sha256', json_encode([$event->id, $source->id, $team->id, $actorId, $page, $snapshot], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
