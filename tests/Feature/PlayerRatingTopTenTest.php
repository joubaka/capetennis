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
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        return app(PlayerRatingTopTen::class)->build($user, $selected, $filters);
    }

    public function test_match_comparisons_only_return_requested_public_comparable_ratings(): void
    {
        foreach (range(1, 5) as $id) $this->player($id, 'Opponent '.$id, status: $id === 4 ? 'draft' : 'published');
        $selected = \App\Models\Player::find(1);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->twice()->andReturnTrue();
        $access->shouldReceive('allows')->twice()->with($user, $selected)->andReturn(true, false);
        $snapshot = $this->snapshot([1 => 80, 2 => 75, 3 => 70, 4 => 65, 5 => 60]);
        $snapshot['all_age']['ratings'][3]['comparable'] = false;
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn($snapshot);
        $service = app(PlayerRatingTopTen::class);
        $ratings = $service->forMatchPlayers($user, $selected, [1, 2, 3, 4]);
        $this->assertSame([1, 2], array_keys($ratings));
        $this->assertSame(['id', 'name', 'score', 'confidence'], array_keys($ratings[2]));
        $this->assertSame(75.0, $ratings[2]['score']);
        $this->assertSame([], $service->forMatchPlayers($user, $selected, [2]));
    }

    public function test_plus_receives_own_positions_and_age_top_ten_without_full_browsing(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        foreach (range(1, 3) as $id) {
            $this->player($id, 'Private peer '.$id);
            DB::table('players')->where('id', $id)->update(['dateOfBirth' => '2015-01-01', 'gender' => '1']);
        }
        $selected = \App\Models\Player::find(2);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->once()->with($user)->andReturnTrue();
        $access->shouldReceive('allows')->once()->with($user, $selected)->andReturnTrue();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn($this->snapshot([1 => 90, 2 => 80, 3 => 70]));
        $result = app(PlayerRatingTopTen::class)->build($user, $selected, ['rating_page' => 2, 'rating_gender' => 'girls']);
        $this->assertFalse($result['can_browse']);
        $this->assertSame([1, 2, 3], array_column($result['lists']['overall']['players'], 'id'));
        $this->assertSame('boys', $result['filters']['rating_gender']);
        $this->assertArrayNotHasKey('pagination', $result['lists']['overall']);
        foreach (['age', 'overall'] as $scope) {
            $this->assertSame(2, $result['summary'][$scope]['player']['id']);
            $this->assertSame(2, $result['summary'][$scope]['player']['position']);
            $this->assertSame(3, $result['summary'][$scope]['total']);
        }
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $result, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertStringContainsString('Rating position #2 of 3', strip_tags($html));
        $this->assertStringContainsString('Your age-group Top 10', $html);
        foreach (['Explore ratings', 'rating_per_page', 'name="rating_age"', 'name="rating_gender"', 'Next players'] as $hidden) $this->assertStringNotContainsString($hidden, $html);
    }

    public function test_discovery_entitlement_cannot_read_an_unlinked_players_position(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $selected = new \App\Models\Player();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->once()->andReturnTrue();
        $access->shouldReceive('allows')->once()->with($user, $selected)->andReturnFalse();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $result = app(PlayerRatingTopTen::class)->build($user, $selected);
        $this->assertFalse($result['visible']);
        $this->assertSame([], $result['summary']);
        $this->assertSame([], $result['lists']);
    }

    public function test_plus_rating_ignores_old_player_search_parameters(): void
    {
        $this->player(1, 'Selected');
        $this->player(2, 'Former search result');
        DB::table('players')->where('id', 1)->update(['dateOfBirth' => '2015-01-01', 'gender' => '1']);
        $selected = \App\Models\Player::findOrFail(1);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->twice()->andReturnTrue();
        $access->shouldReceive('allows')->twice()->with($user, $selected)->andReturnTrue();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->twice()->andReturn($this->snapshot([1 => 50, 2 => 51]));

        $service = app(PlayerRatingTopTen::class);
        $result = $service->build($user, $selected, ['rating_search' => 'Former search result', 'rating_search_player' => 2]);
        $this->assertSame($service->build($user, $selected), $result);
        $this->assertArrayNotHasKey('search', $result);
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $result, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertStringContainsString('data-plus-age-top-ten', $html);
        $this->assertStringNotContainsString('name="rating_search"', $html);
        $this->assertStringNotContainsString('data-rating-search-result', $html);
        $this->assertStringNotContainsString('Former search result', $html);
    }

    public function test_plus_top_ten_is_server_scoped_bounded_and_publication_is_rechecked(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        foreach (range(1, 18) as $id) {
            $this->player($id, sprintf('Peer %02d', $id), status: $id === 18 ? 'draft' : 'published');
            DB::table('players')->where('id', $id)->update(['dateOfBirth' => $id === 16 ? '2014-01-01' : ($id === 17 ? '2016-01-01' : '2015-01-01'), 'gender' => $id === 15 ? '2' : '1']);
        }
        $selected = \App\Models\Player::find(14);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->times(4)->andReturnTrue();
        $access->shouldReceive('allows')->times(4)->with($user, $selected)->andReturnTrue();
        $snapshot = $this->snapshot(array_combine(range(1, 18), range(90, 73)));
        $snapshot['all_age']['ratings'][1]['comparable'] = false;
        $snapshot['all_age']['ratings'][17]['score'] = 99;
        $snapshot['all_age']['ratings'][11]['score'] = $snapshot['all_age']['ratings'][10]['score'];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->times(4)->andReturn($snapshot);
        $normal = app(PlayerRatingTopTen::class)->build($user, $selected);
        $tampered = app(PlayerRatingTopTen::class)->build($user, $selected, ['rating_age' => '', 'rating_gender' => 'girls', 'rating_level' => 'red', 'rating_page' => 200, 'rating_per_page' => 500]);
        $this->assertSame($normal['lists'], $tampered['lists']);
        $this->assertSame(['overall'], array_keys($tampered['lists']));
        $this->assertSame(['rating_age' => 'u11', 'rating_gender' => 'boys', 'rating_level' => 'overall', 'rating_per_page' => 10], $tampered['filters']);
        $this->assertSame([17, 2, 3, 4, 5, 6, 7, 8, 9, 10], array_column($tampered['lists']['overall']['players'], 'id'));
        $this->assertCount(10, $tampered['lists']['overall']['players']);
        DB::table('series')->where('id', 1)->update(['leaderboard_published' => false]);
        $revoked = app(PlayerRatingTopTen::class)->build($user, $selected);
        $this->assertSame([], $revoked['lists']['overall']['players']);
        DB::table('series')->where('id', 1)->update(['leaderboard_published' => true]);
        DB::table('players')->where('id', $selected->id)->update(['dateOfBirth' => null]);
        $selected->dateOfBirth = null;
        $unknown = app(PlayerRatingTopTen::class)->build($user, $selected);
        $this->assertSame('unknown', $unknown['selected_age']);
        $this->assertSame([], $unknown['lists']['overall']['players']);
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
        $this->assertSame(13, $result['summary']['age']['player']['position']);
        $this->assertSame(13, $result['summary']['overall']['player']['position']);
        $this->assertSame(range(2, 13), array_column($result['lists']['overall']['players'], 'id'));
        $filtered = $this->build($snapshot, selected: $selected, filters: ['rating_age' => 'u13', 'rating_gender' => 'girls', 'rating_level' => 'red']);
        $this->assertSame([1], array_column($filtered['lists']['red']['players'], 'id'));
        $this->assertSame($result['summary'], $filtered['summary']);
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $filtered, 'ratingPlayer' => $selected,
            'ratingHistory' => ['cohort' => 'overall', 'month' => 11, 'scope' => 'new', 'scale' => 'focused']])->render();
        $this->assertStringContainsString('Rating position #13 of 13', strip_tags($html));
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

    public function test_red_and_orange_ignore_age_filters_and_hide_age_controls(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        $this->player(1);
        $this->player(2);
        DB::table('players')->update(['gender' => '1']);
        DB::table('players')->where('id', 1)->update(['dateOfBirth' => '2017-01-01']);
        DB::table('players')->where('id', 2)->update(['dateOfBirth' => '2013-01-01']);
        $selected = \App\Models\Player::find(1);
        $snapshot = $this->snapshot([1 => 60, 2 => 70]);

        foreach (['red', 'orange', 'overall'] as $level) {
            $result = $this->build($snapshot, selected: $selected, filters: ['rating_level' => $level, 'rating_age' => 'u9']);
            $this->assertSame($level === 'overall' ? [1] : [2, 1], array_column($result['lists'][$level]['players'], 'id'));
            $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $result, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//*[@data-rating-age-filter]')->length);
            $this->assertSame($level === 'overall' ? 0 : 1, $xpath->query('//*[@data-rating-age-filter and @hidden]')->length);
            if ($level === 'overall') $this->assertStringContainsString('Age group:', $html);
            else $this->assertStringNotContainsString('Age group:', $html);
        }
    }

    public function test_plus_selected_player_can_browse_all_published_ratings_with_global_ties(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        $scores = [];
        foreach (range(1, 52) as $id) {
            $this->player($id, sprintf('Player %02d', $id));
            $scores[$id] = $id === 26 ? 75 : 100 - $id;
        }
        DB::table('players')->update(['dateOfBirth' => '2017-01-01', 'gender' => '1']);
        $selected = \App\Models\Player::find(52);
        $snapshot = $this->snapshot($scores);
        $first = $this->build($snapshot, selected: $selected, filters: ['rating_per_page' => 25]);
        $this->assertSame(range(1, 25), array_column($first['lists']['overall']['players'], 'id'));
        $this->assertSame(['page' => 1, 'pages' => 3, 'total' => 52, 'per_page' => 25], $first['lists']['overall']['pagination']);
        $second = $this->build($snapshot, selected: $selected, filters: ['rating_per_page' => 25, 'rating_page' => 2]);
        $this->assertSame(range(26, 50), array_column($second['lists']['overall']['players'], 'id'));
        $this->assertSame(25, $second['lists']['overall']['players'][0]['position']);
        $this->assertSame(27, $second['lists']['overall']['players'][1]['position']);
        $last = $this->build($snapshot, selected: $selected, filters: ['rating_per_page' => 25, 'rating_page' => 999]);
        $this->assertSame([51, 52], array_column($last['lists']['overall']['players'], 'id'));
        $this->assertSame(52, $last['lists']['overall']['players'][1]['position']);
        $this->assertSame($first['summary'], $last['summary']);

        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $second, 'ratingPlayer' => $selected,
            'ratingHistory' => ['month' => 3, 'scope' => 'all', 'scale' => 'full']])->render();
        $this->assertStringContainsString('Showing 26–50 of 52 players', $html);
        $this->assertStringNotContainsString('Top 10', $html);
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $links = (new \DOMXPath($document))->query('//nav[@aria-label="Rating list pages"]//a[@data-rating-link]');
        $this->assertSame(2, $links->length);
        foreach ($links as $index => $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame(['month' => '3', 'scope' => 'all', 'scale' => 'full', 'rating_age' => 'u9', 'rating_gender' => 'boys', 'rating_level' => 'overall', 'rating_per_page' => '25', 'rating_page' => $index === 0 ? '1' : '3'], $query);
        }

        DB::table('series')->where('id', 1)->update(['leaderboard_published' => false]);
        $revoked = $this->build($snapshot, selected: $selected, filters: ['rating_per_page' => 25, 'rating_page' => 2]);
        $this->assertSame([], $revoked['lists']['overall']['players']);
        $this->assertSame(0, $revoked['lists']['overall']['pagination']['total']);
        $denied = $this->build($snapshot, allowed: false, selected: $selected, filters: ['rating_per_page' => 25, 'rating_page' => 2]);
        $this->assertFalse($denied['visible']);
        $this->assertSame([], $denied['lists']);
    }

    public function test_overall_junior_scopes_include_younger_players_and_special_buckets_stay_exact(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        $birthdays = [1 => '2016-12-31', 2 => '2017-01-01', 3 => '2015-12-31', 4 => '2019-12-31',
            5 => null, 6 => '2026-12-31', 7 => '2008-12-31', 8 => '2007-12-31', 9 => '2018-12-31',
            10 => '2016-01-01', 11 => '2026-02-30'];
        foreach ($birthdays as $id => $birthday) {
            $this->player($id, sprintf('Player %02d', $id));
            DB::table('players')->where('id', $id)->update(['dateOfBirth' => $birthday, 'gender' => '1']);
        }
        $snapshot = $this->snapshot(array_combine(range(1, 11), range(99, 89)));
        $selected = \App\Models\Player::find(1);
        $expected = ['u7' => [4], 'u10' => [1, 2, 4, 9, 10], 'u18' => [1, 2, 3, 4, 7, 9, 10],
            'unknown' => [5, 6, 11], 'other' => [8], '' => range(1, 11)];
        foreach ($expected as $scope => $ids) {
            $result = $this->build($snapshot, selected: $selected, filters: ['rating_age' => $scope]);
            $this->assertSame($ids, array_column($result['lists']['overall']['players'], 'id'), $scope);
            $this->assertSame(count($ids), $result['lists']['overall']['pagination']['total']);
            $this->assertSame(5, $result['summary']['age']['total']);
        }
        foreach (['red', 'orange'] as $level) {
            $result = $this->build($snapshot, selected: $selected, filters: ['rating_age' => 'u10', 'rating_level' => $level]);
            $this->assertSame(range(1, 11), array_column($result['lists'][$level]['players'], 'id'));
        }
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $this->build($snapshot, selected: $selected),
            'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertStringContainsString('U10 and younger · born 2016 or later', $html);
        $this->assertStringContainsString('Red and Orange have separate ratings and include all ages.', $html);
    }

    public function test_younger_scope_is_applied_before_pagination_and_tie_positions(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        $scores = [];
        foreach (range(1, 52) as $id) {
            $this->player($id, sprintf('Player %02d', $id));
            DB::table('players')->where('id', $id)->update(['dateOfBirth' => $id <= 22 ? '2015-12-31' : ($id <= 37 ? '2017-01-01' : '2016-12-31'), 'gender' => '1']);
            $scores[$id] = $id === 48 ? 53 : 100 - $id;
        }
        $selected = \App\Models\Player::find(52);
        $snapshot = $this->snapshot($scores);
        $first = $this->build($snapshot, selected: $selected, filters: ['rating_per_page' => 25]);
        $second = $this->build($snapshot, selected: $selected, filters: ['rating_per_page' => 25, 'rating_page' => 2]);
        $this->assertSame(range(23, 47), array_column($first['lists']['overall']['players'], 'id'));
        $this->assertSame(['page' => 2, 'pages' => 2, 'total' => 30, 'per_page' => 25], $second['lists']['overall']['pagination']);
        $this->assertSame(range(48, 52), array_column($second['lists']['overall']['players'], 'id'));
        $this->assertSame([25, 27, 28, 29, 30], array_column($second['lists']['overall']['players'], 'position'));
        $this->assertSame(30, $first['summary']['age']['total']);
        $this->assertSame(30, $first['summary']['age']['player']['position']);
    }

    public function test_selected_list_defaults_to_500_and_keeps_later_page_ties_and_size_links(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        $scores = [];
        foreach (range(1, 505) as $id) {
            $this->player($id, sprintf('Player %03d', $id));
            $scores[$id] = $id === 501 ? 50 : 100 - $id / 10;
        }
        $selected = \App\Models\Player::find(1);
        $snapshot = $this->snapshot($scores);
        $filters = ['rating_age' => '', 'rating_gender' => ''];
        $first = $this->build($snapshot, selected: $selected, filters: $filters);
        $this->assertCount(500, $first['lists']['overall']['players']);
        $this->assertSame(['page' => 1, 'pages' => 2, 'total' => 505, 'per_page' => 500], $first['lists']['overall']['pagination']);
        $second = $this->build($snapshot, selected: $selected, filters: $filters + ['rating_page' => 2]);
        $this->assertSame(range(501, 505), array_column($second['lists']['overall']['players'], 'id'));
        $this->assertSame([500, 502, 503, 504, 505], array_column($second['lists']['overall']['players'], 'position'));
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $first, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertStringContainsString('name="rating_per_page"', $html);
        $this->assertMatchesRegularExpression('/value="500"\\s+selected/', $html);
        $this->assertStringContainsString('rating_per_page=500', $html);
        $this->assertStringContainsString('Showing 1–500 of 505 players', $html);

        foreach ([0, 501, 1000000, '500.5', []] as $invalid) {
            $fallback = $this->build($snapshot, selected: $selected, filters: array_replace($filters, ['rating_per_page' => $invalid]));
            $this->assertCount(500, $fallback['lists']['overall']['players']);
            $this->assertSame(500, $fallback['filters']['rating_per_page']);
        }
    }

    public function test_rating_endpoint_rejects_unapproved_page_size_before_reading_history(): void
    {
        $this->mock(PlayerRatingAccess::class)->shouldReceive('allows')->once()->andReturnTrue();
        $this->mock(\App\Services\Performance\PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $request = \Illuminate\Http\Request::create('/my-tennis/players/1/rating', 'GET', ['rating_per_page' => 501]);
        $request->setUserResolver(fn () => new User());
        try {
            app(\App\Http\Controllers\Frontend\MyTennisController::class)->rating($request, new \App\Models\Player());
            $this->fail('Unapproved page size was accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('rating_per_page', $exception->errors());
        }
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
