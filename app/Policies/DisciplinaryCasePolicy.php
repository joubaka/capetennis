<?php

namespace App\Policies;

use App\Models\DisciplinaryCase;
use App\Models\User;

class DisciplinaryCasePolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('super-user') ? true : null;
    }

    public function view(User $user, DisciplinaryCase $case): bool
    {
        return $user->is_event_admin($case->event_id)
            || $case->reported_by === $user->id
            || $case->assignments()->where('user_id', $user->id)->whereNull('recused_at')->exists()
            || in_array((int) $case->player_id, $user->ownedPlayerIds(), true);
    }

    public function manage(User $user, DisciplinaryCase $case): bool
    {
        return $user->is_event_admin($case->event_id);
    }

    public function decide(User $user, DisciplinaryCase $case): bool
    {
        return $case->assignments()->where('user_id', $user->id)
            ->where('role', 'chair')->whereNull('recused_at')
            ->where('conflict_declared', false)->exists();
    }

    public function respond(User $user, DisciplinaryCase $case): bool
    {
        return in_array((int) $case->player_id, $user->ownedPlayerIds(), true);
    }
}
