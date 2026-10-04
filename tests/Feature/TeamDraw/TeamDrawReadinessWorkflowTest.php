<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, EventType, Team, TeamEventFormat, TeamEventFormatRubber, TeamFixture, TeamTie, User};
use App\Services\{FeatureFlags, TeamTieGenerationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamDrawReadinessWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $this->event = Event::factory()->create(['eventType' => 3]);
        $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        Team::factory()->count(3)->create(['category_event_id' => $category->id]);
        $type = DB::table('draw_types')->insertGetId(['drawTypeName' => 'Round robin', 'btn_color' => 'primary']);
        $this->payload = ['draw_type_id' => $type, 'drawName' => 'Preview draw', 'category_ids' => [$category->id]];
        FeatureFlags::enable(FeatureFlags::TEAM_DRAW_V2);
    }

    protected function tearDown(): void
    {
        FeatureFlags::clearOverride(FeatureFlags::TEAM_DRAW_V2);
        parent::tearDown();
    }

    private function format(?Event $event = null, bool $default = false): TeamEventFormat
    {
        $format = TeamEventFormat::factory()->create(['event_id' => ($event ?? $this->event)->id, 'is_default' => $default]);
        TeamEventFormatRubber::create(['format_id' => $format->id, 'sequence' => 1, 'name' => 'Crossed singles',
            'rubber_code' => 'singles', 'player_count_per_team' => 1, 'home_positions' => [1], 'away_positions' => [2]]);
        return $format;
    }

    public function test_preview_shows_real_rounds_byes_and_missing_slots_without_writes(): void
    {
        $format = $this->format();
        $this->postJson(route('headoffice.previewTeamDraw', $this->event), $this->payload + ['format_id' => $format->id])
            ->assertOk()->assertJsonPath('readiness.tie_count', 3)->assertJsonPath('readiness.round_count', 3)
            ->assertJsonPath('readiness.bye_count', 3)->assertJsonPath('readiness.rubber_count', 3)
            ->assertJsonPath('readiness.missing_slots', 6)->assertJsonPath('readiness.ready', false)
            ->assertJsonPath('readiness.rounds.0.ties.0.rubbers.0.away_positions', [2]);
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('team_ties', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
    }

    public function test_selected_format_overrides_event_default_for_creation(): void
    {
        $this->format(default: true);
        $selected = $this->format();
        $response = $this->postJson(route('headoffice.createSingleDraw.team', $this->event), $this->payload + ['format_id' => $selected->id]);
        $response->assertOk();
        $this->assertSame($selected->id, Draw::findOrFail($response->json('draw.id'))->team_event_format_id);
        $this->assertDatabaseCount('team_ties', 3);
        $this->assertDatabaseCount('team_fixtures', 3);
    }

    public function test_global_format_and_complete_imported_rosters_have_ready_preview(): void
    {
        $format = $this->format();
        $format->update(['event_id' => null, 'min_roster_size' => 2, 'max_roster_size' => 2]);
        foreach (Team::all() as $team) {
            foreach ([1, 2] as $rank) {
                \App\Models\NoProfileTeamPlayer::create(['team_id' => $team->id, 'pay_status' => 0, 'name' => 'Imported', 'surname' => (string) $rank, 'rank' => $rank]);
            }
        }
        $this->postJson(route('headoffice.previewTeamDraw', $this->event), $this->payload + ['format_id' => $format->id])
            ->assertOk()->assertJsonPath('readiness.ready', true)->assertJsonPath('readiness.missing_slots', 0);
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('team_fixture_players', 0);
    }

    public function test_preview_warns_when_a_format_prohibits_player_reuse(): void
    {
        $format = $this->format();
        $format->update(['min_roster_size' => 2, 'max_roster_size' => 2, 'allow_player_reuse' => false]);
        $this->importedRosters();
        TeamEventFormatRubber::create(['format_id' => $format->id, 'sequence' => 2, 'name' => 'Repeated singles',
            'rubber_code' => 'singles', 'player_count_per_team' => 1, 'home_positions' => [1], 'away_positions' => [2]]);
        $response = $this->postJson(route('headoffice.previewTeamDraw', $this->event), $this->payload + ['format_id' => $format->id]);
        $response->assertOk()->assertJsonPath('readiness.ready', false)->assertJsonPath('readiness.missing_slots', 0);
        $this->assertStringContainsString('reuses a player', implode(' ', $response->json('readiness.warnings')));
        $this->assertSame('Imported 1', $response->json('readiness.rounds.0.ties.0.rubbers.0.slots.0.team1_name'));
        $this->assertDatabaseCount('team_fixture_players', 0);
    }

    public function test_imported_mixed_gender_unknown_needs_operator_verification(): void
    {
        $format = $this->mixedFormat();
        $this->importedRosters();
        $response = $this->postJson(route('headoffice.previewTeamDraw', $this->event), $this->payload + ['format_id' => $format->id]);
        $response->assertOk()->assertJsonPath('readiness.ready', false)->assertJsonPath('readiness.missing_slots', 0);
        $this->assertStringContainsString('verify unrecorded player genders', implode(' ', $response->json('readiness.warnings')));
    }

    public function test_same_gender_mixed_pairs_are_not_ready(): void
    {
        $format = $this->mixedFormat();
        foreach (Team::all() as $team) {
            foreach ([1, 2] as $rank) {
                $player = \App\Models\Player::factory()->create(['gender' => 1]);
                \App\Models\TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank]);
            }
        }
        $response = $this->postJson(route('headoffice.previewTeamDraw', $this->event), $this->payload + ['format_id' => $format->id]);
        $response->assertOk()->assertJsonPath('readiness.ready', false)->assertJsonPath('readiness.missing_slots', 0);
        $this->assertStringContainsString('requires one male and one female', implode(' ', $response->json('readiness.warnings')));
    }

    private function importedRosters(): void
    {
        foreach (Team::all() as $team) {
            foreach ([1, 2] as $rank) {
                \App\Models\NoProfileTeamPlayer::create(['team_id' => $team->id, 'pay_status' => 0, 'name' => 'Imported', 'surname' => (string) $rank, 'rank' => $rank]);
            }
        }
    }

    private function mixedFormat(): TeamEventFormat
    {
        $format = $this->format();
        $format->update(['min_roster_size' => 2, 'max_roster_size' => 2]);
        $format->rubbers()->first()->update(['rubber_code' => 'mixed_doubles', 'gender_rule' => 'mixed',
            'player_count_per_team' => 2, 'home_positions' => [1, 2], 'away_positions' => [1, 2]]);
        return $format;
    }

    public function test_foreign_format_is_rejected_before_any_draw_is_created(): void
    {
        $foreign = $this->format(Event::factory()->create(['eventType' => 3]));
        foreach (['headoffice.previewTeamDraw', 'headoffice.createSingleDraw.team'] as $route) {
            $this->postJson(route($route, $this->event), $this->payload + ['format_id' => $foreign->id])
                ->assertUnprocessable()->assertJsonValidationErrors('format_id');
        }
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_generation_failure_rolls_back_draw_settings_teams_and_ties(): void
    {
        $format = $this->format();
        $this->mock(TeamTieGenerationService::class, function ($mock) {
            $mock->shouldReceive('generateFromFormat')->once()->andThrow(new \RuntimeException('Generation failed'));
        });
        $this->postJson(route('headoffice.createSingleDraw.team', $this->event), $this->payload + ['format_id' => $format->id])->assertStatus(500);
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('team_ties', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
    }

    public function test_preview_without_format_reports_unknown_readiness(): void
    {
        $this->postJson(route('headoffice.previewTeamDraw', $this->event), $this->payload)
            ->assertOk()->assertJsonPath('readiness.ready', false)->assertJsonPath('readiness.format_id', null)
            ->assertJsonPath('readiness.rubber_count', 0);
    }

    public function test_gender_named_categories_remain_available_for_standard_and_mixed_draws(): void
    {
        $linked = [];
        foreach (['u/13 Boys', 'u/13 Girls', 'u/12 Girls- A division'] as $name) {
            $category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
            DB::table('categories')->where('id', $category->category_id)->update(['name' => $name]);
            $linked[] = $category;
        }
        $other = CategoryEvent::factory()->create();
        DB::table('categories')->where('id', $other->category_id)->update(['name' => 'Other event girls']);
        $response = $this->get(route('headOffice.show', $this->event))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach ($linked as $category) {
            $this->assertSame(1, $xpath->query('//input[@name="category_choice" and @value="'.$category->id.'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//input[@name="category_choice_boys" and @value="'.$linked[0]->id.'"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="category_choice_girls" and @value="'.$linked[1]->id.'"]')->length);
        $this->assertSame('u/13 Girls', $xpath->query('//input[@name="category_choice" and @value="'.$linked[1]->id.'"]')->item(0)->getAttribute('data-age'));
        $this->assertSame(0, $xpath->query('//input[@name="category_choice" and @value="'.$other->id.'"]')->length);
    }

    public function test_existing_event_formats_are_selectable_without_legacy_rollout_flag(): void
    {
        $format = $this->format();
        FeatureFlags::disable(FeatureFlags::TEAM_DRAW_V2);
        $this->get(route('headOffice.show', $this->event))
            ->assertOk()->assertSee('id="format_id"', false)->assertSee($format->name);
    }
}
