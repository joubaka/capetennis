<?php

declare(strict_types=1);

/*
 * Preview: php scripts/apply-event-233-final-schedule.php
 * Apply:   php scripts/apply-event-233-final-schedule.php --apply
 *
 * Optional overrides: --event=233 --date=2026-09-06 --venue="Hermanus Sports Club"
 */

use App\Models\{Draw, DrawAuditLog};
use App\Services\ScheduleEngine;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['apply', 'event::', 'date::', 'venue::']);
$apply = array_key_exists('apply', $options);
$eventId = (int) ($options['event'] ?? 233);
$date = (string) ($options['date'] ?? '2026-09-06');
$venueName = trim((string) ($options['venue'] ?? 'Hermanus Sports Club'));

if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "The --date value must use YYYY-MM-DD.\n");
    exit(2);
}

$normalise = static fn (string $value): string => strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));

$plan = [
    'u13girls' => [
        1 => ['08:00:00', '2'],
        2 => ['08:00:00', '5'],
        4 => ['08:00:00', '7'],
        5 => ['08:00:00', '8'],
    ],
    'u10bboys' => [
        1 => ['08:45:00', '7'],
        2 => ['08:45:00', '8'],
    ],
    'u10bgirls' => [
        1 => ['09:30:00', '7'],
        2 => ['09:30:00', '8'],
        7 => ['10:15:00', '7'],
        3 => ['10:30:00', '8'],
        4 => ['11:00:00', '7'],
        8 => ['11:15:00', '8'],
        5 => ['12:00:00', '7'],
        6 => ['12:00:00', '8'],
        9 => ['12:45:00', '7'],
    ],
];

