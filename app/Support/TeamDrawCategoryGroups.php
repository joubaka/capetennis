<?php

namespace App\Support;

final class TeamDrawCategoryGroups
{
    /** Group display aliases without changing the event-category records they select. */
    public static function make(iterable $categories): array
    {
        $groups = [];
        foreach ($categories as $category) {
            $name = trim($category->name);
            $age = $name;
            $gender = null;
            if (preg_match('/^(u\s*\/\s*\d+)\s*(boys|girls)(?:\s*[-–]?\s*A\s+division)?$/iu', $name, $matches)) {
                $age = 'u/'.preg_replace('/\D/', '', $matches[1]);
                $gender = strtolower($matches[2]);
                $name = $age.' '.ucfirst($gender);
            }
            if ($gender === null && preg_match('/^(.*?)(?:\s*[-\x{2013}]?\s*)(boys|girls|mixed)$/iu', $name, $matches)) {
                $age = trim($matches[1]);
                $gender = strtolower($matches[2]);
            }
            if ($gender === null) {
                $parsed = app(\App\Services\TeamDrawSideResolver::class)->categoryKey($name);
                if ($parsed['gender'] !== null) {
                    $age = $parsed['group'];
                    $gender = $parsed['gender'];
                }
            }
            $key = mb_strtolower($name);
            if (!isset($groups[$key])) {
                $groups[$key] = (object) ['name' => $name, 'parsed_age' => $age,
                    'parsed_gender' => $gender, 'pivot_id' => $category->pivot_id, 'pivot_ids' => []];
            }
            $groups[$key]->pivot_ids[] = (int) $category->pivot_id;
        }
        $groups = array_values($groups);
        usort($groups, fn ($a, $b) => strnatcasecmp($a->name, $b->name));

        return $groups;
    }
}
