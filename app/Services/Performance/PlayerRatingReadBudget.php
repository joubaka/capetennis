<?php

namespace App\Services\Performance;

/** Private saved graphs need the same process-local budget as their canonical builders. */
class PlayerRatingReadBudget
{
    public const MIN_BYTES = 512 * 1024 * 1024;

    public function ensure(): bool
    {
        $limit = ini_get('memory_limit');
        if ($limit === false) return false;
        $bytes = ini_parse_quantity($limit);
        if ($bytes === -1 || $bytes >= self::MIN_BYTES) return true;
        if (! function_exists('ini_set') || @ini_set('memory_limit', (string) self::MIN_BYTES) === false) return false;
        $effective = ini_get('memory_limit');
        if ($effective === false) return false;
        $bytes = ini_parse_quantity($effective);
        return $bytes === -1 || $bytes >= self::MIN_BYTES;
    }
}
