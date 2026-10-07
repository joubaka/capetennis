<?php

namespace App\Services\Performance;

use App\Models\{Category, CategoryEvent, Draw};

/** Request-scoped, private name-display lookup. No profile-history queries. */
class PlayerRatingBadgeService
{
    private ?array $snapshot = null;
    private array $contexts = [];

    public static function visible(): bool
    {
        return (bool) auth()->user()?->hasRole('super-user') && !request()->routeIs(
            'event.draw.get.pdf', 'headoffice.printDrawsPdf', 'headoffice.printDrawsData', 'headoffice.drawPack'
        );
    }

    public function forPlayer(int $playerId, Draw|CategoryEvent|Category|null $context = null): ?array
    {
        if (!self::visible() || $playerId < 1) { return null; }
        $cohort = $context ? $this->contextCohort($context) : null;
        if ($context && $cohort === null) { return null; }
        $this->snapshot ??= app(PlayerSharedAbilityService::class)->badgeSnapshot();
        $ratings = $this->snapshot[$playerId] ?? [];
        if ($context) { $ratings = array_filter($ratings, fn ($rating) => $rating['cohort'] === $cohort); }
        usort($ratings, fn ($a, $b) => strcmp($b['last_played'], $a['last_played']) ?: strcmp($a['cohort'], $b['cohort']));
        $rating = $ratings[0] ?? null;
        if (!$rating) { return null; }
        $reference = $rating['baseline_status'] ?? 'No connected main-trial baseline';
        if ($rating['baseline_source'] ?? null) { $reference .= ': '.$rating['baseline_source']['event_name'].' ('.$rating['baseline_source']['date'].'). Model-derived trial-match reference, not a published finish.'; }
        else { $reference .= '. Local comparison only; not trial-calibrated.'; }
        $confidenceLabel = AbilityConfidenceDisplay::label((int) ($rating['confidence_index'] ?? 0));
        $rating['confidence_band'] = $confidenceLabel;
        return $rating + ['confidence_label' => $confidenceLabel, 'label' => number_format($rating['score'], 1),
            'display_label' => number_format($rating['score'], 1).' | '.$confidenceLabel,
            'title' => 'Provisional singles ability: '.number_format($rating['score'], 1).'/100 · '.$rating['cohort'].' · comparison group '.$rating['component'].'. Evidence confidence: '.$confidenceLabel.' (strength and freshness of recorded evidence), as of '.($rating['confidence_as_of'] ?? 'unknown').'; not an accuracy percentage. Last own eligible activity: '.($rating['last_eligible_activity'] ?? 'unknown').'. '.$reference.' Only compare players in this cohort and group.'.(!empty($rating['snapshot_stale']) ? ' Saved update is stale; awaiting the nightly refresh.' : '')];
    }

    private function contextCohort(Draw|CategoryEvent|Category $context): ?string
    {
        $key = get_class($context).':'.$context->id;
        if (array_key_exists($key, $this->contexts)) { return $this->contexts[$key]; }
        $field = $context instanceof Category ? null : ($context instanceof Draw ? $context->categoryEvent : $context);
        if ($context instanceof Draw && $field && (int) $field->event_id !== (int) $context->event_id) { $field = null; }
        // Legacy draws may have no foreign key: use their canonical age/category label,
        // while identities always come from actual registration/player IDs.
        $label = $field?->category?->name ?? ($context instanceof Draw ? $context->drawName : ($context instanceof Category ? $context->name : null));
        if (!$label) { return $this->contexts[$key] = null; }
        $division = app(PlayerPerformancePilotService::class)->division($label);
        if ($division['reason'] && $division['reason'] !== 'No explicit A/B division in category name') { return $this->contexts[$key] = null; }
        $label = $division['cohort'];
        $event = $context instanceof Draw ? $context->event : $field?->event;
        if ($event?->frontend_type_view === 'masters') { $label = 'masters · '.$label; }
        return $this->contexts[$key] = app(PlayerSharedAbilityService::class)->cohort($label);
    }
}
