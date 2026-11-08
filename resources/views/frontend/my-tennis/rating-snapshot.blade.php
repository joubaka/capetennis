@php
  $snapshot = $ratingSnapshot ?? \App\Services\Performance\PlayerRatingHistoryDisplay::snapshot($ratingHistory ?? []);
  $snapshotPoints = $snapshot['points'];
  $snapshotLatest = $snapshotPoints ? $snapshotPoints[array_key_last($snapshotPoints)] : null;
  $expandedTrend = $expandedTrend ?? false;
  $chartX = fn ($index) => $expandedTrend ? 38 + $index * 422 / 11 : 10 + $index * 260 / 11;
  $chartY = fn ($score) => $expandedTrend ? 114 - $score : 80 - $score * .7;
  $snapshotChanged = false;
  foreach ($snapshotPoints as $i => $savedPoint) {
    $before = $snapshotPoints[$i - 1] ?? null;
    if ($before && $before['index'] + 1 === $savedPoint['index'] && $before['group'] !== $savedPoint['group']) $snapshotChanged = true;
  }
@endphp
@if(!$snapshotLatest && ($compactUnavailable ?? false))
<p class="ctr-rating-status small mb-0" role="status" data-rating-snapshot data-current-rating-summary>
  {{ $snapshot['reason'] || $snapshot['stale'] ? 'Rating update pending.' : 'No rating yet.' }}
</p>
@else
<section class="ct-rating-snapshot" aria-label="Latest saved rating summary" data-rating-snapshot data-current-rating-summary>
  <h3>Latest saved rating</h3>
  @if($snapshotLatest)
    <div class="ct-rating-snapshot-score">
      <x-ctr-badge size="small" :stage="$snapshot['stage']" :overall="empty($snapshot['stage'])" :score="$snapshotLatest['score']" :local="$snapshotLatest['connection_status'] === 'disconnected'" />
      <p>{{ $snapshot['label'] }}<br>{{ $snapshotLatest['confidence_label'] }} confidence<br><time datetime="{{ $snapshotLatest['date'] }}">{{ $snapshotLatest['date'] }}</time></p>
    </div>
    <figure class="ct-rating-snapshot-trend">
      <svg viewBox="{{ $expandedTrend ? '0 0 480 145' : '0 0 280 90' }}" role="img" aria-label="Saved monthly rating trend on a fixed zero to one hundred scale" data-rating-mini-trend>
        <title>Available saved rating trend · 0–100</title><desc>Missing months remain gaps. Dashed links indicate changed comparison groups and are not like-for-like progress.</desc>
        @if($expandedTrend)
          @foreach([100, 50, 0] as $tick)
            <line x1="38" y1="{{ $chartY($tick) }}" x2="460" y2="{{ $chartY($tick) }}" stroke="#70869b" />
            <text x="34" y="{{ $chartY($tick) + 5 }}" fill="#e0ebf5" font-size="18" text-anchor="end">{{ $tick }}</text>
          @endforeach
          @foreach($snapshotPoints as $savedPoint)
            @if($loop->last || ($loop->first && $snapshotLatest['index'] - $savedPoint['index'] >= 4))
              <text x="{{ $chartX($savedPoint['index']) }}" y="138" fill="#e0ebf5" font-size="18" text-anchor="{{ $loop->last && $savedPoint['index'] >= 3 ? 'end' : 'start' }}">{{ \Carbon\CarbonImmutable::parse($savedPoint['date'])->format('M Y') }}</text>
            @endif
          @endforeach
        @else
          <line x1="10" y1="80" x2="270" y2="80" stroke="#ffffff25" />
        @endif
        @foreach($snapshotPoints as $savedPoint)
          @php($before = $snapshotPoints[$loop->index - 1] ?? null)
          @if($before && $before['index'] + 1 === $savedPoint['index'] && $before['group'] !== null && $savedPoint['group'] !== null)
            <line x1="{{ $chartX($before['index']) }}" y1="{{ $chartY($before['score']) }}" x2="{{ $chartX($savedPoint['index']) }}" y2="{{ $chartY($savedPoint['score']) }}" stroke="#c4e4ff" stroke-width="2" @if($before['group'] !== $savedPoint['group']) stroke-dasharray="4 4" data-snapshot-group-change @endif />
          @endif
          <circle cx="{{ $chartX($savedPoint['index']) }}" cy="{{ $chartY($savedPoint['score']) }}" r="{{ $loop->last ? ($expandedTrend ? 6 : 4) : ($expandedTrend ? 3.5 : 2.5) }}" fill="{{ $loop->last ? '#ff9f43' : '#d8edff' }}" data-snapshot-point><title>{{ $savedPoint['date'] }} · {{ number_format($savedPoint['score'], 1) }} CTR</title></circle>
        @endforeach
      </svg>
      <figcaption>Ratings are out of 100. Gaps show months without a saved rating.@if($snapshotChanged) A dashed line shows a change in the group used for comparison.@endif</figcaption>
    </figure>
    @if($snapshotLatest['evidence_basis'] === 'finish_only')<p>Low confidence · final placings only</p>@endif
    @if($snapshotLatest['connection_status'] === 'disconnected')<p>Provisional within-group estimate; not calibrated against other groups.</p>@endif
    @if($snapshotLatest['historical_connection'])<p>Low confidence — historical connection. Link dated {{ $snapshotLatest['connection_reference_date'] ?? 'unknown' }}.</p>@endif
  @else
    <p>No rating yet. A rating needs enough suitable results.</p>
    @if($snapshot['reason'])<p role="status">{{ $snapshot['reason'] }}</p>@endif
  @endif
  @if(!$expandedTrend)
    <p class="ct-rating-snapshot-note">Provisional estimate, not an official ranking.@if($snapshot['as_of']) Saved through {{ $snapshot['as_of'] }}.@endif @if($snapshot['stale']) An update is pending.@endif</p>
  @elseif($snapshot['stale'])
    <p class="ct-rating-snapshot-note">An update is pending.</p>
  @endif
</section>
@endif
