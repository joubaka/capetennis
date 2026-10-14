<?php

namespace App\Services;

use App\Models\Event;

/** Versioned, reusable rules; each event keeps its own exclusions and schedule context. */
final class TeamSelectionCriteria
{
    public const PLATTELAND = 'platteland_v1';
    public const LABEL = 'Platteland criteria for selection of teams';

    public function isPlatteland(Event $event): bool
    {
        return ($event->team_result_settings['criteria_preset'] ?? 'legacy') === self::PLATTELAND;
    }

    public function context(Event $event, array $excluded = []): array
    {
        $active = $this->isPlatteland($event);
        $excluded = $active ? ($event->team_result_settings['excluded_result_region_ids'] ?? []) : $excluded;
        $excluded = array_values(array_unique(array_map('intval', $excluded))); sort($excluded);
        return ['preset' => $active ? self::PLATTELAND : 'legacy', 'version' => 1,
            'label' => $active ? self::LABEL : 'Existing selection criteria',
            'expected_matches' => $active ? ($event->team_result_settings['criteria_expected_matches'] ?? null) : null,
            'weak_record_max_wins' => $active ? ($event->team_result_settings['criteria_weak_max_wins'] ?? null) : null,
            'excluded_result_region_ids' => $excluded];
    }

    public function fingerprint(Event $event): string
    {
        return hash('sha256', json_encode($this->context($event), JSON_THROW_ON_ERROR));
    }
}
