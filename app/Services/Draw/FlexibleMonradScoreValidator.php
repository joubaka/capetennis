<?php

namespace App\Services\Draw;

use Illuminate\Validation\ValidationException;

final class FlexibleMonradScoreValidator
{
    public function validate(array $sets, int $bestOf, bool $requireFullSets = true): void
    {
        $fail = fn ($message) => throw ValidationException::withMessages(['sets' => $message]);
        if (! in_array($bestOf, [1, 3, 5], true)) $fail('The draw must use one, three or five sets.');
        if (! $sets || count($sets) > $bestOf) $fail("Enter a completed best-of-{$bestOf} match.");
        $needed = intdiv($bestOf, 2) + 1;
        $wins = [0, 0];
        foreach ($sets as $set) {
            if (max($wins) === $needed) $fail('Remove sets entered after the match was already won.');
            if (! is_array($set) || ! array_is_list($set) || count($set) !== 2 || ! is_int($set[0]) || ! is_int($set[1])) {
                $fail('Each set needs two whole-number scores.');
            }
            if ($set[0] < 0 || $set[1] < 0) {
                $fail('Set scores cannot be negative.');
            }
            if ($set[0] === $set[1]) {
                $fail('Each set must have a winner.');
            }
            if ($requireFullSets) {
                [$high, $low] = [max($set), min($set)];
                if (!(($high === 6 && $low >= 0 && $low <= 4) || ($high === 7 && in_array($low, [5, 6], true)))) {
                    $fail('Enter a completed set: 6–0 to 6–4, 7–5 or 7–6.');
                }
            }
            $wins[$set[0] > $set[1] ? 0 : 1]++;
        }
        if (max($wins) !== $needed) $fail("This match needs {$needed} set wins to finish.");
    }
}
