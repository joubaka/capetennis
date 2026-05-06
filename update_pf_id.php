<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = \Illuminate\Support\Facades\DB::connection();

// Show current state
$tx = $db->selectOne("SELECT id, pf_payment_id, amount_gross, event_id FROM transactions_pf WHERE pf_payment_id = '294643427'");
echo "Current transaction:\n";
print_r($tx);

// Update
$updated = $db->update("UPDATE transactions_pf SET pf_payment_id = '1809315' WHERE pf_payment_id = '294643427'");
echo "\nRows updated: $updated\n";

// Confirm
$tx2 = $db->selectOne("SELECT id, pf_payment_id, amount_gross, event_id FROM transactions_pf WHERE pf_payment_id = '1809315'");
echo "Updated transaction:\n";
print_r($tx2);
