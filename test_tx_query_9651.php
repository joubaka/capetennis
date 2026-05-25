<?php
/**
 * Verify the fixed STEP 4 query picks up Jean Joubert's withdrawal on event 239.
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CategoryEventRegistration;

$eventId = 239;

// Replicate the OLD (broken) query
$old = CategoryEventRegistration::with(['players','categoryEvent.category','payfastTransaction'])
    ->whereHas('categoryEvent', fn($q) => $q->where('event_id', $eventId))
    ->where('status', 'withdrawn')
    ->whereIn('refund_status', ['completed', 'pending'])
    ->whereNotNull('pf_transaction_id')
    ->whereHas('payfastTransaction', fn($q) => $q->where('is_test', false))
    ->pluck('id');

// Replicate the NEW (fixed) query
$new = CategoryEventRegistration::with(['players','categoryEvent.category','payfastTransaction'])
    ->whereHas('categoryEvent', fn($q) => $q->where('event_id', $eventId))
    ->where('status', 'withdrawn')
    ->where('payment_status_id', 1)
    ->whereIn('refund_status', ['completed', 'pending'])
    ->where(function ($q) {
        $q->whereHas('payfastTransaction', fn($q2) => $q2->where('is_test', false))
          ->orWhere(function ($q3) {
              $q3->whereNotNull('pf_transaction_id')
                 ->whereDoesntHave('payfastTransaction');
          });
    })
    ->get();

echo "OLD query matched CER IDs: " . $old->implode(', ') . " (" . $old->count() . " rows)\n";
echo "NEW query matched CER IDs: " . $new->pluck('id')->implode(', ') . " (" . $new->count() . " rows)\n\n";

foreach ($new as $cer) {
    $payment = $cer->paymentInfo();
    $player  = $cer->players->first();
    echo "CER {$cer->id} | " . ($player ? "{$player->name} {$player->surname}" : '?')
       . " | refund_status={$cer->refund_status}"
       . " | gross=" . ($payment['gross'] ?? 'NULL')
       . " | fee=" . ($payment['fee'] ?? 'NULL')
       . " | _legacy=" . (($payment['_legacy'] ?? false) ? 'yes' : 'no')
       . "\n";
}
