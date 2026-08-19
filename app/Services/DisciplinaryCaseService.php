<?php

namespace App\Services;

use App\Models\DisciplinaryAppeal;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryCaseAssignment;
use App\Models\DisciplinaryCaseEvent;
use App\Models\DisciplinaryDecision;
use App\Models\DisciplinarySanction;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\PlayerViolation;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\ViolationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\SiteSetting;
use App\Notifications\DisciplinaryCaseNotification;
use Illuminate\Support\Facades\Notification;

class DisciplinaryCaseService
{
    public const PANEL_QUORUM = 3;
    public const RESPONSE_DAYS = 7;
    public const APPEAL_DAYS = 14;

    public function report(Event $event, Player $player, User $reporter, array $data): DisciplinaryCase
    {
        $this->assertSystemEnabled();
        $this->validateEventContext($event, $player, $data);

        return DB::transaction(function () use ($event, $player, $reporter, $data) {
            $case = DisciplinaryCase::create([
                'event_id' => $event->id,
                'category_event_id' => $data['category_event_id'] ?? null,
                'fixture_id' => $data['fixture_id'] ?? null,
                'player_id' => $player->id,
                'reported_by' => $reporter->id,
                'status' => DisciplinaryCase::STATUS_SUBMITTED,
                'severity' => $data['severity'] ?? 'standard',
                'incident_location' => $data['incident_location'] ?? null,
                'incident_at' => $data['incident_at'],
                'summary' => $data['summary'],
                'rulebook_version' => $data['rulebook_version'] ?? 'current',
            ]);

            $case->update(['case_number' => sprintf('CTD-%s-%06d', now()->format('Y'), $case->id)]);

            $violationType = ViolationType::find($data['violation_type_id']);
            $case->charges()->create([
                'violation_type_id' => $violationType?->id,
                'rule_code' => $data['rule_code'] ?? null,
                'rule_title' => $violationType?->name ?? $data['rule_title'],
                'allegation' => $data['allegation'] ?? $data['summary'],
                'finding' => 'pending',
                'points' => 0,
            ]);

            if (! empty($data['statement'])) {
                $case->evidence()->create([
                    'submitted_by' => $reporter->id,
                    'kind' => 'official_report',
                    'title' => 'Initial incident report',
                    'statement' => $data['statement'],
                    'visibility' => 'parties',
                ]);
            }

            $this->log($case, $reporter, 'case.reported', null, DisciplinaryCase::STATUS_SUBMITTED);

            return $case->fresh(['event', 'player', 'charges']);
        });
    }

    public function triage(DisciplinaryCase $case, User $actor, string $action, ?string $reason = null): DisciplinaryCase
    {
        $this->assertSystemEnabled();
        return DB::transaction(function () use ($case, $actor, $action, $reason) {
            $case = DisciplinaryCase::lockForUpdate()->findOrFail($case->id);
            if (! in_array($case->status, [DisciplinaryCase::STATUS_SUBMITTED, DisciplinaryCase::STATUS_TRIAGE], true)) {
                throw ValidationException::withMessages(['status' => 'This case has already passed triage.']);
            }

            $from = $case->status;
            if ($action === 'dismiss') {
                if (! $reason) {
                    throw ValidationException::withMessages(['reason' => 'A dismissal reason is required.']);
                }
                $case->update([
                    'status' => DisciplinaryCase::STATUS_DISMISSED,
                    'triaged_by' => $actor->id,
                    'closure_reason' => $reason,
                    'finalized_at' => now(),
                ]);
                $this->log($case, $actor, 'case.dismissed_at_triage', $from, $case->status, $reason);
                $this->notifyPlayerParties($case, 'Disciplinary case closed', 'The incident was closed at triage. No disciplinary finding was made.');
                return $case;
            }

            $case->update([
                'status' => DisciplinaryCase::STATUS_AWAITING_RESPONSE,
                'triaged_by' => $actor->id,
                'response_due_at' => now()->addDays(self::RESPONSE_DAYS),
            ]);
            $this->log($case, $actor, 'case.notice_issued', $from, $case->status, $reason);
            $this->notifyPlayerParties($case, 'Response required: '.$case->case_number, 'A disciplinary incident has proceeded to formal review. Please read the allegation and respond by the deadline.');
            return $case;
        });
    }

