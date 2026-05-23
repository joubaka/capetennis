<?php

namespace Database\Factories;

use App\Models\DrawGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrawGroupFactory extends Factory
{
    protected $model = DrawGroup::class;

    public function definition(): array
    {
        return [
            'draw_id' => null,
            'name'    => $this->faker->randomElement(['A', 'B', 'C', 'D']),
        ];
    }
}
