<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Fixture};
use Illuminate\Support\Facades\DB;

final class DrawFormatResetService
{
    public function reset(Draw $draw): array
    {
        return DB::transaction(function () use ($draw) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked || $draw->published || $draw->oop_published, 409,
                'Unlock and unpublish the draw and its schedule before changing format.');

            $fixtureIds = Fixture::where('draw_id', $draw->id)->lockForUpdate()->pluck('id');
            $resultCount = DB::table('fixture_results')->whereIn('fixture_id', $fixtureIds)->count();
            abort_if($resultCount > 0, 409,
                'This draw has recorded results and cannot be reset. Remove or preserve those results before changing format.');

            $groupIds = DB::table('draw_groups')->where('draw_id', $draw->id)->pluck('id');
            $counts = [
                'fixtures' => $fixtureIds->count(),
                'scheduled_times' => DB::table('order_of_plays')
                    ->where('draw_id', $draw->id)->orWhereIn('fixture_id', $fixtureIds)->count(),
                'groups' => $groupIds->count(),
                'roster_entries_preserved' => DB::table('draw_registrations')->where('draw_id', $draw->id)->count(),
            ];

            DB::table('order_of_plays')->where('draw_id', $draw->id)->orWhereIn('fixture_id', $fixtureIds)->delete();
            DB::table('schedules')->where('draw_id', $draw->id)->orWhereIn('fixture_id', $fixtureIds)->delete();
            Fixture::whereIn('id', $fixtureIds)->delete();
            DB::table('draw_group_registrations')->whereIn('draw_group_id', $groupIds)->delete();
            DB::table('draw_group_rankings')->whereIn('draw_group_id', $groupIds)->delete();
            DB::table('draw_groups')->where('draw_id', $draw->id)->delete();
            DB::table('flexible_monrad_draws')->where('draw_id', $draw->id)->delete();
            $draw->update(['oop_created' => 0, 'start_time' => null, 'time_per_match' => null]);

            DrawAuditLog::record($draw->id, 'format_reset', null, $counts);

            return $counts;
        });
    }
}
