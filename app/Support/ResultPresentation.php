<?php

namespace App\Support;

use App\Models\Fixture;
use App\Models\TeamFixture;
use App\Services\TeamRubberResultService;
use Illuminate\Support\Collection;

final class ResultPresentation
{
    public static function classes(Fixture|TeamFixture|null $fixture): array
    {
        if (!$fixture) return ['', ''];
        if ($fixture instanceof TeamFixture) {
            $outcome = app(TeamRubberResultService::class)->outcome($fixture);
            return self::sideClasses($outcome['complete'] ? $outcome['winner'] : null);
        }
        $winner = $fixture->fixtureResults->isNotEmpty() ? $fixture->winner_id : $fixture->winner_registration;
        return self::registrationClasses($winner, $fixture->registration1_id, $fixture->registration2_id);
    }

    public static function registrationClasses($winner, $home, $away): array
    {
        if (!$winner) return ['', ''];
        if ($home && (int) $winner === (int) $home) return ['winner-home', $away ? 'loser-home' : ''];
        if ($away && (int) $winner === (int) $away) return [$home ? 'loser-home' : '', 'winner-home'];
        return ['', ''];
    }

    public static function sideClasses(?string $winner): array
    {
        return match ($winner) {
            'home' => ['winner-home', 'loser-home'],
            'away' => ['loser-home', 'winner-home'],
            default => ['', ''],
        };
    }

    public static function tieClasses(Collection $fixtures): array
    {
        $first = $fixtures->first();
        if (!$first) return ['', ''];
        if ($first->team_tie_id && $first->teamTie) {
            $tie = $first->teamTie;
            if ((int) $tie->draw_id !== (int) $first->draw_id) return ['', ''];
            // Check the required format against the displayed rubbers; never load
            // undisplayed results or query each tie's rubber rows again.
            $snapshot = $tie->format_snapshot ?? $first->draw?->team_format_snapshot;
            $required = is_array($snapshot)
                ? collect($snapshot['rubbers'] ?? [])->filter(fn ($rubber) => $rubber['is_required'] ?? true)->pluck('sequence')->all()
                : ($first->draw?->teamEventFormat?->rubbers->where('is_required', true)->pluck('sequence')->all() ?? []);
            if (array_diff($required, $fixtures->pluck('rubber_sequence')->all())) return ['', ''];
        }
        $wins = ['home' => 0, 'away' => 0];
        foreach ($fixtures as $fixture) {
            $outcome = app(TeamRubberResultService::class)->outcome($fixture);
            if (!$outcome['complete'] || !in_array($outcome['winner'], ['home', 'away'], true)) return ['', ''];
            $wins[$outcome['winner']]++;
        }
        return self::sideClasses($wins['home'] === $wins['away'] ? null : ($wins['home'] > $wins['away'] ? 'home' : 'away'));
    }
}
