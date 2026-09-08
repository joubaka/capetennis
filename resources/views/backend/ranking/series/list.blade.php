@extends('layouts.backend')

@section('title', $series->name . ' – Ranking List')

@section('vendor-style')
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
@endsection

@section('page-style')
<style>
  .rank-pos { font-weight: 700; width: 60px; }
  .points { font-weight: 600; }
  .category-title { font-weight: 600; margin-top: 1rem; }
  .ranking-event-score {
    min-width: 170px;
    border: 1px solid var(--bs-border-color);
    border-left-width: 4px;
    border-radius: .5rem;
    padding: .45rem .6rem;
    color: inherit;
    background: var(--bs-body-bg);
    transition: box-shadow .15s ease, transform .15s ease;
  }
  .ranking-event-score:hover { box-shadow: 0 .2rem .65rem rgba(0, 0, 0, .1); transform: translateY(-1px); }
  .ranking-event-score--counted { border-left-color: var(--bs-success); }
  .ranking-event-score--dropped { border-left-color: var(--bs-danger); opacity: .82; }
  .ranking-event-score--automatic { border-left-color: var(--bs-warning); background: rgba(var(--bs-warning-rgb), .08); }
  .ranking-event-name { max-width: 220px; }
  .tie-decision-note { background: rgba(var(--bs-warning-rgb), .08) !important; }
  .tie-order-grid { display: grid; grid-template-columns: minmax(0, 1fr) 110px; gap: .5rem; align-items: center; }
  @media (max-width: 575.98px) { .tie-order-grid { grid-template-columns: minmax(0, 1fr) 88px; } }
  .tiebreak-note { font-size: .75rem; }
  .ranking-process-step { border-left: 3px solid var(--bs-border-color); padding-left: .85rem; }
  .ranking-process-step.is-current { border-left-color: var(--bs-primary); }
  .ranking-process-step.is-complete { border-left-color: var(--bs-success); }
  .ranking-process-number {
    align-items: center;
    background: var(--bs-secondary-bg);
    border-radius: 50%;
    display: inline-flex;
    font-size: .75rem;
    font-weight: 700;
    height: 1.5rem;
    justify-content: center;
    width: 1.5rem;
  }
  .ranking-process-step.is-current .ranking-process-number { background: var(--bs-primary); color: #fff; }
  .ranking-process-step.is-complete .ranking-process-number { background: var(--bs-success); color: #fff; }

  @media print {
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
    .card { border: 1px solid #ccc !important; box-shadow: none !important; break-inside: avoid; }
    .card-header { background: #eee !important; color: #000 !important; }
    .badge { border: 1px solid #ccc; color: #000 !important; background: #f0f0f0 !important; }
    .badge.bg-success { background: #d4edda !important; border-color: #28a745; }
    .badge.bg-danger { background: #f8d7da !important; border-color: #dc3545; }
    .badge.bg-warning { background: #fff3cd !important; border-color: #ffc107; }
    .ranking-event-score { min-width: 0; box-shadow: none !important; transform: none !important; }
    a { color: #000 !important; text-decoration: none !important; }
    .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: #f9f9f9 !important; }
  }
</style>
@endsection

@section('content')
<div class="container-xl print-area">

  {{-- HEADER --}}
  <div class="card mb-4 no-print">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h4 class="mb-1">Ranking List</h4>
        <div class="text-muted">
          {{ $series->name }} ({{ $series->year }})
        </div>
        <div class="mt-2 d-flex gap-2 align-items-center flex-wrap">
          <span class="badge bg-label-{{ $activeStatus === 'published' ? 'success' : ($activeStatus === 'reviewed' ? 'info' : ($activeStatus === 'calculated' ? 'warning' : 'secondary')) }}">
            {{ $activeStatus ? ucfirst($activeStatus) : 'No ranking run' }}
          </span>
          @if($activeRunId)
            <small class="text-muted">Run {{ $activeRunId }}</small>
          @endif
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('series.show', $series) }}" class="btn btn-outline-secondary">
          <i class="ti ti-arrow-left me-1"></i> Back to Series
        </a>

        <a href="{{ route('ranking.series.audit', $series) }}" class="btn btn-outline-info">
          <i class="ti ti-clipboard-check me-1"></i> Audit Rankings
        </a>

        <button id="print-ranking" class="btn btn-outline-dark" onclick="window.print()">
          <i class="ti ti-printer me-1"></i> Print Rankings
        </button>

        <button id="rebuild-ranking" class="btn btn-warning">
          <i class="ti ti-refresh me-1"></i> Rebuild Rankings
        </button>

        @if($activeStatus === 'calculated')
          <button class="btn btn-info ranking-lifecycle-action"
                  data-url="{{ route('ranking.series.ranking.review', $series) }}"
                  data-confirm="Mark this complete run as reviewed?">
            <i class="ti ti-check me-1"></i> Mark Reviewed
          </button>
        @elseif($activeStatus === 'reviewed')
          @if(!$reviewCampaign)
            <button id="share-ranking-review" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#rankingReviewModal">
              <i class="ti ti-mail-forward me-1"></i> Share for Review
            </button>
          @endif
          <button class="btn btn-success ranking-lifecycle-action"
                  data-url="{{ route('ranking.series.ranking.publish', $series) }}"
                  data-confirm="{{ $reviewCampaign ? 'Finalize and publish the exact ranking circulated to participants?' : 'Publish this reviewed run directly to the public leaderboard?' }}">
            <i class="ti ti-world-upload me-1"></i> {{ $reviewCampaign ? 'Finalize & Publish' : 'Publish' }}
          </button>
        @elseif($activeStatus === 'published' && $hasArchivedSnapshot)
          <button class="btn btn-outline-danger ranking-lifecycle-action"
                  data-url="{{ route('ranking.series.ranking.rollback', $series) }}"
                  data-confirm="Roll back to the previous published snapshot?">
            <i class="ti ti-history me-1"></i> Roll Back
          </button>
        @endif
      </div>
    </div>
  </div>

  @php
    $pendingTieDecisions = collect($tieDecisionAdvisories ?? [])->filter(
      fn ($decision) => empty($decision['confirmed'])
    )->count();
    $legacyTieDecisions = collect($tieDecisionAdvisories ?? [])->filter(
      fn ($decision) => !empty($decision['requires_rebuild'])
    )->count();
    $workflowStep = match (true) {
      !$activeStatus => 1,
      $activeStatus === 'calculated' && $pendingTieDecisions > 0 => 2,
      $activeStatus === 'calculated' => 3,
      $activeStatus === 'reviewed' && !$reviewCampaign => 4,
      $activeStatus === 'reviewed' => 5,
      $activeStatus === 'published' => 6,
      default => 1,
    };
  @endphp

  <div class="card mb-4 no-print border-start border-primary border-3" id="ranking-process-guide">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1">How to complete this ranking</h5>
        <div class="small text-muted">Work through these checks in order. Nothing is shown publicly until a reviewed run is published and leaderboard visibility is enabled.</div>
      </div>
      <span class="badge bg-label-primary">{{ $workflowStep > 5 ? 'Complete' : 'Step '.$workflowStep.' of 5' }}</span>
    </div>
    <div class="card-body">
      <div class="row g-3">
        @foreach([
          1 => ['Build the ranking', 'Use Rebuild Rankings after all official event results and ranking categories are ready.'],
          2 => ['Check scores and ties', 'Open event scores, use Audit Rankings, and confirm the final order and reason for every highlighted tie.'],
          3 => ['Mark it reviewed', 'Only mark the run reviewed after the totals, counted scores, dropped scores and tie decisions are correct.'],
          4 => ['Share with participants', 'Review the exact recipients, email and reply cutoff, then send the provisional ranking for feedback.'],
          5 => ['Finalize and publish', 'After the review window and any corrections, publish this exact reviewed run. Public leaderboard visibility remains a separate setting.'],
        ] as $stepNumber => [$stepTitle, $stepHelp])
          @php
            $stepClass = $workflowStep > $stepNumber ? 'is-complete' : ($workflowStep === $stepNumber ? 'is-current' : '');
          @endphp
          <div class="col-lg col-md-6">
            <div class="ranking-process-step {{ $stepClass }} h-100">
              <div class="d-flex align-items-center gap-2 mb-1">
                <span class="ranking-process-number">{{ $workflowStep > $stepNumber ? '✓' : $stepNumber }}</span>
                <strong class="small">{{ $stepTitle }}</strong>
              </div>
              <div class="small text-muted">{{ $stepHelp }}</div>
            </div>
          </div>
        @endforeach
      </div>

      <div class="alert {{ $activeStatus === 'published' ? 'alert-success' : ($legacyTieDecisions > 0 ? 'alert-warning' : 'alert-info') }} mt-3 mb-0 py-2" role="status">
        @if(!$activeStatus)
          <strong>Next:</strong> Build the ranking once the official results and category links are ready.
        @elseif($activeStatus === 'calculated' && $legacyTieDecisions > 0)
          <strong>Next:</strong> Rebuild this legacy calculated run so its {{ $legacyTieDecisions }} tie {{ Str::plural('decision', $legacyTieDecisions) }} can be recorded safely.
        @elseif($activeStatus === 'calculated' && $pendingTieDecisions > 0)
          <strong>Next:</strong> Review the ranking and confirm {{ $pendingTieDecisions }} highlighted tie {{ Str::plural('decision', $pendingTieDecisions) }} below. You cannot mark the ranking reviewed until all are confirmed.
        @elseif($activeStatus === 'calculated')
          <strong>Next:</strong> Check the totals and event scores below, use Audit Rankings if needed, then select <strong>Mark Reviewed</strong>.
        @elseif($activeStatus === 'reviewed' && !$reviewCampaign)
          <strong>Next:</strong> Select <strong>Share for Review</strong> to check recipients, the message and cutoff before emailing participants.
        @elseif($activeStatus === 'reviewed')
          <strong>Next:</strong> Monitor delivery and participant feedback. Correct source results and rebuild if necessary; otherwise wait for the review to finish and select <strong>Finalize &amp; Publish</strong>.
        @elseif($activeStatus === 'published')
          <strong>Complete:</strong> This run is published. Use Series Settings to control public leaderboard visibility. Rebuild only when you intend to create and review a replacement run.
        @endif
      </div>
    </div>
  </div>

  @if($reviewCampaign && $reviewCampaignReport)
    <div class="card mb-4 no-print border-start border-info border-3" id="ranking-review-status-card">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
          <div>
            <h5 class="mb-1">Participant ranking review</h5>
            <div class="text-muted">Run {{ $reviewCampaign->run_id }} · cutoff {{ $reviewCampaignReport['cutoff_display'] }}</div>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <span class="badge bg-label-primary">{{ $reviewCampaignReport['player_count'] }} players</span>
              <span class="badge bg-label-info">{{ $reviewCampaignReport['recipient_count'] }} recipients</span>
              <span class="badge bg-label-success">{{ $reviewCampaignReport['delivery']['sent'] }} sent</span>
              <span class="badge bg-label-warning">{{ $reviewCampaignReport['delivery']['queued'] }} queued</span>
              <span class="badge bg-label-danger">{{ $reviewCampaignReport['delivery']['failed'] }} failed</span>
              @if($reviewCampaignReport['missing_email_count'])
                <span class="badge bg-label-secondary">{{ $reviewCampaignReport['missing_email_count'] }} missing email</span>
              @endif
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-primary btn-sm" href="{{ app(\App\Domain\Ranking\Services\RankingReviewCirculationService::class)->signedPublicUrl($reviewCampaign) }}" target="_blank" rel="noopener">
              Preview shared ranking
            </a>
            @if($reviewCampaignReport['delivery']['failed'] > 0 && !in_array($reviewCampaign->status, ['finalized', 'superseded']))
              <button class="btn btn-outline-danger btn-sm" id="retry-ranking-review" data-url="{{ route('ranking.series.review-circulation.retry', [$series, $reviewCampaign]) }}">
                Retry failed emails
              </button>
            @endif
          </div>
        </div>
        @if($reviewCampaignReport['missing_email_count'])
          <details class="mt-3">
            <summary class="small fw-semibold">Players without a usable email address</summary>
            <ul class="small mt-2 mb-0">
              @foreach($reviewCampaignReport['missing'] as $missing)
                <li>{{ $missing['name'] }}@if($missing['categories']) — {{ implode(', ', $missing['categories']) }}@endif</li>
              @endforeach
            </ul>
          </details>
        @endif
      </div>
    </div>
  @endif

  @if($activeStatus === 'published' && $nextMastersEvent)
    <div class="alert alert-success no-print d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div><strong>Rankings finalized.</strong> The next invitation workflow remains separately reviewable and confirmed.</div>
      <a href="{{ route('backend.masters.setup', $nextMastersEvent) }}" class="btn btn-success btn-sm">
        Prepare invitations for {{ $nextMastersEvent->name }}
      </a>
    </div>
  @endif

  {{-- PRINT HEADER (only shown when printing) --}}
  <div class="d-none d-print-block mb-4">
    <h2>{{ $series->name }} ({{ $series->year }}) – Ranking List</h2>
    <small class="text-muted">Printed: {{ now()->format('d M Y H:i') }}</small>
    <hr>
  </div>

  {{-- BODY --}}
  @foreach($categories as $category)
    @php
      $rows = $rankings
        ->where('category_id', $category->id)
        ->sortBy('rank_position')
        ->values();
    @endphp

    @if($rows->isNotEmpty())
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0 category-title">{{ $category->name }}</h5>
        </div>

        <div class="card-body table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th width="70">Rank</th>
                <th>Player</th>
                <th width="160">Total Points</th>
                <th>Scores per Event</th>
              </tr>
            </thead>

            <tbody>
              @foreach($rows as $rowIndex => $row)
                @php
                  $legs = collect($scoreDetails[$row->id] ?? []);
                  $meta = is_array($row->meta_json) ? $row->meta_json : [];
                  $tieKey = $row->ranking_list_id.':'.$row->total_points;
                  $nextRow = $rows->get($rowIndex + 1);
                  $isLastInTie = !$nextRow || (int) $nextRow->total_points !== (int) $row->total_points;
                  $tieDecision = $isLastInTie ? ($tieDecisionAdvisories[$tieKey] ?? null) : null;
                @endphp

                <tr>
                  <td class="rank-pos">#{{ $row->rank_position }}</td>

                  <td>
                    {{ $row->player->full_name
                      ?? $row->player->name
                      ?? 'Unknown Player' }}
                  </td>

                  <td class="points">{{ $row->total_points }}</td>

                  <td>
                    <div class="d-flex gap-2 flex-wrap">
                      @foreach($legs as $leg)
                        @php
                          $event = $leg['event'];
                          $isAuto = $leg['synthetic'];
                          $scoreClass = $isAuto
                            ? 'ranking-event-score--automatic'
                            : ($leg['counted'] ? 'ranking-event-score--counted' : 'ranking-event-score--dropped');
                          $fragment = $leg['category_event_id'] ? '#category-event-'.$leg['category_event_id'] : '';
                          $statusLabel = $isAuto ? 'Automatic award' : ($leg['counted'] ? 'Counted' : 'Third score');
                        @endphp
                        @if($event)
                          <a
                            href="{{ route('admin.events.results.individual', $event).$fragment }}"
                            class="ranking-event-score {{ $scoreClass }} text-decoration-none d-block"
                            title="Open {{ $event->name }} final positions"
                          >
                            <span class="d-flex justify-content-between gap-2 align-items-start">
                              <span class="ranking-event-name small fw-semibold text-truncate">{{ $event->name }}</span>
                              <i class="ti ti-external-link small" aria-hidden="true"></i>
                            </span>
                            <span class="d-block mt-1">
                              <strong>{{ $leg['points'] }} pts</strong>
                              <span class="text-muted">·
                                @if($isAuto)
                                  Automatic #{{ $leg['ranking_position'] ?? 1 }}
                                @elseif($leg['actual_position'])
                                  Finished #{{ $leg['actual_position'] }}
                                @else
                                  Position unavailable
                                @endif
                              </span>
                            </span>
                            @if(!$isAuto && $leg['actual_position'] && $leg['ranking_position'] && $leg['actual_position'] !== $leg['ranking_position'])
                              <span class="d-block small text-warning-emphasis">Ranking points position #{{ $leg['ranking_position'] }}</span>
                            @endif
                            <span class="badge mt-1 {{ $isAuto ? 'bg-warning text-dark' : ($leg['counted'] ? 'bg-label-success' : 'bg-label-danger') }}">
                              {{ $statusLabel }}
                            </span>
                          </a>
                        @else
                          <span class="ranking-event-score {{ $scoreClass }} d-block">
                            <strong>{{ $leg['points'] }} pts</strong>
                            <span class="d-block small text-muted">{{ $statusLabel }} · Event unavailable</span>
                          </span>
                        @endif
                      @endforeach
                    </div>
                    @foreach($meta['tiebreak_notes'] ?? [] as $tiebreakNote)
                      <div class="tiebreak-note text-muted mt-1">
                        <i class="ti ti-scale me-1" aria-hidden="true"></i>{{ $tiebreakNote }}
                      </div>
                    @endforeach
                  </td>
                </tr>

                @if($tieDecision)
                  <tr class="tie-decision-note">
                    <td></td>
                    <td colspan="3">
                      <div class="d-flex gap-2 align-items-start py-2">
                        <span class="badge {{ $tieDecision['confirmed'] ? 'bg-success' : 'bg-warning text-dark' }} mt-1">
                          {{ $tieDecision['confirmed'] ? 'Tie decision confirmed' : 'Confirmation required' }}
                        </span>
                        <div class="flex-grow-1">
                          <div class="fw-semibold">
                            @if($tieDecision['confirmed'])
                              @php
                                $reasonLabel = match($tieDecision['reason']) {
                                  'head_to_head' => 'qualifying head-to-head',
                                  'third_event_score' => 'third-event score',
                                  'previous_ranking' => 'previous published ranking',
                                  'shared_position' => 'shared position',
                                  default => 'administrator decision',
                                };
                              @endphp
                              Tie broken by {{ $reasonLabel }} — administrator confirmed.
                              @if($tieDecision['note']) <span class="fw-normal">{{ $tieDecision['note'] }}</span> @endif
                            @elseif($tieDecision['requires_rebuild'])
                              This legacy calculated tie has no run-scoped decision record. Rebuild the ranking before review.
                            @else
                              Every equal-points ranking requires an administrator’s final decision before review, sharing, or publication.
                            @endif
                          </div>
                          @if($tieDecision['matches'])
                            <div class="small fw-semibold mt-2">Head-to-head review</div>
                          @endif
                          @foreach($tieDecision['matches'] as $match)
                            @php
                              $matchEvent = $series->events->firstWhere('id', $match['event_id']);
                              $matchFragment = $match['category_event_id'] ? '#category-event-'.$match['category_event_id'] : '';
                            @endphp
                            <div class="small mt-1">
                              <strong>{{ $match['winner_name'] }}</strong> beat {{ $match['loser_name'] }}
                              @if($match['score'])({{ $match['score'] }})@endif
                              at
                              @if($matchEvent)
                                <a href="{{ route('admin.events.results.individual', $matchEvent).$matchFragment }}">{{ $match['event_name'] }}</a>
                              @else
                                {{ $match['event_name'] }}
                              @endif
                              <span class="text-muted">· {{ $match['phase'] === 'playoff' ? 'Playoff phase' : 'Single-phase round robin' }} · qualifying full set {{ $match['qualifying_set']['score'] }}</span>
                            </div>
                          @endforeach
                          @if($tieDecision['matches'])
                            <div class="small text-muted mt-1">Only a playoff match, or a sole-phase round-robin match, with a completed standard full set reaching six games can qualify.</div>
                          @endif

                          @if($activeStatus === 'calculated' && !$tieDecision['confirmed'] && !$tieDecision['requires_rebuild'] && $tieDecision['tie_key'])
                            <form class="tie-decision-form border rounded p-3 mt-2 bg-white"
                                  data-url="{{ route('ranking.series.ranking.tie-decision.confirm', [$series, $tieDecision['tie_key']]) }}">
                              <div class="small text-muted mb-2">
                                @if($tieDecision['suggested_method'] === 'head_to_head')
                                  Suggested order uses the qualifying head-to-head shown above.
                                @elseif($tieDecision['suggested_method'] === 'third_event_score')
                                  Suggested order uses the configured third-event score.
                                @else
                                  No complete automatic rule resolved this tie. Select the final order and explain the decision.
                                @endif
                              </div>
                              <div class="tie-order-grid mb-3">
                                @foreach($tieDecision['players'] as $player)
                                  <label for="tie-order-{{ $tieDecision['tie_key'] }}-{{ $player['id'] }}">{{ $player['name'] }}</label>
                                  <select class="form-select form-select-sm tie-order-select"
                                          id="tie-order-{{ $tieDecision['tie_key'] }}-{{ $player['id'] }}"
                                          data-player-id="{{ $player['id'] }}">
                                    @foreach($tieDecision['players'] as $positionIndex => $unusedPlayer)
                                      <option value="{{ $positionIndex + 1 }}"
                                        {{ array_search($player['id'], $tieDecision['suggested_order'], true) === $positionIndex ? 'selected' : '' }}>
                                        {{ $positionIndex + 1 }}
                                      </option>
                                    @endforeach
                                  </select>
                                @endforeach
                              </div>
                              <div class="row g-2">
                                <div class="col-md-5">
                                  <label class="form-label small fw-semibold">Reason</label>
                                  <select class="form-select form-select-sm tie-reason" required>
                                    @if($tieDecision['head_to_head_decision'])
                                      <option value="head_to_head" {{ $tieDecision['suggested_method'] === 'head_to_head' ? 'selected' : '' }}>Qualifying head-to-head</option>
                                    @endif
                                    @if($tieDecision['suggested_method'] === 'third_event_score')
                                      <option value="third_event_score" selected>Third-event score</option>
                                    @endif
                                    <option value="previous_ranking" {{ $tieDecision['suggested_method'] === 'manual' ? 'selected' : '' }}>Previous published ranking</option>
                                    <option value="shared_position">Keep a shared position</option>
                                    <option value="other">Other administrator decision</option>
                                  </select>
                                </div>
                                <div class="col-md-7">
                                  <label class="form-label small fw-semibold">Decision note</label>
                                  <textarea class="form-control form-control-sm tie-note" rows="2" maxlength="1000" placeholder="Add the reason or supporting context. Required when Other is selected."></textarea>
                                </div>
                              </div>
                              <button type="submit" class="btn btn-sm btn-warning mt-2">
                                <i class="ti ti-check me-1"></i>Confirm final tie decision
                              </button>
                            </form>
                          @elseif($activeStatus === 'calculated' && !$tieDecision['confirmed'] && !$tieDecision['tie_key'] && !empty($tieDecision['head_to_head_decision']['fixture_id']))
                            <button type="button"
                                    class="btn btn-sm btn-warning mt-2 legacy-head-to-head-confirm"
                                    data-url="{{ route('ranking.series.ranking.head-to-head.confirm', [$series, $tieDecision['head_to_head_decision']['fixture_id']]) }}">
                              <i class="ti ti-check me-1"></i>Confirm qualifying head-to-head
                            </button>
                          @endif
                        </div>
                      </div>
                    </td>
                  </tr>
                @endif
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  @endforeach

  <div class="modal fade no-print" id="rankingReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">Share provisional rankings for participant review</h5>
            <div class="small text-muted" id="ranking-review-run">Loading reviewed ranking…</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="ranking-review-error" class="alert alert-danger d-none"></div>
          <div class="row g-3">
            <div class="col-lg-5">
              <div class="alert alert-info py-2">
                <div class="d-flex flex-wrap gap-2" id="ranking-review-counts"></div>
                <div class="small mt-2">Only players on this exact reviewed ranking run are included. Shared family addresses receive one email.</div>
              </div>
              <details class="mb-3">
                <summary class="small fw-semibold">Review exact recipient list</summary>
                <div class="table-responsive mt-2" style="max-height:220px">
                  <table class="table table-sm mb-0">
                    <thead><tr><th>Player(s)</th><th>Email</th><th>Category</th></tr></thead>
                    <tbody id="ranking-review-recipients"></tbody>
                  </table>
                </div>
              </details>
              <input type="hidden" id="ranking-review-uuid">
              <div class="mb-3">
                <label class="form-label">Reply cutoff <span class="text-danger">*</span></label>
                <input type="datetime-local" class="form-control" id="ranking-review-cutoff" required>
                <div class="form-text">Africa/Johannesburg time. This cannot be silently changed after sending.</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Reply-to address <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="ranking-review-reply-to" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Subject <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="ranking-review-subject" maxlength="255" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Message <span class="text-danger">*</span></label>
                <textarea class="form-control" id="ranking-review-message" rows="11" maxlength="10000" required></textarea>
              </div>
              <details id="ranking-review-missing-wrap" class="mb-3 d-none">
                <summary class="small fw-semibold text-warning">Players without a valid email</summary>
                <ul class="small mt-2" id="ranking-review-missing"></ul>
              </details>
              <button type="button" class="btn btn-outline-primary" id="refresh-ranking-email-preview">
                <i class="ti ti-eye me-1"></i> Refresh email preview
              </button>
            </div>
            <div class="col-lg-7">
              <label class="form-label fw-semibold">Email preview</label>
              <div class="small text-muted mb-2" id="ranking-email-preview-recipient">Personalized preview</div>
              <iframe id="ranking-email-preview" title="Ranking review email preview" class="w-100 border rounded bg-white" style="min-height:620px"></iframe>
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex flex-wrap justify-content-between gap-2">
          <div class="form-check me-auto">
            <input class="form-check-input" type="checkbox" id="ranking-review-confirm">
            <label class="form-check-label" for="ranking-review-confirm">I reviewed the recipients, cutoff and email above.</label>
          </div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="send-ranking-review" disabled>
            <i class="ti ti-send me-1"></i> Queue review emails
          </button>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@section('page-script')
<script>
toastr.options = {
  closeButton: true,
  progressBar: true,
  positionClass: 'toast-top-right',
  timeOut: 2500
};

document.getElementById('rebuild-ranking')?.addEventListener('click', () => {
  const btn = document.getElementById('rebuild-ranking');
  btn.disabled = true;

  fetch('{{ route('ranking.series.rebuild', $series) }}', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': '{{ csrf_token() }}'
    }
  })
  .then(async response => {
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.message || 'Failed to rebuild rankings');
    return payload;
  })
  .then(r => {
    toastr.success(r.message);
    location.reload();
  })
  .catch(error => {
    toastr.error(error.message || 'Failed to rebuild rankings');
    btn.disabled = false;
  });
});

document.querySelectorAll('.ranking-lifecycle-action').forEach(button => {
  button.addEventListener('click', async () => {
    if (!window.confirm(button.dataset.confirm)) return;

    button.disabled = true;
    try {
      const response = await fetch(button.dataset.url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Ranking action failed');
      toastr.success(payload.message);
      location.reload();
    } catch (error) {
      toastr.error(error.message || 'Ranking action failed');
      button.disabled = false;
    }
  });
});

document.querySelectorAll('.tie-decision-form').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const ordered = Array.from(form.querySelectorAll('.tie-order-select'))
      .map(select => ({playerId: Number(select.dataset.playerId), position: Number(select.value)}))
      .sort((a, b) => a.position - b.position);
    if (new Set(ordered.map(item => item.position)).size !== ordered.length) {
      toastr.error('Give every tied player a unique order position.');
      return;
    }
    if (!window.confirm('Confirm this final tie order and reason for the current ranking run?')) return;

    button.disabled = true;
    try {
      const response = await fetch(form.dataset.url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
          ordered_player_ids: ordered.map(item => item.playerId),
          reason: form.querySelector('.tie-reason').value,
          note: form.querySelector('.tie-note').value.trim(),
        })
      });
      const payload = await response.json();
      if (!response.ok) {
        const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(firstError || payload.message || 'Tie confirmation failed');
      }
      toastr.success(payload.message);
      location.reload();
    } catch (error) {
      toastr.error(error.message || 'Tie confirmation failed');
      button.disabled = false;
    }
  });
});

