<?php

namespace App\Services\Performance;

use App\Models\{Player, PlayerPathwayProfile, User};
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\Rule;

/** Starting-stage metadata and read-only, evidence-based progression recommendations. */
class PlayerPathwayService
{
    public const STAGES = ['red' => 'Red ball', 'orange' => 'Orange ball', 'standard' => 'Green/Yellow ball'];

    public function available(): bool
    {
        return Schema::hasTable('player_pathway_profiles');
    }

    public function summary(Player $player, array $history = []): array
    {
        $available = $this->available();
        $profile = $available ? PlayerPathwayProfile::where('player_id', $player->id)->first() : null;
        $stage = isset(self::STAGES[$profile?->stage ?? '']) ? $profile->stage : null;
        $saved_division = in_array($profile?->wilson_division, ['A', 'B'], true) ? $profile->wilson_division : null;
        $metrics = $history['pathway_metrics'] ?? [];
        if (($history['reason'] ?? null) !== null || ($metrics['player_id'] ?? null) !== (int) $player->id) $metrics = [];
        $metricAvailable = ($metrics['available'] ?? false) === true;
        if (!$metricAvailable) $metrics = [];
        // Rated level is evidence, independent of the saved starting stage and CTR magnitude.
        $current_stage = null;
        foreach (['standard', 'orange', 'red'] as $ball) {
            if ($this->validScore($metrics['stage_ratings'][$ball]['score'] ?? null) !== null) {
                $current_stage = $ball;
                break;
            }
        }
        $division = $current_stage === 'standard' ? $saved_division : null;
        $stageRating = $current_stage ? ($metrics['stage_ratings'][$current_stage] ?? []) : [];
        $score = $stageRating['score'] ?? null;
        $score = $this->validScore($score);
        $events = $metricAvailable && is_int($metrics['events'] ?? null) && $metrics['events'] >= 0 ? $metrics['events'] : null;
        $eligible = $metricAvailable && $score !== null && ($stageRating['comparable'] ?? false) === true
            && ($stageRating['local'] ?? false) !== true && ($metrics['stale'] ?? true) === false;
        $targetKey = $current_stage === 'standard' ? ($division ? 'standard_'.strtolower($division) : null) : $current_stage;
        $target = $this->target($targetKey);
        $ownEvents = $current_stage && $metricAvailable && is_int($metrics['stage_events'][$current_stage] ?? null) ? $metrics['stage_events'][$current_stage] : null;
        $assessed = $stage !== null && $target !== null && $eligible && $ownEvents !== null;
        $ratingMet = $assessed && $score >= $target['ctr'];
        $eventsMet = $ownEvents !== null && $ownEvents >= ($target['events'] ?? 3);
        $ready = $stage !== null && $ratingMet && $eventsMet;
        $assessment_status = !$assessed ? 'Not assessed' : ($ready ? 'Targets met' : 'Targets not yet met');
        $reason = match (true) {
            $stage === null => 'Assessment pending: a starting stage must be recorded. Available ratings and event counts are shown separately.',
            $current_stage === null => 'Assessment pending: no saved ball-level rating is available.',
            $targetKey === null => 'Record the appropriate Wilson A or B division to show its benchmark.',
            $target === null => 'Progression targets are not configured for this step.',
            !$metricAvailable => 'Verified current rating and event evidence is not available yet.',
            ($metrics['stale'] ?? true) => 'Assessment pending: progress awaits a current saved rating update.',
            !$eligible => 'Assessment pending: progress needs a current comparable rating from this ball level only.',
            $ownEvents === null || (($metrics['unknown_stage_events'] ?? 0) > 0 && $ownEvents < $target['events']) => 'Some included events have conflicting ball labels and cannot count towards a stage target.',
            !$ready => 'Both the CTR target and the minimum number of events in this ball stage must be met.',
            default => 'Current targets met. Consider the next pathway options subject to each event’s entry rules.',
        };
        [$competition, $options] = match ($current_stage) {
            'red' => ['Wilson Red', ['Wilson Orange', 'Wilson U10 B, where age and event rules permit']],
            'orange' => ['Wilson Orange', ['Green/Yellow ball competitions']],
            'standard' => match ($division) {
                'B' => ['Wilson B', ['Wilson A']],
                'A' => ['Wilson A', ['TSA tournaments, subject to their entry rules']],
                default => ['Wilson Green/Yellow ball competitions', ['Confirm the Wilson A or B division before assessing the next benchmark']],
            },
            default => ['No rated ball level available yet', ['Confirm the starting stage and play an eligible published event']],
        };
        $stageProgress = [];
        foreach (self::STAGES as $ball => $label) {
            $ballTarget = $this->target($ball === 'standard' ? ($division ? 'standard_'.strtolower($division) : null) : $ball);
            $count = $metricAvailable && is_int($metrics['stage_events'][$ball] ?? null) ? $metrics['stage_events'][$ball] : null;
            $ballRating = $metrics['stage_ratings'][$ball] ?? [];
            $ballScore = $ballRating['score'] ?? null;
            $ballScore = $this->validScore($ballScore);
            $ballEligible = $metricAvailable && $ballScore !== null && ($ballRating['comparable'] ?? false) === true
                && ($ballRating['local'] ?? false) !== true && ($metrics['stale'] ?? true) === false;
            $ballAssessed = $stage !== null && $ballEligible && $ballTarget !== null && $count !== null;
            $met = $ballAssessed && $count >= $ballTarget['events'] && $ballScore >= $ballTarget['ctr'];
            $stageProgress[$ball] = ['label' => $label, 'ctr_label' => $this->ctrLabel($ball), 'score' => $ballScore, 'local' => ($ballRating['local'] ?? false) === true,
                'confidence_label' => $ballRating['confidence_label'] ?? null, 'reason' => $ballRating['reason'] ?? null, 'events' => $count, 'target' => $ballTarget,
                'assessed' => $ballAssessed, 'status' => !$ballAssessed ? 'Not assessed' : ($met ? 'Targets met' : 'Targets not yet met'), 'met' => $met];
        }
        $badgeStage = $current_stage;
        $badge = ['stage' => $badgeStage, 'score' => $stageProgress[$badgeStage]['score'] ?? null,
            'local' => $stageProgress[$badgeStage]['local'] ?? false];
        $ctr_label = $this->ctrLabel($current_stage);
        $next_steps = match (true) {
            $stage === null => ['Ask Cape Tennis to record your starting stage so progress can be assessed. Your available rating and event counts remain visible.'],
            $current_stage === null => ['Play eligible published Cape Tennis singles events at your ball level, then wait for the saved rating update. Ask Cape Tennis if played results are missing.'],
            $current_stage === 'standard' && $division === null => ['Ask Cape Tennis to confirm your current Wilson A or B division; it is separate from your starting stage.'],
            $target === null => ['Ask Cape Tennis to review the benchmark configuration for this level.'],
            ($metrics['stale'] ?? true) => ['Wait for the next saved rating update before reviewing progress. Your displayed rating is the latest saved estimate.'],
            !$eligible => ['Play more eligible singles at this ball level to build comparable rating evidence. Ask Cape Tennis or your coach to review a within-group or low-confidence estimate.'],
            $ownEvents === null => ['Ask Cape Tennis to review the published played-event evidence for this ball level.'],
            $ready => [match ($current_stage) {
                'red' => 'Discuss Orange-ball play with your coach and check the next event’s age and entry rules.',
                'orange' => 'Discuss Green/Yellow-ball play with your coach and check the next event’s age and entry rules.',
                default => $division === 'B'
                    ? 'Discuss Wilson A with your coach and check the event’s entry rules.'
                    : 'Review your next competition options with your coach. TSA tournament entry depends on its own rules, not this benchmark.',
            }, 'Both current benchmarks are met; this does not automatically promote you or complete earlier levels.'],
            default => array_values(array_filter([
                $score < $target['ctr'] ? 'Build your '.$ctr_label.' from '.number_format($score, 1).' to '.number_format($target['ctr'], 1).' ('.number_format($target['ctr'] - $score, 1).' points to the benchmark) through eligible singles at this ball level.' : null,
                $ownEvents < $target['events'] ? 'Play '.($target['events'] - $ownEvents).' more published '.self::STAGES[$current_stage].' '.($target['events'] - $ownEvents === 1 ? 'event' : 'events').' to reach '.$target['events'].' at this level.' : null,
            ])),
        };

        return compact('available', 'stage', 'division', 'saved_division', 'current_stage', 'competition', 'options', 'score', 'events', 'target', 'ownEvents', 'eligible', 'ratingMet', 'eventsMet', 'ready', 'assessed', 'assessment_status', 'reason', 'stageProgress', 'badge', 'ctr_label', 'next_steps') + [
            'stage_label' => $stage ? self::STAGES[$stage] : 'Stage not recorded',
            'current_stage_label' => $current_stage ? self::STAGES[$current_stage] : 'Not rated yet',
            'metrics' => $metrics,
            'benchmarks' => $this->benchmarks(),
        ];
    }

