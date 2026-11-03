@php
  $snapshot = $ratingSnapshot ?? \App\Services\Performance\PlayerRatingHistoryDisplay::snapshot($ratingHistory ?? []);
  $snapshotPoints = $snapshot['points'];
  $snapshotLatest = $snapshotPoints ? $snapshotPoints[array_key_last($snapshotPoints)] : null;
  $snapshotChanged = false;
  foreach ($snapshotPoints as $i => $savedPoint) {
    $before = $snapshotPoints[$i - 1] ?? null;
    if ($before && $before['index'] + 1 === $savedPoint['index'] && $before['group'] !== $savedPoint['group']) $snapshotChanged = true;
  }
@endphp
<section class="ct-rating-snapshot" aria-label="Latest saved rating summary" data-rating-snapshot data-current-rating-summary>
  <h3>Latest saved estimate</h3>
  @if($snapshotLatest)
    <div class="ct-rating-snapshot-score">
      <x-ctr-badge size="small" :stage="$snapshot['stage']" :overall="empty($snapshot['stage'])" :score="$snapshotLatest['score']" :local="$snapshotLatest['connection_status'] === 'disconnected'" />
      <p>{{ $snapshot['label'] }}<br>{{ $snapshotLatest['confidence_label'] }} confidence<br><time datetime="{{ $snapshotLatest['date'] }}">{{ $snapshotLatest['date'] }}</time></p>
    </div>
    <figure class="ct-rating-snapshot-trend">
      <svg viewBox="0 0 280 90" role="img" aria-label="Saved monthly rating trend on a fixed zero to one hundred scale" data-rating-mini-trend>
        <title>Available saved rating trend · 0–100</title><desc>Missing months remain gaps. Dashed links indicate changed comparison groups and are not like-for-like progress.</desc>
        <line x1="10" y1="80" x2="270" y2="80" stroke="#ffffff25" />
        @foreach($snapshotPoints as $savedPoint)
          @php($before = $snapshotPoints[$loop->index - 1] ?? null)
          @if($before && $before['index'] + 1 === $savedPoint['index'] && $before['group'] !== null && $savedPoint['group'] !== null)
            <line x1="{{ 10 + $before['index'] * 260 / 11 }}" y1="{{ 80 - $before['score'] * .7 }}" x2="{{ 10 + $savedPoint['index'] * 260 / 11 }}" y2="{{ 80 - $savedPoint['score'] * .7 }}" stroke="#c4e4ff" stroke-width="2" @if($before['group'] !== $savedPoint['group']) stroke-dasharray="4 4" data-snapshot-group-change @endif />
          @endif
          <circle cx="{{ 10 + $savedPoint['index'] * 260 / 11 }}" cy="{{ 80 - $savedPoint['score'] * .7 }}" r="{{ $loop->last ? 4 : 2.5 }}" fill="{{ $loop->last ? '#ff9f43' : '#d8edff' }}" data-snapshot-point><title>{{ $savedPoint['date'] }} · {{ number_format($savedPoint['score'], 1) }} CTR</title></circle>
        @endforeach
      </svg>
      <figcaption>Saved trend · 0–100 · gaps retained.@if($snapshotChanged) Dashed links: group changed, not like-for-like progress.@endif</figcaption>
    </figure>
    @if($snapshotLatest['evidence_basis'] === 'finish_only')<p>Low confidence · final placings only</p>@endif
    @if($snapshotLatest['connection_status'] === 'disconnected')<p>Provisional within-group estimate; not calibrated against other groups.</p>@endif
    @if($snapshotLatest['historical_connection'])<p>Low confidence — historical connection. Link dated {{ $snapshotLatest['connection_reference_date'] ?? 'unknown' }}.</p>@endif
  @else
    <p>Not rated yet. Suitable eligible results are needed to estimate playing strength.</p>
    @if($snapshot['reason'])<p role="status">{{ $snapshot['reason'] }}</p>@endif
  @endif
  <p class="ct-rating-snapshot-note">Provisional estimate, not an official ranking.@if($snapshot['as_of']) Saved through {{ $snapshot['as_of'] }}.@endif @if($snapshot['stale']) An update is pending.@endif</p>
</section>
