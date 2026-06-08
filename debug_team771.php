<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check team 771 (u/12 Boys) specifically
$teamId = 771;

echo "=== team_players raw rows for team $teamId ===\n";
$rows = DB::table('team_players')->where('team_id', $teamId)->orderBy('rank')->get();
foreach ($rows as $r) {
    echo "id:{$r->id}, rank:{$r->rank}, player_id:{$r->player_id}, pay_status:{$r->pay_status}\n";
    if ($r->player_id > 0) {
        $p = DB::table('players')->where('id', $r->player_id)->first(['id','name','surname']);
        echo "  -> player: " . ($p ? "{$p->name} {$p->surname}" : "NOT FOUND in players table") . "\n";
    }
}

echo "\n=== no_profile_team_players for team $teamId ===\n";
$nps = DB::table('no_profile_team_players')->where('team_id', $teamId)->orderBy('rank')->get();
foreach ($nps as $np) {
    echo "id:{$np->id}, rank:{$np->rank}, name:{$np->name}, surname:{$np->surname}\n";
}

echo "\n=== What editRoster controller returns ===\n";
$team = App\Models\Team::findOrFail($teamId);
$slots = App\Models\TeamPlayer::where('team_id', $teamId)
    ->orderBy('rank')
    ->get(['id', 'rank', 'player_id', 'pay_status']);

echo json_encode($slots->toArray(), JSON_PRETTY_PRINT) . "\n";
