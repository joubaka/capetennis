<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Services\RankingTieDecisionService;
use App\Domain\Ranking\Services\RankingPublicationService;
use App\Domain\Ranking\Services\WilsonU9IdentitySnapshotService;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WilsonU9IdentitySnapshotServiceTest extends TestCase
{
    use RefreshDatabase { refreshTestDatabase as private refreshDatabaseUsingTrait; }

    private User $actor;
    private string $sqlitePath;
    private array $publishedBefore;

    protected function refreshTestDatabase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ct-ranking-clone-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create isolated ranking test database.');
        }
        $this->sqlitePath = $path;
        RefreshDatabaseState::$migrated = false;
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $path);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->refreshDatabaseUsingTrait();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->sqlitePath) && is_file($this->sqlitePath)) {
            @unlink($this->sqlitePath);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super-user', 'web');
        $this->actor = User::factory()->create()->assignRole('super-user');
        User::factory()->create(['id' => 2340]);
        User::factory()->create(['id' => 4025]);
        Player::query()->forceCreate(['id' => 2439, 'name' => 'Dirk', 'surname' => 'Coetzee', 'dateOfBirth' => '2010-03-25', 'userId' => 2340]);
        Player::query()->forceCreate(['id' => 5332, 'name' => 'Dirkie', 'surname' => 'Coetzee', 'dateOfBirth' => '2017-09-09', 'userId' => 4025]);
        $partner = Player::factory()->create();
        Series::query()->forceCreate(['id' => 18, 'name' => 'Wilson Series 2026', 'year' => 2026]);
        $category = Category::query()->forceCreate(['id' => 131, 'name' => 'Boys U/9']);
        Category::query()->forceCreate(['id' => 132, 'name' => 'Girls U/9']);
        RankingList::query()->forceCreate(['id' => 938, 'series_id' => 18, 'category_id' => 131]);
        RankingList::query()->forceCreate(['id' => 939, 'series_id' => 18, 'category_id' => 132]);
        $event = Event::factory()->create(['id' => 254]);
        CategoryEvent::query()->forceCreate(['id' => 2182, 'event_id' => 254, 'category_id' => 131, 'entry_fee' => 0]);
        MastersInvitationBatch::query()->create([
            'id' => 4, 'event_id' => 254, 'series_id' => 18, 'ranking_run_id' => 'published-run',
            'created_by' => $this->actor->id, 'top_x' => 8, 'status' => 'sent',
            'response_deadline' => now()->addDay(), 'payment_deadline' => now()->addDays(2),
            'replacement_payment_deadline' => now()->addDays(3),
        ]);
        MastersInvitation::query()->create([
            'id' => 807, 'batch_id' => 4, 'event_id' => 254, 'category_event_id' => 2182,
            'ranking_list_id' => 938, 'ranking_category_id' => 131, 'player_id' => 2439,
            'ranking_position' => 8, 'queue_position' => 8, 'total_points' => 80,
            'status' => 'declined',
        ]);
        foreach ([18445 => 9435, 20410 => 11283] as $id => $itemId) {
            Registration::query()->forceCreate(['id' => $id]);
            DB::table('player_registrations')->insert(['registration_id' => $id, 'player_id' => 5332, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('registration_order_items')->insert([
                'id' => $itemId, 'order_id' => $itemId, 'registration_id' => $id,
                'player_id' => 5332, 'category_event_id' => 2182, 'item_price' => 285,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $players = collect([2439, $partner->id])->sort()->values()->all();
        $tieKey = hash('sha256', '938:80:'.implode(',', $players));
        $decision = [
            'tie_key' => $tieKey, 'ranking_list_id' => 938, 'total_points' => 80,
            'player_ids' => $players, 'confirmed_order' => [2439, $partner->id],
            'reason' => 'previous_ranking', 'note' => null,
            'confirmed_by' => $this->actor->id, 'confirmed_at' => now()->toIso8601String(),
        ];
        $headToHead = [
            'ranking_list_id' => 938, 'fixture_id' => 7001, 'player_ids' => $players,
            'winner_player_id' => 2439, 'phase' => 'playoff',
            'qualifying_set' => ['score' => '6-4'], 'confirmed_by' => $this->actor->id,
            'confirmed_at' => now()->toIso8601String(),
        ];
        foreach ([
            [938, 131, 2439, 8, 80, ['events_played' => 2, 'tie_decision' => $decision, 'head_to_head_decision' => $headToHead]],
            [938, 131, $partner->id, 9, 80, ['events_played' => 3, 'tie_decision' => $decision, 'head_to_head_decision' => $headToHead]],
            [939, 132, Player::factory()->create()->id, 1, 200, ['events_played' => 4, 'marker' => 'unchanged']],
        ] as [$list, $cat, $player, $rank, $points, $meta]) {
            SeriesRanking::query()->create([
                'series_id' => 18, 'ranking_list_id' => $list, 'category_id' => $cat,
                'player_id' => $player, 'rank_position' => $rank, 'total_points' => $points,
                'meta_json' => $meta, 'status' => 'published', 'run_id' => 'published-run',
                'published_by' => $this->actor->id, 'published_at' => now()->subDay(),
            ]);
        }
        SeriesRanking::query()->create([
            'series_id' => 18, 'ranking_list_id' => 938, 'category_id' => 131,
            'player_id' => $partner->id, 'rank_position' => 1, 'total_points' => 999,
            'meta_json' => ['rebuilt' => true], 'status' => 'calculated', 'run_id' => 'bad-current-run',
        ]);
        DB::table('ranking_tie_decisions')->insert([
            'series_id' => 18, 'run_id' => 'published-run', 'ranking_list_id' => 938,
            'tie_key' => $tieKey, 'total_points' => 80,
            'player_ids' => json_encode($players), 'ordered_player_ids' => json_encode([2439, $partner->id]),
            'reason' => 'previous_ranking', 'note' => null, 'fixture_id' => null,
            'confirmed_by' => $this->actor->id, 'confirmed_at' => now(),
            'decision_snapshot' => json_encode($decision), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ranking_head_to_head_confirmations')->insert([
            'series_id' => 18, 'run_id' => 'published-run', 'ranking_list_id' => 938,
            'fixture_id' => 7001, 'player1_id' => $players[0], 'player2_id' => $players[1],
            'winner_player_id' => 2439, 'confirmed_by' => $this->actor->id, 'confirmed_at' => now(),
            'decision_snapshot' => json_encode($headToHead), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ranking_tie_decisions')->insert([
            'series_id' => 18, 'run_id' => 'bad-current-run', 'ranking_list_id' => 938,
            'tie_key' => str_repeat('a', 64), 'total_points' => 999,
            'player_ids' => '[]', 'ordered_player_ids' => '[]', 'reason' => 'other', 'note' => 'stale',
            'fixture_id' => null, 'confirmed_by' => $this->actor->id, 'confirmed_at' => now(),
            'decision_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ranking_head_to_head_confirmations')->insert([
            'series_id' => 18, 'run_id' => 'bad-current-run', 'ranking_list_id' => 938,
            'fixture_id' => 7999, 'player1_id' => 2439, 'player2_id' => $partner->id,
            'winner_player_id' => 2439, 'confirmed_by' => $this->actor->id, 'confirmed_at' => now(),
            'decision_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->publishedBefore = SeriesRanking::query()->where('status', 'published')->orderBy('id')->get()->map->getAttributes()->all();
    }

    public function test_it_clones_exact_published_snapshot_with_only_identity_and_run_state_changed(): void
    {
        $registrationLinksBefore = DB::table('player_registrations')->whereIn('registration_id', [18445, 20410])->orderBy('registration_id')->get()->map(fn ($row) => (array) $row)->all();
        $orderItemsBefore = DB::table('registration_order_items')->whereIn('id', [9435, 11283])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $runId = app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);

        $calculated = SeriesRanking::query()->where('run_id', $runId)->orderBy('rank_position')->get();
        $this->assertCount(3, $calculated);
        $this->assertDatabaseHas('series_rankings', ['run_id' => $runId, 'player_id' => 5332, 'rank_position' => 8, 'total_points' => 80, 'status' => 'calculated']);
        $this->assertDatabaseMissing('series_rankings', ['run_id' => $runId, 'player_id' => 2439]);
        $this->assertDatabaseMissing('series_rankings', ['run_id' => 'bad-current-run']);
        $this->assertDatabaseMissing('ranking_tie_decisions', ['run_id' => 'bad-current-run']);
        $this->assertDatabaseMissing('ranking_head_to_head_confirmations', ['run_id' => 'bad-current-run']);
        $this->assertSame('unchanged', $calculated->firstWhere('ranking_list_id', 939)->meta_json['marker']);
        foreach (SeriesRanking::query()->where('status', 'published')->where('run_id', 'published-run')->get() as $published) {
            $expectedPlayer = (int) $published->player_id === 2439 ? 5332 : (int) $published->player_id;
            $clone = $calculated->first(fn (SeriesRanking $row): bool =>
                (int) $row->ranking_list_id === (int) $published->ranking_list_id
                && (int) $row->category_id === (int) $published->category_id
                && (int) $row->player_id === $expectedPlayer
                && (int) $row->rank_position === (int) $published->rank_position
                && (int) $row->total_points === (int) $published->total_points
            );
            $this->assertNotNull($clone, 'Every published row must have one position/points-identical clone.');
            $this->assertSame(
                $this->normalizeIdentityMeta($published->meta_json),
                $clone->meta_json,
                'Only the audited player identity and derived tie key may change in metadata.'
            );
        }
        $this->assertSame($this->publishedBefore, SeriesRanking::query()->where('status', 'published')->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($registrationLinksBefore, DB::table('player_registrations')->whereIn('registration_id', [18445, 20410])->orderBy('registration_id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame($orderItemsBefore, DB::table('registration_order_items')->whereIn('id', [9435, 11283])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertDatabaseHas('masters_invitations', ['id' => 807, 'player_id' => 2439, 'status' => 'declined', 'registration_id' => null, 'order_id' => null]);
        $this->assertDatabaseHas('ranking_audit_logs', ['series_id' => 18, 'run_id' => $runId, 'action' => 'clone_published_snapshot_for_player_identity_correction', 'user_id' => $this->actor->id]);
    }

    public function test_it_carries_confirmed_tie_decision_forward_with_mapped_identity(): void
    {
        $runId = app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
        $row = SeriesRanking::query()->where('run_id', $runId)->where('player_id', 5332)->firstOrFail();
        $decision = $row->meta_json['tie_decision'];
        $this->assertContains(5332, $decision['player_ids']);
        $this->assertNotContains(2439, $decision['player_ids']);
        $this->assertDatabaseHas('ranking_tie_decisions', ['series_id' => 18, 'run_id' => $runId, 'tie_key' => $decision['tie_key']]);
        $this->assertDatabaseHas('ranking_head_to_head_confirmations', [
            'series_id' => 18, 'run_id' => $runId, 'ranking_list_id' => 938,
            'fixture_id' => 7001, 'winner_player_id' => 5332,
        ]);
        app(RankingTieDecisionService::class)->assertAllConfirmed(Series::query()->findOrFail(18), $runId);
        app(RankingPublicationService::class)->markReviewed(Series::query()->findOrFail(18), $this->actor->id);
        $this->assertSame(3, SeriesRanking::query()->where('run_id', $runId)->where('status', 'reviewed')->count());
    }

    public function test_it_rejects_replay_and_preserves_first_corrected_run(): void
    {
        $runId = app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
        try {
            app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
            $this->fail('Replay should fail closed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already been created', $exception->getMessage());
        }
        $this->assertSame(3, SeriesRanking::query()->where('run_id', $runId)->count());
    }

    public function test_it_rejects_published_target_collision_without_changing_calculated_run(): void
    {
        $source = SeriesRanking::query()->where('status', 'published')->firstOrFail();
        SeriesRanking::query()->create(array_merge($source->only(['series_id', 'ranking_list_id', 'category_id', 'rank_position', 'total_points', 'meta_json', 'status', 'run_id']), ['player_id' => 5332]));
        $this->expectException(\RuntimeException::class);
        try {
            app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
        } finally {
            $this->assertDatabaseHas('series_rankings', ['run_id' => 'bad-current-run', 'total_points' => 999]);
        }
    }

    public function test_it_rejects_source_rank_or_points_drift_without_changing_calculated_run(): void
    {
        SeriesRanking::query()->where('status', 'published')->where('player_id', 2439)->update(['total_points' => 81]);
        $this->expectException(\RuntimeException::class);
        try {
            app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
        } finally {
            $this->assertDatabaseHas('series_rankings', ['run_id' => 'bad-current-run', 'total_points' => 999]);
            $this->assertDatabaseMissing('ranking_audit_logs', ['action' => 'clone_published_snapshot_for_player_identity_correction']);
        }
    }

    public function test_it_rejects_a_second_source_row_in_another_list(): void
    {
        SeriesRanking::query()->create([
            'series_id' => 18, 'ranking_list_id' => 939, 'category_id' => 132,
            'player_id' => 2439, 'rank_position' => 2, 'total_points' => 150,
            'meta_json' => ['cross_list' => true], 'status' => 'published', 'run_id' => 'published-run',
        ]);
        $this->expectException(\RuntimeException::class);
        try {
            app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
        } finally {
            $this->assertDatabaseHas('series_rankings', ['run_id' => 'bad-current-run', 'total_points' => 999]);
            $this->assertDatabaseHas('series_rankings', ['run_id' => 'published-run', 'ranking_list_id' => 939, 'player_id' => 2439]);
        }
    }

    public function test_it_rejects_tie_audit_drift_without_deleting_current_artifacts(): void
    {
        DB::table('ranking_tie_decisions')->where('run_id', 'published-run')->update(['player_ids' => json_encode([2439])]);
        $this->expectException(\RuntimeException::class);
        try {
            app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
        } finally {
            $this->assertDatabaseHas('ranking_tie_decisions', ['run_id' => 'bad-current-run']);
            $this->assertDatabaseHas('ranking_head_to_head_confirmations', ['run_id' => 'bad-current-run']);
        }
    }

    public function test_it_rejects_head_to_head_audit_drift_without_deleting_current_artifacts(): void
    {
        DB::table('ranking_head_to_head_confirmations')->where('run_id', 'published-run')->update(['winner_player_id' => 999999]);
        $this->expectException(\RuntimeException::class);
        try {
            app(WilsonU9IdentitySnapshotService::class)->replaceCalculatedRun($this->actor, WilsonU9IdentitySnapshotService::CONFIRMATION);
        } finally {
            $this->assertDatabaseHas('ranking_tie_decisions', ['run_id' => 'bad-current-run']);
            $this->assertDatabaseHas('ranking_head_to_head_confirmations', ['run_id' => 'bad-current-run']);
        }
    }

    public function test_command_requires_super_user_and_exact_confirmation(): void
    {
        $ordinary = User::factory()->create();
        $this->artisan('ranking:clone-wilson-for-dirkie', ['--actor' => $ordinary->id, '--confirm' => WilsonU9IdentitySnapshotService::CONFIRMATION])->assertFailed();
        $this->artisan('ranking:clone-wilson-for-dirkie', ['--actor' => $this->actor->id, '--confirm' => 'wrong'])->assertFailed();
        $this->assertDatabaseHas('series_rankings', ['run_id' => 'bad-current-run', 'total_points' => 999]);
    }

    private function normalizeIdentityMeta(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            if ($key === 'tie_key' && isset($value['player_ids']) && in_array(2439, array_map('intval', $value['player_ids']), true)) {
                $players = collect($value['player_ids'])->map(fn ($id): int => (int) $id === 2439 ? 5332 : (int) $id)->sort()->values();
                $value[$key] = hash('sha256', implode(':', [(int) $value['ranking_list_id'], (int) $value['total_points'], $players->implode(',')]));
            } elseif ((str_ends_with((string) $key, 'player_id') || in_array((string) $key, ['player_ids', 'confirmed_order', 'suggested_order'], true))) {
                $value[$key] = $this->replaceTestPlayer($item);
            } else {
                $value[$key] = $this->normalizeIdentityMeta($item);
            }
        }
        return $value;
    }

    private function replaceTestPlayer(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->replaceTestPlayer($item), $value);
        }
        return is_numeric($value) && (int) $value === 2439 ? 5332 : $value;
    }
}
