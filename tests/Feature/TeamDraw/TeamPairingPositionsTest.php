<?php

namespace Tests\Feature\TeamDraw;

use App\Domain\TeamDraw\TeamEventFormatDefinitionValidator;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamPlayer;
use App\Models\TeamTie;
use App\Models\User;
use App\Models\EventType;
use App\Services\FeatureFlags;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Services\TeamTieGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamPairingPositionsTest extends TestCase
{
    use RefreshDatabase;

    private function template(string $code, array $home, array $away): TeamEventFormatRubber
    {
        return TeamEventFormatRubber::create([
            'format_id' => TeamEventFormat::factory()->create()->id,
            'sequence' => 1, 'rubber_code' => $code, 'name' => 'Exact pairing',
            'player_count_per_team' => count($home),
            'home_positions' => $home, 'away_positions' => $away,
        ]);
    }

    public function test_crossed_singles_and_repeat_generation_preserve_manual_assignment(): void
    {
        $tie = TeamTie::factory()->create();
        $home = Player::factory()->create();
        $away = Player::factory()->create();
        TeamPlayer::create(['team_id' => $tie->home_team_id, 'player_id' => $home->id, 'rank' => 1]);
        TeamPlayer::create(['team_id' => $tie->away_team_id, 'player_id' => $away->id, 'rank' => 2]);
        $template = $this->template('reverse_singles', [1], [2]);
        $service = app(TeamTieGenerationService::class);
        $fixture = $service->generateFromFormat($tie, $template->format)->first();
        $slot = $fixture->fixturePlayers()->first();
        $this->assertSame($home->id, $slot->team1_id);
        $this->assertSame($away->id, $slot->team2_id);
        $replacement = Player::factory()->create();
        $slot->update(['team1_id' => $replacement->id]);
        $service->generateFromFormat($tie, $template->format);
        $this->assertSame($replacement->id, $slot->fresh()->team1_id);
        $this->assertSame(1, $fixture->fixturePlayers()->count());
    }

    public function test_doubles_use_distinct_imported_positions_and_missing_rank_stays_empty(): void
    {
        $tie = TeamTie::factory()->create();
        $home = [];
        foreach ([3, 4] as $rank) {
            $home[] = NoProfileTeamPlayer::create(['team_id' => $tie->home_team_id, 'name' => 'Imported', 'surname' => 'Player', 'rank' => $rank, 'pay_status' => 0]);
        }
        $away = NoProfileTeamPlayer::create(['team_id' => $tie->away_team_id, 'name' => 'Imported', 'surname' => 'Away', 'rank' => 6, 'pay_status' => 0]);
        $template = $this->template('doubles', [3, 4], [6, 5]);
        $fixture = app(TeamTieGenerationService::class)->generateFromFormat($tie, $template->format)->first();
        $slots = $fixture->fixturePlayers()->orderBy('slot_no')->get();
        $this->assertSame([$home[0]->id, $home[1]->id], $slots->pluck('team1_no_profile_id')->all());
        $this->assertSame([$away->id, null], $slots->pluck('team2_no_profile_id')->all());
        $this->assertSame([null, null], $slots->pluck('team2_id')->all());
    }

    public function test_snapshot_pairing_survives_later_template_edits(): void
    {
        $tie = TeamTie::factory()->create();
        $template = $this->template('reverse_singles', [1], [2]);
        $tie->draw->update(['team_format_snapshot' => ['rubbers' => [$template->toArray()]]]);
        $template->update(['home_positions' => [3], 'away_positions' => [4]]);
        $home = Player::factory()->create();
        TeamPlayer::create(['team_id' => $tie->home_team_id, 'player_id' => $home->id, 'rank' => 1]);
        $fixture = app(TeamTieGenerationService::class)->generateFromFormat($tie->fresh(), $template->format)->first();
        $this->assertSame($home->id, $fixture->fixturePlayers()->first()->team1_id);
    }

    public function test_attach_format_rejects_snapshot_changes_and_recorded_results(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'team event', 'type' => EventType::TEAM]);
        $tie = TeamTie::factory()->create();
        $tie->draw->update(['event_id' => \App\Models\Event::factory()->create(['eventType' => 3])->id]);
        $template = $this->template('singles', [1], [1]);
        $other = TeamEventFormat::factory()->create();
        $draw = $tie->draw;
        $draw->update(['team_event_format_id' => $template->format_id, 'team_format_snapshot' => ['rubbers' => [$template->toArray()]]]);
        FeatureFlags::enable(FeatureFlags::TEAM_DRAW_V2);
        try {
            $this->actingAs($actor)->postJson("/backend/team-draw/{$draw->id}/attach-format", ['format_id' => $other->id])->assertStatus(409);
            $this->assertSame($template->format_id, $draw->fresh()->team_event_format_id);
            $draw->update(['team_format_snapshot' => null]);
            $fixture = app(TeamTieGenerationService::class)->generateFromFormat($tie->fresh(), $template->format)->first();
            $fixture->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
            $this->actingAs($actor)->postJson("/backend/team-draw/{$draw->id}/attach-format", ['format_id' => $other->id])->assertStatus(409);
            $this->assertSame($template->format_id, $draw->fresh()->team_event_format_id);
        } finally {
            FeatureFlags::clearOverride(FeatureFlags::TEAM_DRAW_V2);
        }
    }

    public static function invalidPositions(): array
    {
        return [[[1, 1], [1, 2]], [[1], [1, 2]], [[1, 7], [1, 2]], [[1, 2], null], [[1.5, 2], [1, 2]], [[0, 2], [1, 2]]];
    }

    public function test_explicit_reverse_doubles_does_not_require_legacy_reverse_position(): void
    {
        app(TeamEventFormatDefinitionValidator::class)->validate([
            'min_roster_size' => 8, 'max_roster_size' => 8,
            'rubbers' => [['sequence' => 1, 'rubber_code' => 'reverse_doubles', 'player_count_per_team' => 2, 'home_positions' => [5, 6], 'away_positions' => [7, 8]]],
        ]);
        $this->assertTrue(true);
    }

    public function test_snapshot_required_rubbers_remain_required_when_template_changes(): void
    {
        $tie = TeamTie::factory()->create();
        $template = $this->template('singles', [1], [1]);
        $tie->draw->update(['team_format_snapshot' => ['rubbers' => [$template->toArray(), array_merge($template->toArray(), ['sequence' => 2, 'is_required' => true])]]]);
        app(TeamTieGenerationService::class)->generateFromFormat($tie->fresh(), $template->format);
        $tie->rubbers()->where('rubber_sequence', 2)->delete();
        $template->update(['is_required' => false]);
        $this->assertFalse(app(\App\Services\TeamTieValidationService::class)->requiredRubbersPresent($tie->fresh()));
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie->fresh());
    }

    public function test_snapshot_validation_rejects_player_from_other_team(): void
    {
        $tie = TeamTie::factory()->create();
        $template = $this->template('singles', [1], [1]);
        $tie->draw->update(['team_format_snapshot' => ['allow_player_reuse' => true, 'rubbers' => [$template->toArray()]]]);
        $home = Player::factory()->create();
        $away = Player::factory()->create();
        TeamPlayer::create(['team_id' => $tie->home_team_id, 'player_id' => $home->id, 'rank' => 1]);
        TeamPlayer::create(['team_id' => $tie->away_team_id, 'player_id' => $away->id, 'rank' => 1]);
        $fixture = app(TeamTieGenerationService::class)->generateFromFormat($tie->fresh(), $template->format)->first();
        app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie->fresh());
        $fixture->fixturePlayers()->first()->update(['team1_id' => $away->id]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rubber players must belong');
        app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie->fresh());
    }

    #[DataProvider('regenerationModes')]
    public function test_regeneration_never_purges_scored_ties_even_with_override(bool $rubbersOnly): void
    {
        $tie = TeamTie::factory()->create();
        $template = $this->template('singles', [1], [1]);
        $tie->draw->update(['team_event_format_id' => $template->format_id]);
        $fixture = app(TeamTieGenerationService::class)->generateFromFormat($tie, $template->format)->first();
        $fixture->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        try {
            $service = app(\App\Services\TeamDrawRegenerationService::class);
            $rubbersOnly ? $service->regenerateRubbersOnly($tie->draw->fresh(), true)
                : $service->regenerate($tie->draw->fresh(), collect([$tie->homeTeam, $tie->awayTeam]), $template->format, true, true);
            $this->fail('Scored draw regeneration must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('team_ties', ['id' => $tie->id]);
        $this->assertDatabaseHas('team_fixtures', ['id' => $fixture->id]);
        $this->assertSame(1, $fixture->fixtureResults()->count());
    }

    public static function regenerationModes(): array
    {
        return [[false], [true]];
    }

    public static function protectedGenerationStates(): array
    {
        return [['results'], ['played'], ['tie_published'], ['draw_locked'], ['draw_published']];
    }

    #[DataProvider('protectedGenerationStates')]
    public function test_override_cannot_generate_rubbers_or_reassign_protected_tie(string $state): void
    {
        $tie = TeamTie::factory()->create();
        $template = $this->template('singles', [1], [1]);
        $service = app(TeamTieGenerationService::class);
        $fixture = $service->generateFromFormat($tie, $template->format)->first();
        if ($state === 'results') {
            $fixture->fixtureResults()->create(['set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        } elseif ($state === 'played') {
            $fixture->update(['match_status' => \App\Domain\Draws\Enums\FixtureState::STATUS_COMPLETED]);
        } elseif ($state === 'tie_published') {
            $tie->update(['status' => TeamTie::STATUS_PUBLISHED, 'published_at' => now()]);
        } else {
            $tie->draw->update([$state === 'draw_locked' ? 'locked' : 'published' => true]);
        }
        // A second template would create a new fixture if the lifecycle guard failed.
        TeamEventFormatRubber::create(array_merge($template->getAttributes(), ['id' => null, 'sequence' => 2]));
        try {
            $service->generateFromFormat($tie->fresh(), $template->format->fresh(), true);
            $this->fail('Protected rubber generation must fail.');
        } catch (\App\Domain\TeamDraw\TeamDrawConflictException $exception) {
            $this->assertStringContainsString('cannot', $exception->getMessage());
        }
        $this->assertSame(1, $tie->rubbers()->count());
        $this->assertSame(1, $fixture->fixturePlayers()->count());
    }

    public function test_snapshot_gender_and_player_reuse_rules_are_enforced(): void
    {
        $tie = TeamTie::factory()->create();
        $template = $this->template('singles', [1], [1]);
        $template->update(['gender_rule' => 'male']);
        $snapshot = ['allow_player_reuse' => false, 'rubbers' => [$template->toArray(), array_merge($template->toArray(), ['sequence' => 2])]];
        $tie->draw->update(['team_format_snapshot' => $snapshot]);
        foreach ([$tie->home_team_id, $tie->away_team_id] as $teamId) {
            TeamPlayer::create(['team_id' => $teamId, 'player_id' => Player::factory()->create(['gender' => 1])->id, 'rank' => 1]);
        }
        app(TeamTieGenerationService::class)->generateFromFormat($tie->fresh(), $template->format);
        try {
            app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie->fresh());
            $this->fail('Reuse must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('reused', $exception->getMessage());
        }
        $snapshot['allow_player_reuse'] = true;
        $tie->draw->update(['team_format_snapshot' => $snapshot]);
        app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie->fresh());
        $tie->homeTeam->team_players->first()->player->update(['gender' => 2]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires all players to be male');
        app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie->fresh());
    }

    public function test_publication_readiness_uses_snapshotted_roster_limits(): void
    {
        $tie = TeamTie::factory()->create();
        $template = $this->template('singles', [1], [1]);
        foreach ([$tie->home_team_id, $tie->away_team_id] as $teamId) {
            TeamPlayer::create(['team_id' => $teamId, 'player_id' => Player::factory()->create()->id, 'rank' => 1]);
        }
        $tie->draw->update(['team_format_snapshot' => ['min_roster_size' => 2, 'max_roster_size' => 6, 'rubbers' => [$template->toArray()]]]);
        app(TeamTieGenerationService::class)->generateFromFormat($tie->fresh(), $template->format);
        $template->format->update(['min_roster_size' => 1]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 2');
        app(\App\Services\TeamTieValidationService::class)->assertTieComplete($tie->fresh());
    }

    public function test_null_positions_preserve_legacy_rank_selection(): void
    {
        $tie = TeamTie::factory()->create();
        $home = Player::factory()->create();
        TeamPlayer::create(['team_id' => $tie->home_team_id, 'player_id' => $home->id, 'rank' => 2]);
        $template = $this->template('reverse_singles', [1], [1]);
        $template->update(['home_positions' => null, 'away_positions' => null, 'reverse_from_position' => 2]);
        $fixture = app(TeamTieGenerationService::class)->generateFromFormat($tie, $template->format)->first();
        $this->assertSame($home->id, $fixture->fixturePlayers()->first()->team1_id);
    }

    #[DataProvider('invalidPositions')]
    public function test_invalid_explicit_pairings_are_rejected(array $home, ?array $away): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(TeamEventFormatDefinitionValidator::class)->validate([
            'min_roster_size' => 6, 'max_roster_size' => 6,
            'rubbers' => [['sequence' => 1, 'rubber_code' => 'doubles', 'player_count_per_team' => 2, 'home_positions' => $home, 'away_positions' => $away]],
        ]);
    }
}
