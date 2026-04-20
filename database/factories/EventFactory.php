<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $end = (clone $start)->modify('+3 days');

        return [
            'name' => $this->faker->sentence(3),
            'information' => $this->faker->paragraph(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'email' => $this->faker->safeEmail(),
            'organizer' => $this->faker->name(),
            'entryFee' => $this->faker->randomFloat(2, 0, 500),
            'deadline' => $this->faker->numberBetween(0, 30),
            'withdrawal_deadline' => $start->format('Y-m-d H:i:s'),
            'eventType' => null,
            'status' => 'active',
            'venue_notes' => null,
            'logo' => null,
            'published' => true,
            'signUp' => true,
            'series_id' => null,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['published' => false]);
    }

    public function closedSignUp(): static
    {
        return $this->state(['signUp' => false]);
    }
}
