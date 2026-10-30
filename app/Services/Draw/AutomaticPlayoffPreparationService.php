<?php

namespace App\Services\Draw;

use App\Http\Controllers\Backend\RoundRobinController;
use App\Models\{Draw, DrawAuditLog};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Prepare stable qualifier placeholders at explicit draw-writing boundaries only. */
final class AutomaticPlayoffPreparationService
{
    /** Called inside the settings transaction, before saving the new configuration. */
    public function replaceUnbookedPlaceholders(Draw $draw, array $configuration): bool
    {
        $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
        $previous = $draw->settings?->playoff_config ?? [];
        $structure = fn (array $config) => collect($config)->map(fn ($entry) => [
            'slug' => strtoupper((string) ($entry['slug'] ?? 'MAIN')),
            'enabled' => (bool) ($entry['enabled'] ?? false), 'size' => (int) ($entry['size'] ?? 0),
            'positions' => array_map('intval', $entry['positions'] ?? []), 'group_order' => $entry['group_order'] ?? [],
        ])->all();
        if ($structure($previous) === $structure($configuration)) return false;
        $fixtures = $draw->drawFixtures()->where('stage', '!=', 'RR')->lockForUpdate()->get();
        if ($fixtures->isEmpty()) return false;
        $ids = $fixtures->modelKeys();
        if ($draw->locked || $draw->published || $draw->oop_published
            || $draw->drawFixtures()->where(fn ($query) => $query->withRecordedResult()->orWhereIn('match_status', [1, 2, 4]))->exists()
            || $fixtures->contains(fn ($fixture) => (int) $fixture->match_status !== 0 || $fixture->registration1_id || $fixture->registration2_id)
            || !DrawAuditLog::where('draw_id', $draw->id)->where('action', 'bracket_prepared_for_schedule')->exists()
            || DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->exists()
            || DB::table('order_of_plays')->whereIn('fixture_id', $ids)->exists()
            || DB::table('schedules')->whereIn('fixture_id', $ids)->exists()) {
            throw ValidationException::withMessages(['playoff_config' => 'Existing playoffs have players, results, saved times or publication protection. Remove saved playoff times and hide the public schedule before changing unplayed placeholder stages. Playoffs with players or results require the existing draw review workflow.']);
        }
        DrawAuditLog::record($draw->id, 'playoff_placeholders_replaced', null, [
            'previous_config' => $previous, 'replacement_config' => $configuration,
            'before_fixtures' => $fixtures->map(fn ($fixture) => $fixture->getAttributes())->all(),
        ]);
        $draw->drawFixtures()->whereIn('id', $ids)->delete();
        return true;
    }

    public function prepare(Draw $draw, bool $replacementRequired = false): int
    {
        return DB::transaction(function () use ($draw, $replacementRequired) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $draw->load('settings');
            if ($draw->locked || $draw->published || $draw->oop_published || $draw->isTeamDraw()
                || $draw->usesFlexibleMonrad() || $draw->settings?->workflow !== 'round_robin_playoffs'
                || !$draw->drawFixtures()->where('stage', 'RR')->exists()
                || $draw->drawFixtures()->where('stage', '!=', 'RR')->exists()
                || $draw->drawFixtures()->where(fn ($query) => $query->withRecordedResult()->orWhereIn('match_status', [1, 2, 4]))->exists()
                || DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->exists()
                || !collect($draw->settings->playoff_config ?? [])->contains(fn ($entry) => is_array($entry) && ($entry['enabled'] ?? false))) {
                return 0;
            }
            try {
                return app(RoundRobinController::class)->prepareSchedulePlayoffs($draw);
            } catch (\InvalidArgumentException $exception) {
                if ($replacementRequired) throw ValidationException::withMessages(['playoff_config' => $exception->getMessage()]);
                // Incomplete or bye-based templates retain the existing guarded
                // workspace preparation action and calendar readiness warning.
                return 0;
            }
        });
    }
}
