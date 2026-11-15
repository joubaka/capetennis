<?php

declare(strict_types=1);

/** CLI-only, read-only competition manifest. No contacts, accounts, money or scores. */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (PHP_SAPI !== 'cli' || !in_array('--event=259', $argv, true)) {
    throw new RuntimeException('Requires CLI and exact --event=259 scope.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if ($db->getDriverName() !== 'mysql') throw new RuntimeException('Requires a MySQL consistent read-only transaction.');
$read = static function (string $table, array $columns, string $key, array $ids, bool $required = false) use ($db): array {
    if (!Schema::hasTable($table)) {
        if ($required) throw new RuntimeException('Required table missing: '.$table);
        return [];
    }
    $available = Schema::getColumnListing($table);
    foreach ([$key] as $column) if (!in_array($column, $available, true)) throw new RuntimeException('Missing scope column: '.$table.'.'.$column);
    $rows = $db->table($table)->whereIn($key, $ids)->limit(1001)->get(array_values(array_intersect($columns, $available)))->map(fn ($r) => (array) $r)->all();
    if (count($rows) > 1000) throw new RuntimeException('Manifest row limit exceeded: '.$table);
    return $rows;
};
$ids = static fn (array $rows, string $key = 'id'): array => array_values(array_unique(array_filter(array_column($rows, $key), fn ($v) => $v !== null)));
$db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $event = $read('events', ['id','name','start_date','end_date','eventType','deadline','engine_mode'], 'id', [259], true)[0] ?? throw new RuntimeException('Event 259 not found.');
    $ces = $read('category_events', ['id','event_id','category_id','ordering','entry_limit'], 'event_id', [259], true);
    $ceIds = $ids($ces);
    $entrantQuery = $db->table('category_event_registrations')->whereIn('category_event_id', $ceIds)
        ->whereNotIn('status', ['pending_checkout','withdrawn','withdrawn_pending_refund','withdrawn_refunded'])
        ->where('payment_status_id', 1)->whereNull('withdrawn_at');
    if (Schema::hasColumn('category_event_registrations', 'deleted_at')) $entrantQuery->whereNull('deleted_at');
    $entrants = $entrantQuery->limit(1001)->get(['id','category_event_id','registration_id'])->map(fn ($r) => (array) $r)->all();
    if (count($entrants) > 1000) throw new RuntimeException('Entrant limit exceeded.');
    $draws = $read('draws', ['id','event_id','category_event_id','drawName','drawType_id','num_courts','start_time','time_per_match','gender','engine_mode'], 'event_id', [259], true);
    $drawIds = $ids($draws);
    $settings = $read('draw_settings', ['id','draw_id','workflow','draw_format_id','draw_type_id','boxes','playoff_size','num_sets','score_format','match_duration_minutes','changeover_minutes','require_full_sets','playoff_config','preset_key','schedule_visibility'], 'draw_id', $drawIds);
    $members = $read('draw_registrations', ['draw_id','registration_id','seed'], 'draw_id', $drawIds);
    $groups = $read('draw_groups', ['id','draw_id','name','color','category_slug','round_robin_phase','qualifying_position'], 'draw_id', $drawIds);
    $groupMembers = $read('draw_group_registrations', ['draw_group_id','registration_id','seed'], 'draw_group_id', $ids($groups));
    $monrad = [];
    $additionalIds = [];
    foreach ($read('flexible_monrad_draws', ['draw_id','draft'], 'draw_id', $drawIds) as $record) {
        $draft = json_decode((string) $record['draft'], true, 32, JSON_THROW_ON_ERROR) ?? [];
        // Never export the played graph, fixture map or results.
        $safe = array_intersect_key($draft, array_flip(['size','mode','slots','additional_registration_ids','rolling_challenges','rolling_challenge_stages','challenge_playoffs','challenge_pairs']));
        $additional = array_map('intval', $safe['additional_registration_ids'] ?? []);
        if (count($additional) > 1000 || array_filter($additional, fn ($id) => $id < 1)) throw new RuntimeException('Invalid additional Monrad participant IDs.');
        $additionalIds = array_merge($additionalIds, $additional);
        $monrad[] = ['draw_id'=>$record['draw_id'], 'draft'=>$safe];
    }
    $regIds = array_values(array_unique(array_merge($ids($entrants, 'registration_id'), $ids($members, 'registration_id'), $ids($groupMembers, 'registration_id'), $additionalIds)));
    $pivots = $read('player_registrations', ['registration_id','player_id'], 'registration_id', $regIds, true);
    $eventVenues = $read('event_venues', ['id','event_id','venue_id','num_courts'], 'event_id', [259]);
    $drawVenues = $read('draw_venues', ['id','draw_id','venue_id','num_courts'], 'draw_id', $drawIds);
    $courts = $read('event_venue_courts', ['id','event_id','venue_id','label','ball_type','active'], 'event_id', [259]);
    $allocations = $read('draw_venue_court_allocations', ['id','draw_id','venue_id','court_label'], 'draw_id', $drawIds);
    $venueIds = array_values(array_unique(array_merge($ids($eventVenues, 'venue_id'), $ids($drawVenues, 'venue_id'), $ids($courts, 'venue_id'), $ids($allocations, 'venue_id'))));
    $formatIds = $ids($settings, 'draw_format_id');
    $manifest = [
        'schema_version' => 1,
        'event' => $event,
        'event_type' => $read('eventtypes', ['id','name','type','code'], 'id', [$event['eventType']], true)[0] ?? throw new RuntimeException('Event type missing.'),
        'categories' => $read('categories', ['id','name'], 'id', $ids($ces, 'category_id'), true),
        'category_events' => $ces,
        'entrants' => $entrants,
        'player_registrations' => $pivots,
        'players' => $read('players', ['id','name','surname','gender','dateOfBirth'], 'id', $ids($pivots, 'player_id'), true),
        'reference' => [
            'draws' => $draws, 'draw_settings' => $settings,
            'draw_types' => $read('draw_types', ['id','drawTypeName','type','scoring_id'], 'id', array_values(array_unique(array_merge($ids($draws, 'drawType_id'), $ids($settings, 'draw_type_id'))))),
            'draw_formats' => $read('draw_formats', ['id','name'], 'id', $formatIds),
            'draw_format_options' => $read('draw_format_options', ['id','draw_format_id','supports_boxes','supports_playoff','default_boxes','default_playoff_size','default_num_sets'], 'draw_format_id', $formatIds),
            'draw_registrations' => $members, 'draw_groups' => $groups, 'draw_group_registrations' => $groupMembers,
            'flexible_monrad_drafts' => $monrad,
            'draw_only_registration_ids' => array_values(array_diff($regIds, $ids($entrants, 'registration_id'))),
            'event_venues' => $eventVenues, 'draw_venues' => $drawVenues, 'event_venue_courts' => $courts,
            'draw_venue_court_allocations' => $allocations,
            'venues' => $read('venues', ['id','name','num_courts'], 'id', $venueIds),
            'event_age_group_venue_defaults' => $read('event_age_group_venue_defaults', ['event_id','age','gender','venues'], 'event_id', [259]),
        ],
    ];
    $json = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    $db->rollBack();
}
echo in_array('--base64', $argv, true) ? base64_encode(gzencode($json, 9)).PHP_EOL : $json.PHP_EOL;
