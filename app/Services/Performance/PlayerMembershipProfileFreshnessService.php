<?php

namespace App\Services\Performance;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};

/** Informational only; never changes rating or leaderboard eligibility. */
class PlayerMembershipProfileFreshnessService
{
    public function enrich(array $rows): array
    {
        if (count($rows) > 50) { throw new \LogicException('Profile freshness is limited to displayed rows.'); }
        $ids = array_values(array_unique(array_column($rows, 'id')));
        $dates = [];
        if ($ids && Schema::hasTable('players') && Schema::hasColumn('players', 'profile_updated_at')) {
            $dates = DB::table('players')->whereIn('id', $ids)->pluck('profile_updated_at', 'id')->all();
        }
        $today = CarbonImmutable::today('Africa/Johannesburg');
        foreach ($rows as &$row) {
            $row['profile_updated_date'] = null;
            $row['profile_freshness'] = 'Profile update date unavailable';
            $value = $dates[$row['id']] ?? null;
            if (!is_string($value) || !preg_match('/^(?!0000)\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/', $value)) { continue; }
            $fractional = str_contains($value, '.');
            $format = $fractional ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
            if ($fractional) { [$whole, $fraction] = explode('.', $value); $value = $whole.'.'.str_pad($fraction, 6, '0'); }
            try { $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'Africa/Johannesburg'); } catch (\Throwable) { continue; }
            if (!$date || $date->format($format) !== $value) { continue; }
            if ($date->greaterThan(CarbonImmutable::now('Africa/Johannesburg'))) { continue; }
            $row['profile_updated_date'] = $date->toDateString();
            $row['profile_freshness'] = $date->startOfDay()->greaterThanOrEqualTo($today->subMonthsNoOverflow(6))
                ? 'Profile updated within 6 months' : 'Profile not updated in last 6 months';
        }
        unset($row);
        return $rows;
    }
}
