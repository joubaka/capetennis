<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

// All PayFast pf_payment_ids from the CSV for event 227
// Format: [pf_payment_id, type, player_id(custom_int2), order_id(custom_int5), team_id(custom_int1), gross]
$csv = [
    // new-style (have order in custom_int5, team in custom_int1 empty)
    [282088155, 'Credit',    4590, 7,    null, 450.00], // Milan Snyman
    [281867899, 'Reversal',  4918, 5,    null, -450.00], // Karlienke Nel REVERSAL
    [281884735, 'Credit',    4918, 5,    null, 450.00], // Karlienke Nel REPLACEMENT
    [281867899, 'Credit',    4918, 5,    null, 450.00], // Karlienke Nel original (reversed)
    // old-style (custom_int5 empty, team in custom_int1)
    [281737595, 'Credit',    4919, null, 739, 450.00],
    [281714351, 'Credit',    3935, null, 742, 450.00],
    [281699903, 'Credit',    4918, null, 740, 450.00],
    [281630101, 'Credit',    4915, null, 739, 450.00],
    [281618753, 'Credit',    4576, null, 744, 450.00],
    [281610387, 'Credit',    3949, null, 744, 450.00],
    [281594025, 'Credit',    4025, null, 743, 450.00],
    [281591107, 'Credit',    3226, null, 742, 450.00],
    [280955825, 'Credit',    3727, null, 733, 450.00],
    [280715531, 'Credit',    4903, null, 744, 450.00],
    [280304121, 'Credit',    4882, null, 743, 450.00],
    [280296023, 'Credit',    4881, null, 740, 450.00],
    [280294931, 'Credit',    2390, null, 742, 450.00],
    [280294351, 'Credit',    3310, null, 742, 450.00],
    [280291455, 'Credit',    3930, null, 743, 450.00],
    [280183663, 'Credit',    4587, null, 739, 450.00],
    [280179391, 'Credit',    3267, null, 741, 450.00],
    [280178199, 'Credit',    3269, null, 741, 450.00],
    [280120999, 'Credit',    2188, null, 734, 450.00],
    [280105429, 'Credit',    3988, null, 741, 450.00],
    [279921627, 'Credit',    3247, null, 743, 450.00],
    [279889459, 'Credit',    4598, null, 740, 450.00],
    [279878369, 'Credit',    2400, null, 742, 450.00],
    [279804013, 'Credit',    3252, null, 743, 450.00],
    [279803401, 'Credit',    3297, null, 739, 450.00],
    [279801795, 'Credit',    2368, null, 742, 450.00],
    [279792197, 'Credit',    3260, null, 744, 450.00],
    [279718413, 'Credit',    3243, null, 743, 450.00],
    [279718049, 'Credit',    4592, null, 741, 450.00],
    [279581295, 'Credit',    1021, null, 735, 450.00],
    [279571791, 'Credit',    4581, null, 740, 450.00],
    [279569211, 'Credit',    2665, null, 738, 450.00],
    [279568795, 'Credit',    4597, null, 740, 450.00],
    [279546895, 'Credit',    4594, null, 739, 450.00],
    [279484251, 'Credit',    3993, null, 744, 450.00],
    [279448409, 'Credit',    4580, null, 739, 450.00],
    [279443841, 'Credit',    4607, null, 740, 450.00],
    [279409749, 'Credit',    2118, null, 738, 450.00],
    [279404713, 'Credit',    2467, null, 738, 450.00],
    [279399751, 'Credit',    3799, null, 738, 450.00],
    [279365511, 'Credit',    1605, null, 735, 450.00],
    [279356665, 'Credit',    3009, null, 737, 450.00],
    [279349939, 'Credit',    4611, null, 743, 450.00],
    [279344185, 'Credit',    2068, null, 737, 450.00],
    [279339755, 'Credit',     756, null, 735, 450.00],
    [279338605, 'Credit',    2553, null, 733, 450.00],
    [279335601, 'Credit',     595, null, 738, 450.00],
    [279315889, 'Credit',    2514, null, 738, 450.00],
    [279314993, 'Credit',    2074, null, 735, 450.00],
    [279314415, 'Credit',    1998, null, 734, 450.00],
    [279305755, 'Credit',    1847, null, 735, 450.00],
    [279302119, 'Credit',    4584, null, 742, 450.00],
    [279296997, 'Credit',    1064, null, 737, 450.00],
    [279296553, 'Credit',    2303, null, 734, 450.00],
    [279289515, 'Credit',    3635, null, 736, 450.00],
    [279283313, 'Credit',    1972, null, 733, 450.00],
    [279281607, 'Credit',    3020, null, 736, 450.00],
    [279275213, 'Credit',    2549, null, 737, 450.00],
    [279248887, 'Credit',    2580, null, 735, 450.00],
    [279240835, 'Credit',    3838, null, 737, 450.00],
    [279239933, 'Credit',    1987, null, 734, 450.00],
    [279237925, 'Credit',    1729, null, 734, 450.00],
    [279237575, 'Credit',    2546, null, 737, 450.00],
    [279235733, 'Credit',    4588, null, 744, 450.00],
    [279232883, 'Credit',    3983, null, 741, 450.00],
    [279232717, 'Credit',    2825, null, 736, 450.00],
    [279231031, 'Credit',    1621, null, 734, 450.00],
    [279216411, 'Credit',    3451, null, 736, 450.00],
    [279206099, 'Credit',    3710, null, 737, 450.00],
    [279195703, 'Credit',     884, null, 734, 450.00],
    [279186313, 'Credit',     992, null, 735, 450.00],
    [279185171, 'Credit',    2675, null, 733, 450.00],
    [279169207, 'Credit',    2299, null, 734, 450.00],
    [279167011, 'Credit',    4833, null, 743, 450.00],
    [279159243, 'Credit',    3526, null, 736, 450.00],
    [279153473, 'Credit',    3965, null, 741, 450.00],
    [279145511, 'Credit',    3003, null, 736, 450.00],
    [279130775, 'Credit',    3406, null, 733, 450.00],
    [279125781, 'Credit',    4578, null, 740, 450.00],
    [279125563, 'Credit',    4577, null, 739, 450.00],
    [279120477, 'Credit',    4579, null, 744, 450.00],
    [279119917, 'Credit',    4830, null, 739, 450.00],
    [279116437, 'Credit',    2373, null, 742, 450.00],
    [279113533, 'Credit',    3397, null, 733, 450.00],
    [279112045, 'Credit',    3525, null, 733, 450.00],
    [279095449, 'Credit',    4575, null, 741, 450.00],
    [279092825, 'Credit',    3494, null, 736, 450.00],
    [279084693, 'Credit',    3934, null, 741, 450.00],
    [279084211, 'Credit',    2463, null, 737, 450.00],
    [279083989, 'Credit',    3995, null, 744, 450.00],
    [279084039, 'Credit',    2540, null, 733, 450.00],
    [279064259, 'Credit',    3479, null, 736, 450.00],
];

