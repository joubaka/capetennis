<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, Registration, TeamFixture, User};
use App\Services\Draw\IndividualScoreRevision;
use App\Services\TeamFixtureScoreService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Real InnoDB lock waits; never uses the application or shared test database. */
class ScoreRevisionMySqlConcurrencyTest extends TestCase
{
    private static bool $schemaPrepared = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') $this->markTestSkipped('Requires isolated MySQL contention configuration.');
        $expected = getenv('CT_STALE_SAVE_MYSQL_DATABASE');
        $this->assertIsString($expected);
        $this->assertMatchesRegularExpression('/^ct_testing_stale_save_[a-z0-9_]+$/D', $expected);
        $this->assertSame('testing', app()->environment());
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame($expected, config('database.connections.mysql.database'));
        $this->assertSame($expected, DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame('array', config('mail.default'));
        foreach (config('mail.mailers', []) as $mailer) $this->assertSame('array', $mailer['transport']);
        // Each case creates its own event/draw. Avoid repeating expensive DDL on
        // the disposable database; no outer test transaction may hide its commits.
        if (!self::$schemaPrepared) {
            $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
            self::$schemaPrepared = true;
        }
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->assertSame('REPEATABLE-READ', DB::selectOne('SELECT @@transaction_isolation AS level')->level);
        foreach (['events', 'draws', 'draw_settings', 'fixtures', 'fixture_results', 'team_fixtures', 'team_fixture_results'] as $table) {
            $engine = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$expected, $table]);
            $this->assertSame('InnoDB', $engine?->engine, "$table must provide real row locks for this regression.");
        }
        config(['database.connections.score_observer' => config('database.connections.mysql')]);
    }

    public static function competingClients(): array
    {
        $cases = [];
        foreach (['backend', 'api', 'team_http', 'team_service', 'team_outer'] as $client) {
            foreach (['save', 'delete'] as $operation) $cases["$client $operation"] = [$client, $operation];
        }
        return $cases;
    }

    #[DataProvider('competingClients')]
    public function test_waiting_old_score_edit_rechecks_committed_content(string $client, string $operation): void
    {
        $team = str_starts_with($client, 'team_');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        if ($team) DB::table('eventtypes')->updateOrInsert(['id' => 990], ['name' => 'Concurrency team event', 'type' => \App\Models\EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $team ? 990 : 6, 'result_notifications_enabled' => false]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => false, 'published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin', 'score_format' => 'one_full_set', 'num_sets' => 1]);
        $actor = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        if ($team) {
            $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
                'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0]);
            $save = route('frontend.fixtures.score.store', $fixture);
            $token = fn () => app(TeamFixtureScoreService::class)->revision($fixture->fresh());
            $score = fn (int $away) => ['set1_home' => 6, 'set1_away' => $away, 'score_revision' => $token()];
        } else {
            $players = Registration::factory()->count(2)->create();
            $group = $draw->groups()->create(['name' => 'A']);
            $group->registrations()->sync($players->modelKeys());
            $draw->registrations()->sync($players->modelKeys());
            $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
                'stage' => 'RR', 'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id,
                'match_status' => 0, 'winner_registration' => null]);
            $save = route('backend.roundrobin.score.store', $fixture);
            $token = fn () => app(IndividualScoreRevision::class)->forFixture($fixture->fresh());
            $score = fn (int $away) => ['sets' => ["6-$away"], 'score_revision' => $token()];
        }
        // Both edits keep winner, participants and completed status unchanged.
        $this->postJson($save, $score(1))->assertOk();
        $oldRevision = $token();
        $process = null;
        $pipes = [];
        try {
            DB::beginTransaction();
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $this->postJson($save, $score(2))->assertOk();
            $committedResult = $this->snapshot($draw);
            // Windows anonymous proc pipes do not support nonblocking reads.
            // Poll dedicated files so a waiting worker cannot block the observer.
            $ipcDirectory = storage_path('framework/testing/mysql-score-ipc');
            if (!is_dir($ipcDirectory)) mkdir($ipcDirectory, 0755, true);
            $ipcName = bin2hex(random_bytes(8));
            $outputPath = $ipcDirectory.'/'.$ipcName.'.out';
            $errorPath = $ipcDirectory.'/'.$ipcName.'.err';
            file_put_contents($outputPath, '');
            file_put_contents($errorPath, '');
            $errors = fn () => file_get_contents($errorPath);
            $process = proc_open([PHP_BINARY, base_path('tests/Support/score-revision-mysql-worker.php')],
                [['pipe', 'r'], ['file', $outputPath, 'a'], ['file', $errorPath, 'a']], $pipes, base_path());
            $this->assertIsResource($process);
            fwrite($pipes[0], json_encode(['actor_id' => $actor->id, 'draw_id' => $draw->id, 'fixture_id' => $fixture->id,
                'client' => $client, 'operation' => $operation, 'revision' => $oldRevision], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $output = '';
            $connectionId = null;
            $deadline = microtime(true) + 15;
            while (microtime(true) < $deadline && $connectionId === null) {
                $output = file_get_contents($outputPath);
                foreach (explode("\n", $output) as $line) $connectionId ??= json_decode($line, true)['connection_id'] ?? null;
                if (!proc_get_status($process)['running']) break;
                usleep(20000);
            }
            $this->assertNotNull($connectionId, 'Worker did not connect: '.$errors());
            $waiting = false;
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline) {
                $wait = DB::connection('score_observer')->selectOne(
                    'SELECT COUNT(*) AS total FROM performance_schema.data_lock_waits AS w '
                    .'JOIN performance_schema.threads AS t ON t.THREAD_ID = w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID = ?',
                    [$connectionId]);
                if ((int) $wait->total > 0) { $waiting = true; break; }
                if (!proc_get_status($process)['running']) break;
                usleep(20000);
            }
            $this->assertTrue($waiting, 'Expected a real InnoDB lock wait before commit. Worker: '.$output.$errors());
            DB::commit();
            $deadline = microtime(true) + 15;
            do {
                $output = file_get_contents($outputPath);
                $status = proc_get_status($process);
                if (!$status['running']) break;
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertFalse($status['running'], 'Worker did not finish after the lock was released.');
            $output = file_get_contents($outputPath);
            $responses = array_values(array_filter(array_map(fn ($line) => json_decode($line, true), explode("\n", $output))));
            $response = end($responses);
            $this->assertSame(409, $response['status'] ?? null, 'Stale worker response: '.$output.$errors());
            $this->assertStringContainsString('This match or its scoring rules changed', $response['message'] ?? '');
            $this->assertSame($committedResult, $this->snapshot($draw), 'Rejected stale edit changed the accepted result or history.');
        } finally {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            while (DB::transactionLevel() > 0) DB::rollBack();
            DB::disconnect('score_observer');
        }
    }

    private function snapshot(Draw $draw): array
    {
        return [
            DB::table('fixtures')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson(),
            DB::table('fixture_results')->whereIn('fixture_id', $draw->drawFixtures()->select('id'))->orderBy('id')->get()->toJson(),
            DB::table('team_fixtures')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson(),
            DB::table('team_fixture_results')->whereIn('team_fixture_id', $draw->fixtures()->select('id'))->orderBy('id')->get()->toJson(),
            DB::table('draw_audit_logs')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson(),
        ];
    }
}
