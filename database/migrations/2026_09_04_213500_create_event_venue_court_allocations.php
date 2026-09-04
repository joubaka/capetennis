<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_venue_courts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('label', 50);
            $table->string('ball_type', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['event_id', 'venue_id', 'label'], 'event_venue_court_label_unique');
            $table->index(['event_id', 'venue_id', 'active'], 'event_venue_court_active_index');
        });

        Schema::create('draw_venue_court_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('draw_id');
            $table->unsignedBigInteger('venue_id');
            $table->string('court_label', 50);
            $table->timestamps();
            $table->unique(['draw_id', 'venue_id', 'court_label'], 'draw_venue_court_unique');
            $table->index(['venue_id', 'court_label'], 'draw_venue_court_lookup_index');
        });

        if (Schema::hasTable('event_venues')) {
            $counts = DB::table('event_venues')->get()->mapWithKeys(fn ($assignment) => [
                $assignment->event_id.'|'.$assignment->venue_id => [
                    'event_id' => (int) $assignment->event_id,
                    'venue_id' => (int) $assignment->venue_id,
                    'courts' => max(1, (int) $assignment->num_courts),
                ],
            ]);
            if (Schema::hasTable('draw_venues') && Schema::hasTable('draws')) {
                $legacyDrawCounts = DB::table('draw_venues')->join('draws', 'draws.id', '=', 'draw_venues.draw_id')
                    ->whereNotNull('draws.event_id')->select('draws.event_id', 'draw_venues.venue_id')
                    ->selectRaw('MAX(draw_venues.num_courts) AS courts')
                    ->groupBy('draws.event_id', 'draw_venues.venue_id')->get();
                foreach ($legacyDrawCounts as $assignment) {
                    $key = $assignment->event_id.'|'.$assignment->venue_id;
                    $current = $counts->get($key);
                    $counts[$key] = [
                        'event_id' => (int) $assignment->event_id,
                        'venue_id' => (int) $assignment->venue_id,
                        'courts' => max((int) ($current['courts'] ?? 0), 1, (int) $assignment->courts),
                    ];
                }
            }
            if (Schema::hasTable('venues') && Schema::hasColumn('venues', 'num_courts')) {
                $venueCounts = DB::table('venues')->whereIn('id', $counts->pluck('venue_id')->unique())
                    ->pluck('num_courts', 'id');
                foreach ($counts as $key => $assignment) {
                    $assignment['courts'] = max($assignment['courts'], (int) ($venueCounts[$assignment['venue_id']] ?? 0));
                    $counts[$key] = $assignment;
                }
            }
            foreach ($counts as $assignment) {
                foreach (range(1, $assignment['courts']) as $court) {
                    DB::table('event_venue_courts')->insertOrIgnore([
                        'event_id' => $assignment['event_id'], 'venue_id' => $assignment['venue_id'],
                        'label' => (string) $court, 'ball_type' => null, 'active' => true,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_venue_court_allocations');
        Schema::dropIfExists('event_venue_courts');
    }
};