    public function submitResponse(DisciplinaryCase $case, User $actor, string $response): DisciplinaryCase
    {
        $this->assertSystemEnabled();
        return DB::transaction(function () use ($case, $actor, $response) {
            $case = DisciplinaryCase::lockForUpdate()->findOrFail($case->id);
            if ($case->status !== DisciplinaryCase::STATUS_AWAITING_RESPONSE) {
                throw ValidationException::withMessages(['status' => 'This case is not awaiting a response.']);
            }
            $from = $case->status;
            $case->update([
                'player_response' => $response,
                'responded_at' => now(),
                'status' => DisciplinaryCase::STATUS_PANEL_REVIEW,
            ]);
            $case->evidence()->create([
                'submitted_by' => $actor->id,
                'kind' => 'player_response',
                'title' => 'Player or guardian response',
                'statement' => $response,
                'visibility' => 'parties',
            ]);
            $this->log($case, $actor, 'case.response_submitted', $from, $case->status);
            return $case;
        });
    }

    public function appointPanel(DisciplinaryCase $case, User $actor, array $members): void
    {
        $this->assertSystemEnabled();
        if (count($members) < self::PANEL_QUORUM) {
            throw ValidationException::withMessages(['panel' => 'At least three independent panel members are required.']);
        }
        if (collect($members)->pluck('user_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['panel' => 'A panel member may only be appointed once.']);
        }
        if (collect($members)->where('role', 'chair')->count() !== 1) {
            throw ValidationException::withMessages(['panel' => 'Exactly one panel chair is required.']);
        }
        if (collect($members)->contains(fn ($member) => (int) $member['user_id'] === (int) $case->reported_by)) {
            throw ValidationException::withMessages(['panel' => 'The incident reporter may not sit on the panel.']);
        }
        $memberIds = collect($members)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $eligibleCount = User::whereIn('id', $memberIds)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-user']))->count();
        if ($eligibleCount !== count($memberIds)) {
            throw ValidationException::withMessages(['panel' => 'Every panel member must be an authorized disciplinary administrator.']);
        }

        DB::transaction(function () use ($case, $actor, $members) {
            $case->assignments()->delete();
            foreach ($members as $member) {
                DisciplinaryCaseAssignment::create([
                    'disciplinary_case_id' => $case->id,
                    'user_id' => $member['user_id'],
                    'role' => $member['role'],
                    'accepted_at' => now(),
                ]);
            }
            if ($case->status === DisciplinaryCase::STATUS_AWAITING_RESPONSE && $case->response_due_at?->isPast()) {
                $case->update(['status' => DisciplinaryCase::STATUS_PANEL_REVIEW]);
            }
            $this->log($case, $actor, 'panel.appointed', $case->status, $case->status, metadata: [
                'member_ids' => collect($members)->pluck('user_id')->all(),
            ]);
        });
    }

    public function declareConflict(DisciplinaryCaseAssignment $assignment, User $actor, bool $conflict, ?string $notes): void
    {
        $this->assertSystemEnabled();
        $assignment->update([
            'conflict_declared' => $conflict,
            'conflict_notes' => $notes,
            'recused_at' => $conflict ? now() : null,
        ]);
        $this->log($assignment->disciplinaryCase, $actor, $conflict ? 'panel.member_recused' : 'panel.no_conflict_declared', metadata: [
            'assignment_id' => $assignment->id,
        ]);
    }

    public function finalizeDecision(DisciplinaryCase $case, User $chair, array $data): DisciplinaryDecision
    {
        $this->assertSystemEnabled();
        return DB::transaction(function () use ($case, $chair, $data) {
            $case = DisciplinaryCase::lockForUpdate()->findOrFail($case->id);
            if ($case->decisions()->exists() || in_array($case->status, [DisciplinaryCase::STATUS_DECIDED, DisciplinaryCase::STATUS_FINAL], true)) {
                throw ValidationException::withMessages(['decision' => 'This case already has a final decision.']);
            }
            if ($case->status !== DisciplinaryCase::STATUS_PANEL_REVIEW) {
                throw ValidationException::withMessages(['decision' => 'The response stage must close before the panel can finalize a decision.']);
            }

            $panel = $case->assignments()->with('user')->whereNull('recused_at')
                ->where('conflict_declared', false)->get();
            if ($panel->count() < self::PANEL_QUORUM || ! $panel->contains(fn ($a) => $a->role === 'chair' && $a->user_id === $chair->id)) {
                throw ValidationException::withMessages(['panel' => 'A conflict-free panel of at least three, including the appointed chair, is required.']);
            }

            $outcome = $data['outcome'];
            $proven = in_array($outcome, ['upheld', 'partially_upheld'], true);
            foreach ($case->charges()->with('violationType')->get() as $charge) {
                $charge->update([
                    'finding' => $proven ? 'proven' : 'not_proven',
                    'points' => $proven ? (int) ($charge->violationType?->default_points ?? 0) : 0,
                ]);
            }

            $decision = DisciplinaryDecision::create([
                'disciplinary_case_id' => $case->id,
                'decided_by' => $chair->id,
                'outcome' => $outcome,
                'reasons' => $data['reasons'],
                'panel_snapshot' => $panel->map(fn ($a) => [
                    'user_id' => $a->user_id, 'name' => $a->user?->name, 'role' => $a->role,
                ])->values()->all(),
                'rule_snapshot' => $case->charges()->get(['rule_code', 'rule_title', 'finding', 'points'])->toArray(),
                'decided_at' => now(),
                'served_at' => now(),
            ]);

            if ($proven) {
                foreach ($data['sanctions'] ?? [] as $sanctionData) {
                    $this->createSanction($case, $decision, $sanctionData);
                }

                $points = (int) $case->charges()->sum('points');
                $violationTypeId = $case->charges()->whereNotNull('violation_type_id')->value('violation_type_id');
                if ($points > 0 && $violationTypeId) {
                    PlayerViolation::create([
                        'player_id' => $case->player_id,
                        'violation_type_id' => $violationTypeId,
                        'violation_date' => $case->incident_at->toDateString(),
                        'points_assigned' => $points,
                        'notes' => "Confirmed by panel in {$case->case_number}",
                        'recorded_by' => $chair->id,
                        'event_id' => $case->event_id,
                        'disciplinary_case_id' => $case->id,
                    ]);
                }
            }

            $from = $case->status;
            $case->update(['status' => DisciplinaryCase::STATUS_DECIDED, 'finalized_at' => now()]);
            $this->log($case, $chair, 'decision.finalized', $from, $case->status, metadata: ['decision_id' => $decision->id]);
            $this->notifyPlayerParties($case, 'Panel decision: '.$case->case_number, 'The panel has finalized and served its decision. Sign in to read the outcome, reasons, and any sanction.');
            return $decision->fresh('sanctions');
        });
    }

    public function appeal(DisciplinaryCase $case, User $actor, string $grounds): DisciplinaryAppeal
    {
        $this->assertSystemEnabled();
        return DB::transaction(function () use ($case, $actor, $grounds) {
            if (! $case->finalized_at || $case->finalized_at->lt(now()->subDays(self::APPEAL_DAYS))) {
                throw ValidationException::withMessages(['appeal' => 'The appeal deadline has passed.']);
            }
            if ($case->appeals()->whereIn('status', ['submitted', 'under_review'])->exists()) {
                throw ValidationException::withMessages(['appeal' => 'An appeal is already open for this case.']);
            }
            $appeal = $case->appeals()->create([
                'submitted_by' => $actor->id,
                'grounds' => $grounds,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);
            $from = $case->status;
            $case->update(['status' => DisciplinaryCase::STATUS_APPEALED]);
            $this->log($case, $actor, 'appeal.submitted', $from, $case->status, metadata: ['appeal_id' => $appeal->id]);
            return $appeal;
        });
    }

    public function decideAppeal(DisciplinaryAppeal $appeal, User $actor, string $outcome, string $reasons): void
    {
        $this->assertSystemEnabled();
        DB::transaction(function () use ($appeal, $actor, $outcome, $reasons) {
            $appeal = DisciplinaryAppeal::lockForUpdate()->findOrFail($appeal->id);
            if ($appeal->status !== 'submitted') {
                throw ValidationException::withMessages(['appeal' => 'This appeal has already been decided.']);
            }
            $appeal->update([
                'status' => $outcome,
                'outcome_reasons' => $reasons,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);
            $case = $appeal->disciplinaryCase;
            if ($outcome === 'overturned') {
                $case->sanctions()->whereNull('revoked_at')->update([
                    'revoked_at' => now(), 'revoked_by' => $actor->id, 'revocation_reason' => $reasons,
                ]);
                PlayerViolation::where('disciplinary_case_id', $case->id)->whereNull('voided_at')->update([
                    'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reasons,
                ]);
            }
            $from = $case->status;
            $case->update(['status' => DisciplinaryCase::STATUS_FINAL]);
            $this->log($case, $actor, 'appeal.decided', $from, $case->status, $reasons, ['outcome' => $outcome]);
            $this->notifyPlayerParties($case, 'Appeal outcome: '.$case->case_number, 'The appeal has been decided. Sign in to review the final case status.');
        });
    }

    private function createSanction(DisciplinaryCase $case, DisciplinaryDecision $decision, array $data): DisciplinarySanction
    {
        $scope = $data['scope'] ?? 'global';
        $scopeId = match ($scope) {
            'event' => $case->event_id,
            'series' => $case->event?->series_id,
            default => null,
        };

        return $case->sanctions()->create([
            'disciplinary_decision_id' => $decision->id,
            'player_id' => $case->player_id,
            'type' => $data['type'],
            'scope' => $scope,
            'scope_id' => $scopeId,
            'points' => $data['points'] ?? 0,
            'starts_at' => $data['starts_at'] ?? today(),
            'ends_at' => $data['ends_at'] ?? null,
            'details' => $data['details'] ?? null,
        ]);
    }

    private function validateEventContext(Event $event, Player $player, array $data): void
    {
        $hasIndividualEntry = $player->registrations()
            ->whereHas('categoryEvents', fn ($q) => $q->where('category_events.event_id', $event->id))
            ->exists();
        $hasTeamEntry = TeamPlayer::where('player_id', $player->id)
            ->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))
            ->exists();

        if (! $hasIndividualEntry && ! $hasTeamEntry) {
            throw ValidationException::withMessages(['player_id' => 'The selected player is not entered in this event.']);
        }
        if (! empty($data['category_event_id']) && ! CategoryEvent::whereKey($data['category_event_id'])->where('event_id', $event->id)->exists()) {
            throw ValidationException::withMessages(['category_event_id' => 'The selected category does not belong to this event.']);
        }
        if (! empty($data['fixture_id'])) {
            $fixture = Fixture::with(['draw', 'registration1.players', 'registration2.players'])->find($data['fixture_id']);
            if (! $fixture || (int) $fixture->draw?->event_id !== (int) $event->id) {
                throw ValidationException::withMessages(['fixture_id' => 'The selected fixture does not belong to this event.']);
            }
            $playerIds = $fixture->registration1?->players->pluck('id')->merge($fixture->registration2?->players->pluck('id') ?? collect());
            if (! $playerIds->contains($player->id)) {
                throw ValidationException::withMessages(['player_id' => 'The selected player is not part of this fixture.']);
            }
        }
    }

    private function assertSystemEnabled(): void
    {
        if (! SiteSetting::disciplinarySystemEnabled()) {
            throw new \RuntimeException('The disciplinary case system is currently disabled by the Super Admin.');
        }
    }

    private function log(
        DisciplinaryCase $case,
        ?User $actor,
        string $action,
        ?string $from = null,
        ?string $to = null,
        ?string $notes = null,
        array $metadata = [],
    ): void {
        DisciplinaryCaseEvent::create([
            'disciplinary_case_id' => $case->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
        PlatformAuditLogger::log('disciplinary.'.$action, $case, meta: ['event_id' => $case->event_id] + $metadata, userId: $actor?->id);
    }

    private function notifyPlayerParties(DisciplinaryCase $case, string $subject, string $message): void
    {
        if (SiteSetting::get('email_on_violation', '1') !== '1') {
            return;
        }

        $case->loadMissing('player.users');
        $emails = collect([$case->player?->email])
            ->merge($case->player?->users?->pluck('email') ?? [])
            ->merge($case->player?->agreements()->whereNotNull('guardian_email')->latest('accepted_at')->limit(2)->pluck('guardian_email') ?? [])
            ->filter()->map(fn ($email) => mb_strtolower(trim($email)))->unique();

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify(new DisciplinaryCaseNotification($case, $subject, $message));
        }
    }
}