echo "=== CSV analysis for event 227 ===\n";
echo "Total CSV rows: ".count($csv)."\n";
$credits = array_filter($csv, fn($r) => $r[1] === 'Credit');
$reversals = array_filter($csv, fn($r) => $r[1] === 'Reversal');
echo "Credits: ".count($credits)."\n";
echo "Reversals: ".count($reversals)."\n\n";

// Get all pf_payment_ids already in transactions_pf for event 227
$existingPfIds = DB::table('transactions_pf')
    ->where('event_id', 227)
    ->where('transaction_type', 'Registration')
    ->pluck('pf_payment_id')
    ->filter()
    ->values();

echo "Existing in transactions_pf: ".count($existingPfIds)."\n\n";

echo "=== Credits NOT in transactions_pf ===\n";
echo "pf_id,player_id,player_name,order_id,team_id,note\n";
$toInsert = [];
foreach ($credits as $row) {
    [$pfId, $type, $playerId, $orderId, $teamId, $gross] = $row;

    // Skip the reversed payment (281867899 original — the reversal makes it void)
    // The replacement 281884735 should be included
    // 281867899 appears twice in CSV: once as Credit (original, got reversed) and once as Reversal
    // We should NOT insert 281867899 since it was reversed
    if ($pfId == 281867899) {
        echo "{$pfId},SKIPPED — this was reversed (281867899), replacement is 281884735\n";
        continue;
    }

    if ($existingPfIds->contains($pfId)) {
        continue; // already in DB
    }

    $player = DB::table('players')->where('id', $playerId)->first();
    $pname = $player ? trim($player->name.' '.$player->surname) : 'NOT FOUND';
    $note = $orderId ? "order_id={$orderId}" : "old-style(team={$teamId})";
    echo "{$pfId},{$playerId},{$pname},{$note}\n";
    $toInsert[] = $row;
}
echo "\nTo insert: ".count($toInsert)."\n";

// Also check: reversal pf_id 281867899 — is it in transactions_pf? Should NOT be
echo "\n=== Reversal check ===\n";
$reversalInDb = DB::table('transactions_pf')->where('pf_payment_id', 281867899)->get();
echo "281867899 (reversed) in transactions_pf: ".count($reversalInDb)." row(s)\n";
foreach ($reversalInDb as $r) {
    echo "  tx_id={$r->id}, event_id={$r->event_id}, type={$r->transaction_type}, gross={$r->amount_gross}\n";
}

// 281884735 (replacement) — is it in transactions_pf?
$replacementInDb = DB::table('transactions_pf')->where('pf_payment_id', 281884735)->get();
echo "281884735 (replacement) in transactions_pf: ".count($replacementInDb)." row(s)\n";
foreach ($replacementInDb as $r) {
    echo "  tx_id={$r->id}, event_id={$r->event_id}, type={$r->transaction_type}, gross={$r->amount_gross}\n";
}

// team_payment_orders for Karlienke Nel (player 4918)
echo "\n=== team_payment_orders for player 4918 (Karlienke Nel) ===\n";
$nels = DB::table('team_payment_orders')->where('player_id', 4918)->where('event_id', 227)->get();
foreach ($nels as $o) {
    echo "order_id={$o->id}, team_id={$o->team_id}, pay_status={$o->pay_status}, pf_id={$o->payfast_pf_payment_id}, amount={$o->payfast_amount_due}\n";
}
