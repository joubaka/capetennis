<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name'             => $this->faker->city() . ' ' . $this->faker->randomElement(['A', 'B', 'C']),
            'num_team_members' => $this->faker->numberBetween(4, 12),
            'year'             => date('Y'),
            'published'        => true,
            'user_id'          => User::factory(),
            'region_id'        => null,
            'category_event_id'=> null,
            'noProfile'        => false,
        ];
    }
}
