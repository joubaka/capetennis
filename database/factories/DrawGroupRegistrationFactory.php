<?php

namespace Database\Factories;

use App\Models\DrawGroupRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrawGroupRegistrationFactory extends Factory
{
    protected $model = DrawGroupRegistration::class;

    public function definition(): array
    {
        return [
            'draw_group_id'   => null,
            'registration_id' => null,
            'seed'            => null,
        ];
    }
}
