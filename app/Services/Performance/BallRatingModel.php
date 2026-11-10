<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Independent singles graphs with weaker published finishing fields; unstated conditions belong to normal tennis. */
class BallRatingModel
{
    public const VERSION = 4;
    public const LABELS = ['red' => 'Red ball', 'orange' => 'Orange ball', 'standard' => 'Green/Yellow ball'];
    public const COHORTS = ['red' => 'red ball · singles', 'orange' => 'orange ball · singles', 'standard' => 'green/yellow ball · singles'];

    /** Current rated level follows saved evidence, never age or CTR magnitude. */
    public static function currentStage(array $ratings): ?string
    {
        foreach (['standard', 'orange', 'red'] as $stage) {
            $score = $ratings[$stage]['score'] ?? null;
            if (is_numeric($score) && is_finite((float) $score) && $score >= 0 && $score <= 100) return $stage;
        }
        return null;
    }

    public static function bucket(?string $ball, ?string $eventName = null): ?string
    {
        $ball = mb_strtolower(trim((string) $ball));
        if (in_array($ball, ['red', 'orange'], true)) return $ball;
        if (in_array($ball, ['green', 'yellow', 'standard'], true)) return 'standard';
        $label = self::labelled($eventName);
        return $label === null ? (self::isWilsonOrange($eventName) ? 'orange' : 'standard') : $label['stage'];
    }

    /** Labels containing Red/Orange identify that level; both together need more specific context. */
    public static function labelled(?string $name, bool $category = false): ?array
    {
        $label = mb_strtolower(trim((string) $name));
        $red = str_contains($label, 'red');
        $orange = str_contains($label, 'orange');
        if ($red || $orange) return ['stage' => $red && $orange ? null : ($red ? 'red' : 'orange')];
        foreach (['green', 'yellow', 'standard', 'normal'] as $normal) {
            if (str_contains($label, $normal)) return ['stage' => 'standard'];
        }
        return null;
    }

    public static function isWilsonOrange(?string $specific, ?string $eventName = null): bool
    {
        if (!str_contains(mb_strtolower((string) $specific.' '.(string) $eventName), 'wilson')) return false;
        $specific = str_replace('wilson', ' ', mb_strtolower((string) $specific));
        return preg_match('/\bu\s*\/?\s*9\s*[- ]?\s*b\b/u', $specific) === 1
            || preg_match('/\bu\s*\/?\s*9\b[^\r\n]*\bb(?:\s*[-–]?\s*division)?\b/u', $specific) === 1;
    }

    /** Reviewed event/category defaults apply only when explicit conditions are absent. */
    public static function isNamedOrange(?string $category, ?string $eventName = null): bool
    {
        if (self::isWilsonOrange($category, $eventName)) return true;
        $event = mb_strtolower((string) $eventName);
        $category = mb_strtolower((string) $category);
        // Combined A/B or multi-age fields do not establish this specific division.
        if (preg_match('/\ba\b/u', $category) === 1 || preg_match_all('/\bu\s*\/?\s*\d+/u', $category) > 1) return false;
        if (preg_match('/\bceres\b/u', $event) === 1 && preg_match('/\btournament\b/u', $event) === 1) {
            return preg_match('/\bu\s*\/?\s*9\s*[- ]?\s*b\b/u', $category) === 1
                || preg_match('/\bu\s*\/?\s*9\b[^\r\n]*\bb(?:\s*[-–]?\s*division)?\b/u', $category) === 1;
        }
        if (preg_match('/\b(?:overberg|west[\s-]*coast)\b/u', $event) !== 1
            || preg_match('/\btrials?\b/u', $event) !== 1) return false;
        return self::isU10BDivision($category);
    }

    private static function isU10BDivision(string $category): bool
    {
        if (preg_match('/\ba\b/u', $category) === 1 || preg_match_all('/\bu\s*\/?\s*\d+/u', $category) > 1) return false;

        return preg_match('/\bu\s*\/?\s*10\s*[- ]?\s*b\b/u', $category) === 1
            || preg_match('/\bu\s*\/?\s*10\b[^\r\n]*\bb(?:\s*[-–]?\s*division)?\b/u', $category) === 1;
    }

    public static function stage(string $cohort): ?string
    {
        $stage = array_search($cohort, self::COHORTS, true);
        return $stage === false ? null : $stage;
    }

    public static function forMatch(array $match): ?string
    {
        if (array_key_exists('rating_ball_type', $match)) return in_array($match['rating_ball_type'], ['red', 'orange', 'standard'], true) ? $match['rating_ball_type'] : null;
        $eventName = $match['event_name'] ?? $match['event'] ?? null;
        if (in_array($match['ball_type'] ?? null, ['red', 'orange', 'green', 'yellow', 'standard'], true)) {
            return self::forCourt($match['ball_type'], $match['category'] ?? null, $eventName);
        }
        $category = self::labelled($match['category'] ?? null, true);
        if ($category !== null) return $category['stage'];
        if (self::isNamedOrange($match['category'] ?? null, $eventName)) return 'orange';
        return self::bucket(null, $eventName);
    }

    public static function forCourt(string $ballType, ?string $category, ?string $eventName): ?string
    {
        $event = mb_strtolower(trim((string) $eventName));
        if ($ballType === 'standard' && preg_match('/^overberg\b[^\r\n]*\btrials?\b/u', $event) === 1
            && self::isU10BDivision(mb_strtolower((string) $category))) {
            // Shared courts can carry the generic standard label even when this division played Orange.
            $explicit = self::labelled($category, true);

            return $explicit === null ? 'orange' : $explicit['stage'];
        }

        return self::bucket($ballType);
    }

    public function fit(array $matches, CarbonImmutable $asOf, array $fields = []): array
    {
        $buckets = array_fill_keys(array_keys(self::LABELS), []);
        foreach ($matches as $match) {
            $stage = self::forMatch($match);
            if ($stage === null) continue;
            $match['ball_type'] = $stage;
            $buckets[$stage][] = $match;
        }
        $ratings = [];
        foreach ($buckets as $stage => $rows) {
            $ownFields = array_values(array_filter($fields, fn ($field) => ($field['rating_ball_type'] ?? null) === $stage));
            $ratings[$stage] = (new AllAgeRatingModel)->fit($rows, $asOf, ordinalFields: $ownFields);
            if (array_filter($ratings[$stage]['components'], fn ($component) => empty($component['converged']))) {
                // A failed independent fit is unrated; it must not block healthy ball levels or invent a score.
                $ratings[$stage] = (new AllAgeRatingModel)->fit([], $asOf);
                $ratings[$stage]['unrated_reason'] = 'This ball-level model did not converge. Its rating is withheld until a valid fit is available.';
                $ratings[$stage]['excluded']['Ball-level model did not converge'] = count($rows);
            }
            $ratings[$stage]['policy']['ball_bucket'] = $stage;
            $ratings[$stage]['policy']['classification_version'] = self::VERSION;
        }
        return $ratings;
    }
}
