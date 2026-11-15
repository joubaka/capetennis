<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Models\{Draw, DrawAuditLog, Event, Fixture};
use App\Services\DrawService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Replace an unplayed draw and its working bookings as one audited operation. */
final class RoundRobinRebuildService
{
    public function revision(Draw $draw): string
    {
        $draw = $draw->fresh('settings');
        $fixtures = $draw->drawFixtures()->with('fixtureResults')->orderBy('id')->get();
        $ids = $fixtures->modelKeys();
        $bookings = [];
        foreach (['order_of_plays', 'schedules'] as $table) {
            $bookings[$table] = DB::table($table)->where(fn ($query) => $query->where('draw_id', $draw->id)->orWhereIn('fixture_id', $ids))->orderBy('id')->get()->all();
        }
        return hash('sha256', json_encode([
            $draw->only(['id', 'event_id', 'locked', 'published', 'oop_published']), $draw->settings?->getAttributes(),
            app(GroupAssignmentService::class)->revision($draw),
            $fixtures->map(fn ($fixture) => [$fixture->getAttributes(), $fixture->fixtureResults->sortBy('id')->map->getAttributes()->all()])->all(),
            $bookings,
            DB::table('published_schedule_assignments')->where('event_id', $draw->event_id)->where('draw_id', $draw->id)->orderBy('fixture_kind')->orderBy('fixture_id')->get()->all(),
        ]));
    }

    public function rebuild(Draw $draw, ?string $revision = null, ?string $rebuildRevision = null): Draw
    {
        return DB::transaction(function () use ($draw, $revision, $rebuildRevision) {
            $eventId = (int) $draw->event_id;
            Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            if ((int) $draw->event_id !== $eventId) {
                throw ValidationException::withMessages(['draw' => 'The draw event changed. Reload before rebuilding.']);
            }
            if ($draw->isTeamDraw() || $draw->usesFlexibleMonrad()
                || ($draw->settings?->workflow && !in_array($draw->settings->workflow, ['round_robin', 'round_robin_playoffs', 'round_robin_round_robin'], true))) {
                throw ValidationException::withMessages(['draw' => 'Use this draw format’s own fixture preparation controls.']);
            }
            RoundRobinGenerationService::assertRegenerable($draw);
            if (!is_string($revision) || !hash_equals(app(GroupAssignmentService::class)->revision($draw), $revision)) {
                throw ValidationException::withMessages(['revision' => 'Assignments changed. Reload players before generating fixtures.']);
            }
            abort_unless(is_string($rebuildRevision) && hash_equals($this->revision($draw), $rebuildRevision), 409,
                'The matches, rules or saved bookings changed. Reload players and review the rebuild again.');
            $fixtures = $draw->drawFixtures()->orderBy('id')->lockForUpdate()->get();
            $fixtureIds = $fixtures->pluck('id');
            $before = [];
            foreach (['order_of_plays', 'schedules'] as $table) {
                $bookings = DB::table($table)->where(fn ($query) => $query
                    ->where('draw_id', $draw->id)->orWhereIn('fixture_id', $fixtureIds))
                    ->orderBy('id')->lockForUpdate()->get();
                $referencedIds = $bookings->pluck('fixture_id')->filter();
                $foreignReference = Fixture::withoutGlobalScopes()->whereIn('id', $referencedIds)
                    ->where('draw_id', '!=', $draw->id)->exists();
                if ($foreignReference || $bookings->contains(fn ($booking) => $booking->draw_id !== null && (int) $booking->draw_id !== (int) $draw->id)) {
                    throw ValidationException::withMessages(['draw' => 'A saved booking has inconsistent draw ownership. Review it before rebuilding.']);
                }
                if (DB::table('published_schedule_assignments')->where('fixture_kind', 'individual')
                    ->whereIn('fixture_id', $referencedIds)->exists()) {
                    throw ValidationException::withMessages(['draw' => 'Hide the published schedule before rebuilding its fixture identities.']);
                }
                $before[$table] = $bookings->map(fn ($booking) => (array) $booking)->all();
                DB::table($table)->whereIn('id', $bookings->pluck('id'))->delete();
            }
            $draw->drawFixtures()->delete();
            app(DrawService::class)->regenerateRoundRobinFixtures($draw);
            $preparedPlayoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')->count();
            $draw->update(['oop_created' => 0]);
            DrawAuditLog::record($draw->id, 'rr_regenerated', null, [
                'before' => ['fixture_ids' => $fixtureIds->all(), 'fixtures' => $fixtures->map(fn ($fixture) => $fixture->getAttributes())->all(), 'bookings' => $before],
                'deleted_rr' => $fixtures->where('stage', 'RR')->count(),
                'deleted_playoffs' => $fixtures->where('stage', '!=', 'RR')->count(),
                'released_bookings' => count($before['order_of_plays']),
                'released_legacy_schedules' => count($before['schedules']),
                'fixture_count' => $draw->drawFixtures()->count(),
                'prepared_playoffs' => $preparedPlayoffs,
            ]);
            return $draw->fresh();
        });
    }
}