    private function ctrLabel(?string $stage): string
    {
        return match ($stage) {
            'red' => 'Red-ball CTR',
            'orange' => 'Orange-ball CTR',
            'standard' => 'Green/Yellow-ball CTR',
            default => 'Ball-level CTR',
        };
    }

    private function benchmarks(): array
    {
        $benchmarks = [];
        foreach (['red' => ['Red-ball CTR', 'Red ball'], 'orange' => ['Orange-ball CTR', 'Orange ball'],
            'standard_b' => ['Green/Yellow-ball CTR · Wilson B', 'Green/Yellow ball'], 'standard_a' => ['Green/Yellow-ball CTR · Wilson A', 'Green/Yellow ball']] as $key => [$label, $ball]) {
            $benchmarks[] = ['label' => $label, 'ball' => $ball, 'target' => $this->target($key)];
        }
        return $benchmarks;
    }

    private function validScore(mixed $score): ?float
    {
        return is_numeric($score) && is_finite((float) $score) && $score >= 0 && $score <= 100 ? (float) $score : null;
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
            'available' => false, 'stage' => null, 'division' => null, 'saved_division' => null,
            'current_stage' => null, 'current_stage_label' => 'Not rated yet', 'assessed' => false, 'assessment_status' => 'Not assessed',
            'competition' => 'No rated ball level available yet',
            'options' => ['Confirm the starting stage and play an eligible published event'], 'stage_label' => 'Stage not recorded',
            'score' => null, 'events' => null, 'ownEvents' => null, 'target' => null, 'metrics' => [],
            'badge' => ['stage' => null, 'score' => null, 'local' => false],
            'eligible' => false, 'ratingMet' => false, 'eventsMet' => false, 'ready' => false, 'stageProgress' => [],
            'reason' => 'Assessment pending: a starting stage must be recorded. Available ratings and event counts are shown separately.',
            'ctr_label' => 'Ball-level CTR',
            'next_steps' => ['Ask Cape Tennis to record your starting stage so progress can be assessed. Your available rating and event counts remain visible.'],
            'benchmarks' => (new self)->benchmarks(),
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
