@php
    $scoreSetTypes = [];
    if (!$isTeamFixture && $draw->settings?->score_format) {
        $scoreSetTypes = \App\Domain\Draws\Services\TennisScoreFormat::get($draw->settings->score_format)['set_types'];
    } elseif ($isFlexible && ($draw->settings?->requiresFullSets() ?? true)) {
        $scoreSetTypes = (int) ($draw->settings?->num_sets ?: 3) === 3
            ? ['full', 'full', 'match_tiebreak'] : array_fill(0, (int) ($draw->settings?->num_sets ?: 3), 'full');
    }
@endphp
<button type="button" class="btn btn-sm btn-primary score-action js-open-score"
                      data-fixture="{{ $match->id }}" data-draw-id="{{ $draw->id }}" data-has-result="{{ $hasScore ? '1' : '0' }}" data-home="{{ $home }}" data-away="{{ $away }}"
                      data-score-revision="{{ $engine === 'team' ? app(\App\Services\TeamFixtureScoreService::class)->revision($match) : ($engine === 'standard' ? app(\App\Services\Draw\IndividualScoreRevision::class)->forFixture($match) : '') }}" data-engine="{{ $engine }}" data-participant-revision="{{ $engine === 'team' ? app(\App\Services\TeamParticipantHistoryService::class)->revision($match) : '' }}"
                      data-store="{{ $isFlexible ? $flexibleUrl : $normalStore }}"
                      data-delete="{{ $isFlexible ? $flexibleUrl : $normalDelete }}"
                      data-revision="{{ $draw->flexibleMonrad?->revision ?? 0 }}"
                      data-score-format="{{ $isTimed ? 'timed' : '' }}" data-stage="{{ $isTeamFixture ? '' : $match->stage }}"
                      data-set-types='@json($scoreSetTypes)'
                      data-home-registration="{{ $isTeamFixture ? '' : $match->registration1_id }}" data-away-registration="{{ $isTeamFixture ? '' : $match->registration2_id }}"
                      data-deciding-winner="{{ $isTimed && $hasScore ? ($winnerRegistration ?? '') : '' }}"
                      data-walkover-winner="{{ $isWalkover ? $match->winner_registration : '' }}"
                      data-retired-registration="{{ $isRetired ? ($match->winner_registration == $match->registration1_id ? $match->registration2_id : $match->registration1_id) : '' }}"
                      data-match-minutes="{{ $matchTiming['duration_minutes'] ?? '' }}"
                      data-changeover-minutes="{{ $matchTiming['court_gap_minutes'] ?? '' }}"
                      data-num-sets="{{ $draw->settings?->score_format ? \App\Domain\Draws\Services\TennisScoreFormat::maxSets($draw->settings->score_format) : max(1, min(5, (int) ($draw->settings?->num_sets ?: 3))) }}"
                      data-score-guidance="{{ $draw->settings?->score_format
                        ? \App\Domain\Draws\Services\TennisScoreFormat::rules($draw->settings->score_format)
                        : 'Enter completed sets' }}"
                      data-require-full-sets="{{ $isFlexible && ($draw->settings?->requiresFullSets() ?? true) ? '1' : '0' }}"
                      data-scores='@json($sets)'>
                  {{ $hasScore ? 'Correct score' : 'Enter score' }}
                </button>
