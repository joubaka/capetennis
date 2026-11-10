<?php

namespace App\Services\Performance;

use App\Models\{Player, PlayerPathwayMove, PlayerPathwayProfile, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema, Validator};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Automatic development assessment with optional historical participation metadata. */
class PlayerPathwayService
{
    public const STAGES = ['red' => 'Red ball', 'orange' => 'Orange ball', 'standard' => 'Wilson B / Wilson A'];
    public const NEXT_BALL_LEVEL = ['red' => 'orange', 'orange' => 'standard'];
    public const EVENT_OPTIONS = [
        'red' => ['Academy events', 'TSA Rainbow Kids events', 'School leagues'],
        'orange' => ['U/9 B events', 'Academy events', 'TSA Rainbow Kids events', 'School leagues'],
        'standard' => ['Wilson events', 'Academy events', 'Regional trial events', 'TSA Rainbow Kids events', 'School leagues'],
    ];

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
        $current_stage = BallRatingModel::currentStage($metrics['stage_ratings'] ?? []);
        $division = $current_stage === 'standard' ? $saved_division : null;
        $stageRating = $current_stage ? ($metrics['stage_ratings'][$current_stage] ?? []) : [];
        $score = $stageRating['score'] ?? null;
        $score = $this->validScore($score);
        $assessmentAvailable = ($metrics['assessment_available'] ?? !(bool) ($metrics['stale'] ?? true)) === true;
        $events = $metricAvailable && is_int($metrics['events'] ?? null) && $metrics['events'] >= 0 ? $metrics['events'] : null;
        $eligible = $metricAvailable && $score !== null && ($stageRating['comparable'] ?? false) === true
            && ($stageRating['local'] ?? false) !== true && $assessmentAvailable;
        $ownEvents = $current_stage && $metricAvailable && is_int($metrics['stage_events'][$current_stage] ?? null) ? $metrics['stage_events'][$current_stage] : null;
        $wilsonProgress = [];
        $benchmark_division = null;
        $wilsonCutoff = $this->validScore(config('pathway.wilson_a_above'));
        $tsaCutoff = $this->validScore(config('pathway.tsa_above'));
        // Locate the displayed saved badge without claiming a fresh progression assessment.
        $savedComparable = $current_stage === 'standard' && $score !== null
            && ($stageRating['comparable'] ?? false) === true && ($stageRating['local'] ?? false) !== true;
        $currentCompetition = $savedComparable && $wilsonCutoff !== null
            ? ($tsaCutoff !== null && $tsaCutoff > $wilsonCutoff && $score > $tsaCutoff ? 'TSA tournaments' : 'Wilson '.($score > $wilsonCutoff ? 'A' : 'B')) : null;
        $suggestedDivision = $current_stage === 'standard' && $eligible && $wilsonCutoff !== null ? ($score > $wilsonCutoff ? 'A' : 'B') : null;
        $suggestedCompetition = $suggestedDivision ? ($tsaCutoff !== null && $tsaCutoff > $wilsonCutoff && $score > $tsaCutoff ? 'TSA tournaments' : 'Wilson '.$suggestedDivision) : null;
        $targetKey = $current_stage === 'standard' ? null : $current_stage;
        $target = $this->target($targetKey);
        $assessed = $target !== null && $eligible && $ownEvents !== null;
        $ratingMet = $assessed && $score >= $target['ctr'];
        $eventsMet = $ownEvents !== null && $ownEvents >= ($target['events'] ?? 3);
        $ready = $ratingMet && $eventsMet;
        $assessment_status = !$assessed ? 'Not assessed' : ($ready ? 'Targets met' : 'Targets not yet met');
        $reason = match (true) {
            $current_stage === null => 'Assessment pending: no saved ball-level rating is available.',
            $target === null => 'Progression targets are not configured for this step.',
            !$metricAvailable => 'Verified current rating and event evidence is not available yet.',
            !$assessmentAvailable => 'Assessment pending: progress awaits a current saved rating update.',
            !$eligible => 'Assessment pending: progress needs a current comparable rating from this ball level only.',
            $ownEvents === null || (($metrics['unknown_stage_events'] ?? 0) > 0 && $ownEvents < $target['events']) => 'Some included events have conflicting ball labels and cannot count towards a stage target.',
            !$ready => 'Both the CTR target and the minimum number of events in this ball stage must be met.',
            default => 'Current targets met. Consider the next pathway options subject to each event’s entry rules.',
        };
        [$competition, $options] = match ($current_stage) {
            'red' => [implode(' · ', self::EVENT_OPTIONS['red']), ['Orange-ball events: U/9 B events, academy events, TSA Rainbow Kids events and school leagues, where age and event rules permit']],
            'orange' => [implode(' · ', self::EVENT_OPTIONS['orange']), ['Wilson B / Wilson A competitions']],
            'standard' => [implode(' · ', self::EVENT_OPTIONS['standard']), $suggestedCompetition ? [$suggestedCompetition.', subject to their entry rules'] : ['Ask your coach to review Wilson B / Wilson A options while current comparable evidence is pending']],
            default => ['No rated ball level available yet', ['Play an eligible published event at your ball level']],
        };
        $stageProgress = [];
        foreach (self::STAGES as $ball => $label) {
            $ballTarget = $this->target($ball === 'standard' ? null : $ball);
            $count = $metricAvailable && is_int($metrics['stage_events'][$ball] ?? null) ? $metrics['stage_events'][$ball] : null;
            $matches = $metrics['stage_matches'][$ball] ?? null;
            $ballRating = $metrics['stage_ratings'][$ball] ?? [];
            $ballScore = $ballRating['score'] ?? null;
            $ballScore = $this->validScore($ballScore);
            $ballEligible = $metricAvailable && $ballScore !== null && ($ballRating['comparable'] ?? false) === true
                && ($ballRating['local'] ?? false) !== true && $assessmentAvailable;
            $ballAssessed = $ballEligible && $ballTarget !== null && $count !== null;
            $met = $ballAssessed && $count >= $ballTarget['events'] && $ballScore >= $ballTarget['ctr'];
            $stageProgress[$ball] = ['label' => $label, 'ctr_label' => $this->ctrLabel($ball), 'score' => $ballScore, 'local' => ($ballRating['local'] ?? false) === true,
                'confidence_label' => $ballRating['confidence_label'] ?? null, 'reason' => $ballRating['reason'] ?? null, 'events' => $count,
                'matches' => is_int($matches) && $matches >= 0 ? $matches : null, 'target' => $ballTarget,
                'assessed' => $ballAssessed, 'status' => !$ballAssessed ? 'Not assessed' : ($met ? 'Targets met' : 'Targets not yet met'), 'met' => $met];
        }
        $badgeStage = $current_stage;
        $badge = ['stage' => $badgeStage, 'score' => $stageProgress[$badgeStage]['score'] ?? null,
            'local' => $stageProgress[$badgeStage]['local'] ?? false];
        $ctr_label = $this->ctrLabel($current_stage);
        if ($assessed) $reason = 'Latest saved '.$ctr_label.': '.number_format($score, 1).'. '.$reason;
        $next_steps = match (true) {
            $current_stage === null => ['Play eligible published Cape Tennis singles events at your ball level, then wait for the saved rating update. Ask Cape Tennis if played results are missing.'],
            $target === null => ['Ask Cape Tennis to review the benchmark configuration for this level.'],
            !$assessmentAvailable => ['Wait for the next saved rating update before reviewing progress. Your displayed rating is the latest saved estimate.'],
            !$eligible => ['Play more eligible singles at this ball level to build comparable rating evidence. Ask Cape Tennis or your coach to review a within-group or low-confidence estimate.'],
            $ownEvents === null => ['Ask Cape Tennis to review the published played-event evidence for this ball level.'],
            $ready => [match ($current_stage) {
                'red' => 'Chat with your coach about trying Orange-ball events and check their age and entry requirements.',
                'orange' => 'Chat with your coach about trying Wilson B or Wilson A events and check their age and entry requirements.',
                default => 'Review your next competition options with your coach. TSA tournament entry depends on its own rules, not this benchmark.',
            }, 'You have met both current targets. Your coach can help you plan the next step; earlier levels and event entry are reviewed separately.'],
            default => array_values(array_filter([
                $score < $target['ctr'] ? 'Build your '.$ctr_label.' from '.number_format($score, 1).' to '.number_format($target['ctr'], 1).' ('.number_format($target['ctr'] - $score, 1).' points to the benchmark) through eligible singles at this ball level.' : null,
                $ownEvents < $target['events'] ? 'Play '.($target['events'] - $ownEvents).' more published '.self::STAGES[$current_stage].' '.($target['events'] - $ownEvents === 1 ? 'event' : 'events').' to reach '.$target['events'].' at this level.' : null,
            ])),
        };

