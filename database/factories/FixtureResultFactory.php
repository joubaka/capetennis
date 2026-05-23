<?php

namespace Database\Factories;

use App\Models\FixtureResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class FixtureResultFactory extends Factory
{
    protected $model = FixtureResult::class;

    public function definition(): array
    {
        return [
            'fixture_id'          => null,
            'set_nr'              => 1,
            'registration1_score' => 6,
            'registration2_score' => 4,
            'winner_registration' => null,
            'loser_registration'  => null,
        ];
    }
}
