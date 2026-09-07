<?php

namespace App\Domain\Draws\Services;

use InvalidArgumentException;

final class TennisScoreFormat
{
    public const ONE_SET_TO_3 = 'one_set_to_3';
    public const SHORT_SET_4 = 'short_set_4';
    public const ONE_SET_TO_5 = 'one_set_to_5';
    public const ONE_SET_TO_6 = 'one_set_to_6';
    public const BEST_OF_3_TO_3 = 'best_of_3_to_3';
    public const BEST_OF_3_TO_4 = 'best_of_3_to_4';
    public const BEST_OF_3_TO_5 = 'best_of_3_to_5';
    public const BEST_OF_3_TO_6 = 'best_of_3_to_6';
    public const BEST_OF_5_TO_3 = 'best_of_5_to_3';
    public const BEST_OF_5_TO_4 = 'best_of_5_to_4';
    public const BEST_OF_5_TO_5 = 'best_of_5_to_5';
    public const BEST_OF_5_TO_6 = 'best_of_5_to_6';
    public const ONE_FULL_SET = 'one_full_set';
    public const PRO_SET_8 = 'pro_set_8';
    public const MATCH_TIEBREAK_10 = 'match_tiebreak_10';
    public const BEST_OF_3_FULL = 'best_of_3_full';
    public const BEST_OF_3_MATCH_TIEBREAK = 'best_of_3_match_tiebreak';
    public const BEST_OF_5_FULL = 'best_of_5_full';
    public const CUSTOM_1 = 'custom_1';
    public const CUSTOM_3 = 'custom_3';
    public const CUSTOM_5 = 'custom_5';

