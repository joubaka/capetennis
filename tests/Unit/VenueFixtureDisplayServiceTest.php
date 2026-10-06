<?php

namespace Tests\Unit;

use App\Models\TeamFixture;
use App\Models\TeamTie;
use App\Services\VenueFixtureDisplayService;
use PHPUnit\Framework\TestCase;

class VenueFixtureDisplayServiceTest extends TestCase
{
    private function fixture(int $id, int $draw, int $round, ?string $start, ?int $tie = null, int $home = 1, int $away = 2): TeamFixture
    {
        $fixture = new TeamFixture;
        $fixture->setDateFormat('Y-m-d H:i:s');
        $fixture->fill(['draw_id' => $draw, 'round_nr' => $round, 'scheduled_at' => $start,
            'region1' => $home, 'region2' => $away]);
        $fixture->id = $id;
        $fixture->setRelation('teamTie', $tie ? new TeamTie(['id' => $tie, 'draw_id' => $draw]) : null);
        if ($tie) $fixture->teamTie->id = $tie;
        return $fixture;
    }

    public function test_real_ties_remain_separate_and_waves_and_next_later_tie_are_chronological(): void
    {
        $fixtures = collect([
            $this->fixture(3, 1, 1, '2026-10-09 09:15', 10),
            $this->fixture(1, 1, 1, '2026-10-09 08:30', 10),
            $this->fixture(2, 1, 1, '2026-10-09 08:30', 11),
            $this->fixture(4, 1, 1, '2026-10-09 10:00', 12),
            $this->fixture(5, 1, 1, null, 13),
        ]);
        $groups = (new VenueFixtureDisplayService)->groups($fixtures);
        self::assertCount(4, $groups);
        self::assertSame([1, 3], $groups[0]['waves']->flatten()->pluck('id')->all());
        self::assertSame('10:00', $groups[0]['next_start']->format('H:i'));
        self::assertSame('10:00', $groups[1]['next_start']->format('H:i'));
        self::assertNull($groups[2]['next_start']);
        self::assertNull($groups[3]['start']);
        self::assertSame([1, 2, 3, 4, 5], $groups->pluck('waves')->flatten()->pluck('id')->sort()->values()->all());
    }

    public function test_legacy_groups_respect_draw_round_region_and_do_not_merge_unknown_opponents(): void
    {
        $fixtures = collect([
            $this->fixture(1, 1, 1, '2026-10-09 08:30'),
            $this->fixture(2, 1, 1, '2026-10-09 09:15', null, 2, 1),
            $this->fixture(3, 2, 1, '2026-10-09 08:30'),
            $this->fixture(4, 1, 2, '2026-10-09 08:30'),
            $this->fixture(5, 1, 1, '2026-10-09 08:30', null, 1, 3),
            $this->fixture(6, 1, 1, null, null, 0, 0),
            $this->fixture(7, 1, 1, null, null, 0, 0),
        ]);
        $groups = (new VenueFixtureDisplayService)->groups($fixtures);
        self::assertCount(6, $groups);
        self::assertSame([1, 2], $groups[0]['waves']->flatten()->pluck('id')->all());
    }

    public function test_mismatched_tie_is_not_used_for_grouping(): void
    {
        $fixture = $this->fixture(1, 1, 1, null, 10);
        $fixture->teamTie->draw_id = 2;
        $other = $this->fixture(2, 1, 1, null);
        self::assertCount(1, (new VenueFixtureDisplayService)->groups(collect([$fixture, $other])));
    }
}
