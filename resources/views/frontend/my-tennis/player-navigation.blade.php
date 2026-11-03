{{-- Private links require PlayerRatingAccess; locked mode emits preview links only. --}}
@php
  $lockedPreview = ($locked ?? false) === true;
  $previewUrl = fn ($feature) => route('my.tennis.feature-preview', ['player' => $navigationPlayer->id, 'feature' => $feature]);
@endphp
<style>.ct-player-navigation .btn{min-height:44px;display:inline-flex;align-items:center;color:#004177;border-color:#9ab4cf}.ct-player-navigation .btn[aria-current=page]{background:#004177;color:#fff}</style>
<nav class="ct-player-navigation record-nav d-flex flex-wrap gap-2 my-3" aria-label="Player Plus views" @if($lockedPreview) data-plus-locked-navigation @endif>
  @unless($embeddedDashboard ?? false)
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}">Overview</a>
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}#rating"><x-ctr-mark class="me-2" />Player rating</a>
  @endunless
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl('record') : route('my.tennis.record', $navigationPlayer) }}" @if($lockedPreview) data-plus-feature="record" @endif @if(!$lockedPreview && ($recordSection ?? null) === 'record') aria-current="page" @endif>Playing Record @if($lockedPreview)<small>· Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl('opponents') : route('my.tennis.record', ['player' => $navigationPlayer, 'section' => 'opponents']) }}" @if($lockedPreview) data-plus-feature="opponents" @endif @if(!$lockedPreview && ($recordSection ?? null) === 'opponents') aria-current="page" @endif>Opponent Insights @if($lockedPreview)<small>· Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" @if($lockedPreview) data-plus-feature="pathway" @else data-pathway-navigation @endif href="{{ $lockedPreview ? $previewUrl('pathway') : route('my.tennis.pathway', $navigationPlayer) }}">Tennis pathway @if($lockedPreview)<small>· Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl('events') : route('my.tennis.event-history', $navigationPlayer) }}" @if($lockedPreview) data-plus-feature="events" @endif><x-ctr-mark class="me-2" />Past-event ratings @if($lockedPreview)<small>· Plus preview</small>@endif</a>
</nav>
