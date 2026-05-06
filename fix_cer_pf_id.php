<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$db = \Illuminate\Support\Facades\DB::connection();

$cer = $db->selectOne("SELECT id, pf_transaction_id, status, refund_status FROM category_event_registrations WHERE id = 17853");
echo "CER #17853:\n";
print_r($cer);

$tx = $db->selectOne("SELECT id, pf_payment_id, amount_gross FROM transactions_pf WHERE id = 1218");
echo "\nTransaction #1218:\n";
print_r($tx);

// Fix: update CER pf_transaction_id to new value
$updated = $db->update("UPDATE category_event_registrations SET pf_transaction_id = '1809315' WHERE id = 17853");
echo "\nCER pf_transaction_id updated: $updated row(s)\n";

$cer2 = $db->selectOne("SELECT id, pf_transaction_id FROM category_event_registrations WHERE id = 17853");
echo "CER after update:\n";
print_r($cer2);
