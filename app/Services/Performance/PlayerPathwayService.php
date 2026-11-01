<?php

namespace App\Services\Performance;

use App\Models\{Player, PlayerPathwayProfile, User};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Starting-stage metadata and read-only, evidence-based progression recommendations. */
class PlayerPathwayService
{
    public const STAGES = ['red' => 'Red ball', 'orange' => 'Orange ball', 'standard' => 'Standard ball'];

    public function available(): bool
    {
        return Schema::hasTable('player_pathway_profiles');
    }

    public function summary(Player $player, array $history = []): array
    {
        $available = $this->available();
        $profile = $available ? PlayerPathwayProfile::where('player_id', $player->id)->first() : null;
        $stage = isset(self::STAGES[$profile?->stage ?? '']) ? $profile->stage : null;
        $division = $stage === 'standard' && in_array($profile?->wilson_division, ['A', 'B'], true) ? $profile->wilson_division : null;
        $metrics = $history['pathway_metrics'] ?? [];
        if (($history['reason'] ?? null) !== null || ($metrics['player_id'] ?? null) !== (int) $player->id) $metrics = [];
        $metricAvailable = ($metrics['available'] ?? false) === true;
        $score = $metrics['score'] ?? null;
        $score = is_numeric($score) && is_finite((float) $score) && $score >= 0 && $score <= 100 ? (float) $score : null;
        $events = $metricAvailable && is_int($metrics['events'] ?? null) && $metrics['events'] >= 0 ? $metrics['events'] : null;
        $eligible = $metricAvailable && $score !== null && ($metrics['comparable'] ?? false) === true && ($metrics['stale'] ?? true) === false;
        $targetKey = $stage === 'standard' ? ($division ? 'standard_'.strtolower($division) : null) : $stage;
        $target = $this->target($targetKey);
        $ownEvents = $stage && $metricAvailable && is_int($metrics['stage_events'][$stage] ?? null) ? $metrics['stage_events'][$stage] : null;
        $ratingMet = $target !== null && $eligible && $score >= $target['ctr'];
        $eventsMet = $target !== null && $eligible && $ownEvents !== null && $ownEvents >= $target['events'];
        $ready = $stage !== null && $ratingMet && $eventsMet;
        $reason = match (true) {
            $stage === null => 'Record a starting playing stage to assess the next step.',
            $targetKey === null => 'Record the appropriate Wilson A or B division to show its benchmark.',
            $target === null => 'Progression targets are not configured for this step.',
            !$metricAvailable => 'Verified current rating and event evidence is not available yet.',
            !$eligible => $metrics['reason'] ?? 'Progress needs a current comparable CTR.',
            $ownEvents === null || (($metrics['unknown_stage_events'] ?? 0) > 0 && $ownEvents < $target['events']) => 'Some included events lack confirmed ball metadata and cannot count towards a stage target.',
            !$ready => 'Both the CTR target and the minimum number of events in this ball stage must be met.',
            default => 'Targets met. Ready to consider an eligible next competition.',
        };
        [$competition, $options] = match ($stage) {
            'red' => ['Wilson Red', ['Wilson Orange', 'Wilson U10 B, where age and event rules permit']],
            'orange' => ['Wilson Orange', ['A suitable Green or standard-ball competition']],
            'standard' => match ($division) {
                'B' => ['Wilson B', ['Wilson A']],
                'A' => ['Wilson A', ['A suitable TSA tournament']],
                default => ['A suitable Wilson standard-ball competition', ['Record the appropriate Wilson A or B division']],
            },
            default => ['A competition suitable for the player’s recorded eligibility', ['Record a starting playing stage']],
        };
        $stageProgress = [];
        foreach (self::STAGES as $ball => $label) {
            $ballTarget = $this->target($ball === 'standard' ? ($division ? 'standard_'.strtolower($division) : null) : $ball);
            $count = $metricAvailable && is_int($metrics['stage_events'][$ball] ?? null) ? $metrics['stage_events'][$ball] : null;
            $stageProgress[$ball] = ['label' => $label, 'events' => $count, 'target' => $ballTarget,
                'met' => $stage !== null && $eligible && $ballTarget !== null && $count !== null
                    && $count >= $ballTarget['events'] && $score >= $ballTarget['ctr']];
        }
        return compact('available', 'stage', 'division', 'competition', 'options', 'score', 'events', 'target', 'ownEvents', 'eligible', 'ratingMet', 'eventsMet', 'ready', 'reason', 'stageProgress') + [
            'stage_label' => $stage ? self::STAGES[$stage] : 'Stage not recorded',
            'metrics' => $metrics,
        ];
    }

    private function target(?string $key): ?array
    {
        $target = $key ? config('pathway.targets.'.$key) : null;
        $ctr = is_array($target) ? ($target['ctr'] ?? null) : null;
        $events = is_array($target) ? ($target['events'] ?? null) : null;
        if (!is_numeric($ctr) || !is_finite((float) $ctr) || $ctr < 0 || $ctr > 100 || !is_int($events) || $events < 3) return null;
        return ['ctr' => (float) $ctr, 'events' => $events];
    }

    /** Safe presentation fallback for setup states and stand-alone view rendering. */
    public static function unrecorded(): array
    {
        return [
            'available' => false, 'stage' => null, 'division' => null,
            'competition' => 'A competition suitable for the player’s recorded eligibility',
            'options' => ['Record a starting playing stage'], 'stage_label' => 'Stage not recorded',
            'score' => null, 'events' => null, 'ownEvents' => null, 'target' => null, 'metrics' => [],
            'eligible' => false, 'ratingMet' => false, 'eventsMet' => false, 'ready' => false, 'stageProgress' => [],
            'reason' => 'Record a starting playing stage to assess the next step.',
        ];
    }

    public function recordStage(Player $player, User $actor, array $input): void
    {
        $this->authorizeWrite($player, $actor);
        $data = Validator::make($input, [
            'stage' => ['nullable', Rule::in(['red', 'orange', 'standard', 'green', 'yellow'])],
            'wilson_division' => ['nullable', Rule::in(['A', 'B'])],
        ])->validate();
        $stage = $data['stage'] ?? null;
        if (in_array($stage, ['green', 'yellow'], true)) $stage = 'standard';
        $division = $data['wilson_division'] ?? null;
        if ($stage !== 'standard' && $division !== null) {
            throw ValidationException::withMessages(['wilson_division' => 'A Wilson division applies only to the standard-ball stage.']);
        }

        DB::transaction(function () use ($player, $actor, $stage, $division) {
            Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
            $profile = PlayerPathwayProfile::firstOrNew(['player_id' => $player->id]);
            $profile->fill(['stage' => $stage, 'wilson_division' => $division]);
            if (!$profile->exists || $profile->isDirty(['stage', 'wilson_division'])) {
                $profile->recorded_by_user_id = $actor->id;
                $profile->save();
            }
        });
    }

    public function verifyMilestones(Player $player, User $actor, array $input): never
    {
        abort_unless($actor->hasRole('super-user') && app(PlayerRatingAccess::class)->allows($actor, $player), 403);
        abort(410, 'Manual milestone verification is no longer available. Progress is calculated from CTR and eligible played events.');
    }

    private function authorizeWrite(Player $player, User $actor): void
    {
        abort_unless($actor->hasRole('super-user') && app(PlayerRatingAccess::class)->allows($actor, $player), 403);
        abort_unless($this->available(), 503, 'Player pathway setup is pending.');
    }
}
