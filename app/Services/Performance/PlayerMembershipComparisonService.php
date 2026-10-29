<?php

namespace App\Services\Performance;

class PlayerMembershipComparisonService
{
    public function assess(array $first, array $second, array $history): array
    {
        $reason = match (true) {
            $first['id'] === $second['id'] => 'Choose two different players.',
            $first['cohort'] !== $second['cohort'] => 'Players are in different recorded age groups.',
            $first['component'] !== $second['component'] => 'The players belong to different comparison groups.',
            in_array($first['status'], ['Inactive', 'Unrated'], true) || in_array($second['status'], ['Inactive', 'Unrated'], true) => 'Inactive or unrated evidence cannot support a current numeric comparison.',
            $first['score'] === null || $second['score'] === null => 'A displayed rating is unavailable.',
            !($history['available'] ?? false) => 'Match evidence does not currently match the saved calculation.',
            default => null,
        };
        $matches = $history['players'][$first['id']]['matches'] ?? [];
        $headToHead = array_values(array_filter($matches, fn ($m) => $m['opponent_id'] === $second['id']));
        return ['comparable' => $reason === null, 'reason' => $reason, 'gap' => $reason === null ? round($first['score'] - $second['score'], 1) : null,
            'provisional' => $first['status'] === 'Provisional' || $second['status'] === 'Provisional',
            'head_to_head' => $reason === null ? ['played' => count($headToHead), 'first_wins' => count(array_filter($headToHead, fn ($m) => $m['won'])),
                'second_wins' => count(array_filter($headToHead, fn ($m) => !$m['won']))] : null];
    }
}
