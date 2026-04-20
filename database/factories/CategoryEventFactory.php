<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryEventFactory extends Factory
{
    protected $model = CategoryEvent::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'category_id' => Category::factory(),
            'entry_fee' => $this->faker->randomFloat(2, 0, 300),
            'ordering' => $this->faker->numberBetween(1, 20),
            'nominations_published' => false,
            'locked_at' => null,
        ];
    }
}
