<?php

namespace Database\Factories;

use App\Models\Fixture;
use Illuminate\Database\Eloquent\Factories\Factory;

class FixtureFactory extends Factory
{
    protected $model = Fixture::class;

    public function definition(): array
    {
        return [
            'draw_id'              => null,
            'stage'                => 'MAIN',
            'round'                => 1,
            'match_nr'             => $this->faker->unique()->numberBetween(1, 9999),
            'match_status'         => 0,
            'registration1_id'     => null,
            'registration2_id'     => null,
            'winner_registration'  => null,
            'parent_fixture_id'    => null,
            'loser_parent_fixture_id' => null,
            'draw_group_id'        => null,
        ];
    }
}
