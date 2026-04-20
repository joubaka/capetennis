<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->firstName(),
            'surname' => $this->faker->lastName(),
            'cellNr' => $this->faker->phoneNumber(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'userId' => null,
            'email' => $this->faker->unique()->safeEmail(),
            'dateOfBirth' => $this->faker->date('Y-m-d', '-10 years'),
            'coach' => null,
        ];
    }

    public function male(): static
    {
        return $this->state(['gender' => 'male']);
    }

    public function female(): static
    {
        return $this->state(['gender' => 'female']);
    }
}
