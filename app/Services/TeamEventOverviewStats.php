<?php

namespace App\Services;

use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\TeamFixture;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;

/** Read-only, event-scoped roster and competition counts. */
class TeamEventOverviewStats
{
    public function for(Event $event): array
    {
        $teams = app(EventTeamScope::class)->query($event)->withoutEagerLoads();
        $profileTeams = (clone $teams)->where(fn ($query) => $query->where('noProfile', false)->orWhereNull('noProfile'))->select('teams.id');
        $importedTeams = (clone $teams)->where('noProfile', true)->select('teams.id');
        $profiles = TeamPlayer::withoutGlobalScopes()->whereIn('team_id', $profileTeams)->where('player_id', '>', 0);
        // Imported slots are authoritative; their profile mirrors are not extra players.
        $imported = NoProfileTeamPlayer::query()->whereIn('team_id', $importedTeams)
            ->where(fn ($query) => $query->where('player_profile', '>', 0)
                ->orWhereRaw("TRIM(COALESCE(name, '')) <> ''")
                ->orWhereRaw("TRIM(COALESCE(surname, '')) <> ''"));
        $players = (clone $profiles)->count() + (clone $imported)->count();
        $paid = (clone $profiles)->where('pay_status', 1)->count() + (clone $imported)
            ->where(fn ($query) => $query->where('pay_status', 1)->orWhereExists(fn ($mirror) => $mirror
                ->selectRaw('1')->from('team_players')
                ->whereColumn('team_players.team_id', 'no_profile_team_players.team_id')
                ->whereColumn('team_players.rank', 'no_profile_team_players.rank')
                ->whereColumn('team_players.player_id', 'no_profile_team_players.player_profile')
                ->where('team_players.player_id', '>', 0)->where('team_players.pay_status', 1)))->count();
        $orders = TeamPaymentOrder::query()->where('event_id', $event->id);
        $fixtures = TeamFixture::query()->withoutEagerLoads()->whereHas('draw', fn ($query) => $query->where('event_id', $event->id));
        $played = 0;
        (clone $fixtures)->with(['draw', 'teamResults'])->chunkById(200, function ($fixtures) use (&$played): void {
            foreach ($fixtures as $fixture) {
                if (app(TeamRubberResultService::class)->outcome($fixture)['complete']) {
                    $played++;
                }
            }
        });

        return [
            'categories' => $event->categories()->count(),
            'regions' => $event->regions()->count(),
            'teams' => (clone $teams)->count(),
            'entries' => $players,
            'players' => $players,
            'paid' => $paid,
            'pending_checkouts' => (clone $orders)->whereNull('withdrawn_at')
                ->where(fn ($query) => $query->whereNull('collection_status')->orWhere('collection_status', ''))
                ->where(fn ($query) => $query->whereNull('pay_status')->orWhere('pay_status', false))->count(),
            // Unpaid checkout cancellation/expiry also sets withdrawn_at, but is not a paid withdrawal.
            'withdrawals' => (clone $orders)->whereNotNull('withdrawn_at')->where('pay_status', true)->count(),
            'pending_refunds' => (clone $orders)->where('refund_status', 'pending')->count(),
            'drawsLocked' => $event->draws()->where('locked', 1)->count(),
            'matchesTotal' => (clone $fixtures)->count(),
            'matchesPlayed' => $played,
        ];
    }
}
