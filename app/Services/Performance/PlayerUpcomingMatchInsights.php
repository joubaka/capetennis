<?php

namespace App\Services\Performance;

use App\Models\{Player, TeamFixture, User};
use App\Services\MyTennisService;

/** Published schedule details, independent of saved results and season filters. */
class PlayerUpcomingMatchInsights
{
    public function forPlayer(User $user, Player $player, int $page = 1): array
    {
        $fixtures = app(MyTennisService::class)->upcomingScheduledMatchesFor($player);
        $pages = max(1, (int) ceil($fixtures->count() / 20));
        $page = max(1, min($page, $pages));
        $rows = $fixtures->slice(($page - 1) * 20, 20)->map(function ($fixture) use ($player) {
            $team = $fixture instanceof TeamFixture;
            if ($team) {
                $home = $fixture->lineup_display['home']['selected_player_assigned'] ?? false;
                $assigned = $home || ($fixture->lineup_display['away']['selected_player_assigned'] ?? false);
                $opponents = collect($assigned ? ($fixture->lineup_display[$home ? 'away' : 'home']['players'] ?? []) : [])
                    ->map(fn ($opponent) => ['id' => $opponent['player_id'] ?? null, 'name' => $opponent['name']]);
            } else {
                $home = $fixture->registration1?->players->contains('id', $player->id);
                $opponents = ($home ? $fixture->registration2?->players : $fixture->registration1?->players)
                    ?->map(fn ($opponent) => ['id' => (int) $opponent->id, 'name' => $opponent->full_name]) ?? collect();
            }
            return ['date' => $fixture->scheduled_at, 'event' => $fixture->draw?->event?->name,
                'draw' => $fixture->draw?->drawName, 'venue' => $fixture->venue?->name,
                'court' => $team ? $fixture->court_label : $fixture->orderOfPlay?->court,
                'team' => $team ? implode(' vs ', $fixture->tie_display ?? []) : null,
                'opponents' => $opponents->values()->all(),
                'url' => route($team ? 'frontend.fixtures.show' : 'public.roundrobin.show', $fixture->draw_id)];
        })->values()->all();
        // Share only the saved badge for opponents on this page of published fixtures.
        $opponentIds = collect($rows)->pluck('opponents')->flatten(1)->pluck('id')->filter()->unique();
        $ratings = [];
        $access = app(PlayerRatingAccess::class);
        if ($opponentIds->isNotEmpty() && $access->allows($user, $player) && $access->allowsDiscovery($user)) {
            $snapshot = app(PlayerAbilitySnapshotStore::class)->current();
            if (array_key_exists('reason', $snapshot) && $snapshot['reason'] === null) {
                foreach ($opponentIds as $id) {
                    $estimates = [];
                    foreach (array_keys(BallRatingModel::LABELS) as $stage) {
                        $estimates[$stage] = $snapshot['ball_ratings'][$stage]['ratings'][$id] ?? [];
                    }
                    $stage = BallRatingModel::currentStage($estimates);
                    if ($stage === null) continue;
                    $estimate = $estimates[$stage];
                    $ratings[$id] = ['stage' => $stage, 'score' => round((float) $estimate['score'], 1),
                        'confidence_label' => AbilityConfidenceDisplay::label((int) ($estimate['confidence_index'] ?? 0), $estimate),
                        'connection_status' => ($estimate['comparable'] ?? true) ? null : 'disconnected'];
                }
            }
        }
        return ['rows' => $rows, 'ratings' => $ratings, 'total' => $fixtures->count(), 'page' => $page, 'pages' => $pages];
    }
}
