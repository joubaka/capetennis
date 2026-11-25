<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingAccess, PlayerRatingTopTen};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PlayerRatingTopTenTest extends TestCase
{
    public function test_set_based_public_history_preserves_exact_event_division_membership_and_both_fixture_sides(): void
    {
        foreach (range(1, 14) as $id) $this->player($id, 'Historical player '.$id);
        DB::table('fixtures')->delete();
        DB::table('draws')->update(['published' => false]);
        DB::table('events')->where('id', 1)->update(['results_published' => true]);
        DB::table('events')->insert(['id' => 2, 'published' => false, 'results_published' => true]);
        DB::table('category_events')->insert(['id' => 20, 'event_id' => 2, 'category_id' => 1]);
        foreach ([1, 2, 7, 8, 10, 11, 13] as $id) DB::table('draws')->where('id', $id)->update(['published' => true]);
        DB::table('draws')->where('id', 7)->update(['category_event_id' => 20]);
        DB::table('draws')->where('id', 10)->update(['event_id' => 2, 'category_event_id' => 20]);
        DB::table('draws')->where('id', 13)->update(['category_event_id' => null]);
        foreach ([1, 7, 8, 9, 10, 11, 13] as $id) DB::table('fixtures')->insert(['draw_id' => $id, 'registration1_id' => $id]);
        DB::table('fixtures')->insert(['draw_id' => 2, 'registration2_id' => 2]);
        DB::table('player_registrations')->insert(['registration_id' => 1, 'player_id' => 3]);
        DB::table('category_event_registrations')->where('registration_id', 8)->update(['deleted_at' => '2026-11-01']);
        DB::table('category_event_registrations')->where('registration_id', 12)->update(['category_event_id' => 20]);
        DB::table('category_event_registrations')->whereIn('registration_id', [1, 14])
            ->update(['status' => 'withdrawn_pending_refund', 'withdrawn_at' => '2026-11-01']);
        DB::table('registrations')->where('id', 11)->delete();
        foreach ([[4, 1, 1], [5, 2, 1], [6, 1, 999], [12, 1, 1], [14, 1, 1]] as [$registration, $event, $category]) {
            DB::table('category_results')->insert(['registration_id' => $registration, 'event_id' => $event, 'category_id' => $category]);
        }
        $scores = array_combine(range(1, 14), range(99, 86));
        $snapshot = $this->snapshot($scores);
        $result = $this->build($snapshot, selected: \App\Models\Player::findOrFail(1), filters: ['rating_age' => '', 'rating_gender' => '']);
        $actual = array_column($result['lists']['overall']['players'], 'id');
        $this->assertSame([1, 2, 3, 4, 13, 14], $actual);
        $this->assertSame($this->legacyPublicIndividualIds(array_keys($scores)), $actual, 'The rewritten set projection preserves the former public-history audience.');
        $this->assertDatabaseCount('players', 14);
        $this->assertDatabaseCount('category_event_registrations', 14);
        $this->assertDatabaseHas('category_event_registrations', ['registration_id' => 1, 'payment_status_id' => 0, 'status' => 'withdrawn_pending_refund']);
        DB::table('events')->where('id', 1)->update(['results_published' => false]);
        $afterResultsRevocation = $this->build($snapshot, selected: \App\Models\Player::findOrFail(1), filters: ['rating_age' => '', 'rating_gender' => '']);
        $this->assertSame([1, 2, 3, 13], array_column($afterResultsRevocation['lists']['overall']['players'], 'id'));
        DB::table('events')->where('id', 1)->update(['published' => false]);
        $afterEventRevocation = $this->build($snapshot, selected: \App\Models\Player::findOrFail(1), filters: ['rating_age' => '', 'rating_gender' => '']);
        $this->assertSame([], $afterEventRevocation['lists']['overall']['players']);
    }

    private function legacyPublicIndividualIds(array $ids): array
    {
        $visible = fn ($event) => $event->visibleTo(null);
        $draw = fn ($query) => $query->where('published', true)->whereHas('event', $visible)
            ->whereHas('event.eventCategories', fn ($field) => $field
                ->where(fn ($category) => $category->whereNull('draws.category_event_id')->orWhereColumn('category_events.id', 'draws.category_event_id'))
                ->whereHas('categoryEventRegistrations', fn ($entry) => $entry->whereColumn('registration_id', 'registrations.id')));
        $results = \App\Models\CategoryResult::query()->select('registration_id')->whereHas('event', fn ($event) => $event
            ->visibleTo(null)->where('results_published', true)->whereHas('eventCategories', fn ($field) => $field
                ->whereColumn('category_events.category_id', 'category_results.category_id')
                ->whereHas('categoryEventRegistrations', fn ($entry) => $entry->whereColumn('registration_id', 'category_results.registration_id'))));
        return \App\Models\Player::query()->whereIntegerInRaw('id', $ids)->whereHas('registrations', fn ($registrations) => $registrations
            ->where(fn ($entry) => $entry->whereIn('registrations.id', $results)->orWhereHas('fixturesAsOne.draw', $draw)->orWhereHas('fixturesAsTwo.draw', $draw)))
            ->orderBy('id')->pluck('id')->all();
    }

    public function test_legacy_text_fixture_registration_ids_keep_the_existing_comparison_semantics(): void
    {
        foreach (range(1, 4) as $id) $this->player($id, 'Legacy player '.$id);
        Schema::drop('fixtures');
        Schema::create('fixtures', function (Blueprint $table) {
            $table->id(); $table->integer('draw_id');
            $table->string('registration1_id')->nullable(); $table->string('registration2_id')->nullable();
        });
        DB::table('fixtures')->insert([
            ['draw_id' => 1, 'registration1_id' => '001', 'registration2_id' => '2'],
            ['draw_id' => 3, 'registration1_id' => 'not-registration', 'registration2_id' => null],
            ['draw_id' => 4, 'registration1_id' => '999', 'registration2_id' => null],
        ]);
        $result = $this->build($this->snapshot([1 => 90, 2 => 80, 3 => 70, 4 => 60]), selected: \App\Models\Player::findOrFail(1));
        $actual = array_column($result['lists']['overall']['players'], 'id');
        $this->assertSame([1, 2], $actual);
        $this->assertSame($this->legacyPublicIndividualIds([1, 2, 3, 4]), $actual);
    }

    public function test_selected_admin_page_checks_only_requested_pool_and_green_yellow_summary_candidates(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-20'));
        foreach (range(1, 3) as $id) $this->player($id, 'Example '.$id);
        DB::table('players')->update(['dateOfBirth' => '2015-01-01', 'gender' => 'Male']);
        $selected = \App\Models\Player::findOrFail(1);
        $snapshot = $this->snapshot([1 => 80]);
        $snapshot['ball_ratings']['red'] = $this->snapshot([2 => 90])['ball_ratings']['red'];
        $snapshot['ball_ratings']['orange'] = $this->snapshot([3 => 95])['ball_ratings']['orange'];
        DB::enableQueryLog();
        $ctr = $this->build($snapshot, selected: $selected);
        $queries = DB::getQueryLog();
        DB::flushQueryLog();
        $red = $this->build($snapshot, selected: $selected, filters: ['rating_level' => 'red']);
        $redQueries = DB::getQueryLog();
        DB::disableQueryLog();
        $publicationQuery = fn ($queries) => collect($queries)->first(fn ($query) => str_contains($query['query'], ' union ')
            && str_contains($query['query'], '"player_registrations" as "public_members"'))['query'] ?? '';
        $this->assertStringContainsString('"public_members"."player_id" in (1)', $publicationQuery($queries));
        $this->assertMatchesRegularExpression('/"public_members"\."player_id" in \((?:1, 2|2, 1)\)/', $publicationQuery($redQueries));
        $this->assertSame(['overall'], array_keys($ctr['lists']));
        $this->assertSame([1], array_column($ctr['lists']['overall']['players'], 'id'));
        $this->assertSame(['red'], array_keys($red['lists']));
        $this->assertSame([2], array_column($red['lists']['red']['players'], 'id'));
        $this->assertSame($ctr['summary'], $red['summary'], 'Green/Yellow age and overall summaries remain identical while the requested Red list changes.');
    }

    public function test_member_discovery_checks_publication_only_for_its_age_and_gender_candidates(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-16'));
        foreach (range(1, 5) as $id) $this->player($id, 'Player'.$id, published: $id !== 3);
        DB::table('players')->whereIn('id', [1, 3])->update(['dateOfBirth' => '2015-01-01', 'gender' => 'Male']);
        DB::table('players')->where('id', 2)->update(['dateOfBirth' => '2016-01-01', 'gender' => 'Male']);
        DB::table('players')->where('id', 4)->update(['dateOfBirth' => '2014-01-01', 'gender' => 'Male']);
        DB::table('players')->where('id', 5)->update(['dateOfBirth' => '2015-01-01', 'gender' => 'Female']);
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->andReturnTrue();
        $access->shouldReceive('allows')->andReturnTrue();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')
            ->andReturn($this->snapshot([1 => 80, 2 => 90, 3 => 99, 4 => 98, 5 => 97]));
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $selected = \App\Models\Player::findOrFail(1);
        DB::enableQueryLog();
        $result = app(PlayerRatingTopTen::class)->build($user, $selected,
            ['rating_age' => 'u18', 'rating_gender' => 'girls', 'rating_level' => 'red']);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([2, 1], array_column($result['lists']['overall']['players'], 'id'));
        $this->assertSame(2, $result['summary']['age']['total']);
        $this->assertSame(2, $result['summary']['age']['player']['position']);
        $publicationQuery = collect($queries)->first(fn ($query) => str_contains($query['query'], ' union ')
            && str_contains($query['query'], '"player_registrations" as "public_members"'));
        $this->assertNotNull($publicationQuery);
        $this->assertStringContainsString('"public_members"."player_id" in (1, 2, 3)', $publicationQuery['query']);
        $this->assertStringNotContainsString('"public_members"."player_id" in (1, 2, 3, 4, 5)', $publicationQuery['query']);
        // A later publication revocation still takes effect on the next read.
        DB::table('draws')->where('id', 2)->update(['published' => false]);
        $after = app(PlayerRatingTopTen::class)->build($user, $selected);
        $this->assertSame([1], array_column($after['lists']['overall']['players'], 'id'));
        $this->assertSame(1, $after['summary']['age']['total']);
    }

    public function test_ctr_and_match_comparisons_ignore_a_legacy_combined_graph_and_keep_ball_lists_separate(): void
    {
        foreach (range(1, 4) as $id) $this->player($id);
        $snapshot = $this->snapshot([1 => 80, 2 => 60]);
        $snapshot['all_age'] = $this->snapshot([3 => 99, 4 => 98])['all_age'];
        $snapshot['ball_ratings']['red'] = $this->snapshot([3 => 90])['all_age'];
        $snapshot['ball_ratings']['orange'] = $this->snapshot([4 => 95])['all_age'];
        $result = $this->build($snapshot);
        $this->assertSame([1, 2], array_column($result['lists']['overall']['players'], 'id'));
        $this->assertSame('CTR', $result['lists']['overall']['label']);
        $this->assertSame([3], array_column($result['lists']['red']['players'], 'id'));
        $this->assertSame([4], array_column($result['lists']['orange']['players'], 'id'));
    }

    public function test_missing_green_yellow_pool_never_falls_back_to_combined_or_orange_scores(): void
    {
        $this->player(1);
        $snapshot = $this->snapshot([1 => 99]);
        unset($snapshot['ball_ratings']['standard']);
        $result = $this->build($snapshot);
        $this->assertSame([], $result['lists']['overall']['players']);
        $this->assertSame([1], array_column($result['lists']['orange']['players'], 'id'));
    }

    public function test_context_preserves_member_scope_without_loading_snapshots_or_participants(): void
    {
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->once()->andReturnTrue();
        $access->shouldReceive('allows')->once()->andReturnTrue();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $player = new \App\Models\Player(['dateOfBirth' => '2014-02-01', 'gender' => 'Male']);
        DB::enableQueryLog();
        $result = app(PlayerRatingTopTen::class)->context($user, $player,
            ['rating_age' => 'u18', 'rating_gender' => 'female', 'rating_level' => 'red', 'rating_per_page' => 500]);
        $this->assertTrue($result['visible']);
        $this->assertFalse($result['can_browse']);
        $this->assertSame($result['selected_age'], $result['filters']['rating_age']);
        $this->assertSame('overall', $result['filters']['rating_level']);
        $this->assertSame(10, $result['filters']['rating_per_page']);
        $this->assertSame([], $result['lists']);
        $this->assertSame([], DB::getQueryLog());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('players', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('surname'); $table->string('dateOfBirth')->nullable(); $table->string('gender')->nullable(); });
        Schema::create('events', function (Blueprint $table) { $table->id(); $table->boolean('published'); $table->boolean('results_published')->default(false); $table->integer('eventType')->default(1); });
        Schema::create('eventtypes', function (Blueprint $table) { $table->id(); $table->string('name'); $table->string('code')->nullable(); $table->string('type')->nullable(); });
        Schema::create('registrations', function (Blueprint $table) { $table->id(); });
        Schema::create('player_registrations', function (Blueprint $table) { $table->integer('registration_id'); $table->integer('player_id'); $table->timestamps(); });
        Schema::create('category_events', function (Blueprint $table) { $table->id(); $table->integer('event_id'); $table->integer('category_id'); });
        Schema::create('category_event_registrations', function (Blueprint $table) {
            $table->id(); $table->integer('category_event_id'); $table->integer('registration_id');
            $table->integer('payment_status_id')->default(1); $table->string('status')->default('active'); $table->timestamp('withdrawn_at')->nullable(); $table->softDeletes();
        });
        Schema::create('category_results', function (Blueprint $table) { $table->id(); $table->integer('event_id'); $table->integer('category_id'); $table->integer('registration_id'); });
        Schema::create('draws', function (Blueprint $table) { $table->id(); $table->integer('event_id'); $table->integer('category_event_id')->nullable(); $table->boolean('published'); });
        Schema::create('fixtures', function (Blueprint $table) { $table->id(); $table->integer('draw_id'); $table->integer('registration1_id')->nullable(); $table->integer('registration2_id')->nullable(); });
        Schema::create('team_fixtures', function (Blueprint $table) { $table->id(); $table->integer('draw_id'); $table->integer('team_tie_id')->nullable(); });
        Schema::create('team_ties', function (Blueprint $table) { $table->id(); $table->integer('draw_id'); $table->timestamp('published_at')->nullable(); $table->string('status')->nullable(); });
        Schema::create('team_fixture_players', function (Blueprint $table) { $table->id(); $table->integer('team_fixture_id'); $table->integer('team1_id')->nullable(); $table->integer('team2_id')->nullable(); });
        DB::table('events')->insert(['id' => 1, 'published' => true]);
        DB::table('eventtypes')->insert(['id' => 1, 'name' => 'Individual']);
    }

    private function player(int $id, string $name = 'Player', int $event = 1, bool $published = true): void
    {
        DB::table('players')->insertOrIgnore(['id' => $id, 'name' => $name, 'surname' => 'Example']);
        DB::table('registrations')->insert(['id' => $id]);
        DB::table('player_registrations')->insert(['registration_id' => $id, 'player_id' => $id]);
        DB::table('category_events')->insertOrIgnore(['id' => $event, 'event_id' => $event, 'category_id' => 1]);
        DB::table('category_event_registrations')->insert(['category_event_id' => $event, 'registration_id' => $id, 'payment_status_id' => 0]);
        DB::table('draws')->insert(['id' => $id, 'event_id' => $event, 'category_event_id' => $event, 'published' => $published]);
        DB::table('fixtures')->insert(['draw_id' => $id, 'registration1_id' => $id]);
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
        foreach (range(1, 5) as $id) $this->player($id, 'Opponent '.$id, published: $id !== 4);
        $selected = \App\Models\Player::find(1);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->twice()->andReturnTrue();
        $access->shouldReceive('allows')->twice()->with($user, $selected)->andReturn(true, false);
        $snapshot = $this->snapshot([1 => 80, 2 => 75, 3 => 70, 4 => 65, 5 => 60]);
        $snapshot['ball_ratings']['standard']['ratings'][3]['comparable'] = false;
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
        foreach (range(1, 4) as $id) {
            $this->player($id, 'Private peer '.$id);
            DB::table('players')->where('id', $id)->update(['dateOfBirth' => '2015-01-01', 'gender' => '1']);
        }
        $selected = \App\Models\Player::find(2);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->once()->with($user)->andReturnTrue();
        $access->shouldReceive('allows')->once()->with($user, $selected)->andReturnTrue();
        $snapshot = $this->snapshot([1 => 90, 2 => 80, 3 => 70]);
        // This public age peer has Orange-only evidence and an obsolete combined
        // score. Neither is eligible for this member's Green/Yellow Top 10.
        $snapshot['all_age'] = $this->snapshot([4 => 99])['all_age'];
        $snapshot['ball_ratings']['orange'] = $this->snapshot([4 => 95])['all_age'];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn($snapshot);
        $result = app(PlayerRatingTopTen::class)->build($user, $selected, ['rating_page' => 2, 'rating_gender' => 'girls']);
        $this->assertFalse($result['can_browse']);
        $this->assertSame([1, 2, 3], array_column($result['lists']['overall']['players'], 'id'));
        $this->assertSame('boys', $result['filters']['rating_gender']);
        $this->assertArrayNotHasKey('pagination', $result['lists']['overall']);
        $this->assertSame(['age'], array_keys($result['summary']));
        $this->assertSame(2, $result['summary']['age']['player']['id']);
        $this->assertSame(2, $result['summary']['age']['player']['position']);
        $this->assertSame(3, $result['summary']['age']['total']);
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $result, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertStringContainsString('Rating position #2 of 3', strip_tags($html));
        $this->assertStringContainsString('Your age-group Top 10', $html);
        foreach (['Explore ratings', 'rating_per_page', 'name="rating_age"', 'name="rating_gender"', 'Next players'] as $hidden) $this->assertStringNotContainsString($hidden, $html);
    }

    public function test_plus_confidence_labels_are_visible_without_changing_scores_positions_or_scope(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01'));
        foreach (range(1, 3) as $id) {
            $this->player($id, 'Confidence peer '.$id);
            DB::table('players')->where('id', $id)->update(['dateOfBirth' => '2015-01-01', 'gender' => '1']);
        }
        $selected = \App\Models\Player::find(2);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->once()->andReturnTrue();
        $access->shouldReceive('allows')->once()->with($user, $selected)->andReturnTrue();
        $snapshot = $this->snapshot([1 => 90, 2 => 80, 3 => 70]);
        foreach ([1 => 'Low', 2 => 'Medium', 3 => 'High'] as $id => $label) $snapshot['ball_ratings']['standard']['ratings'][$id]['confidence_label'] = $label;
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn($snapshot);
        $result = app(PlayerRatingTopTen::class)->build($user, $selected);
        $this->assertFalse($result['can_browse']);
        $this->assertSame([1, 2, 3], array_column($result['lists']['overall']['players'], 'position'));
        $this->assertSame([90.0, 80.0, 70.0], array_column($result['lists']['overall']['players'], 'score'));
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $result, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $dom = new \DOMDocument; @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
        $labels = $xpath->query('//*[@data-plus-age-top-ten]//*[@data-rating-confidence]');
        $this->assertSame(['Low confidence', 'Medium confidence', 'High confidence'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($labels)));
        $this->assertSame('Medium confidence', trim($xpath->query('//*[@data-rating-position-confidence]')->item(0)->textContent));
        $this->assertStringContainsString('Rating position #2 of 3', strip_tags($html));
        foreach (['Explore ratings', 'name="rating_age"', 'name="rating_gender"', 'Next players'] as $hidden) $this->assertStringNotContainsString($hidden, $html);
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
            $this->player($id, sprintf('Peer %02d', $id), published: $id !== 18);
            DB::table('players')->where('id', $id)->update(['dateOfBirth' => $id === 16 ? '2014-01-01' : ($id === 17 ? '2016-01-01' : '2015-01-01'), 'gender' => $id === 15 ? '2' : '1']);
        }
        $selected = \App\Models\Player::find(14);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allowsDiscovery')->times(4)->andReturnTrue();
        $access->shouldReceive('allows')->times(4)->with($user, $selected)->andReturnTrue();
        $snapshot = $this->snapshot(array_combine(range(1, 18), range(90, 73)));
        $snapshot['ball_ratings']['standard']['ratings'][1]['comparable'] = false;
        $snapshot['ball_ratings']['standard']['ratings'][15]['score'] = 100;
        $snapshot['ball_ratings']['standard']['ratings'][17]['score'] = 99;
        $snapshot['ball_ratings']['standard']['ratings'][11]['score'] = $snapshot['ball_ratings']['standard']['ratings'][10]['score'];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->times(4)->andReturn($snapshot);
        $normal = app(PlayerRatingTopTen::class)->build($user, $selected);
        $tampered = app(PlayerRatingTopTen::class)->build($user, $selected, ['rating_age' => '', 'rating_gender' => 'girls', 'rating_level' => 'red', 'rating_page' => 200, 'rating_per_page' => 500]);
        $this->assertSame($normal['lists'], $tampered['lists']);
        $this->assertSame($normal['summary'], $tampered['summary']);
        $this->assertSame(['overall'], array_keys($tampered['lists']));
        $this->assertSame(['rating_age' => 'u11', 'rating_gender' => 'boys', 'rating_level' => 'overall', 'rating_per_page' => 10], $tampered['filters']);
        $this->assertSame([17, 2, 3, 4, 5, 6, 7, 8, 9, 10], array_column($tampered['lists']['overall']['players'], 'id'));
        $this->assertCount(10, $tampered['lists']['overall']['players']);
        $this->assertSame(14, $tampered['summary']['age']['player']['id']);
        $this->assertSame(14, $tampered['summary']['age']['player']['position']);
        $this->assertSame(14, $tampered['summary']['age']['total']);
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $tampered, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertStringContainsString('Rating position #14 of 14', strip_tags($html));
        $this->assertStringNotContainsString('Peer 15', $html);
        DB::table('events')->where('id', 1)->update(['published' => false]);
        $revoked = app(PlayerRatingTopTen::class)->build($user, $selected);
        $this->assertSame([], $revoked['lists']['overall']['players']);
        DB::table('events')->where('id', 1)->update(['published' => true]);
        DB::table('players')->where('id', $selected->id)->update(['dateOfBirth' => null]);
        $selected->dateOfBirth = null;
        $unknown = app(PlayerRatingTopTen::class)->build($user, $selected);
        $this->assertSame('unknown', $unknown['selected_age']);
        $this->assertSame([], $unknown['lists']['overall']['players']);
    }

    public function test_selected_player_rank_cards_follow_gender_filter_without_changing_scores(): void
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
        $this->assertSame(12, $result['summary']['overall']['player']['position']);
        $this->assertSame($result['summary']['age']['player'], collect($result['lists']['overall']['players'])->firstWhere('id', 13));
        $all = $this->build($snapshot, selected: $selected, filters: ['rating_gender' => '']);
        $this->assertSame(13, $all['summary']['age']['player']['position']);
        $this->assertSame(13, $all['summary']['overall']['total']);
        $this->assertSame($result['summary']['age']['player']['score'], $all['summary']['age']['player']['score']);
        $boysHtml = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $result, 'ratingPlayer' => $selected, 'ratingHistory' => []])->render();
        $this->assertStringContainsString('CTR · all ages · Boys', $boysHtml);
        $this->assertSame(range(2, 13), array_column($result['lists']['overall']['players'], 'id'));
        $filtered = $this->build($snapshot, selected: $selected, filters: ['rating_age' => 'u13', 'rating_gender' => 'girls', 'rating_level' => 'red']);
        $this->assertSame([1], array_column($filtered['lists']['red']['players'], 'id'));
        foreach (['age', 'overall'] as $scope) {
            $this->assertNull($filtered['summary'][$scope]['player']);
            $this->assertSame(1, $filtered['summary'][$scope]['total']);
        }
        $html = view('frontend.my-tennis.rating-top-ten', ['ratingTopTen' => $filtered, 'ratingPlayer' => $selected,
            'ratingHistory' => ['cohort' => 'overall', 'month' => 11, 'scope' => 'new', 'scale' => 'focused']])->render();
        $this->assertStringContainsString('No comparable position yet.', strip_tags($html));
        $this->assertStringContainsString('name="rating_age"', $html);
        $this->assertStringContainsString('CTR', $html);
        $this->assertStringContainsString('value="overall"', $html);
        $this->assertStringNotContainsString('value="standard"', $html);
    }

    public function test_public_event_participation_qualifies_without_any_series_and_is_rechecked(): void
    {
        $this->player(1);
        $this->player(2, 'Unpublished draw', published: false);
        $this->player(3, 'Unpublished event', event: 2);
        DB::table('events')->insert(['id' => 2, 'published' => false]);
        DB::table('players')->insert(['id' => 4, 'name' => 'External only', 'surname' => 'Example']);
        DB::table('category_event_registrations')->update(['payment_status_id' => 0]);
        $snapshot = $this->snapshot([1 => 65, 2 => 99, 3 => 98, 4 => 97]);
        $result = $this->build($snapshot);
        foreach ($result['lists'] as $list) $this->assertSame([1], array_column($list['players'], 'id'));
        DB::table('events')->where('id', 1)->update(['published' => false]);
        foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([], $list['players']);
    }
    public function test_discovery_denial_and_invalidated_snapshot_fail_closed(): void
    {
        $this->assertFalse($this->build([], false)['visible']);
        foreach ([[], ['reason' => 'Source publication changed']] as $snapshot) {
            foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([], $list['players']);
        }
    }

    public function test_public_active_entries_qualify_before_draws_and_only_expose_the_public_member(): void
    {
        foreach (range(1, 8) as $id) $this->player($id, 'Participant '.$id, published: false);
        DB::table('fixtures')->delete();
        DB::table('category_event_registrations')->update(['payment_status_id' => 1]);
        DB::table('category_event_registrations')->where('registration_id', 2)->update(['payment_status_id' => 0]);
        DB::table('category_event_registrations')->where('registration_id', 3)->update(['status' => 'withdrawn_pending_refund']);
        DB::table('category_event_registrations')->where('registration_id', 4)->update(['withdrawn_at' => '2026-11-01']);
        DB::table('category_event_registrations')->where('registration_id', 5)->update(['deleted_at' => '2026-11-01']);
        DB::table('category_event_registrations')->where('registration_id', 6)->update(['status' => 'pending_checkout']);
        DB::table('category_event_registrations')->where('registration_id', 8)->delete();
        DB::table('player_registrations')->insert(['registration_id' => 1, 'player_id' => 8]);
        DB::table('eventtypes')->insert(['id' => 6, 'name' => 'Interprovincial Trials', 'code' => 'interprovincial-trials']);
        DB::table('events')->insert(['id' => 2, 'published' => true, 'eventType' => 6]);
        DB::table('category_events')->insert(['id' => 2, 'event_id' => 2, 'category_id' => 1]);
        DB::table('category_event_registrations')->where('registration_id', 7)->update(['category_event_id' => 2]);
        $snapshot = $this->snapshot(array_fill_keys(range(1, 8), 50));
        foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([1], array_column($list['players'], 'id'));
        DB::table('events')->where('id', 1)->update(['published' => false]);
        foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([], $list['players']);
    }

    public function test_public_doubles_and_results_qualify_without_series_and_enforce_event_membership(): void
    {
        foreach (range(1, 5) as $id) $this->player($id, 'Participant '.$id);
        DB::table('fixtures')->delete();
        DB::table('category_event_registrations')->where('registration_id', 2)->delete();
        DB::table('player_registrations')->insert(['registration_id' => 1, 'player_id' => 2]);
        DB::table('fixtures')->insert(['draw_id' => 1, 'registration2_id' => 1]);
        DB::table('events')->where('id', 1)->update(['results_published' => true]);
        DB::table('category_results')->insert([
            ['event_id' => 1, 'category_id' => 1, 'registration_id' => 3],
            ['event_id' => 1, 'category_id' => 1, 'registration_id' => 4],
            ['event_id' => 1, 'category_id' => 2, 'registration_id' => 5],
        ]);
        DB::table('events')->insert(['id' => 2, 'published' => true]);
        DB::table('category_events')->insert(['id' => 2, 'event_id' => 2, 'category_id' => 2]);
        DB::table('category_event_registrations')->whereIn('registration_id', [4, 5])->update(['category_event_id' => 2]);
        DB::table('fixtures')->insert([
            ['draw_id' => 1, 'registration1_id' => 4],
            ['draw_id' => 1, 'registration1_id' => 5],
        ]);
        $snapshot = $this->snapshot([1 => 90, 2 => 80, 3 => 70, 4 => 60, 5 => 50]);
        foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([1, 2, 3], array_column($list['players'], 'id'));
        DB::table('events')->where('id', 1)->update(['results_published' => false]);
        foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([1, 2], array_column($list['players'], 'id'));
        DB::table('draws')->where('id', 1)->update(['published' => false]);
        foreach ($this->build($snapshot)['lists'] as $list) $this->assertSame([], $list['players']);
    }

    public function test_public_entry_scan_overflow_withholds_the_whole_comparison(): void
    {
        $this->player(1, published: false);
        DB::table('category_event_registrations')->delete();
        foreach (array_chunk(array_fill(0, 5001, ['registration_id' => 1, 'category_event_id' => 1, 'payment_status_id' => 1]), 500) as $chunk) {
            DB::table('category_event_registrations')->insert($chunk);
        }
        $result = $this->build($this->snapshot([1 => 70]));
        $this->assertNotNull($result['reason']);
        foreach ($result['lists'] as $list) $this->assertSame([], $list['players']);
    }

    public function test_scores_stay_independent_and_disconnected_nonconverged_invalid_scores_are_excluded(): void
    {
        foreach (range(1, 7) as $id) $this->player($id);
        $snapshot = $this->snapshot([1 => 0, 2 => 100, 3 => -1, 4 => 101, 5 => INF, 6 => 90, 7 => 80]);
        $snapshot['ball_ratings']['red']['ratings'][6]['comparable'] = false;
        $snapshot['ball_ratings']['red']['ratings'][7]['component'] = 'local';
        $snapshot['ball_ratings']['orange']['components']['shared']['converged'] = false;
        $snapshot['ball_ratings']['standard']['ratings'][1]['score'] = 99;
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
        $this->assertSame(67.9, $result['summary']['age']['player']['score']);
        $this->assertSame(67.9, $result['summary']['overall']['player']['score']);
        $this->assertSame(1, $result['summary']['age']['player']['position']);
        $this->assertSame(1, $result['summary']['overall']['player']['position']);
        $this->assertSame([1, 2], array_column($result['lists']['overall']['players'], 'id'));

        $all = $this->build($snapshot, selected: $selected, filters: ['rating_level' => 'overall', 'rating_age' => '', 'rating_gender' => '']);
        $this->assertSame([3, 1, 2], array_column($all['lists']['overall']['players'], 'id'));
        $this->assertSame(67.9, $all['lists']['overall']['players'][1]['score']);
        $this->assertSame($result['summary']['age'], $all['summary']['age']);
        $this->assertSame(2, $all['summary']['overall']['player']['position']);
        $this->assertSame(3, $all['summary']['overall']['total']);
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
        foreach (['age', 'overall'] as $scope) {
            $this->assertSame(1, $default['summary'][$scope]['player']['position']);
            $this->assertSame(1, $default['summary'][$scope]['total']);
            $this->assertSame(2, $all['summary'][$scope]['player']['position']);
            $this->assertSame(2, $all['summary'][$scope]['total']);
            $this->assertNull($boys['summary'][$scope]['player']);
            $this->assertSame(1, $boys['summary'][$scope]['total']);
        }

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

        DB::table('events')->where('id', 1)->update(['published' => false]);
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
