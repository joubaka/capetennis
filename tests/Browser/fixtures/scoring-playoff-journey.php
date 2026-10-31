<?php

// Test-only CLI fixture / loopback router. Never install this in public/.
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$database = str_replace('\\', '/', (string) getenv('DB_DATABASE'));
$testingRoot = realpath($root.'/storage/framework/testing');
$runtime = realpath(dirname($database));
$resolvedDatabase = realpath($database);
if (!in_array(PHP_SAPI, ['cli', 'cli-server'], true) || getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || !$testingRoot || !$runtime || !$resolvedDatabase || dirname($runtime) !== $testingRoot
    || !str_starts_with(basename($runtime), 'scoring-playoff-journey-') || basename($resolvedDatabase) !== 'database.sqlite'
    || str_replace('\\', '/', $resolvedDatabase) !== $database) {
    throw new RuntimeException('This fixture requires its own testing SQLite runtime.');
}
if (PHP_SAPI === 'cli-server') {
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit;
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $asset = realpath($root.'/public/'.ltrim((string) $path, '/'));
    if ($path !== '/' && $asset && is_file($asset) && strtolower(pathinfo($asset, PATHINFO_EXTENSION)) !== 'php'
        && str_starts_with($asset, realpath($root.'/public').DIRECTORY_SEPARATOR)) {
        return false;
    }
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config('database.connections.sqlite');
if (!$app->environment('testing') || config('database.default') !== 'sqlite'
    || ($connection['driver'] ?? null) !== 'sqlite' || !empty($connection['url'])
    || realpath((string) ($connection['database'] ?? '')) !== $resolvedDatabase) {
    throw new RuntimeException('Resolved application configuration must use the isolated testing database.');
}
config(['session.driver' => 'database', 'session.cookie' => 'scoring_journey_session',
    'session.domain' => null, 'session.secure' => false, 'cache.default' => 'array',
    'queue.default' => 'sync', 'logging.default' => 'null', 'database.connections.sqlite.synchronous' => 'OFF']);
foreach (array_keys(config('mail.mailers', [])) as $mailer) {
    config(["mail.mailers.{$mailer}" => ['transport' => 'array']]);
}

if (PHP_SAPI === 'cli-server') {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
    return;
}

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, EventConvenor, Fixture, FixtureResult, OrderOfPlay, Player, Registration, User, Venue};
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

$command = $argv[1] ?? '';
if ($command === 'seed') {
    if (DB::getSchemaBuilder()->hasTable('users')) throw new RuntimeException('Refusing to reseed an existing runtime.');
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    foreach (['admin', 'score-keeper'] as $role) Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $admin = User::factory()->create(['name' => 'Journey Administrator', 'email' => 'journey-admin@example.test']);
    $admin->assignRole('admin');
    $scorer = User::factory()->create(['name' => 'Journey Scorer', 'email' => 'journey-scorer@example.test']);
    $scorer->assignRole('score-keeper');
    $manifest = ['admin' => $admin->email, 'scorer' => $scorer->email, 'password' => 'password', 'cases' => []];
    foreach (['desktop', 'phone', 'cancel', 'stale', 'restricted'] as $case) {
        $event = Event::factory()->create(['name' => 'Fictional progression '.$case, 'eventType' => 6,
            'start_date' => '2026-10-31', 'end_date' => '2026-10-31']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'drawName' => 'Fictional orange '.$case, 'published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'boxes' => 2, 'num_sets' => 1,
            'score_format' => 'timed', 'match_duration_minutes' => 15, 'changeover_minutes' => 0,
            'playoff_config' => [
                ['name' => 'Main', 'slug' => 'main', 'size' => 4, 'positions' => [1, 2], 'enabled' => true],
                ['name' => 'Plate', 'slug' => 'plate', 'size' => 4, 'positions' => [3, 4], 'enabled' => true],
            ]]);
        $venue = Venue::forceCreate(['name' => 'Fictional court '.$case]);
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        $entrants = [];
        foreach (range(0, 7) as $index) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create(['name' => 'Example', 'surname' => (string) ($index + 1)])->id);
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                'registration_id' => $registration->id, 'payment_status_id' => 1]);
            $entrants[] = $registration;
        }
        $matchNumber = 1;
        foreach (['A', 'B'] as $groupIndex => $name) {
            $group = $draw->groups()->create(['name' => $name]);
            $members = array_slice($entrants, $groupIndex * 4, 4);
            foreach ($members as $seed => $member) $group->registrations()->attach($member->id, ['seed' => $seed + 1]);
            for ($one = 0; $one < 4; $one++) for ($two = $one + 1; $two < 4; $two++) {
                $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
                    'stage' => 'RR', 'round' => 1, 'match_nr' => $matchNumber,
                    'registration1_id' => $members[$one]->id, 'registration2_id' => $members[$two]->id,
                    'winner_registration' => $matchNumber === 12 ? null : $members[$one]->id,
                    'match_status' => $matchNumber === 12 ? 0 : 3]);
                if ($matchNumber !== 12) FixtureResult::factory()->create(['fixture_id' => $fixture->id,
                    'registration1_score' => 3, 'registration2_score' => 1,
                    'winner_registration' => $members[$one]->id, 'loser_registration' => $members[$two]->id]);
                OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                    'court' => (string) (1 + ($matchNumber % 2)), 'time' => '2026-10-31 08:00:00', 'duration_minutes' => 15, 'gap_minutes' => 0]);
                $matchNumber++;
            }
        }
        app(App\Http\Controllers\Backend\RoundRobinController::class)->prepareSchedulePlayoffs($draw);
        foreach ($draw->drawFixtures()->where('stage', '!=', 'RR')->orderBy('match_nr')->get() as $index => $fixture) {
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => '1', 'time' => Carbon\Carbon::parse('2026-10-31 10:00:00')->addMinutes(15 * $index),
                'duration_minutes' => 15, 'gap_minutes' => 0]);
        }
        $draw->update(['published' => true, 'oop_published' => true]);
        $manifest['cases'][$case] = ['event' => $event->id, 'draw' => $draw->id, 'venue' => $venue->id,
            'lastFixture' => $draw->drawFixtures()->where('stage', 'RR')->where('match_nr', 12)->value('id'),
            'entrants' => array_map(fn ($registration) => $registration->id, $entrants)];
    }
    file_put_contents(dirname($database).'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    echo json_encode($manifest);
} elseif ($command === 'inspect') {
    $manifest = json_decode(file_get_contents(dirname($database).'/manifest.json'), true);
    $case = $manifest['cases'][$argv[2] ?? ''] ?? throw new RuntimeException('Unknown fixture case.');
    $fixtures = Fixture::where('draw_id', $case['draw'])->orderBy('id')->get();
    echo json_encode(['fixtures' => $fixtures->toArray(), 'results' => FixtureResult::whereIn('fixture_id', $fixtures->modelKeys())->orderBy('id')->get()->toArray(),
        'times' => OrderOfPlay::where('draw_id', $case['draw'])->orderBy('id')->get()->toArray()]);
} else {
    throw new RuntimeException('Only seed and inspect CLI commands are supported.');
}
