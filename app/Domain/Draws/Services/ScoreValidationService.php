<?php

namespace App\Domain\Draws\Services;

use App\Models\Fixture;

final class ScoreValidationService
{
    /**
     * Validates the safe common denominator for configured tennis formats.
     * Format-specific overrides can be added without changing controllers.
     */
    public function validate(Fixture $fixture, array $sets, ?int $decidingPointWinnerId = null): array
    {
        $draw = $fixture->relationLoaded('draw')
            ? $fixture->getRelation('draw')
            : ($fixture->exists ? $fixture->draw : null);
        $settings = $draw?->relationLoaded('settings')
            ? $draw->getRelation('settings')
            : ($draw?->exists ? $draw->settings : null);
        $scoreFormat = (string) ($settings?->score_format ?? '');
        if ($scoreFormat === TennisScoreFormat::TIMED) {
            return $this->validateTimed($fixture, $sets, $decidingPointWinnerId);
        }
        if ($decidingPointWinnerId !== null) {
            return $this->failure('A deciding-point winner is only allowed for tied timed playoffs.');
        }
        if ($scoreFormat !== '' && in_array($scoreFormat, TennisScoreFormat::keys(), true)) {
            return $this->validatePreset($scoreFormat, $sets, $fixture->stage === 'RR');
        }

        // Historical draws did not store a format, only a maximum set count.
        // Preserve that behaviour until an administrator deliberately chooses
        // one of the enforceable presets in Setup & Rules.
        $configuredSets = max(1, min(5, (int) ($settings?->num_sets ?: 3)));
        if ($sets === [] || count($sets) > $configuredSets) {
            $label = $configuredSets === 1 ? 'set' : 'sets';

            return ['valid' => false, 'message' => "This draw allows {$configuredSets} {$label} per match."];
        }

        foreach ($sets as $index => [$home, $away]) {
            $exceedsRange = $index === 2
                ? $home > 999 || $away > 999
                : $home > 20 || $away > 20;
            if ($home < 0 || $away < 0 || $exceedsRange || ($home === $away && $fixture->stage !== 'RR')) {
                return ['valid' => false, 'message' => 'Each set must have different, non-negative scores within the valid range.'];
            }
        }

        $homeSets = collect($sets)->where(fn ($set) => $set[0] > $set[1])->count();
        $awaySets = collect($sets)->where(fn ($set) => $set[1] > $set[0])->count();
        if ($homeSets === $awaySets && $fixture->stage !== 'RR') {
            return ['valid' => false, 'message' => 'The entered sets do not produce a match winner.'];
        }

        return ['valid' => true, 'message' => null];
    }

    /** A retirement retains completed sets and the score in the final, unfinished set. */
    public function validateRetirement(Fixture $fixture, array $sets): array
    {
        $format = $this->retirementFormat($fixture);
        if ($sets === [] || !array_is_list($sets) || count($sets) > $format['max_sets']) {
            return $this->failure('Enter the score reached before retirement, within the configured set count.');
        }
        $wins = [0, 0];
        foreach ($sets as $index => $set) {
            if (!is_array($set) || !array_is_list($set) || count($set) !== 2) return $this->failure('Each set needs two whole-number scores.');
            [$home, $away] = array_map(fn ($value) => filter_var($value, FILTER_VALIDATE_INT), array_values($set));
            if ($home === false || $away === false || min($home, $away) < 0 || max($home, $away) > 999) {
                return $this->failure('Retirement scores must be whole numbers from 0 to 999.');
            }
            $type = $format['set_types'][$index] ?? end($format['set_types']);
            $complete = $type !== 'timed' && $type !== 'custom' && $this->setError($type, $home, $away) === null;
            if ($index < count($sets) - 1 && !$complete && !($type === 'custom' && $home !== $away)) {
                return $this->failure('Only the final set may be unfinished when a player retires.');
            }
            if (!$this->reachableRetirementScore($type, $home, $away)) {
                return $this->failure('The retirement score goes beyond a valid score for this set.');
            }
            if ($complete || ($type === 'custom' && $index < count($sets) - 1)) $wins[$home > $away ? 0 : 1]++;
            if ($type !== 'timed' && max($wins) >= $format['wins_needed']) {
                return $this->failure('This match was already completed. Record a played result instead of retirement.');
            }
        }
        return ['valid' => true, 'message' => null];
    }

    /** Count only completed sets in retirement standings, never the unfinished score. */
    public function retirementSetComplete(Fixture $fixture, int $index, int $home, int $away, int $setCount): bool
    {
        $format = $this->retirementFormat($fixture);
        $type = $format['set_types'][$index] ?? end($format['set_types']);
        return $type === 'custom' ? $index < $setCount - 1 && $home !== $away
            : ($type !== 'timed' && $this->setError($type, $home, $away) === null);
    }

