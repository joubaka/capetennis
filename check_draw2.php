<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$setting = DB::table('draw_settings')->where('draw_id', 1422)->first();
if ($setting) {
    echo "playoff_config:\n";
    $cfg = json_decode($setting->playoff_config, true);
    print_r($cfg);
    echo "\nboxes: " . $setting->boxes . "\n";
} else {
    echo "No draw_settings found\n";
}

echo "\nStandings from hub logic:\n";
$fixtures = DB::table('fixtures')
    ->where('draw_id', 1422)
    ->where('stage', 'RR')
    ->get();
echo "RR fixtures count: " . count($fixtures) . "\n";

echo "\nMAIN fixtures:\n";
$main = DB::table('fixtures')->where('draw_id', 1422)->where('stage','MAIN')->orderBy('round')->orderBy('match_nr')->get();
foreach ($main as $f) {
    echo "  id={$f->id} round={$f->round} match={$f->match_nr} r1={$f->registration1_id} r2={$f->registration2_id}\n";
}

echo "\nPLATE fixtures:\n";
$plate = DB::table('fixtures')->where('draw_id', 1422)->where('stage','PLATE')->orderBy('round')->orderBy('match_nr')->get();
foreach ($plate as $f) {
    echo "  id={$f->id} round={$f->round} match={$f->match_nr} r1={$f->registration1_id} r2={$f->registration2_id}\n";
}
