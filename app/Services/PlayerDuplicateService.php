<?php

namespace App\Services;

use App\Models\Player;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PlayerDuplicateService
{
    /** Tables/columns whose rows mean that a profile has been used. */
    private const USAGE_REFERENCES = [
        'registrations' => ['player_id'],
        'registration_order_items' => ['player_id'],
        'player_registrations' => ['player_id'],
        'team_players' => ['player_id'],
        'team_payment_orders' => ['player_id'],
        'transactions_pf' => ['player_id'],
        'player_subscriptions' => ['player_id'],
        'positions' => ['player_id'],
        'ranking_scores' => ['player_id'],
        'ranking_score_legs' => ['player_id'],
        'rankings' => ['player_id'],
        'series_rankings' => ['player_id'],
        'practices' => ['player_id'],
        'exercises' => ['player_id'],
        'invitations' => ['player_id'],
        'leaderboards' => ['player_id'],
        'goals' => ['player_id'],
        'clothing_orders' => ['player_id'],
        'player_agreements' => ['player_id'],
        'player_violations' => ['player_id'],
        'player_suspensions' => ['player_id'],
        'event_nominations' => ['player_id'],
        'goal_players' => ['player_id'],
        'team_fixture_players' => ['team1_id', 'team2_id'],
        'fixture_players' => ['team1_id', 'team2_id'],
        'practice_fixtures' => ['registration1_id', 'registration2_id'],
        'practice_results' => ['winner_registration', 'loser_registration'],
        'team_fixture_results' => ['match_winner_id', 'match_loser_id'],
    ];

    public function candidates(int $perPage = 25): LengthAwarePaginator
    {
        $groups = Player::query()
            ->selectRaw('LOWER(TRIM(name)) as duplicate_name, LOWER(TRIM(surname)) as duplicate_surname, COUNT(*) as duplicate_count')
            ->whereNotNull('name')->whereRaw("TRIM(name) <> ''")
            ->whereNotNull('surname')->whereRaw("TRIM(surname) <> ''")
            ->groupByRaw('LOWER(TRIM(name)), LOWER(TRIM(surname))')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('duplicate_surname')->orderBy('duplicate_name')
            ->paginate($perPage)
            ->withQueryString();

        $groups->setCollection($groups->getCollection()->map(function ($group) {
            $players = Player::query()
                ->with(['user:id,name,email', 'users:id,name,email'])
                ->whereRaw('LOWER(TRIM(name)) = ?', [$group->duplicate_name])
                ->whereRaw('LOWER(TRIM(surname)) = ?', [$group->duplicate_surname])
                ->orderBy('id')
                ->get()
                ->map(fn (Player $player) => $this->describe($player));

            return (object) [
                'name' => $players->first()['player']->full_name,
                'players' => $players,
                'can_merge' => $players->contains(fn ($item) => $item['is_empty']),
            ];
        }));

        return $groups;
    }

    public function describe(Player $player): array
    {
        $usage = $this->usage($player->id);
        $owners = $player->users
            ->push($player->user)
            ->filter()
            ->unique('id')
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values();

        return [
            'player' => $player,
            'owners' => $owners,
            'emails' => $owners->pluck('email')->push($player->email)->filter()->unique()->values(),
            'usage' => $usage,
            'usage_total' => array_sum($usage),
            'is_empty' => array_sum($usage) === 0,
        ];
    }

    public function usage(int $playerId): array
    {
        $usage = [];

        foreach (self::USAGE_REFERENCES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = 0;
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $count += DB::table($table)->where($column, $playerId)->count();
                }
            }

            if ($count > 0) {
                $usage[$table] = $count;
            }
        }

        return $usage;
    }

    public function merge(Player $keep, Player $remove, User $approvedBy): Player
    {
        if ($keep->is($remove)) {
            throw ValidationException::withMessages(['remove_player_id' => 'Choose two different profiles.']);
        }

        if ($this->identityKey($keep) !== $this->identityKey($remove)) {
            throw ValidationException::withMessages(['remove_player_id' => 'The profiles no longer have the same name and surname.']);
        }

        return DB::transaction(function () use ($keep, $remove, $approvedBy) {
            $keep = Player::query()->lockForUpdate()->findOrFail($keep->id);
            $remove = Player::query()->lockForUpdate()->findOrFail($remove->id);
            $usage = $this->usage($remove->id);

            if (array_sum($usage) > 0) {
                throw ValidationException::withMessages([
                    'remove_player_id' => 'The profile selected for removal is in use and cannot be merged automatically.',
                ]);
            }

            $sourceUserIds = DB::table('user_players')->where('player_id', $remove->id)->pluck('user_id');
            if ($remove->userId) {
                $sourceUserIds->push($remove->userId);
            }

            foreach ($sourceUserIds->filter()->unique() as $userId) {
                if (! DB::table('user_players')->where(['user_id' => $userId, 'player_id' => $keep->id])->exists()) {
                    DB::table('user_players')->insert([
                        'user_id' => $userId,
                        'player_id' => $keep->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            foreach (['cellNr', 'email', 'dateOfBirth', 'gender', 'coach', 'profile_updated_at'] as $field) {
                if (blank($keep->{$field}) && filled($remove->{$field})) {
                    $keep->{$field} = $remove->{$field};
                }
            }
            if (! $keep->userId && $remove->userId) {
                $keep->userId = $remove->userId;
            }
            $keep->profile_complete = $keep->isProfileComplete();
            $keep->save();

            DB::table('user_players')->where('player_id', $remove->id)->delete();
            $removedSnapshot = $remove->only(['id', 'name', 'surname', 'email', 'dateOfBirth', 'gender', 'userId']);
            $remove->delete();

            activity('player-profile-merge')
                ->causedBy($approvedBy)
                ->performedOn($keep)
                ->withProperties([
                    'kept_player_id' => $keep->id,
                    'removed_player' => $removedSnapshot,
                    'transferred_user_ids' => $sourceUserIds->filter()->unique()->values()->all(),
                ])
                ->log('Duplicate player profile merged');

            return $keep->refresh();
        });
    }

    private function identityKey(Player $player): string
    {
        return mb_strtolower(trim((string) $player->name).'|'.trim((string) $player->surname));
    }
}
