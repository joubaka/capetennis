<?php

/** CLI-only, read-only policy preview. Never saves ratings or changes canonical results. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use App\Models\{Event, Player};
use App\Services\Performance\{AllAgeMatchFormatResolver, AllAgeRatingModel, PlayerAbilityConsistentRead, PlayerPerformanceHistoryService, PlayerPerformancePilotService, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;

$base = $argv[3] ?? dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$eventId = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$date = $argv[2] ?? '';
if (!$eventId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Usage: php scripts/rating-event-impact.php EVENT YYYY-MM-DD [APP_PATH]\n"); exit(1);
}
$asOf = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Africa/Johannesburg');
if ($asOf->toDateString() !== $date || $asOf->isFuture()) { throw new RuntimeException('Invalid or future date.'); }
if (!in_array(AllAgeRatingModel::VERSION, [4, 5, 6], true)) { throw new RuntimeException('Review the impact preview against the current model first.'); }

[$matches, $names, $excluded, $eventName] = app(PlayerAbilityConsistentRead::class)->run(function () use ($asOf, $eventId, $date) {
    $target = Event::findOrFail($eventId);
    if (!$target->published && !$target->results_published) { throw new RuntimeException('Target event is not published.'); }
    $history = app(PlayerPerformanceHistoryService::class);
    $pilot = app(PlayerPerformancePilotService::class);
    $ability = app(PlayerSharedAbilityService::class);
    $formats = new AllAgeMatchFormatResolver;
    $events = Event::with('eventTypeModel')->whereDate('start_date', '<=', $date)
        ->whereNotNull('end_date')->whereColumn('end_date', '>=', 'start_date')
        ->where(fn ($q) => $q->where('published', true)->orWhere('results_published', true))
        ->orderBy('id')->limit(1001)->get();
    if ($events->count() > 1000) { throw new OverflowException('Event limit exceeded.'); }
    $matches = []; $participants = []; $excluded = []; $seen = []; $scanned = 0;
    foreach ($events as $event) {
        if (App\Models\CategoryEvent::where('event_id', $event->id)->count() > 256) { throw new OverflowException('Event field limit exceeded.'); }
        $rows = (function () use ($history, $pilot, $event, $asOf) {
            yield from $history->individualMatches($event, null, $pilot, $asOf, true);
            yield from $history->allAgeTeamMatches($event, $asOf);
        })();
        foreach ($rows as $row) {
            if (++$scanned > 50000) { throw new OverflowException('Match limit exceeded.'); }
            $today = (int) $event->id === $eventId && $row['confidence_date'] === $date;
            if ($today) {
                foreach (['player1_id', 'player2_id'] as $key) {
                    if (($row[$key] ?? 0) > 0) { $participants[(int) $row[$key]] = true; }
                }
            }
            $reason = $row['reason'] ?? (($row['discipline'] ?? '') !== 'singles' ? 'Non-singles result' : null);
            $source = ($row['source'] === 'Team match' ? 'team-match:' : 'match:').$row['fixture_id'];
            $resolved = $formats->resolve((int) $event->id, $row);
            // Older installations report ball-only exclusions here. Preserve schedule integrity only.
            $integrity = $resolved['format_reason'] ?? null;
            if (in_array($integrity, [
                'Draw court format is mixed or unresolved',
                'Match court is unresolved in an event with modified-ball courts',
                'Cross-context or unclassified team singles require explicit standard playing conditions',
                'Team source declares a modified ball or court format',
            ], true)) { $integrity = null; }
            $reason ??= $integrity;
            $days = CarbonImmutable::parse($row['confidence_date'])->diffInDays($asOf, false);
            if ($days < 0 || $days > AllAgeRatingModel::WINDOW_DAYS) { $reason ??= 'Outside supported match window'; }
            $side = $row['winner_side'] ?? null;
            $winner = (int) ($side === 1 ? $row['player1_id'] : $row['player2_id']);
            $loser = (int) ($side === 1 ? $row['player2_id'] : $row['player1_id']);
            if (!in_array($side, [1, 2], true) || $winner < 1 || $loser < 1 || $winner === $loser) { $reason ??= 'Invalid winner or identity'; }
            if ($reason) { if ($today) { $excluded[$reason] = ($excluded[$reason] ?? 0) + 1; } continue; }
            if (isset($seen[$source])) { continue; }
            $seen[$source] = true;
            $cohort = $row['all_age_cohort'] ?? ($row['source'] === 'Team match' ? ($row['shared_cohort'] ?? '') : $ability->cohort($row['cohort']));
            $matches[] = ['source_id' => $source, 'event_id' => (int) $event->id, 'event_name' => 'Event #'.$event->id,
                'winner' => $winner, 'loser' => $loser, 'category' => $row['category'], 'cohort' => $cohort,
                'age_groups' => $row['all_age_contexts'] ?? [$cohort], 'confidence_date' => $row['confidence_date'],
                'confidence_date_basis' => $row['confidence_date_basis'], 'outcome_target' => $row['outcome_target'] ?? 1.0,
                'ball_type' => $resolved['ball_type'], 'format_reason' => null];
        }
    }
    if (count($participants) > 256) { throw new OverflowException('Participant limit exceeded.'); }
    $names = Player::whereIn('id', array_keys($participants))->get(['id', 'name', 'surname'])
        ->mapWithKeys(fn ($p) => [(int) $p->id => trim($p->name.' '.$p->surname)])->all();
    return [$matches, $names, $excluded, $target->name];
});

// Overriding only format also previews the new policy on the immediately preceding installation.
if (count(array_unique([...array_column($matches, 'winner'), ...array_column($matches, 'loser')])) > 5000) {
    throw new OverflowException('Shared player limit exceeded.');
}
$model = new class extends AllAgeRatingModel {
    public function format(string $category, ?string $ball): array { return ['eligible' => true, 'assumed' => false, 'reason' => null]; }
};
$today = array_values(array_filter($matches, fn ($m) => $m['event_id'] === $eventId && $m['confidence_date'] === $date));
$before = $model->fit(array_values(array_filter($matches, fn ($m) => !($m['event_id'] === $eventId && $m['confidence_date'] === $date))), $asOf);
$after = $model->fit($matches, $asOf);
$players = [];
foreach ($names as $id => $name) {
    $old = $before['ratings'][$id] ?? null; $new = $after['ratings'][$id] ?? null;
    $own = array_values(array_filter($today, fn ($m) => $m['winner'] === $id || $m['loser'] === $id));
    $oldMembers = $before['components'][$old['component'] ?? '']['members'] ?? [];
    $newMembers = $after['components'][$new['component'] ?? '']['members'] ?? [];
    $groupChanged = $oldMembers !== $newMembers || ($before['recent_backbone']['members'] ?? []) !== ($after['recent_backbone']['members'] ?? [])
        || ($old['connection_status'] ?? null) !== ($new['connection_status'] ?? null);
    $oldScore = $old['score'] ?? $old['local_score'] ?? null; $newScore = $new['score'] ?? $new['local_score'] ?? null;
    $players[] = ['id' => $id, 'name' => $name, 'matches' => count($own),
        'wins' => count(array_filter($own, fn ($m) => $m['winner'] === $id)),
        'losses' => count(array_filter($own, fn ($m) => $m['loser'] === $id)),
        'before' => $oldScore, 'after' => $newScore,
        'delta' => $oldScore !== null && $newScore !== null ? round($newScore - $oldScore, 3) : null,
        'comparison_group_changed' => $groupChanged,
        'comparable_to_reference' => $new['comparable'] ?? false,
        'provisional_uncalibrated' => $new !== null && !($new['comparable'] ?? false),
        'before_confidence' => $old !== null && !($old['comparable'] ?? false) ? 'Low' : ($old['confidence_label'] ?? null),
        'after_confidence' => $new !== null && !($new['comparable'] ?? false) ? 'Low' : ($new['confidence_label'] ?? null)];
}
usort($players, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
echo json_encode(['event_id' => $eventId, 'event' => $eventName, 'as_of' => $date,
    'policy' => 'One all-ball, all-age singles rating; disconnected groups receive uncalibrated provisional scores. Same-date counterfactual excluding only this event\'s matches dated today.',
    'before_definition' => 'Hypothetical same-policy rating without this event\'s results dated today; not the previous saved rating.',
    'after_definition' => 'Hypothetical same-policy rating including all eligible results through this date; not written to the application.',
    'eligible_today_matches' => count($today), 'excluded_today' => $excluded,
    'caution' => 'Unrated is not zero. Group changes include network/recalibration effects; deltas are not fixed match points.',
    'players' => $players], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
