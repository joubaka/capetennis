<?php

namespace Tests\Unit;

use App\Models\{Category, CategoryEvent, Draw, Team, TeamRegion};
use App\Services\TeamDrawSideResolver;
use PHPUnit\Framework\TestCase;

class TeamDrawSideLabelTest extends TestCase
{
    public function test_combined_side_uses_one_region_and_age(): void
    {
        $team = new Team(['name' => 'Long boys team name']);
        $team->id = 12;
        $region = new TeamRegion(['region_name' => 'Overberg High Schools', 'short_name' => 'OVB']);
        $team->setRelation('regions', $region);
        $category = new CategoryEvent;
        $category->setRelation('category', new Category(['name' => 'u/15 Boys']));
        $team->setRelation('category', $category);
        $draw = (new Draw)->forceFill(['team_draw_selection' => ['mixed_sides' => [12 => ['name' => 'Long boys + Long girls']]]]);
        $resolver = new TeamDrawSideResolver;

        $this->assertSame('OVB U/15', $resolver->label($draw, $team));
        $region->short_name = ' ';
        $this->assertSame('Overberg High Schools U/15', $resolver->label($draw, $team));
        $this->assertSame('Long boys team name', $resolver->label(new Draw, $team));
    }
}
