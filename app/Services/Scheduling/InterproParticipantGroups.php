<?php

namespace App\Services\Scheduling;

use App\Models\Draw;
use App\Services\Draw\InterproRoundRobinFormat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Prove that same-draw playoff branches are separated by canonical dependencies. */
final class InterproParticipantGroups
{
    public static function forDraw(Draw $draw, Collection $fixtures): ?string
    {
        if (! InterproRoundRobinFormat::selected($draw)) return null;
        try {
            InterproDailyMatchGraph::build($draw, $fixtures);
        } catch (\InvalidArgumentException) {
            return null;
        }
        $playoffs = $fixtures->where('stage', '!=', 'RR')->keyBy('match_nr');
        if ($playoffs->isEmpty()) return null;
        $qualifiers = $playoffs->only([2001, 2002, 3001, 3002, 3003, 3004])
            ->flatMap(fn ($fixture) => [$fixture->registration1_id, $fixture->registration2_id])->filter();
        // Before qualification all slots are conditional. Afterwards each qualifying
        // position must identify a distinct entrant; partial or corrupt seeding proves nothing.
        if ($qualifiers->count() && ($qualifiers->count() !== 12 || $qualifiers->unique()->count() !== 12)) return null;
        if ($qualifiers->isEmpty() && $playoffs->contains(fn ($fixture) => $fixture->registration1_id || $fixture->registration2_id)) return null;
        $expected = [];
        foreach (InterproRoundRobinFormat::template($draw) as $number => $definition) {
            foreach ($definition[2] as $source) {
                if (preg_match('/^([WL])(\d+)$/', $source, $parts)) {
                    $expected[(int) $parts[2]][$parts[1] === 'W' ? 'parent_fixture_id' : 'loser_parent_fixture_id'] = (int) $playoffs[$number]->id;
                }
            }
        }
        foreach ($playoffs as $number => $fixture) {
            foreach (['parent_fixture_id', 'loser_parent_fixture_id'] as $link) {
                if ((int) $fixture->$link !== ($expected[$number][$link] ?? 0)) return null;
            }
        }
        $registrations = $draw->groups()->with('groupRegistrations')->get()
            ->flatMap(fn ($group) => $group->groupRegistrations->pluck('registration_id'))->map(fn ($id) => (int) $id)->unique();
        $memberships = DB::table('player_registrations')->whereIn('registration_id', $registrations)->get();
        if ($memberships->pluck('player_id')->count() !== $memberships->pluck('player_id')->unique()->count()
            || $memberships->pluck('registration_id')->unique()->count() !== $registrations->count()) return null;
        return 'interpro-draw-'.$draw->id;
    }
}
