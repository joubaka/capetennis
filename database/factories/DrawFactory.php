<?php

namespace Database\Factories;

use App\Models\Draw;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrawFactory extends Factory
{
    protected $model = Draw::class;

    public function definition(): array
    {
        return [
            'drawName'  => $this->faker->words(3, true),
            'event_id'  => null,
            'locked'    => false,
            'published' => false,
        ];
    }
}
