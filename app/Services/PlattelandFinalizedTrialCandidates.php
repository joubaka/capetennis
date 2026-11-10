<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSelectionFinalization;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Collection;

/** Project only the candidate rows from a private finalized selection. */
final class PlattelandFinalizedTrialCandidates
{
    private const SOURCE_EVENT = 241;

    public function __construct(
        private EventTeamScope $scope,
        private SelectionPreviewShareService $access,
    ) {}

    public function preview(string $group, User $actor): array
    {
        $source = Event::query()->find(self::SOURCE_EVENT);
        if (! $source?->isTeam() || $source->name !== 'Platteland Tennis Tournament Primary Schools 2026') {
            return $this->unavailable('The Platteland source event is unavailable.');
        }
        if (! $this->access->canView($source, $actor)) {
            return $this->unavailable('You need access to the Platteland selection preview to review Step 2.');
        }

        $finalization = EventSelectionFinalization::active()->where('event_id', $source->id)->first();
        $frozen = $finalization?->groups[$group] ?? null;
        if (! $frozen) {
            return $this->unavailable('There is no active finalized Platteland selection for this division.');
        }

        $snapshot = collect($frozen['snapshot'] ?? [])->keyBy(fn (array $row) => (string) $row['id']);
        $keys = array_map('strval', $frozen['selected_keys'] ?? []);
        $teamSize = (int) ($frozen['team_size'] ?? $finalization->team_size);
        $reserveCount = (int) ($frozen['reserve_count'] ?? $finalization->reserve_count);
        // Match the locked selection preview's order for selected players.
        $selected = collect($keys)->map(fn (string $key) => $snapshot->get($key))->filter()
            ->sortBy(fn (array $row) => $row['draft_position'] ?? $row['position'] ?? PHP_INT_MAX)->values();

        $teamIds = $this->scope->query($source)->without(['team_players', 'team_players_no_profile'])
            ->pluck('id')->map(fn ($id) => (int) $id)->flip();
        $slotIds = $snapshot->flatMap(fn (array $row) => $row['source_participants'] ?? [])
            ->where('type', 'imported')->pluck('id')->unique()->all();
        $slots = NoProfileTeamPlayer::query()->whereIn('team_id', $teamIds->keys())
            ->whereIn('id', $slotIds)->get(['id', 'team_id', 'player_profile', 'name', 'surname'])->keyBy('id');
        $identify = fn (array $row) => $this->profileId($row, $teamIds, $slots);
        $mainIds = $selected->take($teamSize)->map(fn (array $row) => $this->profileId($row, $teamIds))->filter()->all();

        $additional = $selected->slice($teamSize, $reserveCount)
            ->map(fn (array $row) => ['row' => $row, 'list_status' => 'Locked reserve'])
            ->concat($snapshot->except($keys)->map(fn (array $row) => ['row' => $row, 'list_status' => 'Additional candidate']))
            ->map(fn (array $candidate) => $candidate + ['id' => $identify($candidate['row'])])
            ->filter(fn (array $candidate) => $candidate['id'] && ! in_array($candidate['id'], $mainIds, true))
            ->unique('id');

        $players = Player::query()->whereIn('id', $additional->pluck('id'))
            ->get(['id', 'name', 'surname'])->keyBy('id');
        $candidates = $additional->filter(fn (array $candidate) => $players->has($candidate['id']))
            ->map(function (array $candidate) use ($players) {
                $row = $candidate['row'];
                return [
                    'id' => $candidate['id'],
                    'name' => $players[$candidate['id']]->full_name,
                    'source' => 'Platteland',
                    'list_status' => $candidate['list_status'],
                    'team' => implode(', ', $row['teams'] ?? []),
                    'rank' => $row['rank'] ?? null,
                    'source_position' => $row['position'] ?? null,
                    'band' => $row['band'] ?? null,
                    'wins' => $row['wins'] ?? null,
                    'losses' => $row['losses'] ?? null,
                    'starting_credit' => $row['starting_credit'] ?? null,
                    'lost_all' => false,
                    'review_adjacent_band' => false,
                ];
            })->values();

        return ['notice' => null, 'candidates' => $candidates];
    }

    private function profileId(array $row, Collection $teamIds, ?Collection $slots = null): ?int
    {
        $key = (string) $row['id'];
        $participants = collect($row['source_participants'] ?? []);
        if (ctype_digit($key) && (int) $key > 0) {
            return $participants->isNotEmpty() && $participants->every(fn (array $participant) =>
                ($participant['type'] ?? null) === 'profile' && (int) ($participant['id'] ?? 0) === (int) $key
                && (int) ($participant['profile_id'] ?? $key) === (int) $key
                && $teamIds->has((int) ($participant['team_id'] ?? 0))) ? (int) $key : null;
        }
        if (! preg_match('/^imported:([1-9]\d*):([1-9]\d*)$/', $key, $matches) || ! $teamIds->has((int) $matches[1])) {
            return null;
        }

        // A current link alone cannot attest the frozen imported identity.
        $participants = $participants->unique(fn (array $participant) => serialize($participant));
        if ($participants->count() !== 1) {
            return null;
        }
        $participant = $participants->first();
        $slotId = (int) ($participant['id'] ?? 0);
        $profileId = (int) ($participant['profile_id'] ?? 0);
        if (($participant['type'] ?? null) !== 'imported' || (int) ($participant['team_id'] ?? 0) !== (int) $matches[1]
            || $slotId < 1 || $profileId < 1) {
            return null;
        }
        // Frozen main identities are used only to suppress aliases, even
        // when their live links no longer qualify for inclusion.
        if ($slots === null) {
            return $profileId;
        }
        $slot = $slots->get($slotId);
        return $slot && (int) $slot->team_id === (int) $matches[1] && (int) $slot->player_profile === $profileId
            && trim($slot->name.' '.$slot->surname) === trim($row['name'] ?? '') ? $profileId : null;
    }

    private function unavailable(string $notice): array
    {
        return ['notice' => $notice, 'candidates' => collect()];
    }
}
