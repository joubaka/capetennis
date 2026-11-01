<?php

return [
    // Initial Cape Tennis benchmarks, not official TSA qualification rules.
    // CTR is the opponent-adjusted performance measure; no separate win-rate gate applies.
    'targets' => [
        'red' => ['ctr' => 50, 'events' => 3],
        'orange' => ['ctr' => 60, 'events' => 3],
    ],
    'wilson_a_above' => 50,
    'tsa_above' => 85,
];