try {
    $event = DB::table('events')->where('id', $eventId)->first();
    if (! $event) {
        throw new RuntimeException("Event {$eventId} was not found.");
    }

    $venueMatches = DB::table('venues as v')
        ->join('draw_venues as dv', 'dv.venue_id', '=', 'v.id')
        ->join('draws as d', 'd.id', '=', 'dv.draw_id')
        ->where('d.event_id', $eventId)
        ->whereRaw('LOWER(v.name) = ?', [strtolower($venueName)])
        ->select('v.id', 'v.name')
        ->distinct()
        ->get();
    if ($venueMatches->count() !== 1) {
        throw new RuntimeException("Expected exactly one event venue named '{$venueName}', found {$venueMatches->count()}.");
    }
    $venue = $venueMatches->first();

    $drawsByKey = DB::table('draws')->where('event_id', $eventId)->get()
        ->groupBy(fn ($draw) => $normalise((string) $draw->drawName));
    $draws = [];
    foreach (array_keys($plan) as $key) {
        $matches = $drawsByKey->get($key, collect());
        if ($matches->count() !== 1) {
            throw new RuntimeException("Expected exactly one {$key} draw in event {$eventId}, found {$matches->count()}.");
        }
        $draws[$key] = Draw::findOrFail((int) $matches->first()->id);
        if ($draws[$key]->locked) {
            throw new RuntimeException("{$draws[$key]->drawName} is locked. Unlock it before applying this schedule.");
        }
        if (! $draws[$key]->venues()->reorder()->where('venues.id', $venue->id)->exists()) {
            throw new RuntimeException("{$venue->name} is not assigned to {$draws[$key]->drawName}.");
        }
    }

    $activeCourts = DB::table('event_venue_courts')
        ->where('event_id', $eventId)
        ->where('venue_id', $venue->id)
        ->where('active', true)
        ->pluck('label')
        ->map(fn ($court) => (string) $court);
    foreach (collect($plan)->flatMap(fn ($matches) => array_column($matches, 1))->unique() as $court) {
        if (! $activeCourts->contains((string) $court)) {
            throw new RuntimeException("Court {$court} is not active at {$venue->name} for event {$eventId}.");
        }
    }

    $fixtures = [];
    foreach ($plan as $key => $matches) {
        $fixtureRows = DB::table('fixtures')
            ->where('draw_id', $draws[$key]->id)
            ->whereIn('match_nr', array_keys($matches))
            ->get()
            ->groupBy(fn ($fixture) => (int) $fixture->match_nr);
        foreach (array_keys($matches) as $matchNumber) {
            $found = $fixtureRows->get($matchNumber, collect());
            if ($found->count() !== 1) {
                throw new RuntimeException("Expected one {$draws[$key]->drawName} Match {$matchNumber}, found {$found->count()}.");
            }
            $fixtures[$key][$matchNumber] = $found->first();
        }
    }

    $fullyReplacedDrawKeys = ['u13girls', 'u10bgirls'];
    $protectedFixtureIds = collect($fullyReplacedDrawKeys)
        ->flatMap(fn ($key) => DB::table('fixtures')->where('draw_id', $draws[$key]->id)->pluck('id'))
        ->merge(collect($fixtures['u10bboys'])->pluck('id'))
        ->map(fn ($id) => (int) $id)
        ->values();
    $played = DB::table('fixture_results')->whereIn('fixture_id', $protectedFixtureIds)->count();
    if ($played > 0) {
        throw new RuntimeException("Refusing to replace this schedule because {$played} protected fixture result record(s) exist.");
    }

    $desired = collect($plan)->flatMap(function ($matches, $key) use ($fixtures, $date, $draws) {
        return collect($matches)->map(function ($slot, $matchNumber) use ($fixtures, $date, $draws, $key) {
            return [
                'draw_id' => (int) $draws[$key]->id,
                'draw_name' => (string) $draws[$key]->drawName,
                'fixture_id' => (int) $fixtures[$key][$matchNumber]->id,
                'match_nr' => (int) $matchNumber,
                'time' => $date.' '.$slot[0],
                'court' => (string) $slot[1],
            ];
        });
    })->sortBy(['time', 'court'])->values();

    echo 'Database: '.DB::connection()->getDatabaseName().PHP_EOL;
    echo "Event: {$eventId} | Venue: {$venue->name} | Date: {$date}".PHP_EOL;
    echo $apply ? "Mode: APPLY\n" : "Mode: DRY RUN (the transaction will be rolled back)\n";

    DB::beginTransaction();
    try {
        DB::table('events')->where('id', $eventId)->lockForUpdate()->get();
        DB::table('venues')->where('id', $venue->id)->lockForUpdate()->get();
        DB::table('draws')->whereIn('id', collect($draws)->pluck('id'))->orderBy('id')->lockForUpdate()->get();

        foreach ($plan as $key => $matches) {
            foreach (collect($matches)->pluck(1)->unique() as $court) {
                DB::table('draw_venue_court_allocations')->insertOrIgnore([
                    'draw_id' => $draws[$key]->id,
                    'venue_id' => $venue->id,
                    'court_label' => (string) $court,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach ($fullyReplacedDrawKeys as $key) {
            $allFixtureIds = DB::table('fixtures')->where('draw_id', $draws[$key]->id)->pluck('id');
            DB::table('order_of_plays')->where('draw_id', $draws[$key]->id)
                ->orWhereIn('fixture_id', $allFixtureIds)->delete();
            DB::table('fixtures')->where('draw_id', $draws[$key]->id)->update(['scheduled' => 0]);
        }

        $boysFixtureIds = collect($fixtures['u10bboys'])->pluck('id');
        DB::table('order_of_plays')->whereIn('fixture_id', $boysFixtureIds)->delete();
        DB::table('fixtures')->whereIn('id', $boysFixtureIds)->update(['scheduled' => 0]);

        $engine = app(ScheduleEngine::class);
        foreach ($desired as $slot) {
            $engine->saveFixture(
                $draws[$normalise($slot['draw_name'])],
                $slot['fixture_id'],
                $slot['time'],
                (int) $venue->id,
                $slot['court'],
                45,
                true,
                false,
            );
        }

        $final = DB::table('order_of_plays as o')
            ->join('fixtures as f', 'f.id', '=', 'o.fixture_id')
            ->join('draws as d', 'd.id', '=', 'f.draw_id')
            ->whereIn('o.fixture_id', $desired->pluck('fixture_id'))
            ->select('d.drawName', 'f.match_nr', 'o.time', 'o.court', 'o.duration_minutes')
            ->orderBy('o.time')
            ->orderByRaw('CAST(o.court AS UNSIGNED)')
            ->get();

        if ($final->count() !== $desired->count()) {
            throw new RuntimeException("Expected {$desired->count()} final assignments, found {$final->count()}.");
        }

        DrawAuditLog::record($draws['u13girls']->id, 'event_233_final_schedule_script', null, [
            'event_id' => $eventId,
            'venue_id' => (int) $venue->id,
            'date' => $date,
            'assignments' => $desired->count(),
        ]);

        if ($apply) {
            DB::commit();
            echo "Applied {$final->count()} assignments successfully.\n";
        } else {
            DB::rollBack();
            echo "Validated {$final->count()} assignments successfully; no database changes were saved.\n";
        }

        foreach ($final as $slot) {
            printf("%-14s Match %-2d  %s  Court %s\n", $slot->drawName, $slot->match_nr, $slot->time, $slot->court);
        }
    } catch (Throwable $exception) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        throw $exception;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAILED: '.$exception->getMessage().PHP_EOL);
    exit(1);
}
