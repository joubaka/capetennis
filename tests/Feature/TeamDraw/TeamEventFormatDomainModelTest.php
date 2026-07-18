<?php

namespace Tests\Feature\TeamDraw;

use App\Domain\TeamDraw\RubberType;
use App\Models\Draw;
use App\Models\Fixture;
use App\Models\TeamEventFormat;
use App\Models\TeamEventFormatRubber;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamEventFormatDomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_contains_ordered_rubber_definitions(): void
    {
        $format = TeamEventFormat::factory()->create();

        TeamEventFormatRubber::create([
            'format_id' => $format->id,
            'sequence' => 2,
            'rubber_code' => RubberType::DOUBLES,
            'name' => 'Doubles',
            'player_count_per_team' => 2,
            'is_required' => true,
        ]);

        TeamEventFormatRubber::create([
            'format_id' => $format->id,
            'sequence' => 1,
            'rubber_code' => RubberType::SINGLES,
            'name' => 'Singles 1',
            'player_count_per_team' => 1,
            'is_required' => true,
        ]);

        $sequences = $format->fresh('rubbers')->rubbers->pluck('sequence')->values()->all();

        $this->assertSame([1, 2], $sequences);
    }

    public function test_duplicate_sequence_within_format_is_rejected(): void
    {
        $format = TeamEventFormat::factory()->create();

        TeamEventFormatRubber::create([
            'format_id' => $format->id,
            'sequence' => 1,
            'rubber_code' => RubberType::SINGLES,
            'name' => 'Singles 1',
            'player_count_per_team' => 1,
            'is_required' => true,
        ]);

        $this->expectException(QueryException::class);

        TeamEventFormatRubber::create([
            'format_id' => $format->id,
            'sequence' => 1,
            'rubber_code' => RubberType::DOUBLES,
            'name' => 'Doubles',
            'player_count_per_team' => 2,
            'is_required' => true,
        ]);
    }

    public function test_same_sequence_can_exist_in_different_formats(): void
    {
        $formatA = TeamEventFormat::factory()->create();
        $formatB = TeamEventFormat::factory()->create();

        TeamEventFormatRubber::create([
            'format_id' => $formatA->id,
            'sequence' => 1,
            'rubber_code' => RubberType::SINGLES,
            'name' => 'Singles A',
            'player_count_per_team' => 1,
            'is_required' => true,
        ]);

        TeamEventFormatRubber::create([
            'format_id' => $formatB->id,
            'sequence' => 1,
            'rubber_code' => RubberType::SINGLES,
            'name' => 'Singles B',
            'player_count_per_team' => 1,
            'is_required' => true,
        ]);

        $this->assertDatabaseHas('team_event_format_rubbers', [
            'format_id' => $formatA->id,
            'sequence' => 1,
        ]);

        $this->assertDatabaseHas('team_event_format_rubbers', [
            'format_id' => $formatB->id,
            'sequence' => 1,
        ]);
    }

    public function test_max_roster_size_supports_default_of_twelve(): void
    {
        $format = TeamEventFormat::factory()->create();

        $this->assertSame(12, $format->max_roster_size);
    }

    public function test_draw_can_reference_team_event_format_without_affecting_individual_draws(): void
    {
        $format = TeamEventFormat::factory()->create();

        $teamDraw = Draw::factory()->create([
            'team_event_format_id' => $format->id,
        ]);

        $individualDraw = Draw::factory()->create([
            'team_event_format_id' => null,
        ]);

        Fixture::factory()->create([
            'draw_id' => $individualDraw->id,
            'round' => 1,
            'match_nr' => 1,
        ]);

        $this->assertDatabaseHas('draws', [
            'id' => $teamDraw->id,
            'team_event_format_id' => $format->id,
        ]);

        $this->assertDatabaseHas('draws', [
            'id' => $individualDraw->id,
            'team_event_format_id' => null,
        ]);

        $this->assertDatabaseHas('fixtures', [
            'draw_id' => $individualDraw->id,
            'round' => 1,
            'match_nr' => 1,
        ]);
    }
}
