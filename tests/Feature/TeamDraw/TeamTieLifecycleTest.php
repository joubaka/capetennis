<?php

namespace Tests\Feature\TeamDraw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamTie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamTieLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Draw $draw;
    private TeamTie $tie;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        DB::table('eventtypes')->insert(['id' => 3, 'name' => 'Team event', 'type' => EventType::TEAM]);
        $this->draw = Draw::factory()->create(['event_id' => Event::factory()->create(['eventType' => 3])->id]);
        $this->tie = TeamTie::create(['draw_id' => $this->draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => Team::factory()->create()->id, 'away_team_id' => Team::factory()->create()->id,
            'status' => TeamTie::STATUS_DRAFT]);
    }

    private function rubber(int $count = 1): TeamFixture
    {
        $fixture = TeamFixture::create(['draw_id' => $this->draw->id, 'team_tie_id' => $this->tie->id,
            'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1, 'rubber_sequence' => 1,
            'rubber_code' => $count === 1 ? 'singles' : 'doubles', 'player_count_per_team' => $count]);
        foreach (range(1, $count) as $slot) {
            TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => $slot,
                'team1_id' => Player::factory()->create()->id, 'team2_id' => Player::factory()->create()->id]);
        }
        return $fixture;
    }

    public function test_empty_tie_cannot_be_validated_or_published(): void
    {
        $this->postJson(route('team-draw.ties.validate', $this->tie))->assertUnprocessable();
        $this->tie->update(['status' => TeamTie::STATUS_VALIDATED]);
        $this->postJson(route('team-draw.ties.publish', $this->tie))->assertUnprocessable();
        $this->assertNull($this->tie->fresh()->published_at);
    }

    public function test_partial_doubles_and_duplicate_partners_cannot_be_validated(): void
    {
        $fixture = $this->rubber(2);
        $slots = $fixture->fixturePlayers()->orderBy('slot_no')->get();
        $slots[1]->update(['team1_id' => null]);
        $this->postJson(route('team-draw.ties.validate', $this->tie))->assertUnprocessable();
        $slots[1]->update(['team1_id' => $slots[0]->team1_id]);
        $this->postJson(route('team-draw.ties.validate', $this->tie))->assertUnprocessable();
        $this->assertSame(TeamTie::STATUS_DRAFT, $this->tie->fresh()->status);
    }

    public function test_missing_required_rubber_blocks_validation(): void
    {
        $this->rubber();
        $format = TeamEventFormat::factory()->create(['event_id' => $this->draw->event_id]);
        TeamEventFormatRubber::create(['format_id' => $format->id, 'sequence' => 2,
            'rubber_code' => 'singles', 'name' => 'Required singles', 'player_count_per_team' => 1, 'is_required' => true]);
        $this->draw->update(['team_event_format_id' => $format->id]);
        $this->postJson(route('team-draw.ties.validate', $this->tie))->assertUnprocessable();
    }

    public function test_publish_rechecks_assignments_after_validation(): void
    {
        $fixture = $this->rubber(2);
        $this->postJson(route('team-draw.ties.validate', $this->tie))->assertOk();
        $fixture->fixturePlayers()->where('slot_no', 2)->delete();
        $this->postJson(route('team-draw.ties.publish', $this->tie))->assertUnprocessable();
        $this->assertSame(TeamTie::STATUS_VALIDATED, $this->tie->fresh()->status);
        $this->assertNull($this->tie->fresh()->published_at);
    }

    public function test_super_user_cannot_reopen_published_or_completed_tie(): void
    {
        $this->rubber();
        foreach ([TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED] as $status) {
            $this->tie->update(['status' => $status, 'published_at' => now()]);
            $this->postJson(route('team-draw.ties.validate', $this->tie))->assertStatus(409);
            $this->assertSame($status, $this->tie->fresh()->status);
            $this->assertNotNull($this->tie->fresh()->published_at);
        }
    }

    public function test_super_user_cannot_validate_or_publish_in_locked_or_published_draw(): void
    {
        $this->rubber();
        foreach (['locked', 'published'] as $flag) {
            $this->draw->update(['locked' => false, 'published' => false, $flag => true]);
            $this->postJson(route('team-draw.ties.validate', $this->tie))->assertStatus(409);
            $this->tie->update(['status' => TeamTie::STATUS_VALIDATED]);
            $this->postJson(route('team-draw.ties.publish', $this->tie))->assertStatus(409);
        }
    }
}
