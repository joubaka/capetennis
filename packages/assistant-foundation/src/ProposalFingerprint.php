<?php

namespace SportStack\Assistant;

final class ProposalFingerprint
{
    /** Preserve list order, normalize associative key order. */
    public static function make(array $proposal): string
    {
        return hash('sha256', json_encode(self::normalize($proposal), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function normalize(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::normalize($value);
            }
        }

        return $values;
    }
}
