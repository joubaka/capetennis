<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamPlayerFactory extends Factory
{
    protected $model = TeamPlayer::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'rank' => $this->faker->numberBetween(1, 6),
            'pay_status' => 0,
        ];
    }

    public function paid(): static
    {
        return $this->state(['pay_status' => 1]);
    }
}
