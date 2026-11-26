<?php
    $scoreSetTypes = [];
    if (!$isTeamFixture && $draw->settings?->score_format) {
        $scoreSetTypes = \App\Domain\Draws\Services\TennisScoreFormat::get($draw->settings->score_format)['set_types'];
    } elseif ($isFlexible && ($draw->settings?->requiresFullSets() ?? true)) {
        $scoreSetTypes = (int) ($draw->settings?->num_sets ?: 3) === 3
            ? ['full', 'full', 'match_tiebreak'] : array_fill(0, (int) ($draw->settings?->num_sets ?: 3), 'full');
    }
?>
<button type="button" class="btn btn-sm btn-primary score-action js-open-score"
                      data-fixture="<?php echo e($match->id); ?>" data-draw-id="<?php echo e($draw->id); ?>" data-has-result="<?php echo e($hasScore ? '1' : '0'); ?>" data-home="<?php echo e($home); ?>" data-away="<?php echo e($away); ?>"
                      data-score-revision="<?php echo e($engine === 'team' ? app(\App\Services\TeamFixtureScoreService::class)->revision($match) : ($engine === 'standard' ? app(\App\Services\Draw\IndividualScoreRevision::class)->forFixture($match) : '')); ?>" data-engine="<?php echo e($engine); ?>" data-participant-revision="<?php echo e($engine === 'team' ? app(\App\Services\TeamParticipantHistoryService::class)->revision($match) : ''); ?>"
                      data-store="<?php echo e($isFlexible ? $flexibleUrl : $normalStore); ?>"
                      data-delete="<?php echo e($isFlexible ? $flexibleUrl : $normalDelete); ?>"
                      data-revision="<?php echo e($draw->flexibleMonrad?->revision ?? 0); ?>"
                      data-score-format="<?php echo e($isTimed ? 'timed' : ''); ?>" data-stage="<?php echo e($isTeamFixture ? '' : $match->stage); ?>"
                      data-set-types='<?php echo json_encode($scoreSetTypes, 15, 512) ?>'
                      data-home-registration="<?php echo e($isTeamFixture ? '' : $match->registration1_id); ?>" data-away-registration="<?php echo e($isTeamFixture ? '' : $match->registration2_id); ?>"
                      data-deciding-winner="<?php echo e($isTimed && $hasScore ? ($winnerRegistration ?? '') : ''); ?>"
                      data-walkover-winner="<?php echo e($isWalkover ? $match->winner_registration : ''); ?>"
                      data-retired-registration="<?php echo e($isRetired ? ($match->winner_registration == $match->registration1_id ? $match->registration2_id : $match->registration1_id) : ''); ?>"
                      data-match-minutes="<?php echo e($matchTiming['duration_minutes'] ?? ''); ?>"
                      data-changeover-minutes="<?php echo e($matchTiming['court_gap_minutes'] ?? ''); ?>"
                      data-num-sets="<?php echo e($draw->settings?->score_format ? \App\Domain\Draws\Services\TennisScoreFormat::maxSets($draw->settings->score_format) : max(1, min(5, (int) ($draw->settings?->num_sets ?: 3)))); ?>"
                      data-score-guidance="<?php echo e($draw->settings?->score_format
                        ? \App\Domain\Draws\Services\TennisScoreFormat::rules($draw->settings->score_format)
                        : 'Enter completed sets'); ?>"
                      data-require-full-sets="<?php echo e($isFlexible && ($draw->settings?->requiresFullSets() ?? true) ? '1' : '0'); ?>"
                      data-scores='<?php echo json_encode($sets, 15, 512) ?>'>
                  <?php echo e($hasScore ? 'Correct score' : 'Enter score'); ?>

                </button>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\scoring\score-button.blade.php ENDPATH**/ ?>