    /** @return array<string, array{label:string,description:string,max_sets:int,wins_needed:int,set_types:array<int,string>,rules:string}> */
    public static function catalog(): array
    {
        return [
            self::ONE_SET_TO_3 => [
                'label' => 'One set · first to 3 games',
                'description' => 'The set ends when a player reaches 3 games.',
                'max_sets' => 1, 'wins_needed' => 1, 'set_types' => ['target3'],
                'rules' => 'One short set. The set ends when a player reaches 3 games.',
            ],
            self::SHORT_SET_4 => [
                'label' => 'One set · first to 4 games',
                'description' => 'The set ends when a player reaches 4 games.',
                'max_sets' => 1,
                'wins_needed' => 1,
                'set_types' => ['target4'],
                'rules' => 'One short set. The set ends when a player reaches 4 games.',
            ],
            self::ONE_SET_TO_5 => [
                'label' => 'One set · first to 5 games',
                'description' => 'The set ends when a player reaches 5 games.',
                'max_sets' => 1, 'wins_needed' => 1, 'set_types' => ['target5'],
                'rules' => 'One short set. The set ends when a player reaches 5 games.',
            ],
            self::ONE_SET_TO_6 => [
                'label' => 'One set · first to 6 games',
                'description' => 'The set ends when a player reaches 6 games; this is distinct from a full set.',
                'max_sets' => 1, 'wins_needed' => 1, 'set_types' => ['target6'],
                'rules' => 'One short set. The set ends when a player reaches 6 games.',
            ],
            self::ONE_FULL_SET => [
                'label' => 'One full set',
                'description' => 'First to 6 games; tiebreak at 6-all.',
                'max_sets' => 1,
                'wins_needed' => 1,
                'set_types' => ['full'],
                'rules' => 'One full set. First to 6 games, with a tiebreak at 6-all.',
            ],
            self::PRO_SET_8 => [
                'label' => '8-game pro set',
                'description' => 'First to 8 games; tiebreak at 8-all.',
                'max_sets' => 1,
                'wins_needed' => 1,
                'set_types' => ['pro8'],
                'rules' => 'One 8-game pro set. First to 8 games, with a tiebreak at 8-all.',
            ],
            self::MATCH_TIEBREAK_10 => [
                'label' => '10-point match tiebreak',
                'description' => 'First to 10 points, win by 2.',
                'max_sets' => 1,
                'wins_needed' => 1,
                'set_types' => ['match_tiebreak'],
                'rules' => 'One 10-point match tiebreak, won by 2 points.',
            ],
            self::BEST_OF_3_FULL => [
                'label' => 'Best of 3 full sets',
                'description' => 'Two full sets must be won; tiebreak at 6-all in each set.',
                'max_sets' => 3,
                'wins_needed' => 2,
                'set_types' => ['full', 'full', 'full'],
                'rules' => 'Best of 3 full sets. A player must win 2 sets; play a tiebreak at 6-all in each set.',
            ],
            self::BEST_OF_3_TO_3 => [
                'label' => 'Best of 3 sets · first to 3 games',
                'description' => 'First to 2 sets; every set ends at 3 games.',
                'max_sets' => 3, 'wins_needed' => 2, 'set_types' => ['target3', 'target3', 'target3'],
                'rules' => 'Best of 3 short sets. A player must win 2 sets; every set ends when a player reaches 3 games.',
            ],
            self::BEST_OF_3_TO_4 => [
                'label' => 'Best of 3 sets · first to 4 games',
                'description' => 'First to 2 sets; every set ends at 4 games.',
                'max_sets' => 3, 'wins_needed' => 2, 'set_types' => ['target4', 'target4', 'target4'],
                'rules' => 'Best of 3 short sets. A player must win 2 sets; every set ends when a player reaches 4 games.',
            ],
            self::BEST_OF_3_TO_5 => [
                'label' => 'Best of 3 sets · first to 5 games',
                'description' => 'First to 2 sets; every set ends at 5 games.',
                'max_sets' => 3, 'wins_needed' => 2, 'set_types' => ['target5', 'target5', 'target5'],
                'rules' => 'Best of 3 short sets. A player must win 2 sets; every set ends when a player reaches 5 games.',
            ],
            self::BEST_OF_3_TO_6 => [
                'label' => 'Best of 3 sets · first to 6 games',
                'description' => 'First to 2 sets; every set ends at 6 games.',
                'max_sets' => 3, 'wins_needed' => 2, 'set_types' => ['target6', 'target6', 'target6'],
                'rules' => 'Best of 3 short sets. A player must win 2 sets; every set ends when a player reaches 6 games.',
            ],
            self::BEST_OF_3_MATCH_TIEBREAK => [
                'label' => 'Best of 3 · deciding match tiebreak',
                'description' => 'Two full sets, then a 10-point match tiebreak at one set all.',
                'max_sets' => 3,
                'wins_needed' => 2,
                'set_types' => ['full', 'full', 'match_tiebreak'],
                'rules' => 'Best of 3: two full sets, followed at one set all by a 10-point match tiebreak won by 2.',
            ],
            self::BEST_OF_5_FULL => [
                'label' => 'Best of 5 full sets',
                'description' => 'Three full sets must be won; tiebreak at 6-all in each set.',
                'max_sets' => 5,
                'wins_needed' => 3,
                'set_types' => ['full', 'full', 'full', 'full', 'full'],
                'rules' => 'Best of 5 full sets. A player must win 3 sets; play a tiebreak at 6-all in each set.',
            ],
            self::BEST_OF_5_TO_3 => [
                'label' => 'Best of 5 sets · first to 3 games',
                'description' => 'First to 3 sets; every set ends at 3 games.',
                'max_sets' => 5, 'wins_needed' => 3, 'set_types' => array_fill(0, 5, 'target3'),
                'rules' => 'Best of 5 short sets. A player must win 3 sets; every set ends when a player reaches 3 games.',
            ],
            self::BEST_OF_5_TO_4 => [
                'label' => 'Best of 5 sets · first to 4 games',
                'description' => 'First to 3 sets; every set ends at 4 games.',
                'max_sets' => 5, 'wins_needed' => 3, 'set_types' => array_fill(0, 5, 'target4'),
                'rules' => 'Best of 5 short sets. A player must win 3 sets; every set ends when a player reaches 4 games.',
            ],
            self::BEST_OF_5_TO_5 => [
                'label' => 'Best of 5 sets · first to 5 games',
                'description' => 'First to 3 sets; every set ends at 5 games.',
                'max_sets' => 5, 'wins_needed' => 3, 'set_types' => array_fill(0, 5, 'target5'),
                'rules' => 'Best of 5 short sets. A player must win 3 sets; every set ends when a player reaches 5 games.',
            ],
            self::BEST_OF_5_TO_6 => [
                'label' => 'Best of 5 sets · first to 6 games',
                'description' => 'First to 3 sets; every set ends at 6 games.',
                'max_sets' => 5, 'wins_needed' => 3, 'set_types' => array_fill(0, 5, 'target6'),
                'rules' => 'Best of 5 short sets. A player must win 3 sets; every set ends when a player reaches 6 games.',
            ],
            self::CUSTOM_1 => [
                'label' => 'Custom scoring · 1 set',
                'description' => 'Any non-tied whole-number score; one set decides the match.',
                'max_sets' => 1,
                'wins_needed' => 1,
                'set_types' => ['custom'],
                'rules' => 'Custom one-set scoring. Record the agreed completed, non-tied score.',
            ],
            self::CUSTOM_3 => [
                'label' => 'Custom scoring · best of 3',
                'description' => 'Any non-tied whole-number set scores; first to 2 sets.',
                'max_sets' => 3,
                'wins_needed' => 2,
                'set_types' => ['custom', 'custom', 'custom'],
                'rules' => 'Custom best-of-3 scoring. A player must win 2 completed sets.',
            ],
            self::CUSTOM_5 => [
                'label' => 'Custom scoring · best of 5',
                'description' => 'Any non-tied whole-number set scores; first to 3 sets.',
                'max_sets' => 5,
                'wins_needed' => 3,
                'set_types' => ['custom', 'custom', 'custom', 'custom', 'custom'],
                'rules' => 'Custom best-of-5 scoring. A player must win 3 completed sets.',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    /** @return array<string, array<string, array{label:string,description:string,max_sets:int,wins_needed:int,set_types:array<int,string>,rules:string}>> */
    public static function groupedCatalog(): array
    {
        $groups = [
            'One-set formats' => [],
            'Best of 3 formats' => [],
            'Best of 5 formats' => [],
            'Custom formats' => [],
        ];

        foreach (self::catalog() as $key => $format) {
            $group = str_starts_with($key, 'custom_')
                ? 'Custom formats'
                : match ($format['max_sets']) {
                    1 => 'One-set formats',
                    3 => 'Best of 3 formats',
                    5 => 'Best of 5 formats',
                };
            $groups[$group][$key] = $format;
        }

        return $groups;
    }

    /** @return array{label:string,description:string,max_sets:int,wins_needed:int,set_types:array<int,string>,rules:string} */
    public static function get(string $key): array
    {
        return self::catalog()[$key] ?? throw new InvalidArgumentException("Unknown tennis score format [{$key}].");
    }

    public static function maxSets(string $key): int
    {
        return self::get($key)['max_sets'];
    }

    public static function rules(string $key): string
    {
        return self::get($key)['rules'];
    }
}
