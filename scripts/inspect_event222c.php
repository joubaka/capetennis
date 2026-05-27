<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

$event = Event::find(222);
$svc   = app(FinancialLedgerService::class);
$data  = $svc->buildForEvent($event);

echo "=== buildForEvent(222) with event->cape_tennis_fee=R{$event->cape_tennis_fee} ===\n";
echo "gross_payments:  R" . number_format($data['totals']['gross_payments'], 2) . "\n";
echo "pf_fees:         R" . number_format($data['totals']['pf_fees'], 2) . "\n";
echo "cape_fees:       R" . number_format($data['totals']['cape_fees'], 2) . "\n";
echo "net_revenue:     R" . number_format($data['totals']['net_revenue'], 2) . "\n";
echo "payment_rows:    " . $data['paymentRows']->count() . "\n";

$entryCount = $event->isTeam()
    ? $data['paymentRows']->count()
    : $data['paymentRows']->flatMap(fn($t) => optional($t->order)->items ?? collect())->count();
echo "totalEntries:    $entryCount\n\n";

// Breakdown by method
echo "=== Payment row method breakdown ===\n";
$byMethod = $data['paymentRows']->groupBy('method');
foreach ($byMethod as $method => $rows) {
    $gross = $rows->sum('gross');
    $cape  = $rows->sum('capeFee');
    echo sprintf("  %-20s count=%-4d gross=R%-12s cape=R%s\n",
        $method, $rows->count(), number_format($gross, 2), number_format($cape, 2));
}

echo "\n=== Admin entry rows (gross=0, cape=-15 each) — should cape be charged? ===\n";
$adminRows = $data['paymentRows']->filter(fn($r) => $r->method === 'Admin Entry');
echo "  Admin entry count: " . $adminRows->count() . "\n";
echo "  Admin cape total:  R" . number_format($adminRows->sum('capeFee'), 2) . "\n";

echo "\n=== Screen vs Ledger comparison ===\n";
echo "  Screen gross:  R31,065.00\n";
echo "  Ledger gross:  R" . number_format($data['totals']['gross_payments'], 2) . "\n";
echo "  Diff:          R" . number_format(31065 - $data['totals']['gross_payments'], 2) . "\n\n";
echo "  Screen cape:   R1,920.00\n";
echo "  Ledger cape:   R" . number_format(abs($data['totals']['cape_fees']), 2) . "\n";
echo "  Diff:          R" . number_format(1920 - abs($data['totals']['cape_fees']), 2) . "\n";
