<?php

namespace Tests\Feature\TeamDraw;

use App\Exceptions\TeamScoreBusyException;
use App\Models\Draw;
use App\Models\Event;
use App\Models\TeamFixture;
use App\Services\TeamFixtureScoreService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamScoreTransactionBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exercise a real top-level transaction on a disposable connection.
        config(['database.connections.score_boundary' => array_replace(config('database.connections.sqlite'), [
            'database' => ':memory:',
            'url' => null,
        ])]);
        DB::setDefaultConnection('score_boundary');
        $this->artisan('migrate:fresh', ['--database' => 'score_boundary'])->assertExitCode(0);
    }

    public static function failurePhases(): array
    {
        return [
            'contention before commit' => [true, false, true, 0],
            'unrelated query failure' => [false, false, false, 0],
            'contention after commit' => [true, true, false, 1],
        ];
    }

    #[DataProvider('failurePhases')]
    public function test_only_rolled_back_contention_signals_safe_retry(bool $contention, bool $afterCommit, bool $busy, int $resultCount): void
    {
        $draw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'team_scoring_rules' => null]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1, 'round_nr' => 1, 'fixture_type' => 1]);
        $pdoError = new PDOException($contention ? 'Lock wait timeout exceeded; try restarting transaction' : 'Unrelated query failure');
        $pdoError->errorInfo = $contention ? ['HY000', 1205] : ['42S22', 1054];
        $failure = new QueryException('test', 'score test query', [], $pdoError);

        DB::listen(function (QueryExecuted $query) use ($failure, $afterCommit): void {
            if (!str_starts_with($query->sql, 'insert into') || !str_contains($query->sql, 'team_fixture_results')) {
                return;
            }
            if (!$afterCommit) {
                throw $failure;
            }
            DB::afterCommit(static function () use ($failure): void {
                throw $failure;
            });
        });

        $this->assertSame(0, DB::transactionLevel());
        try {
            app(TeamFixtureScoreService::class)->save($fixture, ['set1_home' => 6, 'set1_away' => 3]);
            $this->fail('The injected query error should propagate.');
        } catch (TeamScoreBusyException|QueryException $exception) {
            $this->assertSame($busy, $exception instanceof TeamScoreBusyException);
            $this->assertSame($failure, $busy ? $exception->getPrevious() : $exception);
        }

        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($resultCount, $fixture->teamResults()->count());
    }
}
