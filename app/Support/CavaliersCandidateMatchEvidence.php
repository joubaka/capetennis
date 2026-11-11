<?php

namespace App\Support;

use App\Domain\TeamDraw\RubberType;

final class CavaliersCandidateMatchEvidence
{
    public static function project(mixed $matches): array
    {
        $evidence = [];
        foreach (is_array($matches) ? $matches : [] as $match) {
            if (! is_array($match) || ! is_string($match['opponent'] ?? null)
                || trim($match['opponent']) === '' || ! is_bool($match['won'] ?? null)) {
                continue;
            }

            $score = $match['score'] ?? null;
            $evidence[] = [
                'opponent' => trim($match['opponent']),
                'outcome' => $match['won'] ? 'Won' : 'Lost',
                'playing_number' => is_int($match['roster_rank'] ?? null) && $match['roster_rank'] > 0 ? $match['roster_rank'] : null,
                'score' => is_string($score) && preg_match('/^\d+[-–]\d+(?:, \d+[-–]\d+)*$/uD', $score) ? $score : null,
                'format' => match ($match['format'] ?? null) {
                    RubberType::SINGLES => 'Singles',
                    RubberType::REVERSE_SINGLES => 'Reverse singles',
                    default => null,
                },
            ];
        }

        return $evidence;
    }
}
