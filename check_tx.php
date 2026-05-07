<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();
// Show columns of transactions table
$cols = DB::select("DESCRIBE transactions");
echo "=== transactions columns ===\n";
foreach($cols as $c) echo "  {$c->Field} | {$c->Type} | null:{$c->Null} | default:{$c->Default}\n";
// Show a sample real transaction for event 235
$tx = DB::select("SELECT * FROM transactions WHERE event_id=235 LIMIT 3");
echo "\n=== Sample transactions for event 235 ===\n";
foreach($tx as $t) { foreach((array)$t as $k=>$v) echo "  $k: $v\n"; echo "---\n"; }