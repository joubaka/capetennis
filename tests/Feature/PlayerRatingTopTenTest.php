<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingAccess, PlayerRatingTopTen};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PlayerRatingTopTenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('players', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('surname'); $table->string('dateOfBirth')->nullable(); $table->string('gender')->nullable(); });
        Schema::create('series', function (Blueprint $table) { $table->id(); $table->boolean('leaderboard_published'); $table->integer('minimum_events_for_team_selection')->default(1); });
        Schema::create('series_rankings', function (Blueprint $table) {
            $table->id(); $table->integer('series_id'); $table->integer('player_id'); $table->string('status'); $table->string('run_id')->nullable();
            $table->text('meta_json')->nullable(); $table->timestamp('published_at')->nullable(); $table->timestamps();
        });
        DB::table('series')->insert(['id' => 1, 'leaderboard_published' => true, 'minimum_events_for_team_selection' => 1]);
    }

    private function player(int $id, string $name = 'Player', int $series = 1, string $run = 'current', string $status = 'published', int $events = 1): void
    {
        DB::table('players')->insertOrIgnore(['id' => $id, 'name' => $name, 'surname' => 'Example']);
        DB::table('series_rankings')->insert(['series_id' => $series, 'player_id' => $id, 'run_id' => $run, 'status' => $status,
            'meta_json' => json_encode(['events_played' => $events]), 'published_at' => $run === 'old' ? '2026-10-01' : '2026-11-01', 'updated_at' => '2026-11-01']);
    }

    private function snapshot(array $scores): array
    {
        $ratings = [];
        foreach ($scores as $id => $score) $ratings[$id] = ['component' => 'shared', 'comparable' => true, 'score' => $score, 'confidence_label' => 'Moderate'];
        $graph = ['reference_component' => 'shared', 'components' => ['shared' => ['converged' => true, 'members' => array_keys($scores)]], 'ratings' => $ratings];
        return ['reason' => null, 'snapshot_as_of' => '2026-11-01', 'snapshot_stale' => false, 'all_age' => $graph, 'ball_ratings' => array_fill_keys(['red', 'orange', 'standard'], $graph)];
    }

    private function build(array $snapshot, bool $allowed = true, ?\App\Models\Player $selected = null, array $filters = []): array
    {
        $this->mock(PlayerRatingAccess::class)->shouldReceive('allowsDiscovery')->once()->andReturn($allowed);
        $store = $this->mock(PlayerAbilitySnapshotStore::class);
        if ($allowed) $store->shouldReceive('current')->once()->andReturn($snapshot);
        else $store->shouldNotReceive('current');
        return app(PlayerRatingTopTen::class)->build(new User(), $selected, $filters);
    }

    public function test_selected_player_defaults_to_age_group_and_keeps_overall_summary_when_filters_change(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        foreach (range(1, 13) as $id) $this->player($id);
        DB::table('players')->update(['dateOfBirth' => '2013-12-20', 'gender' => '1']);
        DB::table('players')->where('id', 1)->update(['dateOfBirth' => '2014-01-01', 'gender' => '2']);
        $selected = \App\Models\Player::find(13);
        $snapshot = $this->snapshot(array_combine(range(1, 13), range(99, 87)));
        $result = $this->build($snapshot, selected: $selected);
        $this->assertSame('u13', $result['filters']['rating_age']);
        $this->assertSame('boys', $result['filters']['rating_gender']);
        $this->assertSame(['overall'], array_keys($result['lists']));
        $this->assertSame(12, $result['summary']['age']['player']['position']);
        $this->assertSame(13, $result['summary']['overall']['player']['position']);
        $this->assertSame(range(2, 11), array_column($result['lists']['overall']['players'], 'id'));
        $filtered = $this->build($snapshot, selected: $selected, filters: ['rating_age' => 'u13', 'rating_gender' => 'girls', 'rating_level' => 'red']);
        $this->assertSame([1], array_column($filtered['lists']['red']['players'], 'id'));
        $this->assertSame($result['summary'], $filtered['summary']);
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $filtered, 'ratingPlayer' => $selected,
            'ratingHistory' => ['cohort' => 'overall', 'month' => 11, 'scope' => 'new', 'scale' => 'focused']])->render();
        $this->assertStringContainsString('Rating position #13 of 13', $html);
        $this->assertStringContainsString('name="rating_age"', $html);
        $this->assertStringContainsString('Overall CTR', $html);
        $this->assertStringContainsString('value="overall"', $html);
        $this->assertStringNotContainsString('value="standard"', $html);
    }

    public function test_three_independent_top_tens_use_current_public_participants_and_live_revocation(): void
    {
        $this->player(1); $this->player(2, 'Hidden', run: 'old'); $this->player(3, 'Draft', status: 'draft');
        DB::table('series')->insert(['id' => 2, 'leaderboard_published' => false, 'minimum_events_for_team_selection' => 1]);
        $this->player(4, 'Private', series: 2);
        DB::table('series')->insert(['id' => 3, 'leaderboard_published' => true, 'minimum_events_for_team_selection' => 3]);
        $this->player(5, 'Ineligible', series: 3, events: 2);
        $snapshot = $this->snapshot([1 => 65, 2 => 99, 3 => 98, 4 => 97, 5 => 96, 6 => 95]);
        $result = $this->build($snapshot);
        $this->assertSame(['red', 'orange', 'overall'], array_keys($result['lists']));
        foreach ($result['lists'] as $list) $this->assertSame([1], array_column($list['players'], 'id'));
        DB::table('series')->where('id', 1)->update(['leaderboard_published' => false]);
        foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([], $list['players']);
    }

    public function test_discovery_denial_and_invalidated_snapshot_fail_closed(): void
    {
        $this->assertFalse($this->build([], false)['visible']);
        foreach ([[], ['reason' => 'Source publication changed']] as $snapshot) {
            foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([], $list['players']);
        }
    }

    public function test_scores_stay_independent_and_disconnected_nonconverged_invalid_scores_are_excluded(): void
    {
        foreach (range(1, 7) as $id) $this->player($id);
        $snapshot = $this->snapshot([1 => 0, 2 => 100, 3 => -1, 4 => 101, 5 => INF, 6 => 90, 7 => 80]);
        $snapshot['ball_ratings']['red']['ratings'][6]['comparable'] = false;
        $snapshot['ball_ratings']['red']['ratings'][7]['component'] = 'local';
        $snapshot['ball_ratings']['orange']['components']['shared']['converged'] = false;
        $snapshot['all_age']['ratings'][1]['score'] = 99;
        $result = $this->build($snapshot);
        $this->assertSame([2, 1], array_column($result['lists']['red']['players'], 'id'));
        $this->assertSame([], $result['lists']['orange']['players']);
        $this->assertSame([2, 1, 6, 7], array_column($result['lists']['overall']['players'], 'id'));
    }

    public function test_age_positions_and_filters_use_the_same_overall_scores_including_legacy_links(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        foreach (range(1, 3) as $id) $this->player($id);
        DB::table('players')->whereIn('id', [1, 2])->update(['dateOfBirth' => '2017-01-01', 'gender' => '1']);
        DB::table('players')->where('id', 3)->update(['dateOfBirth' => '2013-01-01', 'gender' => '2']);
        $selected = \App\Models\Player::find(1);
        $snapshot = $this->snapshot([1 => 62.3, 2 => 60, 3 => 80]);
        $snapshot['ball_ratings']['standard']['ratings'][1]['score'] = 67.9;

        $result = $this->build($snapshot, selected: $selected);
        $this->assertSame('overall', $result['filters']['rating_level']);
        $this->assertSame(62.3, $result['summary']['age']['player']['score']);
        $this->assertSame(62.3, $result['summary']['overall']['player']['score']);
        $this->assertSame(1, $result['summary']['age']['player']['position']);
        $this->assertSame(2, $result['summary']['overall']['player']['position']);
        $this->assertSame([1, 2], array_column($result['lists']['overall']['players'], 'id'));

        $all = $this->build($snapshot, selected: $selected, filters: ['rating_level' => 'overall', 'rating_age' => '', 'rating_gender' => '']);
        $this->assertSame([3, 1, 2], array_column($all['lists']['overall']['players'], 'id'));
        $this->assertSame(62.3, $all['lists']['overall']['players'][1]['score']);
        $this->assertSame($result['summary'], $all['summary']);
        $legacy = $this->build($snapshot, selected: $selected, filters: ['rating_level' => 'standard']);
        $this->assertSame('overall', $legacy['filters']['rating_level']);
        $this->assertSame($result['lists'], $legacy['lists']);
    }

    public function test_gender_defaults_match_player_profile_and_explicit_filters_are_preserved(): void
    {
        foreach (['1' => 'boys', ' Male ' => 'boys', '2' => 'girls', 'F' => 'girls', '' => 'unknown'] as $raw => $expected) {
            $selected = (new \App\Models\Player())->forceFill(['id' => 1, 'gender' => $raw]);
            $result = $this->build([], selected: $selected);
            $this->assertSame($expected, $result['filters']['rating_gender']);
        }

        $this->player(1);
        $this->player(2);
        DB::table('players')->update(['dateOfBirth' => '2017-01-01']);
        DB::table('players')->where('id', 1)->update(['gender' => '2']);
        DB::table('players')->where('id', 2)->update(['gender' => '1']);
        $selected = \App\Models\Player::find(1);
        $snapshot = $this->snapshot([1 => 60, 2 => 70]);
        $default = $this->build($snapshot, selected: $selected);
        $this->assertSame('girls', $default['filters']['rating_gender']);
        $this->assertSame([1], array_column($default['lists']['overall']['players'], 'id'));
        $all = $this->build($snapshot, selected: $selected, filters: ['rating_gender' => '']);
        $this->assertSame('', $all['filters']['rating_gender']);
        $this->assertSame([2, 1], array_column($all['lists']['overall']['players'], 'id'));
        $boys = $this->build($snapshot, selected: $selected, filters: ['rating_gender' => 'boys']);
        $this->assertSame([2], array_column($boys['lists']['overall']['players'], 'id'));
        $this->assertSame($default['summary'], $all['summary']);

        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $default, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertMatchesRegularExpression('/value="girls"\\s+selected/', $html);
    }

    public function test_tied_displayed_scores_share_positions_and_lists_are_bounded_to_ten(): void
    {
        $scores = [];
        foreach (range(1, 12) as $id) { $this->player($id, sprintf('Player %02d', $id)); $scores[$id] = $id <= 2 ? 90.04 : 80; }
        $result = $this->build($this->snapshot($scores));
        foreach ($result['lists'] as $list) {
            $this->assertCount(10, $list['players']);
            $this->assertSame([1, 1, 3, 3, 3, 3, 3, 3, 3, 3], array_column($list['players'], 'position'));
            $this->assertSame(range(1, 10), array_column($list['players'], 'id'));
        }
    }
}
