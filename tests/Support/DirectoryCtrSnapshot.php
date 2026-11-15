<?php

namespace Tests\Support;

/** Existing directory scenarios now exercise CTR, retaining combined graphs for explicit historical groups. */
trait DirectoryCtrSnapshot
{
    private function ctrSnapshot(array $snapshot): array
    {
        if (isset($snapshot['all_age'])) $snapshot['ball_ratings']['standard'] = $snapshot['all_age'];
        return $snapshot;
    }
}
