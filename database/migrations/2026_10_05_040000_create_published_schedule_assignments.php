<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('published_schedule_assignments')) Schema::create('published_schedule_assignments', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('event_id'); $table->unsignedBigInteger('draw_id');
            $table->string('fixture_kind', 16); $table->unsignedBigInteger('fixture_id');
            $table->dateTime('scheduled_at'); $table->unsignedBigInteger('venue_id')->nullable();
            $table->string('court', 50)->nullable(); $table->unsignedInteger('duration')->default(75);
            $table->dateTime('published_at'); $table->unsignedBigInteger('published_by')->nullable(); $table->timestamps();
            $table->unique(['event_id', 'fixture_kind', 'fixture_id'], 'published_schedule_fixture_unique');
            $table->index(['event_id', 'scheduled_at', 'venue_id'], 'published_schedule_calendar_index');
            $table->index('draw_id');
        });
        if (! Schema::hasTable('schedule_publication_baselines')) Schema::create('schedule_publication_baselines', function (Blueprint $table) {
            $table->unsignedBigInteger('draw_id')->primary();
            $table->dateTime('captured_at');
        });
        // Freeze existing published schedules before private working changes are possible.
        DB::table('draws')->where('oop_published', true)->orderBy('id')->chunkById(100, function ($draws) {
            foreach ($draws as $draw) {
                DB::transaction(function () use ($draw) {
                    DB::table('venues')->orderBy('id')->limit(1)->lockForUpdate()->get();
                    DB::table('events')->where('id', $draw->event_id)->lockForUpdate()->get();
                    $draw = DB::table('draws')->where('id', $draw->id)->lockForUpdate()->first();
                    if (! $draw || ! $draw->oop_published || DB::table('schedule_publication_baselines')->where('draw_id', $draw->id)->exists()) return;
                $rows = DB::table('order_of_plays')->join('fixtures', 'fixtures.id', '=', 'order_of_plays.fixture_id')
                    ->where('fixtures.draw_id', $draw->id)->whereNotNull('order_of_plays.time')
                    ->orderBy('order_of_plays.id')->select('fixtures.id as fixture_id', 'order_of_plays.time as scheduled_at', 'order_of_plays.venue_id', 'order_of_plays.court', 'order_of_plays.duration_minutes as duration')->get();
                foreach ($rows as $row) $this->capture($draw, 'individual', $row);
                $rows = DB::table('team_fixtures')->where('draw_id', $draw->id)->whereNotNull('scheduled_at')
                    ->where(function ($query) use ($draw) {
                        if ($draw->team_scoring_rules === null) return;
                        $query->whereIn('team_tie_id', DB::table('team_ties')->where('draw_id', $draw->id)->whereNotNull('published_at')->whereIn('status', ['published', 'completed'])->select('id'));
                    })->select('id as fixture_id', 'scheduled_at', 'venue_id', 'court_label as court', 'duration_min as duration')->get();
                foreach ($rows as $row) $this->capture($draw, 'team', $row);
                DB::table('schedule_publication_baselines')->insertOrIgnore(['draw_id' => $draw->id, 'captured_at' => now()]);
                });
            }
        });
    }

    private function capture(object $draw, string $kind, object $row): void
    {
        DB::table('published_schedule_assignments')->insertOrIgnore(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
            'fixture_kind' => $kind, 'fixture_id' => $row->fixture_id, 'scheduled_at' => $row->scheduled_at,
            'venue_id' => $row->venue_id, 'court' => $row->court, 'duration' => $row->duration ?: ($kind === 'team' ? 120 : 75),
            'published_at' => now(), 'published_by' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (DB::table('published_schedule_assignments')->exists()) throw new RuntimeException('Published schedule history must be preserved. Export and review it before rolling back.');
        Schema::dropIfExists('published_schedule_assignments');
        Schema::dropIfExists('schedule_publication_baselines');
    }
};
