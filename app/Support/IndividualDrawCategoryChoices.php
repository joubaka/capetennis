<?php

namespace App\Support;

final class IndividualDrawCategoryChoices
{
    /** Compact standard choices, each linked to exactly one event-category. */
    public static function make(iterable $categories): array
    {
        $categories = collect($categories);
        $choices = [];
        foreach (TeamDrawCategoryGroups::make($categories) as $group) {
            $standards = $categories->filter(fn ($category) => in_array((int) $category->pivot_id, $group->pivot_ids, true)
                && ! preg_match('/\bdivision\b/iu', $category->name))
                ->sort(function ($a, $b) use ($group) {
                    $aCanonical = mb_strtolower(trim($a->name)) === mb_strtolower($group->name);
                    $bCanonical = mb_strtolower(trim($b->name)) === mb_strtolower($group->name);

                    return ($bCanonical <=> $aCanonical) ?: ((int) $a->pivot_id <=> (int) $b->pivot_id);
                });
            if ($standards->isEmpty()) {
                continue;
            }
            $source = $standards->first();
            $choices[] = (object) [
                'name' => $group->name,
                'pivot_id' => (int) $source->pivot_id,
                'source_name' => $source->name,
                'duplicate_name' => $categories->filter(fn ($category) => mb_strtolower(trim($category->name)) === mb_strtolower(trim($source->name)))->count() > 1,
            ];
        }

        return $choices;
    }
}
