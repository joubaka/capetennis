<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tx = DB::table('transactions_pf')->where('pf_payment_id', '299910931')->first();
echo "=== transactions_pf for PF 299910931 ===\n";
print_r($tx);