    private function retirementFormat(Fixture $fixture): array
    {
        $settings = $fixture->draw?->settings;
        $key = (string) ($settings?->score_format ?? '');
        if (in_array($key, TennisScoreFormat::keys(), true)) return TennisScoreFormat::get($key);
        $max = max(1, min(5, (int) ($settings?->num_sets ?: 3)));
        return ['max_sets' => $max, 'wins_needed' => (int) ceil($max / 2),
            'set_types' => array_fill(0, $max, ($settings?->requiresFullSets() ?? true) ? 'full' : 'custom')];
    }

    private function reachableRetirementScore(string $type, int $home, int $away): bool
    {
        [$high, $low] = [max($home, $away), min($home, $away)];
        if (in_array($type, ['timed', 'custom'], true)) return true;
        if (str_starts_with($type, 'target')) return $high <= (int) substr($type, 6) && $low < (int) substr($type, 6);
        if ($type === 'match_tiebreak') return $high <= 10 || $high - $low <= 2;
        $target = $type === 'pro8' ? 8 : 6;
        return $high < $target || ($high === $target && $low <= $target)
            || ($high === $target + 1 && in_array($low, [$target - 1, $target], true));
    }

    private function validateTimed(Fixture $fixture, array $sets, ?int $decidingPointWinnerId): array
    {
        if (count($sets) !== 1 || ! is_array($sets[0]) || count($sets[0]) !== 2) {
            return $this->failure('Record one pair of game totals for a timed match.');
        }
        [$home, $away] = array_map(fn ($score) => filter_var($score, FILTER_VALIDATE_INT), $sets[0]);
        if ($home === false || $away === false || min($home, $away) < 0 || max($home, $away) > 999) {
            return $this->failure('Timed game totals must be whole numbers from 0 to 999.');
        }
        if ($fixture->stage === 'RR' || $home !== $away) {
            return $decidingPointWinnerId === null ? ['valid' => true, 'message' => null]
                : $this->failure('Select a deciding-point winner only when a timed playoff is tied.');
        }
        $participants = array_filter([(int) $fixture->registration1_id, (int) $fixture->registration2_id]);
        if ($decidingPointWinnerId === null || ! in_array($decidingPointWinnerId, $participants, true) || count($participants) !== 2) {
            return $this->failure('A tied timed playoff needs its deciding-point winner, selected from the two current participants.');
        }
        return ['valid' => true, 'message' => null];
    }

    private function validatePreset(string $key, array $sets, bool $roundRobin = false): array
    {
        $format = TennisScoreFormat::get($key);
        if ($sets === [] || count($sets) > $format['max_sets']) {
            return $this->failure("{$format['label']} allows at most {$format['max_sets']} completed set(s).");
        }

        $wins = [0, 0];
        foreach ($sets as $index => $set) {
            if (max($wins) === $format['wins_needed']) {
                return $this->failure('Remove sets entered after the match was already won.');
            }
            if (! is_array($set) || count($set) !== 2 || ! is_numeric($set[0]) || ! is_numeric($set[1])) {
                return $this->failure('Each set needs two whole-number scores.');
            }

            $home = filter_var($set[0], FILTER_VALIDATE_INT);
            $away = filter_var($set[1], FILTER_VALIDATE_INT);
            if ($home === false || $away === false || $home < 0 || $away < 0 || ($home === $away && ! $roundRobin)) {
                return $this->failure('Each set needs different, non-negative whole-number scores.');
            }

            if ($roundRobin && $home === $away) {
                if ($home > 999) return $this->failure('Drawn set scores cannot exceed 999.');
                continue;
            }
            $setType = $format['set_types'][$index] ?? end($format['set_types']);
            $setError = $this->setError($setType, $home, $away);
            if ($setError !== null) {
                return $this->failure($setError);
            }

            $wins[$home > $away ? 0 : 1]++;
        }

        if (max($wins) !== $format['wins_needed'] && ! ($roundRobin && $wins[0] === $wins[1])) {
            return $this->failure("{$format['label']} requires {$format['wins_needed']} set win(s) to complete the match.");
        }

        return ['valid' => true, 'message' => null];
    }

    private function setError(string $type, int $home, int $away): ?string
    {
        [$high, $low] = [max($home, $away), min($home, $away)];

        if (str_starts_with($type, 'target')) {
            $target = (int) substr($type, strlen('target'));

            return $high === $target && $low < $target
                ? null
                : "This short set must finish when a player reaches {$target} games.";
        }

        return match ($type) {
            'full' => (($high === 6 && $low <= 4) || ($high === 7 && in_array($low, [5, 6], true)))
                ? null : 'Enter a completed full set: 6-0 to 6-4, 7-5 or 7-6.',
            'pro8' => (($high === 8 && $low <= 6) || ($high === 9 && in_array($low, [7, 8], true)))
                ? null : 'Enter a completed pro set: 8-0 to 8-6, 9-7 or 9-8.',
            'match_tiebreak' => $high >= 10 && $high - $low >= 2
                ? null : 'A match tiebreak must reach 10 points and be won by 2.',
            'custom' => $high <= 999 ? null : 'Custom scores cannot exceed 999.',
            default => 'The selected scoring format is not supported.',
        };
    }

    private function failure(string $message): array
    {
        return ['valid' => false, 'message' => $message];
    }
}
