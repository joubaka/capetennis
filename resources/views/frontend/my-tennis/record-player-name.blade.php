<span class="record-player-name">
  <strong>{{ $name }}</strong>
  @if($rating)
    <span class="record-rating-mark">
      <x-ctr-badge size="inline" :score="$rating['score']" :stage="$stage ?? null"
        :overall="empty($stage)" :local="($rating['connection_status'] ?? null) === 'disconnected'" role="img" />
      @if(($stage ?? null) === 'standard')<span class="small record-ball-label" aria-hidden="true">Green/Yellow ball</span>@endif
    </span>
  @endif
</span>
