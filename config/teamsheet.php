<?php

return [
    // Configure only after the restricted TeamSheet export is deployed.
    'url' => env('TEAMSHEET_RESULTS_URL'),
    'token' => env('TEAMSHEET_RESULTS_TOKEN'),
    'allow_local_http' => env('TEAMSHEET_ALLOW_LOCAL_HTTP', false),
];
