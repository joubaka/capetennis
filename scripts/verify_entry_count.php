<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$svc   = app(App\Domain\Finance\Services\FinancialLedgerService::class);
$event = App\Models\Event::find(222);
$data  = $svc->buildForEvent($event);
$entries = $data['paymentRows']->sum(fn($r) => $r->entryCount ?? 1);
echo "totalEntries (fixed): {$entries}\n";
echo "cape_fees:            R" . number_format(abs($data['totals']['cape_fees']), 2) . "\n";
echo "Check: {$entries} x R{$event->cape_tennis_fee} = R" . number_format($entries * $event->cape_tennis_fee, 2) . "\n";
echo "gross_payments:       R" . number_format($data['totals']['gross_payments'], 2) . "\n";
