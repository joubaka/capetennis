<?php

namespace App\Services;

use App\Mail\SuspensionAlertMail;
use App\Mail\ViolationNotificationMail;
use App\Models\DisciplineSetting;
use App\Models\Player;
use App\Models\PlayerSuspension;
use App\Models\PlayerViolation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\ViolationType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
     * Total number of suspensions a player has served or is serving.
     * Lifted suspensions (i.e. overturned on appeal) are excluded so that
     * a successful appeal does not escalate the penalty for the next offence.
     */
    public function getSuspensionCount(Player $player): int
    {
        return $player->suspensions()->whereNull('lifted_at')->count();
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

        $this->sendSuspensionNotification($player, $suspension);

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

        $this->sendViolationNotification($violation);

        return $violation;
    }

    /**
     * Send violation notification emails if the setting is enabled.
     * Recipients: player, guardian (if exists), event admins (CC), recorder (CC).
     */
    private function sendViolationNotification(PlayerViolation $violation): void
    {
        if (SiteSetting::get('email_on_violation', '1') !== '1') {
            return;
        }

        $player   = $violation->player;
        $recorder = $violation->recorded_by ? User::find($violation->recorded_by) : null;

        // Build primary TO addresses: player email + guardian email
        $toAddresses = collect();

        if ($player->email) {
            $toAddresses->push($player->email);
        }

        // Parent/guardian email from the most recent accepted agreement
        $guardian = $player->agreements()
            ->whereNotNull('guardian_email')
            ->latest('accepted_at')
            ->first();

        if ($guardian && $guardian->guardian_email && $guardian->guardian_email !== $player->email) {
            $toAddresses->push($guardian->guardian_email);
        }

        // Also include any linked user emails (account holders)
        foreach ($player->users as $user) {
            if ($user->email && !$toAddresses->contains($user->email)) {
                $toAddresses->push($user->email);
            }
        }

        if ($toAddresses->isEmpty()) {
            return;
        }

        // Build CC: event admins + super-users + recorder
        $ccAddresses = collect();

        if ($violation->event_id) {
            $violation->loadMissing('event');
            $event = $violation->event;
            if ($event) {
                $event->admins->pluck('email')->filter()->each(fn ($e) => $ccAddresses->push($e));
            }
        }

        // Super-users always get CC'd
        User::role('super-user')->pluck('email')->filter()
            ->each(fn ($e) => $ccAddresses->push($e));

        if ($recorder?->email) {
            $ccAddresses->push($recorder->email);
        }

        // Deduplicate and remove any CC that already appears in TO
        $ccAddresses = $ccAddresses->unique()->filter()->diff($toAddresses)->values();

        $mailable = new ViolationNotificationMail($player, $violation, $recorder);

        // Build the full CC list: extra TO addresses + admin/recorder CCs, passed in one call
        $allCc = $toAddresses->slice(1)->values()->merge($ccAddresses)->unique()->values();

        $mailer = Mail::to($toAddresses->first());

        if ($allCc->isNotEmpty()) {
            $mailer = $mailer->cc($allCc->toArray());
        }

        $mailer->queue($mailable);
    }

    /**
     * Send suspension notification to the player, guardian, and super-users.
     */
    private function sendSuspensionNotification(Player $player, PlayerSuspension $suspension): void
    {
        if (SiteSetting::get('email_on_violation', '1') !== '1') {
            return;
        }

        $toAddresses = collect();

        if ($player->email) {
            $toAddresses->push($player->email);
        }

        $guardian = $player->agreements()
            ->whereNotNull('guardian_email')
            ->latest('accepted_at')
            ->first();

        if ($guardian && $guardian->guardian_email && $guardian->guardian_email !== $player->email) {
            $toAddresses->push($guardian->guardian_email);
        }

        foreach ($player->users as $user) {
            if ($user->email && !$toAddresses->contains($user->email)) {
                $toAddresses->push($user->email);
            }
        }

        if ($toAddresses->isEmpty()) {
            return;
        }

        $ccAddresses = User::role('super-user')->pluck('email')->filter()
            ->diff($toAddresses)
            ->values();

        $mailable = new SuspensionAlertMail($player, $suspension);

        $allCc = $toAddresses->slice(1)->values()->merge($ccAddresses)->unique()->values();

        $mailer = Mail::to($toAddresses->first());

        if ($allCc->isNotEmpty()) {
            $mailer = $mailer->cc($allCc->toArray());
        }

        $mailer->queue($mailable);
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
