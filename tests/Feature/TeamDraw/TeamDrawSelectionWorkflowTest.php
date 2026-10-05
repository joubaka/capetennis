<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Category, CategoryEvent, Draw, Event, EventType, NoProfileTeamPlayer, Player, Team, TeamEventFormat, TeamEventFormatRubber, TeamFixture, TeamPlayer, TeamRegion, User};
use App\Services\{FeatureFlags, TeamDrawSideResolver, TeamTieValidationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamDrawSelectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;
    private array $categories = [];
    private array $types = [];
    private array $teams = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['eventType' => 3]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        foreach (['Singles', 'Singles Reverse', 'Doubles', 'Mixed Doubles'] as $type) {
            $this->types[$type] = DB::table('draw_types')->insertGetId(['drawTypeName' => 'Team - '.$type, 'btn_color' => 'primary', 'type' => 'team']);
        }
        foreach (['Boys', 'Girls'] as $gender) {
            $category = Category::factory()->create(['name' => 'u/13 '.$gender]);
            $this->categories[$gender] = CategoryEvent::factory()->create(['event_id' => $this->event->id, 'category_id' => $category->id])->id;
        }
        foreach (['East', 'West'] as $regionName) {
            $region = TeamRegion::create(['region_name' => $regionName, 'short_name' => $regionName]);
            foreach (['Boys', 'Girls'] as $gender) {
                $team = Team::factory()->create(['category_event_id' => $this->categories[$gender], 'region_id' => $region->id, 'name' => $regionName.' '.$gender]);
                $this->teams[$regionName][$gender] = $team;
                for ($rank = 1; $rank <= 2; $rank++) {
                    NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => $rank, 'name' => $regionName.' '.$gender, 'surname' => 'Player '.$rank, 'pay_status' => 0]);
                }
            }
        }
        FeatureFlags::enable(FeatureFlags::TEAM_DRAW_V2);
    }

    protected function tearDown(): void
    {
        FeatureFlags::clearOverride(FeatureFlags::TEAM_DRAW_V2);
        parent::tearDown();
    }

    private function item(string $type = 'Mixed Doubles', ?array $ids = null): array
    {
        return ['drawName' => 'u/13 '.$type, 'draw_type_id' => $this->types[$type], 'category_ids' => $ids ?? array_values($this->categories)];
    }

    private function request(array $items, bool $preview = false, string $key = 'selection-test-key')
    {
        return $this->actingAs($this->admin)->postJson(route($preview ? 'headoffice.previewTeamDraw' : 'headoffice.createSingleDraw.team', $this->event), ['batch_key' => $key, 'draws' => $items]);
    }

    public function test_fixture_index_displays_regions_once_and_original_player_ranks(): void
    {
        $items = [$this->item('Doubles', [$this->categories['Boys']]), $this->item('Singles Reverse', [$this->categories['Boys']]), $this->item()];
        $this->request($items)->assertOk();
        $fixtures = TeamFixture::with(['fixturePlayers.player1', 'fixturePlayers.player2', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2', 'teamTie'])->get();
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($fixtures);
        $doubles = $fixtures->firstWhere('rubber_code', 'doubles')->lineup_display;
        $this->assertSame([1, 2], array_column($doubles['home']['players'], 'rank'));
        $this->assertSame([1, 2], array_column($doubles['away']['players'], 'rank'));
        $reverse = $fixtures->firstWhere('rubber_code', 'reverse_singles')->lineup_display;
        $this->assertSame([1], array_column($reverse['home']['players'], 'rank'));
        $this->assertSame([2], array_column($reverse['away']['players'], 'rank'));
        $mixed = $fixtures->where('rubber_code', 'mixed_doubles')->values();
        $this->assertSame([1, 1], array_column($mixed[0]->lineup_display['home']['players'], 'rank'));
        $this->assertSame([2, 2], array_column($mixed[1]->lineup_display['home']['players'], 'rank'));
        $response = $this->actingAs($this->admin)->get(route('backend.team-fixtures.index'))->assertOk();
        $response->assertSee('class="fixture-region">East</div>', false)->assertSee('class="fixture-region">West</div>', false);
        $response->assertSee('East Boys Player 1')->assertSee('East Girls Player 1');
        $this->assertStringNotContainsString('>()</span>', $response->getContent());
        if (getenv('TEAM_FIXTURE_QA') === '1') {
            $html = preg_replace('~https?://[^/"\s]+/~', '/', $response->getContent());
            file_put_contents(public_path('_fixture-lineup-qa.html'), $html);
        }
    }

    public function test_fixture_lineup_resolves_linked_profiles_and_legacy_rank_fallbacks(): void
    {
        $this->request([$this->item('Doubles', [$this->categories['Boys']])])->assertOk();
        $fixture = TeamFixture::first();
        $profile = Player::factory()->create(['name' => 'Roux', 'surname' => 'Example']);
        TeamPlayer::create(['team_id' => $this->teams['East']['Boys']->id, 'player_id' => $profile->id, 'rank' => 2]);
        $fixture->fixturePlayers->last()->update(['team1_id' => $profile->id, 'team1_no_profile_id' => null]);
        $fixture->refresh()->load(['fixturePlayers.player1', 'fixturePlayers.player2', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2', 'teamTie']);
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare(collect([$fixture]));
        $this->assertSame(['name' => 'Roux Example', 'rank' => 2], $fixture->lineup_display['home']['players'][1]);
        $fixture->team_tie_id = null;
        $fixture->rubber_sequence = null;
        $fixture->home_rank_nr = 4;
        $fixture->away_rank_nr = null;
        $fixture->setRelation('teamTie', null);
        foreach ($fixture->fixturePlayers as $row) $row->participant_snapshot = null;
        $fixture->setRelation('region1Name', TeamRegion::first());
        $fixture->region1Name->short_name = null;
        app(\App\Services\TeamFixtureLineupPresenter::class)->prepare(collect([$fixture]));
        $this->assertSame('East', $fixture->lineup_display['home']['region']);
        $this->assertSame(4, $fixture->lineup_display['home']['players'][1]['rank']);
        $this->assertNull($fixture->lineup_display['away']['players'][0]['rank']);
    }

    public function test_mixed_preview_combines_sources_and_names_imported_players_without_writes(): void
    {
        $response = $this->request([$this->item()], true)->assertOk()->assertJsonPath('readiness.team_count', 2)->assertJsonPath('readiness.tie_count', 1)->assertJsonPath('readiness.rubber_count', 2);
        $response->assertJsonPath('readiness.rounds.0.ties.0.home_team.name', 'East Boys + East Girls');
        $response->assertJsonPath('readiness.rounds.0.ties.0.rubbers.0.slots.0.team1_name', 'East Boys Player 1');
        $response->assertJsonPath('readiness.rounds.0.ties.0.rubbers.0.slots.1.team1_name', 'East Girls Player 1');
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('teams', 4);
        if (getenv('TEAM_DRAW_QA') === '1') {
            $html = $this->actingAs($this->admin)->get(route('headOffice.show', $this->event))->assertOk()->getContent();
            $map = [];
            foreach (array_keys($this->types) as $type) {
                foreach ($type === 'Mixed Doubles' ? [array_values($this->categories)] : [[$this->categories['Boys']], [$this->categories['Girls']]] as $ids) {
                    $item = $this->item($type, $ids);
                    $plan = app(\App\Services\TeamDrawSelectionService::class)->plan($this->event, $item);
                    sort($ids);
                    $map[$item['draw_type_id'].'|'.implode(',', $ids)] = $plan['preview'];
                }
            }
            $script = '<script>window.addEventListener("DOMContentLoaded",function(){var fixtures='.json_encode($map).';jQuery.post=function(url,payload){var deferred=jQuery.Deferred();setTimeout(function(){if(!url.includes("preview-team-draw")){deferred.reject({responseJSON:{message:"QA fixture: creation disabled"}});return;}var items=payload.draws||[payload];var draws=items.map(function(item){var ids=item.category_ids.map(Number).sort(function(a,b){return a-b;});return {drawName:item.drawName,readiness:fixtures[item.draw_type_id+"|"+ids.join(",")]||{warnings:["Unavailable fixture selection"],can_create:false,rounds:[]}};});deferred.resolve({draws:draws,readiness:draws[0].readiness});},10);return deferred.promise();};new bootstrap.Modal(document.getElementById("createDrawModal")).show();});</script>';
            $html = preg_replace('~https?://[^/"\s]+/~', '/', $html);
            file_put_contents(public_path('_bulk-draw-qa.html'), str_replace('</body>', $script.'</body>', $html));
        }
    }

    public function test_selected_types_create_exact_rubbers_and_retry_is_idempotent(): void
    {
        $items = [$this->item('Singles', [$this->categories['Boys']]), $this->item('Singles Reverse', [$this->categories['Boys']]), $this->item('Doubles', [$this->categories['Girls']]), $this->item()];
        $first = $this->request($items)->assertOk()->json('draws');
        $this->request($items)->assertOk()->assertJsonPath('draws.0.id', $first[0]['id']);
        $this->assertDatabaseCount('draws', 4);
        $this->assertDatabaseCount('team_ties', 4);
        $this->assertDatabaseCount('team_fixtures', 7);
        $this->assertDatabaseCount('team_fixture_players', 10);
        $this->assertDatabaseCount('teams', 4);
        foreach (Draw::all() as $draw) {
            $this->assertFalse((bool) $draw->published);
            $this->assertEquals([$draw->team_draw_selection['rubber_code']], TeamFixture::where('draw_id', $draw->id)->pluck('rubber_code')->unique()->values()->all());
        }
        $reverse = TeamFixture::where('rubber_code', 'reverse_singles')->first();
        $this->assertEquals(2, $reverse->fixturePlayers->first()->team2_no_profile_id ? NoProfileTeamPlayer::find($reverse->fixturePlayers->first()->team2_no_profile_id)->rank : null);
    }

    public function test_changed_request_cannot_reuse_a_creation_key(): void
    {
        $this->request([$this->item()])->assertOk();
        $this->request([$this->item('Singles', [$this->categories['Boys']])])->assertUnprocessable()->assertJsonValidationErrors('batch_key');
        $this->assertDatabaseCount('draws', 1);
    }

    public function test_missing_mixed_partner_allows_selected_batch_and_normal_byes(): void
    {
        $region = TeamRegion::create(['region_name' => 'North']);
        foreach (['Boys', 'Girls'] as $gender) {
            $team = Team::factory()->create(['category_event_id' => $this->categories[$gender], 'region_id' => $region->id, 'name' => 'North '.$gender]);
            for ($rank = 1; $rank <= 2; $rank++) {
                NoProfileTeamPlayer::create(['team_id' => $team->id, 'rank' => $rank, 'name' => 'North '.$gender, 'surname' => 'Player '.$rank, 'pay_status' => 0]);
            }
        }
        $missingRegion = TeamRegion::create(['region_name' => 'South']);
        $missing = Team::factory()->create(['category_event_id' => $this->categories['Boys'], 'region_id' => $missingRegion->id, 'name' => 'South Boys']);
        $this->request([$this->item()], true)->assertOk()
            ->assertJsonPath('readiness.team_count', 3)->assertJsonPath('readiness.can_create', true)
            ->assertJsonPath('readiness.tie_count', 3)->assertJsonPath('readiness.bye_count', 3);
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
        $items = [$this->item('Singles', [$this->categories['Boys']]), $this->item()];
        $this->request($items)->assertOk();
        $this->request($items)->assertOk();
        $this->assertDatabaseCount('draws', 2);
        $mixed = Draw::where('drawType_id', $this->types['Mixed Doubles'])->firstOrFail();
        $this->assertCount(3, $mixed->teamTies);
        $this->assertSame(6, TeamFixture::where('draw_id', $mixed->id)->count());
        $this->assertArrayNotHasKey($missing->id, $mixed->team_draw_selection['mixed_sides']);
    }

    public function test_roster_position_default_does_not_raise_a_configuration_warning(): void
    {
        $response = $this->request([$this->item()], true)->assertOk();
        $this->assertStringNotContainsString('no format is configured', implode(' ', $response->json('readiness.warnings')));
        $rubbers = $response->json('readiness.rounds.0.ties.0.rubbers');
        foreach ($rubbers as $index => $rubber) {
            foreach ($rubber['slots'] as $slot) {
                $this->assertStringEndsWith('Player '.($index + 1), $slot['team1_name']);
                $this->assertStringEndsWith('Player '.($index + 1), $slot['team2_name']);
            }
        }
    }

    public function test_ambiguous_sources_and_cross_event_categories_are_rejected(): void
    {
        Team::factory()->create(['category_event_id' => $this->categories['Girls'], 'region_id' => $this->teams['East']['Girls']->region_id]);
        $this->request([$this->item()])->assertUnprocessable();
        $foreign = CategoryEvent::factory()->create();
        $this->request([$this->item('Singles', [$foreign->id])])->assertUnprocessable()->assertJsonValidationErrors('draws.0.category_ids.0');
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_ordinary_users_and_other_event_admins_cannot_preview_or_create(): void
    {
        foreach ([User::factory()->create(), User::factory()->create()->assignRole('admin')] as $user) {
            foreach (['headoffice.previewTeamDraw', 'headoffice.createSingleDraw.team'] as $route) {
                $this->actingAs($user)->postJson(route($route, $this->event), ['batch_key' => 'unauthorized', 'draws' => [$this->item()]])->assertForbidden();
            }
        }
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_mixed_validation_and_regeneration_keep_both_sources_and_reject_same_source_pair(): void
    {
        $this->request([$this->item()])->assertOk();
        $draw = Draw::first();
        $tie = $draw->teamTies()->first();
        app(TeamTieValidationService::class)->assertTieComplete($tie);
        $this->actingAs($this->admin)->postJson(route('team-draw.regenerate', $draw), ['regenerate_rubbers' => true])->assertOk();
        $this->assertDatabaseCount('team_ties', 1);
        $this->assertDatabaseCount('team_fixtures', 2);
        $tie = $draw->teamTies()->first();
        $rubber = $tie->rubbers()->first();
        $boys = $this->teams['East']['Boys']->team_players_no_profile;
        $rubber->fixturePlayers()->where('slot_no', 2)->update(['team1_no_profile_id' => $boys->last()->id]);
        $this->expectException(\InvalidArgumentException::class);
        app(TeamTieValidationService::class)->assertTieComplete($tie);
    }

    public function test_mixed_source_scope_change_is_not_accepted(): void
    {
        $this->request([$this->item()])->assertOk();
        $draw = Draw::first();
        $foreign = CategoryEvent::factory()->create();
        $this->teams['East']['Girls']->update(['category_event_id' => $foreign->id]);
        $this->expectException(\InvalidArgumentException::class);
        app(TeamDrawSideResolver::class)->teams($draw);
    }

    public function test_explicit_format_filters_out_unselected_rubbers(): void
    {
        $format = TeamEventFormat::create(['event_id' => $this->event->id, 'name' => 'Combined', 'min_roster_size' => 1, 'max_roster_size' => 12, 'allow_player_reuse' => true]);
        foreach (['singles', 'doubles'] as $index => $code) TeamEventFormatRubber::create(['format_id' => $format->id, 'sequence' => $index + 1, 'rubber_code' => $code, 'name' => $code, 'player_count_per_team' => $index + 1, 'is_required' => true]);
        $item = $this->item('Singles', [$this->categories['Boys']]) + ['format_id' => $format->id];
        $this->request([$item])->assertOk();
        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertEquals('singles', TeamFixture::first()->rubber_code);
    }

    public function test_profile_players_are_named_and_assigned_from_both_mixed_sources(): void
    {
        foreach (['Boys' => 1, 'Girls' => 2] as $gender => $genderId) {
            $team = $this->teams['East'][$gender];
            $team->team_players_no_profile()->where('rank', 1)->delete();
            $player = Player::factory()->create(['name' => 'East '.$gender, 'surname' => 'Profile', 'gender' => $genderId]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1]);
        }
        $response = $this->request([$this->item()], true)->assertOk();
        $slots = $response->json('readiness.rounds.0.ties.0.rubbers.0.slots');
        $this->assertStringContainsString('Profile', $slots[0]['team1_name']);
        $this->assertStringContainsString('Profile', $slots[1]['team1_name']);
        $this->request([$this->item()])->assertOk();
        app(TeamTieValidationService::class)->assertTieComplete(Draw::first()->teamTies()->first());
        $this->assertEquals(2, DB::table('team_fixture_players')->whereNotNull('team1_id')->count());
    }

    public function test_late_generation_failure_rolls_back_every_created_draw(): void
    {
        $calls = 0;
        $this->mock(\App\Services\TeamTieGenerationService::class, function ($mock) use (&$calls) {
            $mock->shouldReceive('generateForTie')->andReturnUsing(function () use (&$calls) {
                if (++$calls === 2) throw new \RuntimeException('Isolated generation failure');
                return collect();
            });
        });
        $this->request([$this->item('Singles', [$this->categories['Boys']]), $this->item('Doubles', [$this->categories['Girls']])])->assertStatus(500);
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('team_ties', 0);
        $this->assertDatabaseCount('draw_teams', 0);
    }

    public function test_selected_source_map_cannot_be_overridden_by_sync_generation_or_regeneration(): void
    {
        $this->request([$this->item()])->assertOk();
        $draw = Draw::first();
        $originalMap = $draw->team_draw_selection['mixed_sides'];
        $this->actingAs($this->admin)->postJson(route('team-draw.sync-teams', $draw), [])->assertOk();
        $this->assertSame($originalMap, $draw->fresh()->team_draw_selection['mixed_sides']);
        foreach (['generate-ties', 'regenerate'] as $operation) {
            $this->actingAs($this->admin)->postJson(route('team-draw.'.$operation, $draw), ['team_ids' => [$this->teams['East']['Boys']->id, $this->teams['East']['Girls']->id]])->assertStatus(409);
        }
        $this->assertDatabaseCount('team_ties', 1);
        $this->assertDatabaseCount('team_fixtures', 2);
    }

    public function test_category_groups_preserve_b_division_pair_metadata(): void
    {
        $groups = \App\Support\TeamDrawCategoryGroups::make([
            (object) ['name' => 'u/13 Boys - B division', 'pivot_id' => 10],
            (object) ['name' => 'u/13 Girls - B division', 'pivot_id' => 11],
            (object) ['name' => 'u/13 Boys - A division', 'pivot_id' => 12],
        ]);
        $boysB = collect($groups)->firstWhere('pivot_id', 10);
        $girlsB = collect($groups)->firstWhere('pivot_id', 11);
        $this->assertEquals('boys', $boysB->parsed_gender);
        $this->assertEquals('girls', $girlsB->parsed_gender);
        $this->assertEquals($boysB->parsed_age, $girlsB->parsed_age);
        $this->assertNotEquals($boysB->parsed_age, collect($groups)->firstWhere('pivot_id', 12)->parsed_age);
    }

    public function test_legacy_single_mixed_request_uses_identical_side_planning_and_safe_retry(): void
    {
        $item = $this->item();
        $this->actingAs($this->admin)->postJson(route('headoffice.previewTeamDraw', $this->event), $item)
            ->assertOk()->assertJsonPath('readiness.team_count', 2)->assertJsonPath('readiness.tie_count', 1);
        $this->actingAs($this->admin)->postJson(route('headoffice.createSingleDraw.team', $this->event), $item)->assertOk();
        $this->actingAs($this->admin)->postJson(route('headoffice.createSingleDraw.team', $this->event), $item)->assertOk();
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('team_ties', 1);
        $this->assertDatabaseCount('team_fixtures', 2);
        $item['category_ids'] = [$this->categories['Boys']];
        $this->actingAs($this->admin)->postJson(route('headoffice.createSingleDraw.team', $this->event), $item)->assertUnprocessable();
        $this->assertDatabaseCount('draws', 1);
    }

    public function test_super_user_cannot_create_selected_team_draw_in_individual_event(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $super = User::factory()->create()->assignRole('super-user');
        DB::table('eventtypes')->insert(['id' => 1, 'name' => 'Individual', 'type' => EventType::INDIVIDUAL]);
        $event = Event::factory()->create(['eventType' => 1]);
        foreach (['headoffice.previewTeamDraw', 'headoffice.createSingleDraw.team'] as $route) {
            $this->actingAs($super)->postJson(route($route, $event), ['batch_key' => 'wrong-kind', 'draws' => [$this->item()]])->assertForbidden();
        }
        $this->assertDatabaseCount('draws', 0);
    }
}
