{{-- Private links require PlayerRatingAccess; locked mode emits preview links only. --}}
@php
  $lockedPreview = ($locked ?? false) === true;
  $previewUrl = route('my.tennis', ['player' => $navigationPlayer->id]).'#rating';
@endphp
<style>.ct-player-navigation .btn{min-height:44px;display:inline-flex;align-items:center;color:#004177;border-color:#9ab4cf}.ct-player-navigation .btn[aria-current=page]{background:#004177;color:#fff}</style>
<nav class="ct-player-navigation record-nav d-flex flex-wrap gap-2 my-3" aria-label="Player Plus views" @if($lockedPreview) data-plus-locked-navigation @endif>
  @unless($embeddedDashboard ?? false)
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}">Overview</a>
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}#rating">Player rating</a>
  @endunless
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl : route('my.tennis.record', $navigationPlayer) }}" @if($lockedPreview) data-plus-preview-link @endif @if(!$lockedPreview && ($recordSection ?? null) === 'record') aria-current="page" @endif>Playing Record @if($lockedPreview)<small>· Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl : route('my.tennis.record', ['player' => $navigationPlayer, 'section' => 'opponents']) }}" @if($lockedPreview) data-plus-preview-link @endif @if(!$lockedPreview && ($recordSection ?? null) === 'opponents') aria-current="page" @endif>Opponent Insights @if($lockedPreview)<small>· Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" @if($lockedPreview) data-plus-preview-link @else data-pathway-navigation @endif href="{{ $lockedPreview ? $previewUrl : route('my.tennis.pathway', $navigationPlayer) }}">Tennis pathway @if($lockedPreview)<small>· Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl : route('my.tennis.event-history', $navigationPlayer) }}" @if($lockedPreview) data-plus-preview-link @endif>Past-event ratings @if($lockedPreview)<small>· Plus preview</small>@endif</a>
</nav>