        if ($current_stage === 'standard') {
            $assessed = $suggestedDivision !== null;
            $assessment_status = $assessed ? $suggestedCompetition.' suggested' : 'Not assessed';
            $stageProgress['standard']['status'] = $assessment_status;
            $stageProgress['standard']['assessed'] = $assessed;
            $reason = $assessed ? 'Your latest saved '.$ctr_label.' of '.number_format($score, 1).' points to a division to explore with your coach.'
                : (!$assessmentAvailable ? 'The saved rating needs an update before a division can be suggested.' : 'This ball-level rating needs comparable evidence before a division can be suggested.');
            $next_steps = [$assessed ? 'Chat with your coach about which '.$suggestedCompetition.' events would suit you. They can help you check the age, entry and nomination requirements.' : 'When your current comparable rating is ready, chat with your coach about the event options.'];
        }
        $movesAvailable = $available && Schema::hasTable('player_pathway_moves');
        $latestMove = $movesAvailable ? PlayerPathwayMove::where('player_id', $player->id)->latest('id')->first() : null;
        $move = $latestMove ? $this->moveEvidence($latestMove, $metrics) : null;
        $moveOptions = $movesAvailable ? $this->moveOptions($latestMove, $move, $metrics) : [];

        return compact('available', 'stage', 'division', 'saved_division', 'current_stage', 'competition', 'options', 'score', 'events', 'target', 'ownEvents', 'eligible', 'ratingMet', 'eventsMet', 'ready', 'assessed', 'assessment_status', 'reason', 'stageProgress', 'badge', 'ctr_label', 'next_steps', 'wilsonProgress', 'benchmark_division', 'wilsonCutoff', 'tsaCutoff', 'suggestedDivision', 'suggestedCompetition', 'move', 'moveOptions') + [
            'stage_label' => $stage ? self::STAGES[$stage] : 'Stage not recorded',
            'current_stage_label' => $current_stage ? self::STAGES[$current_stage] : 'Not rated yet',
            'metrics' => $metrics,
            'assessment_available' => $assessmentAvailable,
            'currentCompetition' => $currentCompetition,
            'benchmarks' => $this->benchmarks(),
        ];
    }

    private function moveEvidence(PlayerPathwayMove $move, array $metrics): array
    {
        $started = $move->started_on->toDateString();
        $matches = collect($metrics['dated_stage_matches'][$move->to_stage] ?? [])
            ->filter(fn ($match) => $match['date'] >= $started)
            ->sortBy('date')->values();
        $rating = $metrics['stage_ratings'][$move->to_stage] ?? [];
        $score = $matches->contains('in_rating', true) && ($rating['evidence_basis'] ?? null) !== 'finish_only'
            ? $this->validScore($rating['score'] ?? null) : null;

        return [
            'from' => $move->from_stage, 'to' => $move->to_stage, 'started_on' => $started,
            'score' => $score, 'local' => $score !== null && ($rating['local'] ?? false),
            'count' => ($metrics['available'] ?? false) ? $matches->count() : null,
            'matches' => $matches->take(5),
        ];
    }

    private function moveOptions(?PlayerPathwayMove $latest, ?array $move, array $metrics): array
    {
        if ($latest && $move['score'] === null) return [];

        return array_filter(array_reverse(self::NEXT_BALL_LEVEL, true), fn ($to, $from) =>
            (!$latest || $from === $latest->to_stage) && $this->validScore($metrics['stage_ratings'][$from]['score'] ?? null) !== null,
            ARRAY_FILTER_USE_BOTH);
    }

    public function recordMove(Player $player, User $actor, array $input): void
    {
        $this->authorizeWrite($player, $actor);
        abort_unless(Schema::hasTable('player_pathway_moves'), 503, 'Ball-level moves are not available yet.');
        $today = CarbonImmutable::today('Africa/Johannesburg')->toDateString();
        $data = Validator::make($input, [
            'move_from_stage' => ['required', Rule::in(array_keys(self::NEXT_BALL_LEVEL))],
            'move_started_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
        ])->validate();
        $history = app(PlayerRatingHistoryService::class)->forPlayer($player);
        $metrics = $history['pathway_metrics'] ?? [];
        if (($history['reason'] ?? null) !== null || ($metrics['player_id'] ?? null) !== (int) $player->id
            || ($metrics['available'] ?? false) !== true) $metrics = [];

        DB::transaction(function () use ($player, $actor, $data, $metrics) {
            Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
            $from = $data['move_from_stage'];
            $to = self::NEXT_BALL_LEVEL[$from];
            $started = $data['move_started_on'];
            $latest = PlayerPathwayMove::where('player_id', $player->id)->latest('id')->first();
            if ($latest?->to_stage === $to && $latest->started_on->toDateString() === $started) return;

            $move = $latest ? $this->moveEvidence($latest, $metrics) : null;
            if (!isset($this->moveOptions($latest, $move, $metrics)[$from])) {
                throw ValidationException::withMessages(['move_from_stage' => 'Choose a rated starting level with an available move.']);
            }
            if ($latest && $started < $latest->started_on->toDateString()) {
                throw ValidationException::withMessages(['move_started_on' => 'Choose a date on or after the previous move.']);
            }
            PlayerPathwayMove::create([
                'player_id' => $player->id, 'from_stage' => $from, 'to_stage' => $to,
                'started_on' => $started, 'recorded_by_user_id' => $actor->id,
            ]);
        });
    }

    private function ctrLabel(?string $stage): string
    {
        return match ($stage) {
            'red' => 'Red-ball CTR',
            'orange' => 'Orange-ball CTR',
            'standard' => 'Wilson pathway CTR',
            default => 'Ball-level CTR',
        };
    }

    private function benchmarks(): array
    {
        $benchmarks = [];
        foreach (['red' => ['Red-ball CTR', 'Red ball'], 'orange' => ['Orange-ball CTR', 'Orange ball']] as $key => [$label, $ball]) {
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
            'options' => ['Play an eligible published event at your ball level'], 'stage_label' => 'Stage not recorded',
            'score' => null, 'events' => null, 'ownEvents' => null, 'target' => null, 'metrics' => [],
            'badge' => ['stage' => null, 'score' => null, 'local' => false],
            'eligible' => false, 'ratingMet' => false, 'eventsMet' => false, 'ready' => false, 'stageProgress' => [],
            'reason' => 'Assessment pending: no saved ball-level rating is available.',
            'ctr_label' => 'Ball-level CTR',
            'next_steps' => ['Play eligible published Cape Tennis singles events at your ball level, then wait for the saved rating update.'],
            'wilsonProgress' => [], 'benchmark_division' => null,
            'wilsonCutoff' => (new self)->validScore(config('pathway.wilson_a_above')), 'suggestedDivision' => null,
            'tsaCutoff' => (new self)->validScore(config('pathway.tsa_above')), 'suggestedCompetition' => null,
            'currentCompetition' => null,
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
