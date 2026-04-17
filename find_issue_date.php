<?php
/**
 * Find the date when missing entries started.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\RegistrationOrderItems;
use App\Models\CategoryEventRegistration;
use Illuminate\Support\Facades\DB;

$csvPath = 'C:\Users\pajou\Downloads\2026-04-17-Transaction_History_11307280.csv';
$handle = fopen($csvPath, 'r');
$headers = fgetcsv($handle);
$col = array_flip($headers);

$issues = [];
$okDates = [];

while (($row = fgetcsv($handle)) !== false) {
    if (($row[$col['Sign']] ?? '') !== 'Credit') continue;
    if (($row[$col['Type']] ?? '') !== 'Funds Received') continue;

    $orderId = (int) ($row[$col['Custom_int5']] ?? 0);
    if (!$orderId) continue;

    $date = $row[$col['Date']] ?? '';
    $order = DB::table('registration_orders')->where('id', $orderId)->first();
    if (!$order) continue; // skip orders not in DB

    $items = RegistrationOrderItems::where('order_id', $orderId)->get();
    $hasIssue = false;

    foreach ($items as $item) {
        if (!$item->registration_id || !$item->category_event_id) continue;

        $entry = CategoryEventRegistration::where('registration_id', $item->registration_id)
            ->where('category_event_id', $item->category_event_id)
            ->first();

        if (!$entry || $entry->payment_status_id != 1) {
            $hasIssue = true;
        }

        $playerOk = DB::table('player_registrations')
            ->where('registration_id', $item->registration_id)
            ->where('player_id', $item->player_id)
            ->exists();

        if (!$playerOk) $hasIssue = true;
    }

    $dateOnly = substr($date, 0, 10);
    if ($hasIssue) {
        $issues[] = $date;
    } else {
        $okDates[] = $date;
    }
}
fclose($handle);

// Sort issues by date
sort($issues);
sort($okDates);

echo "=== TIMELINE OF ISSUES ===\n\n";

if (count($issues) > 0) {
    echo "FIRST issue:  {$issues[0]}\n";
    echo "LAST issue:   " . end($issues) . "\n";
    echo "Total issues: " . count($issues) . "\n\n";

    // Group by date
    $byDate = [];
    foreach ($issues as $d) {
        $day = substr($d, 0, 10);
        $byDate[$day] = ($byDate[$day] ?? 0) + 1;
    }
    ksort($byDate);

    echo "Issues per day:\n";
    foreach ($byDate as $day => $count) {
        echo "  {$day}: {$count} issue(s)\n";
    }

    // Find the transition point — last OK before first issue
    $firstIssue = $issues[0];
    $lastOkBefore = null;
    foreach ($okDates as $d) {
        if ($d < $firstIssue) $lastOkBefore = $d;
    }

    echo "\n--- TRANSITION POINT ---\n";
    echo "Last successful payment:   " . ($lastOkBefore ?? 'none found') . "\n";
    echo "First broken payment:      {$firstIssue}\n";
} else {
    echo "No issues found in CSV transactions (for orders that exist in DB).\n";
}

echo "\nTotal OK transactions:     " . count($okDates) . "\n";
echo "Total broken transactions: " . count($issues) . "\n";