document.querySelectorAll('.legacy-head-to-head-confirm').forEach(button => {
  button.addEventListener('click', async () => {
    if (!window.confirm('Confirm this qualifying head-to-head for the current ranking run?')) return;
    button.disabled = true;
    try {
      const response = await fetch(button.dataset.url, {
        method: 'POST',
        headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Head-to-head confirmation failed');
      toastr.success(payload.message);
      location.reload();
    } catch (error) {
      toastr.error(error.message || 'Head-to-head confirmation failed');
      button.disabled = false;
    }
  });
});

const reviewModal = document.getElementById('rankingReviewModal');
const reviewConfirm = document.getElementById('ranking-review-confirm');
const reviewSendButton = document.getElementById('send-ranking-review');
const reviewError = document.getElementById('ranking-review-error');

function rankingReviewPayload() {
  return {
    uuid: document.getElementById('ranking-review-uuid').value,
    cutoff_at: document.getElementById('ranking-review-cutoff').value,
    reply_to: document.getElementById('ranking-review-reply-to').value.trim(),
    subject: document.getElementById('ranking-review-subject').value.trim(),
    message: document.getElementById('ranking-review-message').value.trim(),
  };
}

async function responsePayload(response) {
  const payload = await response.json();
  if (!response.ok) {
    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
    throw new Error(firstError || payload.message || 'Request failed.');
  }
  return payload;
}

async function refreshRankingEmailPreview() {
  const iframe = document.getElementById('ranking-email-preview');
  iframe.srcdoc = '<p style="font-family:Arial;padding:20px">Loading preview…</p>';
  const response = await fetch('{{ route('ranking.series.review-circulation.email-preview', $series) }}', {
    method: 'POST',
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
    body: JSON.stringify(rankingReviewPayload()),
  });
  const payload = await responsePayload(response);
  iframe.srcdoc = payload.html;
  document.getElementById('ranking-email-preview-recipient').textContent = `Personalized preview for ${payload.preview_recipient}`;
}

reviewModal?.addEventListener('show.bs.modal', async () => {
  reviewError.classList.add('d-none');
  reviewConfirm.checked = false;
  reviewSendButton.disabled = true;
  try {
    const response = await fetch('{{ route('ranking.series.review-circulation.preview', $series) }}', {headers: {'Accept': 'application/json'}});
    const payload = await responsePayload(response);
    document.getElementById('ranking-review-run').textContent = `Reviewed run ${payload.run_id}`;
    document.getElementById('ranking-review-uuid').value = payload.defaults.uuid;
    document.getElementById('ranking-review-cutoff').value = payload.defaults.cutoff_input;
    document.getElementById('ranking-review-reply-to').value = payload.defaults.reply_to;
    document.getElementById('ranking-review-subject').value = payload.defaults.subject;
    document.getElementById('ranking-review-message').value = payload.defaults.message;
    document.getElementById('ranking-review-counts').innerHTML =
      `<span class="badge bg-primary">${payload.audience.player_count} ranked players</span>` +
      `<span class="badge bg-info">${payload.audience.recipient_count} unique emails</span>` +
      `<span class="badge bg-secondary">${payload.audience.shared_email_count} shared emails</span>` +
      `<span class="badge bg-warning text-dark">${payload.audience.missing_email_count} missing</span>`;
    const recipientRows = document.getElementById('ranking-review-recipients');
    recipientRows.innerHTML = '';
    payload.audience.recipients.forEach(item => {
      const row = document.createElement('tr');
      [item.player_names.join(' / '), item.email, item.category_names.join(', ')].forEach(value => {
        const cell = document.createElement('td');
        cell.textContent = value;
        row.appendChild(cell);
      });
      recipientRows.appendChild(row);
    });
    const missingWrap = document.getElementById('ranking-review-missing-wrap');
    const missingList = document.getElementById('ranking-review-missing');
    missingList.innerHTML = '';
    payload.audience.missing.forEach(item => {
      const li = document.createElement('li');
      li.textContent = `${item.name}${item.categories.length ? ' — ' + item.categories.join(', ') : ''}`;
      missingList.appendChild(li);
    });
    missingWrap.classList.toggle('d-none', payload.audience.missing.length === 0);
    await refreshRankingEmailPreview();
  } catch (error) {
    reviewError.textContent = error.message;
    reviewError.classList.remove('d-none');
  }
});

reviewConfirm?.addEventListener('change', () => {
  reviewSendButton.disabled = !reviewConfirm.checked;
});

document.getElementById('refresh-ranking-email-preview')?.addEventListener('click', async () => {
  try {
    await refreshRankingEmailPreview();
    toastr.success('Email preview refreshed.');
  } catch (error) {
    toastr.error(error.message);
  }
});

reviewSendButton?.addEventListener('click', async () => {
  reviewSendButton.disabled = true;
  try {
    const response = await fetch('{{ route('ranking.series.review-circulation.send', $series) }}', {
      method: 'POST',
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify(rankingReviewPayload()),
    });
    const payload = await responsePayload(response);
    toastr.success(payload.message);
    location.reload();
  } catch (error) {
    toastr.error(error.message);
    reviewSendButton.disabled = false;
  }
});

document.getElementById('retry-ranking-review')?.addEventListener('click', async event => {
  const button = event.currentTarget;
  button.disabled = true;
  try {
    const response = await fetch(button.dataset.url, {
      method: 'POST',
      headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
    });
    const payload = await responsePayload(response);
    toastr.success(payload.message);
    location.reload();
  } catch (error) {
    toastr.error(error.message);
    button.disabled = false;
  }
});

document.addEventListener('DOMContentLoaded', () => {
  const tooltipTriggerList = [].slice.call(
    document.querySelectorAll('[data-bs-toggle="tooltip"]')
  );

  tooltipTriggerList.forEach(el => {
    new bootstrap.Tooltip(el);
  });
});
</script>

@endsection
