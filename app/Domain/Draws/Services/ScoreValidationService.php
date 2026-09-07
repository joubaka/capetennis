<?php

namespace App\Domain\Draws\Services;

use App\Models\Fixture;

final class ScoreValidationService
{
    /**
     * Validates the safe common denominator for configured tennis formats.
     * Format-specific overrides can be added without changing controllers.
     */
    public function validate(Fixture $fixture, array $sets): array
    {
        $draw = $fixture->relationLoaded('draw')
            ? $fixture->getRelation('draw')
            : ($fixture->exists ? $fixture->draw : null);
        $settings = $draw?->relationLoaded('settings')
            ? $draw->getRelation('settings')
            : ($draw?->exists ? $draw->settings : null);
        $scoreFormat = (string) ($settings?->score_format ?? '');
        if ($scoreFormat !== '' && in_array($scoreFormat, TennisScoreFormat::keys(), true)) {
            return $this->validatePreset($scoreFormat, $sets);
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
            if ($home < 0 || $away < 0 || $exceedsRange || $home === $away) {
                return ['valid' => false, 'message' => 'Each set must have different, non-negative scores within the valid range.'];
            }
        }

        $homeSets = collect($sets)->where(fn ($set) => $set[0] > $set[1])->count();
        $awaySets = count($sets) - $homeSets;
        if ($homeSets === $awaySets) {
            return ['valid' => false, 'message' => 'The entered sets do not produce a match winner.'];
        }

        return ['valid' => true, 'message' => null];
    }

    private function validatePreset(string $key, array $sets): array
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
            if ($home === false || $away === false || $home < 0 || $away < 0 || $home === $away) {
                return $this->failure('Each set needs different, non-negative whole-number scores.');
            }

            $setType = $format['set_types'][$index] ?? end($format['set_types']);
            $setError = $this->setError($setType, $home, $away);
            if ($setError !== null) {
                return $this->failure($setError);
            }

            $wins[$home > $away ? 0 : 1]++;
        }

        if (max($wins) !== $format['wins_needed']) {
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
