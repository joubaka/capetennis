<?php

namespace Database\Factories;

use App\Models\Draw;
use App\Models\Team;
use App\Models\TeamTie;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamTieFactory extends Factory
{
    protected $model = TeamTie::class;

    public function definition(): array
    {
        return [
            'draw_id'      => Draw::factory(),
            'round_nr'     => 1,
            'tie_nr'       => 1,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'status'       => TeamTie::STATUS_DRAFT,
            'winner_team_id' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status'       => TeamTie::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(['status' => TeamTie::STATUS_COMPLETED]);
    }

    public function validated(): static
    {
        return $this->state(['status' => TeamTie::STATUS_VALIDATED]);
    }
}
