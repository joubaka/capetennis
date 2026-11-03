@php
  $selectedLabel = $ballType === 'overall' ? 'Overall CTR' : \App\Services\Performance\BallRatingModel::LABELS[$ballType].' CTR';
  $localRating = ($selectedRating['comparable'] ?? false) !== true || ($selectedRating['connection_status'] ?? null) === 'disconnected' || isset($selectedRating['within_group_score']);
@endphp
<h2 class="h4">{{ $selectedLabel }} · singles</h2>
@if($ability['reason'])
  <p class="alert alert-warning text-dark" role="status">{{ $ability['reason'] }}</p>
@elseif($selectedRating)
  <p><x-ctr-badge :overall="$ballType === 'overall'" :stage="$ballType === 'overall' ? null : $ballType" :score="$selectedRating['within_group_score'] ?? $selectedRating['score']" :local="$localRating" /></p>
  <p>Comparison group {{ $selectedRating['component'] }} · <x-ctr-confidence-badge :rating="$selectedRating" /></p>
  @if($localRating)<p class="alert alert-warning text-dark" role="status">Within-group estimate only. This rating is not calibrated against other comparison groups.</p>@endif
  @if(($selectedRating['evidence_basis'] ?? null) === 'finish_only')<p><strong>Published final placings only · low confidence.</strong> No eligible played singles are recorded for this ball rating.</p>@endif
  @if(!empty($selectedRating['historical_connection']))<p class="text-warning-emphasis">Low confidence — historical connection. Reference date: {{ $selectedRating['connection_reference_date'] ?? 'Unknown' }}.</p>@endif
  <p>{{ $selectedRating['played'] ?? 0 }} recorded matches · {{ $selectedRating['direct_opponents'] ?? 0 }} distinct opponents.</p>
  @if($selectedRating['reason'] ?? null)<p role="status">{{ $selectedRating['reason'] }}</p>@endif
  <p class="small">{{ $selectedRating['confidence_explanation'] ?? '' }}</p>
@else
  <p class="h3">{{ $selectedLabel }} unavailable</p><p>No eligible saved rating is available in this selected context. A rating from another ball level is not substituted.</p>
@endif
<p class="small">Red, Orange and Green/Yellow have independent scales. Overall CTR is a separate rating. Confidence describes the available evidence, not an accuracy percentage or an official ranking.</p>
<p><a href="{{ route('backend.player-performance.ratings', ['ball_type' => $ballType, 'comparison_group' => $comparisonGroup !== '' ? $comparisonGroup : null]) }}">Back to {{ $selectedLabel }} list</a> · <a href="{{ route('backend.player-performance.show', ['player' => $player->id, 'ball_type' => 'overall']) }}">View Overall CTR</a></p>
