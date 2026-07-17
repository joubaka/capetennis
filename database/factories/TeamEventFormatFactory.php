<?php

namespace Database\Factories;

use App\Models\TeamEventFormat;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamEventFormatFactory extends Factory
{
    protected $model = TeamEventFormat::class;

    public function definition(): array
    {
        return [
            'event_id'           => null,
            'name'               => $this->faker->words(3, true),
            'min_roster_size'    => 1,
            'max_roster_size'    => 12,
            'allow_player_reuse' => false,
            'is_default'         => false,
        ];
    }

    /** Scope to a specific event. */
    public function forEvent(Event $event): static
    {
        return $this->state(['event_id' => $event->id]);
    }

    /** Create a global (event-agnostic) format. */
    public function global(): static
    {
        return $this->state(['event_id' => null]);
    }

    /** Mark as the default format. */
    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
