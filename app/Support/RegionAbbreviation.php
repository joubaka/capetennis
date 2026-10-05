<?php

namespace App\Support;

final class RegionAbbreviation
{
    public static function label(?object $region): string
    {
        $configured = trim((string) ($region?->short_name ?? ''));
        if ($configured !== '') {
            return $configured;
        }
        $name = trim((string) ($region?->region_name ?? ''));
        $words = preg_split('/[\s\p{Pd}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > 1) {
            return mb_strtoupper(implode('', array_map(fn ($word) => mb_substr($word, 0, 1), $words)));
        }

        return mb_convert_case(mb_substr($name, 0, 4), MB_CASE_TITLE);
    }
}
