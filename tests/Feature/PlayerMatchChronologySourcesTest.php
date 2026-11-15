<?php

namespace Tests\Feature;

use App\Services\Performance\PlayerMatchChronologySources;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PlayerMatchChronologySourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']); DB::purge('sqlite');
        Schema::create('events', fn ($t) => [$t->id(), $t->string('start_date'), $t->string('end_date')]); Schema::create('draws', fn ($t) => [$t->id(), $t->integer('event_id')]);
        Schema::create('fixtures', fn ($t) => [$t->id(), $t->integer('draw_id')]); Schema::create('order_of_plays', fn ($t) => [$t->id(), $t->integer('fixture_id'), $t->integer('draw_id'), $t->string('time')]);
        DB::table('events')->insert(['id' => 1, 'start_date' => '2026-11-01', 'end_date' => '2026-11-10']); DB::table('draws')->insert(['id' => 2, 'event_id' => 1]); DB::table('fixtures')->insert(['id' => 3, 'draw_id' => 2]);
        DB::table('order_of_plays')->insert(['fixture_id' => 3, 'draw_id' => 2, 'time' => '2026-11-08 09:00:00']);
    }
    private function matchRow(): array { return ['source_id' => 'match:3', 'event_id' => 1, 'confidence_date' => '2026-11-08', 'confidence_date_basis' => 'scheduled match date']; }
    public function test_schedule_requires_exact_fixture_draw_event_date_and_unique_strict_sast_timestamp(): void
    {
        $resolver = new PlayerMatchChronologySources; $match = $this->matchRow(); $row = $resolver->resolve([$match])['match:3'];
        $this->assertSame('2026-11-08 09:00:00', $row['time']); $this->assertSame('2026-11-01', $row['earliest_date']); $this->assertSame('2026-11-10', $row['latest_date']);
        foreach (['2026-11-08', '2026-11-08 00:00:00', '2026-11-08 99:00:00', '2026-11-11 09:00:00', '2026-11-07 09:00:00'] as $invalid) {
            DB::table('order_of_plays')->update(['time' => $invalid]); $this->assertNull($resolver->resolve([$match])['match:3']['time']);
        }
        DB::table('order_of_plays')->update(['time' => '2026-11-08 09:00:00', 'draw_id' => 99]); $this->assertNull($resolver->resolve([$match])['match:3']['time']);
        DB::table('order_of_plays')->update(['draw_id' => 2]); $match['event_id'] = 99; $this->assertNull($resolver->resolve([$match])['match:3']['time']);
    }
    public function test_duplicate_booking_or_missing_schedule_stays_ambiguous_with_full_event_bounds(): void
    {
        $resolver = new PlayerMatchChronologySources; $match = $this->matchRow();
        DB::table('order_of_plays')->insert(['fixture_id' => 3, 'draw_id' => 2, 'time' => '2026-11-08 10:00:00']);
        $row = $resolver->resolve([$match])['match:3']; $this->assertNull($row['time']); $this->assertSame('2026-11-10', $row['latest_date']);
        DB::table('order_of_plays')->delete(); $this->assertNull($resolver->resolve([$match])['match:3']['time']);
    }
}
