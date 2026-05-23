<?php

namespace Database\Factories;

use App\Models\RankingList;
use Illuminate\Database\Eloquent\Factories\Factory;

class RankingListFactory extends Factory
{
    protected $model = RankingList::class;

    public function definition(): array
    {
        return [
            'series_id'          => null, // caller must supply
            'category_id'        => 1,
            'best_num_of_scores' => null,
        ];
    }
}
