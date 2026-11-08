@php
  $selectedLabel = $ballType === 'overall' ? 'Overall CTR' : \App\Services\Performance\BallRatingModel::LABELS[$ballType].' CTR';
  $localRating = ($selectedRating['comparable'] ?? false) !== true || ($selectedRating['connection_status'] ?? null) === 'disconnected' || isset($selectedRating['within_group_score']);
@endphp
<h2 class="h4 mb-3">{{ $selectedLabel }} · singles</h2>
@if($ability['reason'])
  <p class="alert alert-warning text-dark" role="status">{{ $ability['reason'] }}</p>
@elseif($selectedRating)
  <div class="d-flex flex-wrap align-items-center gap-3 p-3 mb-3 rounded border" style="background:#eff8f7">
    <x-ctr-badge :overall="$ballType === 'overall'" :stage="$ballType === 'overall' ? null : $ballType" :score="$selectedRating['within_group_score'] ?? $selectedRating['score']" :local="$localRating" />
    <div>
      <p class="fw-semibold mb-1">{{ $localRating ? 'Rating within this group' : 'Singles playing strength' }}</p>
      <x-ctr-confidence-badge :rating="$selectedRating" />
      <p class="mb-0 mt-2">Confidence reflects how strong and recent the results behind this rating are.</p>
    </div>
  </div>
  @if($localRating)<p class="alert alert-warning text-dark" role="status">Within-group estimate only. This rating is not calibrated against other comparison groups.</p>@endif
  @if(($selectedRating['evidence_basis'] ?? null) === 'finish_only')<p><strong>Published final placings only · low confidence.</strong> No eligible played singles are recorded for this ball rating.</p>@endif
  @if(!empty($selectedRating['historical_connection']))<p class="text-warning-emphasis">Low confidence — historical connection. Reference date: {{ $selectedRating['connection_reference_date'] ?? 'Unknown' }}.</p>@endif
  <dl class="row g-2 mb-3">
    <div class="col-6"><dt class="fw-normal">Recorded matches</dt><dd class="h4 mb-0">{{ $selectedRating['played'] ?? 0 }}</dd></div>
    <div class="col-6"><dt class="fw-normal">Different opponents</dt><dd class="h4 mb-0">{{ $selectedRating['direct_opponents'] ?? 0 }}</dd></div>
  </dl>
  @if($selectedRating['reason'] ?? null)<p role="status">{{ $selectedRating['reason'] }}</p>@endif
@else
  <p class="h3">{{ $selectedLabel }} unavailable</p><p>No eligible saved rating is available in this selected context. A rating from another ball level is not substituted.</p>
@endif
<details class="mb-3">
  <summary class="text-primary">How to read this rating</summary>
  <p class="mt-2">Compare players on the same rating scale and in the same comparison group. Red, Orange, Green/Yellow and Overall CTR each have their own scale.</p>
  <p>CTR estimates playing strength and is separate from the official rankings. Evidence confidence describes the strength and freshness of the results behind it, not the chance of winning or the accuracy of the rating.</p>
  @if(!$ability['reason'] && $selectedRating)
    <p>Comparison group: {{ $selectedRating['component'] }}</p>
    <p>{{ $selectedRating['confidence_explanation'] ?? '' }}</p>
  @endif
</details>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-outline-primary" href="{{ route('backend.player-performance.ratings', ['ball_type' => $ballType, 'comparison_group' => $comparisonGroup !== '' ? $comparisonGroup : null]) }}">Back to {{ $selectedLabel }} list</a>
  <a class="btn btn-outline-secondary" href="{{ route('backend.player-performance.show', ['player' => $player->id, 'ball_type' => 'overall']) }}">View Overall CTR</a>
</div>
