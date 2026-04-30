<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check activity log for these players / registrations
$playerIds = [3417]; // Katryn Zaayman
// Also check for Benjamin's player id
$benjamin = DB::selectOne("SELECT id FROM players WHERE name LIKE '%Benjamin%' AND surname LIKE '%Merwe%' AND name LIKE '%Brynard%'");
if ($benjamin) $playerIds[] = $benjamin->id;

echo "=== Players to check ===\n";
foreach ($playerIds as $pid) {
    $p = DB::selectOne("SELECT id, name, surname FROM players WHERE id=?", [$pid]);
    echo "  ID={$p->id} {$p->name} {$p->surname}\n";
}

// Check activity_log for withdrawals
echo "\n=== Activity log for these players ===\n";
$logs = DB::select("
    SELECT id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at
    FROM activity_log
    WHERE (description LIKE '%withdraw%' OR log_name LIKE '%withdraw%')
    AND (properties LIKE '%Katryn%' OR properties LIKE '%Zaayman%' OR properties LIKE '%Benjamin%' OR properties LIKE '%Merwe%' OR properties LIKE '%Van der Merwe%')
    ORDER BY created_at DESC
    LIMIT 20
");
if (empty($logs)) {
    echo "  No activity log entries found.\n";
} else {
    foreach ($logs as $l) {
        echo "  [{$l->created_at}] {$l->log_name} | {$l->description}\n";
        echo "    subject: {$l->subject_type}#{$l->subject_id} | causer: {$l->causer_type}#{$l->causer_id}\n";
        echo "    props: {$l->properties}\n\n";
    }
}

// Check old withdrawals table
echo "\n=== Withdrawals table for registration_ids 19429,19382,19383,19384 ===\n";
$ws = DB::select("SELECT * FROM withdrawals WHERE registration_id IN (19429,19382,19383,19384)");
if (empty($ws)) {
    echo "  No entries.\n";
} else {
    foreach ($ws as $w) {
        foreach ((array)$w as $k => $v) echo "  $k: $v\n";
        echo "  ---\n";
    }
}

// Check if there were any CERs that were deleted - check for gaps in IDs around the order dates
echo "\n=== transactions_pf around the time of these payments ===\n";
$txs = DB::select("
    SELECT id, created_at, pf_payment_id, custom_str2, custom_str1, event_id, amount_gross
    FROM transactions_pf
    WHERE id BETWEEN 1210 AND 1230
    ORDER BY id
");
foreach ($txs as $t) {
    $hasCer = DB::selectOne("SELECT id FROM category_event_registrations WHERE pf_transaction_id = ?", [$t->pf_payment_id]);
    echo "  tx#{$t->id} [{$t->created_at}] pf={$t->pf_payment_id} | {$t->custom_str2} | {$t->custom_str1} | CER:" . ($hasCer ? "REG-{$hasCer->id}" : "MISSING") . "\n";
}
