<?php

namespace App\Services;

use App\Models\DisciplineSetting;
use App\Models\Player;
use App\Models\PlayerSuspension;
use App\Models\PlayerViolation;
use App\Models\ViolationType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DisciplinaryService
{
    /**
     * Sum of non-expired suspension points for a player.
     */
    public function getActivePoints(Player $player): int
    {
        return (int) $player->violations()
            ->active()
            ->sum('points_assigned');
    }

    /**
     * Total number of suspensions ever triggered for a player.
     */
    public function getSuspensionCount(Player $player): int
    {
        return $player->suspensions()->count();
    }

    /**
     * Check if active points have crossed the threshold and, if so, create a
     * suspension record. Returns the new suspension or null if not triggered.
     */
    public function checkAndTriggerSuspension(Player $player): ?PlayerSuspension
    {
        $threshold = DisciplineSetting::suspensionThreshold();
        $activePoints = $this->getActivePoints($player);

        if ($activePoints < $threshold) {
            return null;
        }

        // Don't create a second suspension while one is already active.
        if ($player->suspensions()->active()->exists()) {
            return null;
        }

        $suspensionCount = $this->getSuspensionCount($player) + 1;
        $durationMonths  = $suspensionCount === 1
            ? DisciplineSetting::firstSuspensionMonths()
            : DisciplineSetting::secondSuspensionMonths();

        $startsAt = Carbon::today();
        $endsAt   = $startsAt->copy()->addMonths($durationMonths);

        $suspension = PlayerSuspension::create([
            'player_id'        => $player->id,
            'triggered_at'     => $startsAt->toDateString(),
            'suspension_number' => $suspensionCount,
            'duration_months'  => $durationMonths,
            'starts_at'        => $startsAt->toDateString(),
            'ends_at'          => $endsAt->toDateString(),
        ]);

        return $suspension;
    }

    /**
     * Full status summary for a player.
     */
    public function getPlayerStatus(Player $player): array
    {
        $threshold    = DisciplineSetting::suspensionThreshold();
        $activePoints = $this->getActivePoints($player);
        $suspension   = $player->suspensions()->active()->latest()->first();

        $expiryDays = DisciplineSetting::expiryDays();
        $violations  = $player->violations()
            ->with('violationType')
            ->orderByDesc('violation_date')
            ->get()
            ->map(function ($v) use ($expiryDays) {
                return [
                    'id'             => $v->id,
                    'date'           => $v->violation_date->format('Y-m-d'),
                    'type'           => $v->violationType->name ?? '—',
                    'category'       => $v->violationType->category ?? null,
                    'penalty_type'   => $v->penalty_type,
                    'points'         => $v->points_assigned,
                    'is_expired'     => $v->is_expired,
                    'expires_at'     => $v->expires_at->format('Y-m-d'),
                    'notes'          => $v->notes,
                ];
            });

        return [
            'active_points'    => $activePoints,
            'threshold'        => $threshold,
            'suspended'        => $suspension !== null,
            'suspension_ends_at' => $suspension?->ends_at?->format('Y-m-d'),
            'suspension_number' => $suspension?->suspension_number,
            'violations'       => $violations,
        ];
    }

    /**
     * Record a new violation and trigger suspension check.
     */
    public function recordViolation(array $data): PlayerViolation
    {
        $violationType = ViolationType::findOrFail($data['violation_type_id']);

        $violation = PlayerViolation::create([
            'player_id'        => $data['player_id'],
            'violation_type_id' => $data['violation_type_id'],
            'violation_date'   => $data['violation_date'],
            'penalty_type'     => $data['penalty_type'] ?? null,
            'points_assigned'  => $data['points_assigned'] ?? $violationType->default_points,
            'notes'            => $data['notes'] ?? null,
            'recorded_by'      => $data['recorded_by'] ?? Auth::id(),
            'event_id'         => $data['event_id'] ?? null,
        ]);

        $this->checkAndTriggerSuspension($violation->player);

        return $violation;
    }

    /**
     * Returns the next PPS (Progressive Penalty System) consequence for on-court
     * violations within the active rolling window.
     *
     * 1st = Warning, 2nd = Point, 3rd = Game, 4th+ = Game or Default
     */
    public function getPpsConsequence(Player $player): string
    {
        $count = $player->violations()
            ->active()
            ->whereHas('violationType', fn ($q) => $q->where('category', 'on_court'))
            ->count();

        return match (true) {
            $count === 0 => 'Warning',
            $count === 1 => 'Point Penalty',
            $count === 2 => 'Game Penalty',
            default      => 'Game Penalty / Default',
        };
    }
}
