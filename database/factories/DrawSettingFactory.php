<?php

namespace Database\Factories;

use App\Models\DrawSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrawSettingFactory extends Factory
{
    protected $model = DrawSetting::class;

    public function definition(): array
    {
        return [
            'draw_id'        => null,
            'boxes'          => 2,
            'playoff_size'   => 4,
            'num_sets'       => 3,
            'playoff_config' => null,
        ];
    }
}
