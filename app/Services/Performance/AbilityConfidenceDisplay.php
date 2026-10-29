<?php

namespace App\Services\Performance;

/** Presentation only; saved indices and weighting policy are unchanged. */
class AbilityConfidenceDisplay
{
    public static function label(int $index, ?array $context = null): string
    {
        if (($context['rating_scope'] ?? '') === 'all_age' && !empty($context['historical_connection'])) { return 'Low'; }
        if ($context !== null && array_key_exists('played', $context) && (int) $context['played'] === 0) { return 'Low'; }
        $label = $index < 40 ? 'Low' : ($index < 70 ? 'Medium' : 'High');
        if ($label === 'High' && $context !== null && (
            !isset($context['component_inferred'], $context['bridge_count'])
            || $context['component_inferred'] > 0 || $context['bridge_count'] > 0
            || (($context['rating_scope'] ?? '') === 'all_age' ? !($context['comparable'] ?? false) || ($context['standard_assumed_matches'] ?? 0) > 0 || ($context['component_standard_assumed_matches'] ?? 0) > 0 : ($context['baseline_status'] ?? '') !== 'Direct main-trial baseline')
            || ($context['played'] ?? 0) < 5)) { return 'Medium'; }
        return $label;
    }

    public static function normalize(array $estimate): array
    {
        $label = self::label((int) ($estimate['confidence_index'] ?? 0), $estimate);
        $reasons = [];
        if (!isset($estimate['component_inferred'], $estimate['bridge_count'])) { $reasons[] = 'comparison structure is not fully recorded'; }
        elseif ($estimate['component_inferred'] > 0 || $estimate['bridge_count'] > 0) { $reasons[] = 'the comparison group includes inferred or narrow links'; }
        if (($estimate['rating_scope'] ?? '') === 'all_age') {
            if (!($estimate['comparable'] ?? false)) { $reasons[] = 'no supported connection to the recent shared reference group'; }
            if (!empty($estimate['historical_connection'])) {
                $reasons[] = 'the connection to the recent reference depends on a played-result link '.($estimate['connection_age_days'] ?? 'unknown').' days old ('.($estimate['connection_reference_date'] ?? 'date unknown').'); newer results are needed to confirm that connection';
            }
            if (($estimate['standard_assumed_matches'] ?? 0) > 0 || ($estimate['component_standard_assumed_matches'] ?? 0) > 0) { $reasons[] = 'some historical matches assume standard playing conditions'; }
        } elseif (($estimate['baseline_status'] ?? '') !== 'Direct main-trial baseline') { $reasons[] = 'the main-trial reference is indirect or unavailable'; }
        if (($estimate['played'] ?? 0) < 5) { $reasons[] = 'own played-match evidence is sparse'; }
        if (($estimate['rating_scope'] ?? '') === 'all_age' && !empty($estimate['historical_connection'])) {
            $estimate['confidence_index'] = min(AllAgeRatingModel::HISTORICAL_CONFIDENCE_CAP, (int) ($estimate['confidence_index'] ?? 0));
        }
        return array_replace($estimate, ['confidence_band' => $label, 'confidence_label' => $label,
            'confidence_explanation' => 'Strength and freshness of recorded evidence, not calibrated rating accuracy.'.($reasons ? ' Caution: '.implode('; ', $reasons).'.' : '')]);
    }
}
