<?php

/** Standalone two-process InnoDB regression on a disposable, uniquely named DB. */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{CampCoachLink, CampNominee, CategoryEvent, Event};
use App\Services\Camps\{CampAdmission, CampWorkflow};
use Illuminate\Support\Facades\{DB, Schema};

if (! $app->environment('testing') || config('database.connections.mysql.host') !== '127.0.0.1') throw new RuntimeException('Requires explicit testing environment and loopback MySQL.');
$worker = ($argv[1] ?? '') === 'worker';
$input = $worker ? json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR) : [];
$database = $worker ? $input['database'] : 'ct_testing_camp_read_'.bin2hex(random_bytes(5));
if (! preg_match('/^ct_testing_camp_read_[a-f0-9]{10}$/D', $database)) throw new RuntimeException('Unsafe disposable database name.');
$connection = config('database.connections.mysql');
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database,
    'database.connections.mysql.url' => null, 'mail.default' => 'array', 'queue.default' => 'sync', 'logging.default' => 'null']);
DB::purge('mysql');

if ($worker) {
    DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    DB::beginTransaction();
    // Deliberately establish a stale snapshot before the real lock wait.
    $old = $input['mode'] === 'player' ? \App\Models\Player::findOrFail(1)->dateOfBirth
        : ($input['mode'] === 'coach' ? CampCoachLink::findOrFail(1)->revoked_at : CampNominee::findOrFail(1)->status);
    echo json_encode(['connection_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id, 'snapshot' => $old])."\n";
    flush();
    try {
        if (in_array($input['mode'], ['nominee', 'player'], true)) {
            app(CampAdmission::class)->lockEvents([1]);
            app(CampAdmission::class)->assertEligible(CategoryEvent::findOrFail(1), 1);
        } elseif ($input['mode'] === 'admin') {
            app(CampAdmission::class)->lockEvents([1]);
            $division = CategoryEvent::whereKey(1)->lockForUpdate()->firstOrFail();
            $disciplinary = new class extends \App\Services\PlayerEligibilityService {
                public function assertEligible(\App\Models\Player|int $player, \App\Models\Event|int $event): void {}
            };
            (new \App\Domain\Entries\Services\EntryEligibilityService($disciplinary))->assertCanAddAdmin($division, 1);
        } else {
            app(CampWorkflow::class)->submitCoach(str_repeat('a', 64), ['request_token' => '11111111-1111-4111-a111-111111111111',
                'name' => 'Example', 'surname' => 'Child', 'date_of_birth' => '2018-01-01', 'gender' => 1, 'guardian_name' => 'Parent', 'guardian_email' => 'parent@example.test']);
        }
        echo json_encode(['result' => 'unexpected_admission'])."\n";
    } catch (Throwable $e) {
        echo json_encode(['result' => 'rejected', 'message' => $e->getMessage(), 'http' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null])."\n";
    } finally { while (DB::transactionLevel()) DB::rollBack(); }
    exit;
}

config(['database.connections.camp_admin' => [...$connection, 'database' => 'mysql', 'url' => null]]);
$admin = DB::connection('camp_admin');
$admin->statement('CREATE DATABASE `'.$database.'`');
$process = null; $pipes = [];
try {
    Schema::create('events', function ($t) { $t->id(); $t->string('name'); $t->date('start_date'); $t->date('end_date'); $t->integer('deadline')->nullable(); $t->boolean('published'); $t->boolean('signUp'); $t->unsignedBigInteger('eventType')->default(1); });
    Schema::create('eventtypes', function ($t) { $t->id(); $t->string('name'); $t->integer('type'); });
    DB::table('eventtypes')->insert(['id' => 1, 'name' => 'Camp', 'type' => 3]);
    Schema::create('registrations', fn ($t) => $t->id());
    Schema::create('player_registrations', function ($t) { $t->id(); $t->unsignedBigInteger('player_id'); $t->unsignedBigInteger('registration_id'); });
    Schema::create('category_event_registrations', function ($t) { $t->id(); $t->unsignedBigInteger('category_event_id'); $t->unsignedBigInteger('registration_id'); $t->integer('payment_status_id'); $t->string('status'); $t->timestamp('withdrawn_at')->nullable(); $t->softDeletes(); });
    Schema::create('categories', function ($t) { $t->id(); $t->string('name'); });
    Schema::create('category_events', function ($t) { $t->id(); $t->unsignedBigInteger('event_id'); $t->unsignedBigInteger('category_id'); });
    Schema::create('players', function ($t) { $t->id(); $t->string('name'); $t->string('surname'); $t->date('dateOfBirth'); $t->integer('gender'); });
    (require dirname(__DIR__, 2).'/database/migrations/2026_11_20_160000_create_camp_workflows.php')->up();
    DB::table('events')->insert(['id' => 1, 'name' => 'Fictional concurrency camp', 'start_date' => '2027-01-07', 'end_date' => '2027-01-09', 'published' => 1, 'signUp' => 1]);
    DB::table('categories')->insert(['id' => 1, 'name' => 'U9']);
    DB::table('category_events')->insert(['id' => 1, 'event_id' => 1, 'category_id' => 1]);
    DB::table('players')->insert(['id' => 1, 'name' => 'Example', 'surname' => 'Child', 'dateOfBirth' => '2018-01-01', 'gender' => 1]);
    DB::table('camp_configurations')->insert(['event_id' => 1, 'method' => 'invitation_only', 'coach_closes_at' => now()->addDays(20),
        'divisions' => json_encode([1 => ['min_age' => 9, 'max_age' => 10, 'gender' => 0]]), 'revision' => '11111111-1111-4111-a111-111111111111', 'updated_by' => 1]);
    DB::table('camp_nominees')->insert(['id' => 1, 'event_id' => 1, 'category_event_id' => 1, 'player_id' => 1, 'status' => 'approved', 'approved_by' => 1, 'approved_at' => now(), 'token_hash' => str_repeat('b', 64), 'token_encrypted' => 'unused']);
    DB::table('camp_coach_links')->insert(['id' => 1, 'event_id' => 1, 'coach_name' => 'Example Coach', 'token_hash' => hash('sha256', str_repeat('a', 64)), 'expires_at' => now()->addDays(10), 'created_by' => 1]);
    config(['database.connections.camp_observer' => [...$connection, 'database' => $database, 'url' => null]]);
    foreach (['nominee', 'coach', 'admin', 'player'] as $mode) {
        if ($mode === 'admin') DB::table('camp_nominees')->where('id', 1)->update(['status' => 'approved']);
        $directory = storage_path('framework/testing/camp-current-read');
        if (! is_dir($directory)) mkdir($directory, 0777, true);
        $out = $directory.'/'.$mode.'.out'; $err = $directory.'/'.$mode.'.err';
        file_put_contents($out, ''); file_put_contents($err, '');
        DB::beginTransaction();
        if ($mode !== 'coach') {
            Event::whereKey(1)->lockForUpdate()->firstOrFail();
            if ($mode === 'nominee') DB::table('camp_nominees')->where('id', 1)->update(['status' => 'revoked']);
            elseif ($mode === 'admin') {
                $registration = DB::table('registrations')->insertGetId([]);
                DB::table('player_registrations')->insert(['player_id' => 1, 'registration_id' => $registration]);
                DB::table('category_event_registrations')->insert(['category_event_id' => 1, 'registration_id' => $registration, 'payment_status_id' => 1, 'status' => 'active']);
            } else DB::table('players')->where('id', 1)->update(['dateOfBirth' => '2010-01-01']);
        } else {
            CampCoachLink::whereKey(1)->lockForUpdate()->firstOrFail();
            DB::table('camp_coach_links')->where('id', 1)->update(['revoked_at' => now()]);
        }
        $process = proc_open([PHP_BINARY, __FILE__, 'worker'], [['pipe', 'r'], ['file', $out, 'a'], ['file', $err, 'a']], $pipes, dirname(__DIR__, 2));
        if (! is_resource($process)) throw new RuntimeException('Worker failed to start.');
        fwrite($pipes[0], json_encode(['mode' => $mode, 'database' => $database])); fclose($pipes[0]);
        $connectionId = null; $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline && ! $connectionId) {
            foreach (explode("\n", file_get_contents($out)) as $line) $connectionId ??= json_decode($line, true)['connection_id'] ?? null;
            if (! proc_get_status($process)['running']) break;
            usleep(20000);
        }
        if (! $connectionId) throw new RuntimeException('No worker connection: '.file_get_contents($err));
        $waiting = false; $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $wait = DB::connection('camp_observer')->selectOne('SELECT COUNT(*) AS total FROM performance_schema.data_lock_waits AS w JOIN performance_schema.threads AS t ON t.THREAD_ID = w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID = ?', [$connectionId]);
            if ((int) $wait->total) { $waiting = true; break; }
            if (! proc_get_status($process)['running']) break;
            usleep(20000);
        }
        if (! $waiting) throw new RuntimeException('Expected real InnoDB lock wait: '.file_get_contents($out).file_get_contents($err));
        DB::commit();
        $deadline = microtime(true) + 15;
        while (proc_get_status($process)['running'] && microtime(true) < $deadline) usleep(20000);
        if (proc_get_status($process)['running']) throw new RuntimeException('Worker did not finish.');
        $responses = array_values(array_filter(array_map(fn ($line) => json_decode($line, true), explode("\n", file_get_contents($out)))));
        $last = end($responses);
        if (($last['result'] ?? null) !== 'rejected' || ($mode === 'coach' && ($last['http'] ?? null) !== 410)) throw new RuntimeException('Unexpected worker result: '.file_get_contents($out).file_get_contents($err));
        if ($mode === 'admin' && ! str_contains($last['message'] ?? '', 'already registered')) throw new RuntimeException('Admin duplicate check did not reject the new committed association: '.file_get_contents($out));
        if ($mode === 'player' && ! str_contains($last['message'] ?? '', 'age or gender')) throw new RuntimeException('Admission did not reject the corrected player identity: '.file_get_contents($out));
        proc_close($process); $process = null;
        $change = match ($mode) { 'admin' => 'duplicate association', 'player' => 'date-of-birth correction', default => 'revocation' };
        echo $mode.': PASS — real lock wait, committed '.$change.' rejected despite prior snapshot'."\n";
    }
    if (DB::table('camp_coach_submissions')->count() !== 0) throw new RuntimeException('Revoked coach link created a submission.');
} finally {
    if (is_resource($process)) { if (proc_get_status($process)['running']) proc_terminate($process); proc_close($process); }
    while (DB::transactionLevel()) DB::rollBack();
    DB::disconnect('mysql'); DB::disconnect('camp_observer');
    $admin->statement('DROP DATABASE `'.$database.'`');
}
