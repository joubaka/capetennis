<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, CategoryResult, Event, Player, Registration, User};
use App\Services\Performance\PlayerPerformancePilotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerPerformancePilotTest extends TestCase
{
    use RefreshDatabase;

    private function field(Category $category, array $positions = [1, 2], array $eventOverrides = [], int $size = 1, ?Event $existingEvent = null): array
    {
        $event = $existingEvent ?? Event::factory()->create(array_merge(['start_date' => '2026-09-01', 'end_date' => '2026-09-02', 'published' => true, 'results_published' => true], $eventOverrides));
        $ce = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $rows = [];
        foreach ($positions as $position) {
            $reg = Registration::factory()->create();
            $players = Player::factory()->count($size)->create();
            $reg->players()->attach($players->pluck('id'));
            $member = CategoryEventRegistration::factory()->create(['category_event_id' => $ce->id, 'registration_id' => $reg->id]);
            $result = CategoryResult::create(['event_id' => $event->id, 'category_id' => $category->id, 'registration_id' => $reg->id, 'position' => $position]);
            $rows[] = compact('reg', 'players', 'member', 'result');
        }
        return compact('event', 'ce', 'rows');
    }

    private function preview(array $tiers, string $discipline = 'singles'): array
    {
        return app(PlayerPerformancePilotService::class)->preview($tiers, $discipline, CarbonImmutable::parse('2026-10-06'), 12);
    }

    public function test_route_is_private_and_read_only_and_renders_preview(): void
    {
        $url = route('backend.player-performance.index');
        $this->get($url)->assertRedirect();
        foreach (['admin', 'convenor', null] as $role) {
            $user = User::factory()->create();
            if ($role) { Role::findOrCreate($role, 'web'); $user->assignRole($role); }
            $this->actingAs($user)->get($url)->assertForbidden();
        }
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $category = Category::factory()->create(['name' => 'Pilot category']);
        $this->field($category);
        $this->get($url)->assertOk()->assertSee('Player performance pilot');
        $this->get($url.'?a_category_id='.$category->id.'&months=&discipline=&as_of=')->assertOk();
        $counts = [CategoryResult::count(), Registration::count(), CategoryEventRegistration::count()];
        $this->get($url.'?a_category_id='.$category->id.'&as_of=2026-10-06')->assertOk()->assertSee('100.0/100')->assertSee('provisional')->assertSee('Pilot category')->assertSee('Qualification is not assessed');
        $this->assertSame($counts, [CategoryResult::count(), Registration::count(), CategoryEventRegistration::count()]);
        $this->post($url)->assertStatus(405);
        $this->getJson($url.'?a_category_id='.$category->id.'&b_category_id='.$category->id)->assertUnprocessable();
        $this->getJson($url.'?months=25&discipline=team&as_of=2099-01-01')->assertUnprocessable();
        $this->getJson($url.'?a_category_id=999999')->assertUnprocessable();
        $this->getJson($url.'?b_category_id='.$category->id)->assertUnprocessable();
    }

    public function test_explicit_bands_and_recency_weighted_average_are_deterministic(): void
    {
        $a = Category::factory()->create(); $b = Category::factory()->create();
        $fa = $this->field($a); $fb = $this->field($b, [1, 2], ['end_date' => '2026-04-05']);
        $shared = $fa['rows'][0]['players']->first();
        $fb['rows'][0]['reg']->players()->sync([$shared->id]);
        $preview = $this->preview([$a->id => 'A', $b->id => 'B']);
        $player = $preview['players']->firstWhere('id', $shared->id);
        $weightA = pow(0.5, 34 / 180); $weightB = pow(0.5, 184 / 180);
        $this->assertSame(round((100*$weightA + 50*$weightB)/($weightA+$weightB), 1), $player['score']);
        $this->assertSame(2, $player['count']);
        $this->assertSame('2026-09-02', $player['last_played']);
        $this->assertSame(50.0, $preview['players']->firstWhere('id', $fa['rows'][1]['players']->first()->id)['score']);
        $this->assertSame(0.0, $preview['players']->firstWhere('id', $fb['rows'][1]['players']->first()->id)['score']);
    }

    public function test_unpublished_outside_window_future_and_other_categories_are_isolated(): void
    {
        $category = Category::factory()->create(); $other = Category::factory()->create();
        $this->field($category);
        foreach ([['published' => false], ['results_published' => false], ['end_date' => '2024-01-01'], ['end_date' => '2026-10-07']] as $overrides) { $this->field($category, [1,2], $overrides); }
        $this->field($other);
        $this->assertCount(2, $this->preview([$category->id => 'A'])['evidence']);
    }

    public function test_incomplete_duplicate_positions_are_excluded_but_extra_unranked_entries_are_allowed(): void
    {
        $category = Category::factory()->create();
        $this->field($category, [1, 3]); $this->field($category, [1, 1]);
        $partial = $this->field($category);
        CategoryEventRegistration::factory()->create(['category_event_id' => $partial['ce']->id]);
        $preview = $this->preview([$category->id => 'A']);
        $this->assertSame(2, $preview['players']->sum('count'));
        $this->assertCount(4, $preview['evidence']->whereNotNull('reason'));
    }

    public function test_latest_correction_is_used_without_inflating_field_size(): void
    {
        $category = Category::factory()->create(); $field = $this->field($category, [3, 2]);
        CategoryResult::create(['event_id' => $field['event']->id, 'category_id' => $category->id, 'registration_id' => $field['rows'][0]['reg']->id, 'position' => 1]);
        $preview = $this->preview([$category->id => 'A']);
        $this->assertCount(2, $preview['evidence']);
        $this->assertSame(2, $preview['players']->sum('count'));
        $this->assertSame(2, $preview['evidence']->first()['field_size']);
    }

    public function test_missing_cross_event_withdrawn_and_duplicate_player_membership_are_excluded(): void
    {
        $category = Category::factory()->create();
        $missing = $this->field($category); $missing['rows'][0]['member']->delete();
        $withdrawn = $this->field($category); $withdrawn['rows'][0]['member']->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $duplicate = $this->field($category); $duplicate['rows'][1]['reg']->players()->sync($duplicate['rows'][0]['players']->pluck('id'));
        $wrong = $this->field($category); $wrong['rows'][0]['member']->update(['category_event_id' => $missing['ce']->id]);
        $this->assertSame(4, $this->preview([$category->id => 'A'])['players']->sum('count'));
    }

    public function test_duplicate_field_definitions_and_team_event_singles_are_excluded(): void
    {
        $category = Category::factory()->create();
        $duplicate = $this->field($category);
        CategoryEvent::factory()->create(['event_id' => $duplicate['event']->id, 'category_id' => $category->id]);
        $type = \Illuminate\Support\Facades\DB::table('eventtypes')->insertGetId(['name' => 'Pilot team', 'type' => \App\Models\EventType::TEAM, 'code' => 'pilot-team']);
        $this->field($category, [1, 2], ['eventType' => $type]);
        $preview = $this->preview([$category->id => 'A']);
        $this->assertSame(0, $preview['players']->sum('count'));
        $this->assertTrue($preview['evidence']->contains(fn ($r) => $r['reason'] === 'Team-event results are excluded from this pilot'));
    }

    public function test_legacy_team_type_fallback_is_excluded(): void
    {
        $category = Category::factory()->create();
        $this->field($category, [1, 2], ['eventType' => 7]);
        $this->assertSame(0, $this->preview([$category->id => 'A'])['players']->sum('count'));
    }

    public function test_empty_fields_and_recent_field_limit_are_disclosed(): void
    {
        $category = Category::factory()->create();
        for ($index = 0; $index < 51; $index++) {
            $this->field($category, []);
        }
        $preview = $this->preview([$category->id => 'A']);
        $this->assertTrue($preview['truncated']);
        $this->assertCount(50, $preview['evidence']);
        $this->assertCount(0, $preview['players']);
        $this->assertSame(50, $preview['summary']['skipped_fields']);
    }

    public function test_players_are_paginated_and_oversized_fields_do_not_score(): void
    {
        $category = Category::factory()->create();
        $field = $this->field($category, range(1, 21));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $url = route('backend.player-performance.index', ['a_category_id' => $category->id, 'as_of' => '2026-10-06']);
        $this->get($url)->assertOk()->assertViewHas('players', fn ($players) => $players->total() === 21 && $players->count() === 20);
        $this->get($url.'&page=2')->assertOk()->assertViewHas('players', fn ($players) => $players->count() === 1);
        // Result-only overflow is enough to reject; never fabricate a full field size.
        for ($index = 22; $index <= 258; $index++) {
            CategoryResult::create(['event_id' => $field['event']->id, 'category_id' => $category->id, 'registration_id' => Registration::factory()->create()->id, 'position' => $index]);
        }
        $preview = $this->preview([$category->id => 'A']);
        $this->assertSame(0, $preview['players']->sum('count'));
        $this->assertCount(257, $preview['evidence']);
        $this->assertTrue($preview['evidence']->first()['field_capped']);
    }

    public function test_singles_and_doubles_are_never_mixed(): void
    {
        $category = Category::factory()->create(); $this->field($category, [1,2], [], 2);
        $this->assertSame(0, $this->preview([$category->id => 'A'])['players']->sum('count'));
        $doubles = $this->preview([$category->id => 'A'], 'doubles');
        $this->assertCount(4, $doubles['players']);
        $this->assertSame(4, $doubles['players']->sum('count'));
    }

    public function test_all_player_directory_search_pagination_and_private_personal_pages(): void
    {
        $player = Player::factory()->create(['name' => 'Jovan', 'surname' => 'Joubert']);
        Player::factory()->count(26)->create();
        foreach (['backend.player-performance.directory', 'backend.player-performance.show'] as $route) {
            auth()->logout();
            $url = route($route, $route === 'backend.player-performance.show' ? $player : []);
            $this->get($url)->assertRedirect();
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        }
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->get(route('backend.player-performance.directory'))->assertOk()->assertViewHas('players', fn ($rows) => $rows->total() === 27 && $rows->count() === 25);
        $this->get(route('backend.player-performance.directory', ['search' => 'Jovan Joubert']))->assertOk()->assertViewHas('players', fn ($rows) => $rows->total() === 1)->assertSee('Jovan Joubert');
        $this->get(route('backend.player-performance.directory', ['search' => '%']))->assertOk()->assertViewHas('players', fn ($rows) => $rows->total() === 0);
        $this->get(route('backend.player-performance.show', $player))->assertOk()->assertSee('Unrated')->assertSee('Jovan Joubert');
        $this->get(route('backend.player-performance.show', 999999))->assertNotFound();
        $this->post(route('backend.player-performance.show', $player))->assertStatus(405);
    }

    public function test_explicit_division_normalization_remains_conservative(): void
    {
        $service = app(PlayerPerformancePilotService::class);
        foreach (['u10 Boys A division', 'u10 Boys A - Division', "u10 Boys A\u{00a0}division", 'u10 Boys A afdeling', 'u10 Boys A'] as $label) {
            $this->assertSame(['tier' => 'A', 'cohort' => 'u10 boys', 'reason' => null], $service->division($label));
        }
        $this->assertSame('B', $service->division('u10 Boys B-Division')['tier']);
        foreach (['u10 Boys', 'Cavaliers u11A', 'u10 A division B division', 'A division'] as $label) {
            $this->assertNotNull($service->division($label)['reason']);
        }
        $this->assertNotSame($service->division('u10 Boys A')['cohort'], $service->division('u12 Boys A')['cohort']);
        $this->assertNotSame($service->division('u10 Boys A')['cohort'], $service->division('u10 Girls A')['cohort']);
    }

    public function test_personal_scores_are_player_scoped_before_field_cap_and_cohorts_are_separate(): void
    {
        $a = Category::factory()->create(['name' => 'u10 Boys A division']);
        $field = $this->field($a);
        $player = $field['rows'][0]['players']->first();
        for ($i = 0; $i < 51; $i++) { $this->field($a, [], ['end_date' => '2026-10-01']); }
        $otherAge = $this->field(Category::factory()->create(['name' => 'u12 Boys A division']), [1,2], ['end_date' => '2026-10-02']);
        $otherAge['rows'][1]['reg']->players()->sync([$player->id]);
        $doubles = $this->field($a, [1,2], [], 2);
        $doubles['rows'][0]['reg']->players()->sync([$player->id, $doubles['rows'][0]['players'][1]->id]);
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'));
        $this->assertSame(50.0, $personal['headline']['score']);
        $this->assertSame('u12 boys', $personal['headline']['cohort']);
        $this->assertCount(2, $personal['disciplines']['singles']['cohorts']);
        $this->assertSame(100.0, $personal['disciplines']['doubles']['headline']['score']);
        $this->assertFalse($personal['disciplines']['singles']['truncated']);
        $this->assertSame([$player->id], $personal['disciplines']['singles']['evidence']->flatMap(fn ($r) => array_column($r['players'], 'id'))->unique()->values()->all());
    }

    public function test_main_category_inference_requires_unique_same_event_matching_b(): void
    {
        $main = Category::factory()->create(['name' => 'u/10 Boys']);
        $b = Category::factory()->create(['name' => 'u/10 Boys- B division']);
        $field = $this->field($main);
        $player = $field['rows'][0]['players']->first();
        $service = app(PlayerPerformancePilotService::class);
        $asOf = CarbonImmutable::parse('2026-10-06');
        $this->assertSame('Open', $service->forPlayer($player, $asOf)['headline']['band']);
        $pair = CategoryEvent::factory()->create(['event_id' => $field['event']->id, 'category_id' => $b->id]);
        $this->assertSame(100.0, $service->forPlayer($player, $asOf)['headline']['score']);
        $competing = CategoryEvent::factory()->create(['event_id' => $field['event']->id, 'category_id' => Category::factory()->create(['name' => 'u/10 Boys A division'])->id]);
        $this->assertSame('Open', $service->forPlayer($player, $asOf)['headline']['band']);
        $competing->delete();
        $duplicate = CategoryEvent::factory()->create(['event_id' => $field['event']->id, 'category_id' => $b->id]);
        $this->assertSame('Open', $service->forPlayer($player, $asOf)['headline']['band']);
        $duplicate->delete();
        $pair->update(['category_id' => Category::factory()->create(['name' => 'Masters u/10 Boys B division'])->id]);
        $this->assertSame('Open', $service->forPlayer($player, $asOf)['headline']['band']);
    }

    public function test_withdrawn_membership_without_finish_never_fabricates_a_score(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys A division']));
        $registration = Registration::factory()->create();
        $player = Player::factory()->create();
        $registration->players()->attach($player);
        $membership = CategoryEventRegistration::factory()->create(['category_event_id' => $field['ce']->id, 'registration_id' => $registration->id, 'status' => 'withdrawn', 'withdrawn_at' => now()]);
        $membership->delete();
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'));
        $this->assertNull($personal['headline']);
        $this->assertCount(0, $personal['disciplines']['singles']['cohorts']);
        $this->assertSame('No saved finishing result for this player', $personal['disciplines']['singles']['evidence']->first()['reason']);
    }

    public function test_backend_profile_only_calculates_rating_for_super_user(): void
    {
        $player = Player::factory()->create();
        $this->mock(PlayerPerformancePilotService::class)->shouldNotReceive('forPlayer');
        $this->mock(\App\Services\Performance\PlayerSharedAbilityService::class)->shouldNotReceive('forPlayer');
        $this->actingAs(User::factory()->create());
        $this->get(route('backend.player.profile', $player->id))->assertOk()->assertViewHas('performance', null)->assertViewHas('ability', null)->assertDontSee('Cape Tennis Performance Score')->assertDontSee('Cape Tennis Shared Ability');
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->instance(PlayerPerformancePilotService::class, new PlayerPerformancePilotService());
        $this->instance(\App\Services\Performance\PlayerSharedAbilityService::class, new \App\Services\Performance\PlayerSharedAbilityService());
        $this->get(route('backend.player.profile', $player->id))->assertOk()->assertSee('Cape Tennis Performance Score')->assertSee('Unrated');
    }

    public function test_personal_master_context_and_pairing_bound_are_separate(): void
    {
        $category = Category::factory()->create(['name' => 'u10 Boys A division']);
        $field = $this->field($category);
        $player = $field['rows'][0]['players']->first();
        $masters = $this->field($category, [1,2], ['eventType' => 14]);
        $masters['rows'][0]['reg']->players()->sync([$player->id]);
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'));
        $this->assertEqualsCanonicalizing(['u10 boys', 'masters · u10 boys'], $personal['disciplines']['singles']['cohorts']->pluck('cohort')->all());
        $main = $this->field(Category::factory()->create(['name' => 'u10 Girls']));
        CategoryEvent::factory()->create(['event_id' => $main['event']->id, 'category_id' => Category::factory()->create(['name' => 'u10 Girls B division'])->id]);
        for ($i = 0; $i < 255; $i++) { CategoryEvent::factory()->create(['event_id' => $main['event']->id]); }
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($main['rows'][0]['players']->first(), CarbonImmutable::parse('2026-10-06'));
        $this->assertSame('Open', $personal['headline']['band']);
        $this->assertSame('Open — automatic pairing exceeds 256 categories', $personal['disciplines']['singles']['evidence']->first()['division_source']);
    }

    private function match(array $field, array $scores = [[6,4]], array $overrides = [], string $format = 'one_full_set'): \App\Models\Fixture
    {
        $draw = \App\Models\Draw::factory()->create(['event_id' => $field['event']->id, 'category_event_id' => $field['ce']->id, 'published' => true]);
        \App\Models\DrawSetting::create(['draw_id' => $draw->id, 'workflow' => 'round_robin', 'score_format' => $format, 'num_sets' => 1]);
        $fixture = \App\Models\Fixture::factory()->create($overrides + ['draw_id' => $draw->id, 'registration1_id' => $field['rows'][0]['reg']->id, 'registration2_id' => $field['rows'][1]['reg']->id, 'match_status' => 1, 'winner_registration' => $field['rows'][0]['reg']->id]);
        foreach ($scores as $index => [$first, $second]) {
            \App\Models\FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => $index + 1, 'registration1_score' => $first, 'registration2_score' => $second]);
        }
        return $fixture;
    }

    public function test_all_history_and_every_target_field_count_with_bounded_latest_evidence(): void
    {
        $category = Category::factory()->create(['name' => 'u10 Boys A division']);
        $original = $this->field($category, [1,2], ['start_date' => '2020-01-01', 'end_date' => '2020-01-02']);
        $player = $original['rows'][0]['players']->first();
        for ($i = 0; $i < 51; $i++) {
            $field = $this->field($category);
            $field['rows'][0]['reg']->players()->sync([$player->id]);
        }
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'));
        $this->assertSame(52, $personal['headline']['finish_count']);
        $this->assertSame(52, $personal['headline']['count']);
        $this->assertSame(100.0, $personal['headline']['score']);
        $this->assertSame(52, $personal['disciplines']['singles']['evidence_count']);
        $this->assertCount(50, $personal['disciplines']['singles']['evidence']);
    }

    public function test_h2h_and_finishes_normalize_one_event_weight_and_keep_b_band(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys B division']));
        $player = $field['rows'][1]['players']->first(); // B last place = 0, match winner = 50.
        $this->match($field, [[4,6]], ['winner_registration' => $field['rows'][1]['reg']->id]);
        $this->match($field, [[3,6]], ['winner_registration' => $field['rows'][1]['reg']->id]);
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'));
        $this->assertSame(25.0, $personal['headline']['score']);
        $this->assertSame(1, $personal['headline']['count']);
        $this->assertSame(1, $personal['headline']['finish_count']);
        $this->assertSame(2, $personal['headline']['match_count']);
        $this->assertSame(2, $personal['disciplines']['singles']['match_evidence']->whereNull('reason')->count());
    }

    public function test_published_finish_gate_independent_of_event_and_match_draw_publication(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], ['published' => false]);
        $player = $field['rows'][0]['players']->first();
        $fixture = $this->match($field);
        $service = app(PlayerPerformancePilotService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $this->assertSame(1, $service->forPlayer($player, $asOf)['headline']['finish_count']);
        $this->assertSame(0, $service->forPlayer($player, $asOf)['headline']['match_count']);
        $field['event']->update(['published' => true, 'results_published' => false]);
        $this->assertSame(0, $service->forPlayer($player, $asOf)['headline']['finish_count']);
        $this->assertSame(1, $service->forPlayer($player, $asOf)['headline']['match_count']);
        $fixture->draw->update(['published' => false]);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
    }

    public function test_h2h_rejects_partial_duplicate_wrong_winner_and_wrong_membership_but_accepts_legacy_scored_rr(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys A division']), [1,2], ['results_published' => false]);
        $player = $field['rows'][0]['players']->first();
        $good = $this->match($field, [[6,4]], ['match_status' => 2, 'stage' => 'RR', 'winner_registration' => null]);
        $this->match($field, [[1,0]], ['match_status' => 2, 'stage' => 'RR']);
        $this->match($field, [[6,4]], ['winner_registration' => $field['rows'][1]['reg']->id]);
        $duplicate = $this->match($field);
        \App\Models\FixtureResult::create(['fixture_id' => $duplicate->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 4]);
        $partial = $this->match($field, [[6,4]], [], 'best_of_3_full');
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'));
        $this->assertSame(1, $personal['headline']['match_count']);
        $this->assertSame(100.0, $personal['headline']['score']);
        $this->assertSame($good->id, $personal['disciplines']['singles']['match_evidence']->whereNull('reason')->first()['fixture_id']);
        $field['rows'][1]['member']->delete(); // historical deletion still retains recorded event membership
        $this->assertSame(1, app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'))['headline']['match_count']);
        $field['rows'][1]['member']->forceDelete();
        $this->assertNull(app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'))['headline']);
    }

    public function test_multiset_match_uses_majority_not_last_set_and_ongoing_published_results_count(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], ['start_date' => '2026-10-05', 'end_date' => '2026-10-08', 'results_published' => false]);
        $player = $field['rows'][0]['players']->first();
        $fixture = $this->match($field, [[6,4],[6,3]], [], 'best_of_3_full');
        $personal = app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'));
        $this->assertSame(100.0, $personal['headline']['score']);
        $this->assertSame('2026-10-06', $personal['headline']['last_played']);
        $fixture->fixtureResults()->where('set_nr', 2)->update(['registration1_score' => 3, 'registration2_score' => 6]);
        $this->assertNull(app(PlayerPerformancePilotService::class)->forPlayer($player, CarbonImmutable::parse('2026-10-06'))['headline']);
    }

    public function test_team_matches_require_completed_scores_and_verified_event_side_identity(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], ['results_published' => false, 'eventType' => 7]);
        $player = $field['rows'][0]['players']->first(); $opponent = $field['rows'][1]['players']->first();
        $draw = \App\Models\Draw::factory()->create(['event_id' => $field['event']->id, 'category_event_id' => $field['ce']->id, 'published' => true]);
        $home = \App\Models\Team::factory()->create(['category_event_id' => $field['ce']->id]);
        $away = \App\Models\Team::factory()->create(['category_event_id' => $field['ce']->id]);
        \App\Models\TeamPlayer::create(['team_id' => $home->id, 'player_id' => $player->id, 'rank' => 1]);
        \App\Models\TeamPlayer::create(['team_id' => $away->id, 'player_id' => $opponent->id, 'rank' => 1]);
        $tie = \App\Models\TeamTie::factory()->published()->create(['draw_id' => $draw->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1, 'numSets' => 1, 'match_status' => 1, 'match_nr' => 1, 'round_nr' => 1, 'rank_nr' => 1]);
        $row = \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $player->id, 'team2_id' => $opponent->id]);
        \App\Models\TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 4]);
        $service = app(PlayerPerformancePilotService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $this->assertSame(100.0, $service->forPlayer($player, $asOf)['headline']['score']);
        $this->assertSame('team · u10 boys', $service->forPlayer($player, $asOf)['headline']['cohort']);
        $row->forceFill(['participant_snapshot' => [1 => ['event_id' => 999, 'profile_id' => $player->id, 'source_team_id' => $home->id]]])->save();
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
        $row->forceFill(['participant_snapshot' => null])->save();
        $snapshots = [];
        foreach ([1 => [$home, $player], 2 => [$away, $opponent]] as $side => [$team, $profile]) {
            $snapshots[$side] = ['event_id' => $field['event']->id, 'source_team_id' => $team->id, 'category_event_id' => $field['ce']->id, 'region_id' => 0, 'profile_id' => $profile->id, 'imported_id' => null];
        }
        $row->forceFill(['participant_snapshot' => $snapshots])->save();
        $home->team_players()->delete();
        $this->assertSame(1, $service->forPlayer($player, $asOf)['headline']['match_count']);
        \App\Models\TeamPlayer::create(['team_id' => $home->id, 'player_id' => $player->id, 'rank' => 1]);
        $row->forceFill(['participant_snapshot' => null])->save();
        $fixture->teamResults()->update(['team1_score' => 1, 'team2_score' => 0]);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
        $fixture->teamResults()->update(['team1_score' => 6, 'team2_score' => 4]);
        $tie->update(['draw_id' => \App\Models\Draw::factory()->create()->id]);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
        $regionHome = \App\Models\TeamRegion::create(['region_name' => 'Pilot home']);
        $regionAway = \App\Models\TeamRegion::create(['region_name' => 'Pilot away']);
        $home->update(['region_id' => $regionHome->id]); $away->update(['region_id' => $regionAway->id]);
        $fixture->update(['team_tie_id' => null, 'region1' => $regionHome->id, 'region2' => $regionAway->id]);
        $this->assertSame(1, $service->forPlayer($player, $asOf)['headline']['match_count']);
        $duplicateTeam = \App\Models\Team::factory()->create(['category_event_id' => $field['ce']->id, 'region_id' => $regionHome->id]);
        \App\Models\TeamPlayer::create(['team_id' => $duplicateTeam->id, 'player_id' => $player->id, 'rank' => 1]);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
    }

    public function test_stale_draw_category_resolves_unique_actual_event_memberships_without_mutation(): void
    {
        $category = Category::factory()->create(['name' => 'u10 Boys A division']);
        $old = $this->field($category);
        $current = $this->field($category, [1,2], ['results_published' => false]);
        $fixture = $this->match($current);
        $fixture->draw->update(['category_event_id' => $old['ce']->id]);
        $player = $current['rows'][0]['players']->first(); $asOf = CarbonImmutable::parse('2026-10-06');
        $service = app(PlayerPerformancePilotService::class);
        $this->assertSame(1, $service->forPlayer($player, $asOf)['headline']['match_count']);
        $this->assertSame($old['ce']->id, $fixture->draw->fresh()->category_event_id);
        $duplicate = CategoryEvent::factory()->create(['event_id' => $current['event']->id, 'category_id' => $category->id]);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
        foreach ($current['rows'] as $row) { CategoryEventRegistration::factory()->create(['category_event_id' => $duplicate->id, 'registration_id' => $row['reg']->id]); }
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
    }

    public function test_legacy_partial_rr_and_unrecorded_bye_winner_are_excluded(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], ['results_published' => false]);
        $player = $field['rows'][0]['players']->first();
        $fixture = $this->match($field, [[6,4]], ['match_status' => 2, 'stage' => 'RR'], '');
        $fixture->draw->settings->update(['num_sets' => 3]);
        $legacyBye = $this->match($field, [[6,4]], ['match_status' => 3], '');
        $legacyBye->draw->settings->update(['num_sets' => 3]);
        $bye = $this->match($field, [[6,4]], ['match_status' => 3, 'winner_registration' => null]);
        $asOf = CarbonImmutable::parse('2026-10-06'); $service = app(PlayerPerformancePilotService::class);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
        $bye->update(['winner_registration' => $field['rows'][0]['reg']->id]);
        $this->assertSame(1, $service->forPlayer($player, $asOf)['headline']['match_count']);
        $secondSet = \App\Models\FixtureResult::create(['fixture_id' => $legacyBye->id, 'set_nr' => 2, 'registration1_score' => 6, 'registration2_score' => 3]);
        $this->assertSame(2, $service->forPlayer($player, $asOf)['headline']['match_count']);
        $secondSet->delete();
        $bye->fixtureResults()->update(['set_nr' => 3]);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
    }

    public function test_shared_ability_connects_regional_fields_through_shared_open_event_entrants(): void
    {
        $regional = $this->field(Category::factory()->create(['name' => 'u/10 Boys']), [1,2], ['name' => 'Regional trial']);
        $player = $regional['rows'][0]['players']->first();
        $anchor = $regional['rows'][1]['players']->first();
        $openA = $this->field(Category::factory()->create(['name' => 'U10 Boys A division']), [1,2], ['name' => 'Wilson open']);
        $openA['rows'][0]['reg']->players()->sync([$anchor->id]);
        $openB = $this->field(Category::factory()->create(['name' => 'U10 Boys B division']), [1,2], [], 1, $openA['event']);
        $other = $openB['rows'][1]['players']->first();
        $service = app(\App\Services\Performance\PlayerSharedAbilityService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $first = $service->forPlayer($player, $asOf)['headline'];
        $second = $service->forPlayer($other, $asOf)['headline'];
        $this->assertSame('u10 boys', $first['cohort']);
        $this->assertSame($first['component'], $second['component']);
        $this->assertGreaterThan($second['score'], $first['score']);
        $this->assertSame(0, $first['played']);
        $this->assertStringContainsString('Limited', $first['confidence']);
        $this->assertSame(1, $first['division_links']);
        $this->assertTrue($first['anchors']->contains('id', $anchor->id));
    }

    public function test_shared_cache_immediately_removes_unpublished_finish_and_draw_sources_without_timestamp_changes(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys A division']));
        $fixture = $this->match($field); $player = $field['rows'][0]['players']->first();
        $service = app(\App\Services\Performance\PlayerSharedAbilityService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $first = $service->forPlayer($player, $asOf)['headline'];
        $this->assertSame(1, $first['played']); $this->assertSame(1, $first['inferred']);
        \Illuminate\Support\Facades\DB::table('events')->where('id',$field['event']->id)->update(['results_published' => false]);
        $second = $service->forPlayer($player, $asOf)['headline'];
        $this->assertSame(1, $second['played']); $this->assertSame(0, $second['inferred']);
        \Illuminate\Support\Facades\DB::table('draws')->where('id',$fixture->draw_id)->update(['published' => false]);
        $this->assertNull($service->forPlayer($player, $asOf)['headline']);
    }

    public function test_shared_ability_keeps_ball_age_gender_doubles_and_disconnected_groups_separate(): void
    {
        $base = $this->field(Category::factory()->create(['name' => 'u10 Boys']));
        $player = $base['rows'][0]['players']->first();
        foreach (['u12 Boys', 'u10 Girls', 'u10 Boys Green ball'] as $label) {
            $field = $this->field(Category::factory()->create(['name' => $label]));
            $field['rows'][0]['reg']->players()->sync([$player->id]);
        }
        $doubles = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], [], 2);
        $doubles['rows'][0]['reg']->players()->sync([$player->id,$doubles['rows'][0]['players'][1]->id]);
        $disconnected = $this->field(Category::factory()->create(['name' => 'u10 Boys']));
        $service = app(\App\Services\Performance\PlayerSharedAbilityService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $estimate = $service->forPlayer($player,$asOf);
        $this->assertCount(4,$estimate['cohorts']);
        $this->assertSame(4,$estimate['cohorts']->pluck('component')->unique()->count());
        $u10 = $estimate['cohorts']->firstWhere('cohort','u10 boys');
        $other = $service->forPlayer($disconnected['rows'][0]['players']->first(),$asOf)['headline'];
        $this->assertNotSame($u10['component'],$other['component']);
        $this->assertSame(1,$u10['comparators']->count());
    }

    public function test_shared_placement_uses_latest_correction_and_ignores_unranked_entries(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [3,2]);
        CategoryResult::create(['event_id'=>$field['event']->id,'category_id'=>$field['ce']->category_id,'registration_id'=>$field['rows'][0]['reg']->id,'position'=>1]);
        CategoryEventRegistration::factory()->create(['category_event_id'=>$field['ce']->id]);
        $player=$field['rows'][0]['players']->first();
        $estimate=app(\App\Services\Performance\PlayerSharedAbilityService::class)->forPlayer($player,CarbonImmutable::parse('2026-10-06'))['headline'];
        $this->assertSame(1,$estimate['inferred']);
        $this->assertSame(2,$estimate['component_players']);
        $this->assertGreaterThan(50,$estimate['score']);
    }

    public function test_shared_processing_limit_withholds_all_partial_ratings(): void
    {
        $field=$this->field(Category::factory()->create(['name'=>'u10 Boys']));
        for($i=0;$i<256;$i++){CategoryEvent::factory()->create(['event_id'=>$field['event']->id]);}
        $estimate=app(\App\Services\Performance\PlayerSharedAbilityService::class)->forPlayer($field['rows'][0]['players']->first(),CarbonImmutable::parse('2026-10-06'));
        $this->assertNull($estimate['headline']);
        $this->assertCount(0,$estimate['cohorts']);
        $this->assertStringContainsString('No partial rating',$estimate['reason']);
    }

    public function test_shared_division_link_requires_unique_complete_disjoint_fields_and_has_no_event_name_bonus(): void
    {
        $a=$this->field(Category::factory()->create(['name'=>'u10 Boys A division']));
        $b=$this->field(Category::factory()->create(['name'=>'u10 Boys B division']),[1,2],[],1,$a['event']);
        $service=app(\App\Services\Performance\PlayerSharedAbilityService::class);$asOf=CarbonImmutable::parse('2026-10-06');
        $player=$a['rows'][0]['players']->first();
        $first=$service->forPlayer($player,$asOf)['headline'];
        $this->assertSame(1,$first['division_links']);
        $a['event']->update(['name'=>'Wilson international event name']);
        $this->assertSame($first['score'],$service->forPlayer($player,$asOf)['headline']['score']);
        $b['rows'][0]['reg']->players()->sync([$player->id]);
        \Illuminate\Support\Facades\Cache::store('array')->flush();
        $this->assertSame(0,$service->forPlayer($player,$asOf)['headline']['division_links']);
        $b['rows'][0]['reg']->players()->sync([$b['rows'][0]['players']->first()->id]);
        CategoryEvent::factory()->create(['event_id'=>$a['event']->id,'category_id'=>Category::factory()->create(['name'=>'u10 Boys A afdeling'])->id]);
        $this->assertSame(0,$service->forPlayer($player,$asOf)['headline']['division_links']);
    }
    public function test_confidence_uses_own_same_cohort_dates_daily_cache_and_conservative_ongoing_date(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'results_published' => false]);
        $fixture = $this->match($field);
        $player = $field['rows'][0]['players']->first();
        $service = app(\App\Services\Performance\PlayerSharedAbilityService::class);
        $first = $service->forPlayer($player, CarbonImmutable::parse('2026-10-06'))['headline'];
        $this->assertSame('2026-01-01', $first['last_direct_match']);
        $this->assertSame(1, $first['proxy_dated_matches']);
        $this->assertSame(0, $first['recent_played']);
        $next = $service->forPlayer($player, CarbonImmutable::parse('2026-10-07'))['headline'];
        $this->assertSame('2026-10-07', $next['confidence_as_of']);
        $this->assertLessThan($first['effective_played'], $next['effective_played']);
        \App\Models\OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $fixture->draw_id, 'venue_id' => 1, 'time' => '2026-10-05 10:00:00']);
        $scheduled = $service->forPlayer($player, CarbonImmutable::parse('2026-10-06'))['headline'];
        $this->assertSame('2026-10-05', $scheduled['last_direct_match']);
        $this->assertSame(0, $scheduled['proxy_dated_matches']);
        $historical = $service->forPlayer($player, CarbonImmutable::parse('2026-10-01'))['headline'];
        $this->assertNull($historical);
        $other = $this->field(Category::factory()->create(['name' => 'u12 Boys']), [1,2], ['start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $other['rows'][0]['reg']->players()->sync([$player->id]);
        $this->match($other);
        $cohorts = $service->forPlayer($player, CarbonImmutable::parse('2026-10-06'))['cohorts'];
        $this->assertSame('2026-10-05', $cohorts->firstWhere('cohort', 'u10 boys')['last_direct_match']);
        $this->assertSame('2026-10-01', $cohorts->firstWhere('cohort', 'u12 boys')['last_direct_match']);
    }

    public function test_connected_opponent_activity_does_not_refresh_inactive_player_confidence(): void
    {
        $old = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], ['start_date' => '2025-11-30', 'end_date' => '2025-11-30', 'results_published' => false]);
        $this->match($old); $player = $old['rows'][0]['players']->first(); $opponent = $old['rows'][1]['players']->first();
        $service = app(\App\Services\Performance\PlayerSharedAbilityService::class); $date = CarbonImmutable::parse('2026-10-06');
        $before = $service->forPlayer($player, $date)['headline'];
        $recent = $this->field($old['ce']->category, [1,2], ['start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'results_published' => false]);
        $recent['rows'][0]['reg']->players()->sync([$opponent->id]); $this->match($recent);
        $after = $service->forPlayer($player, $date)['headline'];
        $this->assertSame($before['confidence_index'], $after['confidence_index']);
        $this->assertSame($before['effective_played'], $after['effective_played']);
        $this->assertSame('2025-11-30', $after['last_direct_match']);
        $this->assertSame(0, $after['recent_played']);
    }

    public function test_shared_v4_rank_constraints_preserve_every_rank_and_do_not_duplicate_connected_played_results(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), range(1,7));
        $service = app(\App\Services\Performance\PlayerSharedAbilityService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $scores = [];
        foreach ($field['rows'] as $row) {
            $estimate = $service->forPlayer($row['players']->first(), $asOf)['headline'];
            $scores[] = $estimate['score']; $this->assertSame(0, $estimate['played']); $this->assertLessThanOrEqual(15, $estimate['confidence_index']);
        }
        for ($i=0; $i<6; $i++) { $this->assertGreaterThan($scores[$i+1], $scores[$i]); }
        $this->assertSame(50.0, $scores[3]);
        $pair = $this->field(Category::factory()->create(['name' => 'u12 Boys']), [1,2]);
        $fixture = $this->match($pair); $player = $pair['rows'][0]['players']->first();
        $withFinish = $service->forPlayer($player, $asOf)['headline']['score'];
        \Illuminate\Support\Facades\DB::table('events')->where('id', $pair['event']->id)->update(['results_published' => false]);
        $this->assertSame($withFinish, $service->forPlayer($player, $asOf)['headline']['score']);
    }

    public function test_shared_legacy_status_zero_requires_complete_verified_public_scores_without_changing_v2_or_records(): void
    {
        $field = $this->field(Category::factory()->create(['name' => 'Boys U/13']), [1,2], ['results_published' => false]);
        $fixture = $this->match($field, [[6,4],[6,3]], ['match_status' => 0], '');
        $fixture->draw->settings->update(['num_sets' => 3]);
        $player = $field['rows'][0]['players']->first(); $asOf = CarbonImmutable::parse('2026-10-06');
        $shared = app(\App\Services\Performance\PlayerSharedAbilityService::class);
        $estimate = $shared->forPlayer($player, $asOf)['headline'];
        $this->assertSame('u13 boys', $estimate['cohort']); $this->assertSame(1, $estimate['played']);
        $this->assertSame(0, $fixture->fresh()->match_status);
        $this->assertNull(app(PlayerPerformancePilotService::class)->forPlayer($player, $asOf)['headline']);
        $fixture->fixtureResults()->where('set_nr',2)->delete();
        $this->assertNull($shared->forPlayer($player, $asOf)['headline']);
        \App\Models\FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => 2, 'registration1_score' => 6, 'registration2_score' => 3]);
        $fixture->update(['winner_registration' => $field['rows'][1]['reg']->id]);
        $this->assertNull($shared->forPlayer($player, $asOf)['headline']);
        $fixture->update(['winner_registration' => $field['rows'][0]['reg']->id]);
        \Illuminate\Support\Facades\DB::table('draws')->where('id', $fixture->draw_id)->update(['published' => false]);
        $this->assertNull($shared->forPlayer($player, $asOf)['headline']);
    }

    private function legacyTeamField(bool $missingCategory = true): array
    {
        $field = $this->field(Category::factory()->create(['name' => 'u10 Boys']), [1,2], ['results_published' => false, 'eventType' => 7]);
        $first = $field['rows'][0]['players']->first(); $second = $field['rows'][1]['players']->first();
        $homeRegion = \App\Models\TeamRegion::create(['region_name' => 'Home region']);
        $awayRegion = \App\Models\TeamRegion::create(['region_name' => 'Away region']);
        $field['event']->regions()->attach([$homeRegion->id => ['ordering' => 1], $awayRegion->id => ['ordering' => 2]]);
        $home = \App\Models\Team::factory()->create(['name' => 'u10 Boys', 'year' => 2026, 'region_id' => $homeRegion->id, 'category_event_id' => $missingCategory ? null : $field['ce']->id]);
        $away = \App\Models\Team::factory()->create(['name' => 'u10 Boys', 'year' => 2026, 'region_id' => $awayRegion->id, 'category_event_id' => $missingCategory ? null : $field['ce']->id]);
        \App\Models\TeamPlayer::create(['team_id' => $home->id, 'player_id' => $first->id, 'rank' => 1]);
        \App\Models\TeamPlayer::create(['team_id' => $away->id, 'player_id' => $second->id, 'rank' => 1]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $field['event']->id, 'category_event_id' => null, 'drawName' => 'u10 Boys Singles', 'published' => true]);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => null, 'numSets' => 1, 'match_status' => 0, 'match_nr' => 1, 'round_nr' => 1, 'rank_nr' => 1, 'region1' => $homeRegion->id, 'region2' => $awayRegion->id]);
        $row = \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $first->id, 'team2_id' => $second->id]);
        \App\Models\TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 4]);
        return compact('field','first','second','home','away','draw','fixture','row');
    }

    public function test_shared_team_legacy_identity_links_require_event_region_year_unique_roster_and_exact_cohort(): void
    {
        $data = $this->legacyTeamField(); $service = app(\App\Services\Performance\PlayerSharedAbilityService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $estimate = $service->forPlayer($data['first'], $asOf)['headline'];
        $this->assertSame('u10 boys', $estimate['cohort']); $this->assertSame(1, $estimate['played']);
        $this->assertSame(0, $data['fixture']->fresh()->match_status);
        $data['away']->update(['name' => 'u12 Girls']);
        $this->assertNull($service->forPlayer($data['first'], $asOf)['headline']);
        $data['away']->update(['name' => 'u10 Boys', 'year' => 2025]);
        $this->assertNull($service->forPlayer($data['first'], $asOf)['headline']);
        $data['away']->update(['year' => 2026]);
        $duplicate = \App\Models\Team::factory()->create(['name' => 'u10 Boys', 'year' => 2026, 'region_id' => $data['home']->region_id, 'category_event_id' => null]);
        \App\Models\TeamPlayer::create(['team_id' => $duplicate->id, 'player_id' => $data['first']->id, 'rank' => 1]);
        $this->assertNull($service->forPlayer($data['first'], $asOf)['headline']);
        $duplicate->delete();
        $data['field']['event']->regions()->detach($data['away']->region_id);
        $this->assertNull($service->forPlayer($data['first'], $asOf)['headline']);
    }

    public function test_shared_team_actual_event_category_fallback_and_publication_correction_are_read_only(): void
    {
        $data = $this->legacyTeamField(false); $service = app(\App\Services\Performance\PlayerSharedAbilityService::class); $asOf = CarbonImmutable::parse('2026-10-06');
        $this->assertSame(1, $service->forPlayer($data['first'], $asOf)['headline']['played']);
        $this->assertNull($data['draw']->fresh()->category_event_id);
        \Illuminate\Support\Facades\DB::table('draws')->where('id', $data['draw']->id)->update(['published' => false]);
        $this->assertNull($service->forPlayer($data['first'], $asOf)['headline']);
        \Illuminate\Support\Facades\DB::table('draws')->where('id', $data['draw']->id)->update(['published' => true]);
        $tie = \App\Models\TeamTie::factory()->published()->create(['draw_id' => $data['draw']->id, 'home_team_id' => $data['home']->id, 'away_team_id' => $data['away']->id]);
        $data['fixture']->update(['team_tie_id' => $tie->id, 'fixture_type' => 1]);
        $this->assertSame(1, $service->forPlayer($data['first'], $asOf)['headline']['played']);
        \Illuminate\Support\Facades\DB::table('team_ties')->where('id',$tie->id)->update(['published_at' => null]);
        $this->assertNull($service->forPlayer($data['first'], $asOf)['headline']);
    }

}


