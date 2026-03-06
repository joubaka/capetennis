<?php
// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\FixtureResult;
use Illuminate\Support\Facades\DB;

$draw = Draw::with([
    'groups.groupRegistrations.registration.players',
    'settings',
    'drawFixtures.fixtureResults',
    'drawFixtures.registration1.players',
    'drawFixtures.registration2.players',
])->find(1392);

if (!$draw) {
    echo "Draw 1392 NOT FOUND\n";
    exit(1);
}

echo "=== DRAW INFO ===\n";
echo "Draw: {$draw->drawName}\n";
echo "Event: " . ($draw->event->name ?? 'N/A') . "\n";
echo "Groups: " . $draw->groups->count() . "\n\n";

// Show groups and players
foreach ($draw->groups as $g) {
    $regs = $g->groupRegistrations;
    echo "GROUP {$g->name} (id:{$g->id}): {$regs->count()} players\n";
    foreach ($regs as $gr) {
        $p = $gr->registration ? $gr->registration->players->first() : null;
        $name = $p ? $p->full_name : 'Unknown';
        echo "  - Reg:{$gr->registration_id} {$name}\n";
    }
}

// Show fixtures
$fixtures = $draw->drawFixtures->where('stage', 'RR');
echo "\n=== RR FIXTURES: {$fixtures->count()} total ===\n";
$played = $fixtures->where('match_status', 1)->count();
$unplayed = $fixtures->count() - $played;
echo "Played: {$played} | Unplayed: {$unplayed}\n\n";

foreach ($fixtures->take(20) as $fx) {
    $r1 = optional(optional($fx->registration1)->players->first())->full_name ?? 'Unknown';
    $r2 = optional(optional($fx->registration2)->players->first())->full_name ?? 'Unknown';
    $score = $fx->fixtureResults->isEmpty() ? 'Not played' : $fx->score;
    $status = $fx->match_status ? 'Y' : 'O';
    echo "{$status} Fix:{$fx->id} Grp:{$fx->draw_group_id} | {$r1} vs {$r2} | {$score}\n";
}
