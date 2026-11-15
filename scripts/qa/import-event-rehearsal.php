<?php

declare(strict_types=1);

/** Fresh local entrants only; finished draws/venues/settings remain manifest references for UI setup. */
use App\Domain\Entries\Services\EntryService;
use App\Models\{Category, CategoryEvent, Event, Player, User};
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI only.');
$options = getopt('', ['manifest:', 'actor:', 'apply']);
if (empty($options['manifest']) || empty($options['actor'])) throw new RuntimeException('Requires --manifest=PRIVATE_JSON_PATH --actor=EXISTING_LOCAL_SUPERUSER_ID [--apply].');
$file = realpath((string) $options['manifest']);
if (!$file || !is_file($file) || filesize($file) > 2000000) throw new RuntimeException('Missing or oversized manifest.');
$raw = file_get_contents($file);
$manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
if (($manifest['schema_version'] ?? null) !== 1 || (int) ($manifest['event']['id'] ?? 0) !== 259
    || (int) ($manifest['event_type']['type'] ?? 0) !== 1) throw new RuntimeException('Expected event 259 individual manifest v1.');
foreach (['categories','category_events','entrants','player_registrations','players'] as $key) {
    if (!is_array($manifest[$key] ?? null) || count($manifest[$key]) > 1000) throw new RuntimeException('Invalid/bounded manifest collection: '.$key);
}
$byId = static function (array $rows): array {
    $result = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1 || isset($result[$id])) throw new RuntimeException('Invalid or repeated source ID.');
        $result[$id] = $row;
    }
    return $result;
};
$sourceCategories = $byId($manifest['categories']);
$sourceCes = $byId($manifest['category_events']);
$sourcePlayers = $byId($manifest['players']);
foreach ($sourceCes as $categoryEvent) {
    if ((int) ($categoryEvent['event_id'] ?? 0) !== 259
        || !isset($sourceCategories[(int) ($categoryEvent['category_id'] ?? 0)])) {
        throw new RuntimeException('Category reference is missing or belongs to another event.');
    }
}
$members = [];
foreach ($manifest['player_registrations'] as $pivot) {
    $registration = (int) ($pivot['registration_id'] ?? 0);
    $player = (int) ($pivot['player_id'] ?? 0);
    if ($registration < 1 || !isset($sourcePlayers[$player])) throw new RuntimeException('Missing participant identity.');
    $members[$registration][] = $player;
}
$pairs = [];
foreach ($manifest['entrants'] as $entrant) {
    $ce = (int) ($entrant['category_event_id'] ?? 0);
    $reg = (int) ($entrant['registration_id'] ?? 0);
    if (!isset($sourceCes[$ce]) || (int) $sourceCes[$ce]['event_id'] !== 259 || count($members[$reg] ?? []) !== 1) throw new RuntimeException('Entry is foreign, missing, or not singles.');
    $pair = $ce.':'.$members[$reg][0];
    if (isset($pairs[$pair])) throw new RuntimeException('Duplicate active player/category entry.');
    $pairs[$pair] = true;
}
[$app] = require __DIR__.'/event-rehearsal-bootstrap.php';
$actor = User::findOrFail((int) $options['actor']);
if (!$actor->hasRole('super-user')) throw new RuntimeException('Requires an existing copied local super-user; never grants a role.');
$fingerprint = hash('sha256', $raw);
$name = '[LOCAL REHEARSAL] '.$manifest['event']['name'];
if (Event::where('name', $name)->exists()) throw new RuntimeException('Rehearsal already exists; refusing overwrite or duplicate import.');
$preview = ['source_event_id'=>259, 'destination_database'=>'ct_rehearsal_259', 'categories'=>count($sourceCes),
    'active_singles_entries'=>count($manifest['entrants']), 'reference_only_draw_registrations'=>count($manifest['reference']['draw_only_registration_ids'] ?? []),
    'fixtures_to_import'=>0, 'scores_to_import'=>0, 'historical_payment_records_to_import'=>0, 'manifest_sha256'=>$fingerprint];
if (!array_key_exists('apply', $options)) { echo json_encode($preview, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL; exit(0); }
$mapping = DB::transaction(function () use ($manifest, $sourceCategories, $sourceCes, $sourcePlayers, $members, $actor, $name, $fingerprint): array {
    $typeQuery = DB::table('eventtypes')->where('name', $manifest['event_type']['name'])->where('type', 1);
    $type = $typeQuery->first();
    if (!$type) throw new RuntimeException('Matching individual event type lookup must be copied from local database first.');
    $event = Event::create(['name'=>$name, 'information'=>'<p>Isolated local rehearsal; source event 259. Manifest SHA256 '.$fingerprint.'. Simulated results only.</p>',
        'start_date'=>$manifest['event']['start_date'], 'end_date'=>$manifest['event']['end_date'], 'eventType'=>$type->id,
        'deadline'=>0, 'entryFee'=>0, 'email'=>'rehearsal@example.invalid', 'organizer'=>'LOCAL REHEARSAL', 'logo'=>'',
        'status'=>'active', 'published'=>false, 'results_published'=>false, 'result_notifications_enabled'=>false,
        'result_auto_refresh_enabled'=>false, 'signUp'=>false, 'engine_mode'=>$manifest['event']['engine_mode'] ?? null]);
    $map = ['event_id'=>$event->id, 'categories'=>[], 'category_events'=>[], 'players'=>[], 'registrations'=>[], 'entries'=>[]];
    foreach ($sourceCes as $oldId=>$row) {
        $categoryId = (int) $row['category_id'];
        if (!isset($sourceCategories[$categoryId])) throw new RuntimeException('Missing category reference.');
        if (!isset($map['categories'][$categoryId])) $map['categories'][$categoryId] = Category::create(['name'=>$sourceCategories[$categoryId]['name'], 'Fee'=>0])->id;
        $map['category_events'][$oldId] = CategoryEvent::create(['event_id'=>$event->id, 'category_id'=>$map['categories'][$categoryId], 'entry_fee'=>0,
            'ordering'=>$row['ordering'] ?? 0, 'entry_limit'=>null, 'nominations_published'=>false])->id;
    }
    foreach ($manifest['entrants'] as $row) {
        $playerId = $members[(int) $row['registration_id']][0];
        if (!isset($map['players'][$playerId])) {
            $p = $sourcePlayers[$playerId];
            $map['players'][$playerId] = Player::create(['name'=>$p['name'], 'surname'=>$p['surname'], 'gender'=>$p['gender'],
                'dateOfBirth'=>$p['dateOfBirth'], 'email'=>'', 'cellNr'=>'', 'userId'=>0, 'profile_complete'=>false])->id;
        }
        $entry = app(EntryService::class)->addPlayerAsAdmin(CategoryEvent::findOrFail($map['category_events'][(int) $row['category_event_id']]), $map['players'][$playerId], $actor);
        $map['registrations'][(int) $row['registration_id']] = $entry->registration_id;
        $map['entries'][(int) $row['id']] = $entry->id;
    }
    if (count($map['entries']) !== count($manifest['entrants']) || DB::table('draws')->where('event_id',$event->id)->exists()) throw new RuntimeException('Import count or fresh-draw assertion failed.');
    return $map;
});
$output = dirname(__DIR__, 2).'/storage/app/private/event-rehearsal-259/mapping.json';
file_put_contents($output, json_encode(['manifest_sha256'=>$fingerprint, 'mapping'=>$mapping], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);
echo json_encode($preview + ['created_event_id'=>$mapping['event_id'], 'mapping_path'=>$output], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
