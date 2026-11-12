<?php

// Fictional visual QA only: no database reads, recipients or mail dispatch.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$service = app(App\Services\CavaliersRosterReport::class);
$rows = [];
foreach (['selected' => 32, 'reserves' => 5, 'declined' => 1] as $status => $count) {
    for ($position = 1; $position <= $count; $position++) {
        $rows[] = ['id' => count($rows) + 1, 'name' => 'Fictional '.ucfirst($status).' Player '.str_pad((string) $position, 2, '0', STR_PAD_LEFT),
            'status' => $status, 'position' => $position, 'evidence' => ['region' => 'Example Region', 'source' => 'Inter-Districts']];
    }
}
$candidates = [];
foreach (range(1, 8) as $index) {
    $matches = [];
    foreach (range(1, $index === 1 ? 45 : 3) as $match) {
        $matches[] = ['outcome' => $match % 2 ? 'Lost' : 'Won', 'opponent' => 'Fictional Opponent '.str_pad((string) $match, 2, '0', STR_PAD_LEFT), 'score' => '6-3, 4-6, 7-5', 'playing_number' => 3];
    }
    $candidates[] = ['id' => 100 + $index, 'name' => $index === 1 ? 'Fictional Alexandra Long-Double-Barrelled-Surname' : 'Fictional Review Candidate '.$index,
        'source' => 'Inter-Districts', 'region' => 'Example Region', 'wins' => $index === 2 ? null : ($index === 3 ? 0 : ($index === 1 ? 22 : 1)),
        'losses' => $index === 2 ? null : ($index === 3 ? 0 : ($index === 1 ? 23 : 2)), 'matches' => in_array($index, [2, 3]) ? [] : $matches,
        'starting_credit' => 1, 'band' => 2, 'lost_all' => false, 'only_beat_winless' => $index === 4];
}
$preview = ['source_division' => 'Example U10 Girls', 'candidate_regions' => ['Example Region', 'Sample Region'], 'excluded_result_regions' => ['Excluded Example Region'], 'missing_regions' => ['Unpublished Example Region'], 'platteland_notice' => null];
$report = ['event_name' => 'Fictional Cavaliers Trials - Layout Sample', 'unsaved_divisions' => ['Example U12 Girls'], 'sections' => [
    ['division' => 'Example U11 Girls', 'rows' => $rows, 'preview' => $preview, 'explanations' => $service->explanationGroups($candidates, [])],
    ['division' => 'Example U12 Boys', 'rows' => [], 'preview' => [...$preview, 'source_division' => 'Example U11 Boys', 'missing_regions' => []], 'explanations' => ['next_candidates' => [], 'inter_districts' => []]],
]];
$output = $argv[1] ?? dirname(__DIR__, 2).'/output/pdf/cavaliers-nomination-sample.pdf';
if (! is_dir(dirname($output))) mkdir(dirname($output), 0777, true);
file_put_contents($output, $service->pdf($report));
echo $output.PHP_EOL;
