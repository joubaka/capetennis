<?php

namespace Database\Factories;

use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeriesFactory extends Factory
{
    protected $model = Series::class;

    public function definition(): array
    {
        return [
            'name'                   => $this->faker->words(3, true),
            'year'                   => $this->faker->year(),
            'rank_type'              => null,
            'leaderboard_published'  => 0,
            'best_num_of_scores'     => 2,
            'points_template_created'=> 0,
            'auto_award_rule'        => true,
            'use_third_score_tiebreak' => true,
            'use_head_to_head_tiebreak' => true,
        ];
    }
}
