<?php

namespace Tests\Unit;

use App\Models\DrawSetting;
use PHPUnit\Framework\TestCase;

class AllPlayersPlayoffPresetTest extends TestCase
{
    public function test_every_assigned_player_in_uneven_groups_beyond_forty_is_covered_once(): void
    {
        $counts = [12, 11, 10, 9];
        $preset = DrawSetting::allPlayersPreset(count($counts), max($counts));
        $seen = [];
        foreach ($preset['config'] as $playoff) {
            $this->assertTrue($playoff['enabled']);
            $this->assertSame(4, $playoff['size']);
            foreach ($playoff['positions'] as $position) foreach ($counts as $group => $count) {
                if ($position <= $count) $seen[] = $group.':'.$position;
            }
        }
        $this->assertCount(array_sum($counts), $seen);
        $this->assertSame($seen, array_values(array_unique($seen)));
        $this->assertSame([12], $preset['config'][11]['positions']);
        $this->assertCount(count($preset['config']), array_unique(array_column($preset['config'], 'slug')));
    }

    public function test_single_group_pairs_ranks_and_odd_group_counts_use_bye_capable_sizes(): void
    {
        $single = DrawSetting::allPlayersPreset(1, 5);
        $this->assertSame([[1, 2], [3, 4], [5]], array_column($single['config'], 'positions'));
        $this->assertSame([2, 2, 2], array_column($single['config'], 'size'));
        $this->assertSame([4, 4], array_column(DrawSetting::allPlayersPreset(3, 2)['config'], 'size'));
        $this->assertSame([32], array_column(DrawSetting::allPlayersPreset(26, 1)['config'], 'size'));
    }

    public function test_existing_templates_are_unchanged_and_empty_groups_create_no_stages(): void
    {
        $before = DrawSetting::getPresetTemplates();
        DrawSetting::allPlayersPreset(4, 20);
        $this->assertSame($before, DrawSetting::getPresetTemplates());
        $this->assertSame([true, true, true, false, false, false, false, false, false, false],
            array_column($before['4_groups_4s']['config'], 'enabled'));
        $this->assertSame([], DrawSetting::allPlayersPreset(4, 0)['config']);
    }

    public function test_unsupported_group_count_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DrawSetting::allPlayersPreset(27, 4);
    }
}
