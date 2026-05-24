<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$draw = DB::table('draws')->where('id', 1422)->first();
echo "Draw: {$draw->drawName}\n";

$groups = DB::table('draw_groups')->where('draw_id', 1422)->orderBy('name')->get();
foreach ($groups as $g) {
    echo "\nGroup {$g->name} (id:{$g->id}):\n";
    $regs = DB::table('draw_group_registrations')
        ->where('draw_group_id', $g->id)
        ->orderBy('id')->get();
    foreach ($regs as $r) {
        echo "  reg_id={$r->registration_id}\n";
    }
}

echo "\n\nPlate fixtures (current):\n";
$fixtures = DB::table('fixtures')
    ->where('draw_id', 1422)
    ->whereIn('stage', ['PLATE'])
    ->orderBy('round')->orderBy('match_nr')
    ->get();
foreach ($fixtures as $f) {
    echo "  id={$f->id} round={$f->round} match_nr={$f->match_nr} r1={$f->registration1_id} r2={$f->registration2_id}\n";
}
