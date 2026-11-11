<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;

/** Independent singles graphs with weaker published finishing fields; unstated conditions belong to normal tennis. */
class BallRatingModel
{
    public const VERSION = 5;
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
        return $label === null ? (self::isOrangeDivision($eventName) ? 'orange' : 'standard') : $label['stage'];
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

    /** Exact U9 B and U10 B divisions use Orange across all events. */
    public static function isOrangeDivision(?string $category): bool
    {
        $category = mb_strtolower((string) $category);
        // Preserve compact historical labels such as WilsonU9B.
        $category = str_replace('wilson', ' ', $category);
        // Combined divisions and age ranges do not establish one exact playing format.
        if (preg_match('/\b[ac]\b/u', $category) === 1
            || preg_match_all('/\b(?:u\s*\/?\s*|under[\s-]*)\d+/u', $category, $ages, PREG_OFFSET_CAPTURE) !== 1) return false;
        $afterAge = substr($category, $ages[0][0][1] + strlen($ages[0][0][0]));
        if (preg_match('/(?:\/|&|\+|\band\b|[-–])\s*(?:[1-9]|1[0-8])(?!\d)/u', $afterAge) === 1) return false;

        return preg_match('/\b(?:u\s*\/?\s*|under[\s-]*)(?:9|10)\s*[-–]?\s*b\b/u', $category) === 1
            || preg_match('/\b(?:u\s*\/?\s*|under[\s-]*)(?:9|10)\b[^\r\n]*\bb\b/u', $category) === 1;
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
        if (self::isOrangeDivision($match['category'] ?? null)) return 'orange';
        $specific = mb_strtolower((string) ($match['category'] ?? ''));
        if (preg_match('/\b(?:u\s*\/?\s*|under[\s-]*)\d+|\b[ac]\b/u', $specific) === 1) {
            // An event-title division cannot replace a different or ambiguous category division.
            $event = self::labelled($eventName);
            return $event === null ? 'standard' : $event['stage'];
        }
        return self::bucket(null, $eventName);
    }

    public static function forCourt(string $ballType, ?string $category, ?string $eventName): ?string
    {
        if ($ballType === 'standard') {
            // A generic shared-court label does not replace the division's playing format.
            return self::forMatch(['category' => $category, 'event_name' => $eventName]);
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
