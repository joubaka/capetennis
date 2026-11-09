<?php

namespace App\Services;

use App\Models\Event;

/** Versioned, reusable rules; each event keeps its own exclusions and schedule context. */
final class TeamSelectionCriteria
{
    public const PLATTELAND = 'platteland_v1';
    public const INTER_DISTRICTS = 'inter_districts_v1';
    public const LABEL = 'Platteland criteria for selection of teams';

    public function isPlatteland(Event $event): bool
    {
        return ($event->team_result_settings['criteria_preset'] ?? 'legacy') === self::PLATTELAND;
    }

    public function isInterDistricts(Event $event): bool
    {
        // This event shares the generic team type. Match its identity as well as its name.
        return (int) $event->getKey() === 242
            && $event->name === 'Inter-Districts Tournament Primary Schools 2026'
            && $event->isTeam();
    }

    public function context(Event $event, array $excluded = []): array
    {
        $active = $this->isPlatteland($event);
        $excluded = $active ? ($event->team_result_settings['excluded_result_region_ids'] ?? []) : $excluded;
        $excluded = array_values(array_unique(array_map('intval', $excluded))); sort($excluded);
        $preset = $active ? self::PLATTELAND : ($this->isInterDistricts($event) ? self::INTER_DISTRICTS : 'legacy');
        return ['preset' => $preset, 'version' => 1,
            'label' => match ($preset) {
                self::PLATTELAND => self::LABEL,
                self::INTER_DISTRICTS => 'Inter-Districts selection criteria',
                default => 'Existing selection criteria',
            },
            'expected_matches' => $active ? ($event->team_result_settings['criteria_expected_matches'] ?? null) : null,
            'weak_record_max_wins' => $active ? ($event->team_result_settings['criteria_weak_max_wins'] ?? null) : null,
            'excluded_result_region_ids' => $excluded];
    }

    public function fingerprint(Event $event): string
    {
        return hash('sha256', json_encode($this->context($event), JSON_THROW_ON_ERROR));
    }
}
