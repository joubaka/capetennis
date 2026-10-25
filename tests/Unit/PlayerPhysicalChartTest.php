<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\ChartController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlayerPhysicalChartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'physical_chart_test',
            'database.connections.physical_chart_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::purge('physical_chart_test');
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('surname');
        });
        Schema::create('exercise_names', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('exercises', function (Blueprint $table): void {
            $table->id();
            $table->integer('player_id');
            $table->integer('exersize_name_id');
            $table->integer('score');
        });
        DB::table('players')->insert([
            ['id' => 1, 'name' => 'Test', 'surname' => 'Player'],
            ['id' => 2, 'name' => 'Other', 'surname' => 'Player'],
        ]);
    }

    public function test_it_averages_each_exercise_for_only_the_requested_player(): void
    {
        DB::table('exercise_names')->insert([
            ['id' => 1, 'name' => 'Sprint'],
            ['id' => 2, 'name' => 'Jump'],
        ]);
        DB::table('exercises')->insert([
            ['player_id' => 1, 'exersize_name_id' => 1, 'score' => 10],
            ['player_id' => 1, 'exersize_name_id' => 1, 'score' => 15],
            ['player_id' => 1, 'exersize_name_id' => 2, 'score' => 30],
            ['player_id' => 2, 'exersize_name_id' => 1, 'score' => 100],
        ]);

        $data = (new ChartController)->physical(1);

        $this->assertEquals([12.5, 30], $data['x']);
        $this->assertSame(['Sprint', 'Jump'], $data['y']);
        $this->assertSame('Test Player', $data['player']);
    }

    public function test_it_returns_a_stable_empty_chart(): void
    {
        $this->assertSame(
            ['x' => [], 'y' => [], 'player' => 'Test Player'],
            (new ChartController)->physical(1),
        );
    }

    public function test_it_rejects_a_missing_player(): void
    {
        $this->expectException(ModelNotFoundException::class);

        (new ChartController)->physical(999);
    }

    public function test_a_missing_exercise_label_keeps_the_score_and_label_aligned(): void
    {
        DB::table('exercises')->insert(['player_id' => 1, 'exersize_name_id' => 999, 'score' => 20]);

        $data = (new ChartController)->physical(1);

        $this->assertSame([20], $data['x']);
        $this->assertSame(['Unknown exercise'], $data['y']);
    }
}
