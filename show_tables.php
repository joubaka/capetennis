<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = DB::select('SHOW TABLES');
foreach ($tables as $t) {
    $v = array_values((array)$t)[0];
    if (str_contains($v, 'player') || str_contains($v, 'registr')) {
        echo $v . PHP_EOL;
    }
}
