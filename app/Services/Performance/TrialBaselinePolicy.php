<?php

namespace App\Services\Performance;

use App\Models\Event;
use Carbon\CarbonImmutable;

/** Explicit reviewed Cape Tennis main-trials classifier; never infer importance from event names. */
class TrialBaselinePolicy
{
    public const MAIN_TRIAL_EVENT_TYPE = 5;
    public const ANCHOR_WEIGHT = 2.0;
    public const TARGET_LIMIT = 1.0;

    public function eligible(Event $event): bool
    {
        return (int) $event->eventType === self::MAIN_TRIAL_EVENT_TYPE
            && mb_strtolower(trim(preg_replace('/\s+/u', ' ', $event->eventTypeModel?->name ?? ''))) === 'cavaliers trials';
    }

    /** Incident supporting evidence per player/event is at most one before decay. */
    public function budget(array $edges): array
    {
        $degrees = [];
        foreach ($edges as $edge) {
            $degrees[$edge['winner']] = ($degrees[$edge['winner']] ?? 0) + 1;
            $degrees[$edge['loser']] = ($degrees[$edge['loser']] ?? 0) + 1;
        }
        return array_map(function ($edge) use ($degrees) {
            $edge['event_weight'] = 1 / max($degrees[$edge['winner']], $degrees[$edge['loser']]);
            $edge['weight'] *= $edge['event_weight'];
            return $edge;
        }, $edges);
    }

    public function baseline(Event $event, string $cohort, array $edges, CarbonImmutable $asOf): array
    {
        $raw = array_map(fn ($edge) => array_replace($edge, ['weight' => $edge['event_weight']]), $edges);
        $fit = app(SharedAbilityModel::class)->fit($raw, $cohort);
        $components = array_values($fit['components']);
        usort($components, fn ($a, $b) => count($b['members']) <=> count($a['members']) ?: $a['members'][0] <=> $b['members'][0]);
        $selected = $components[0] ?? null;
        if (!$selected || !$selected['converged']) { return ['anchors' => [], 'metadata' => null]; }
        $weight = self::ANCHOR_WEIGHT * pow(0.5, max(0, CarbonImmutable::parse($event->start_date)->diffInDays($asOf, false)) / 180);
        $anchors = [];
        foreach ($selected['members'] as $id) {
            $anchors[$id] = ['target' => max(-self::TARGET_LIMIT, min(self::TARGET_LIMIT, $fit['ratings'][$id]['strength'])), 'weight' => max(PHP_FLOAT_MIN, $weight)];
        }
        return ['anchors' => $anchors, 'metadata' => ['event_id' => $event->id, 'event_name' => $event->name,
            'date' => CarbonImmutable::parse($event->start_date)->toDateString(), 'cohort' => $cohort,
            'trial_component' => $fit['ratings'][$selected['members'][0]]['component'], 'covered_players' => count($anchors),
            'trial_players' => count($fit['ratings']), 'weight' => round($weight, 4), 'source' => 'Model-derived trial-match baseline; not a published finishing rank']];
    }
}
