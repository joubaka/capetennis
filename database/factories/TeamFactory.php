<?php

namespace Database\Factories;

use App\Models\CategoryEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'num_team_members' => $this->faker->numberBetween(2, 6),
            'year' => now()->year,
            'published' => true,
            'region_id' => null,
            'category_event_id' => CategoryEvent::factory(),
            'noProfile' => false,
        ];
    }
}
