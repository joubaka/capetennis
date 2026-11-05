{{-- Private links require PlayerRatingAccess; locked mode emits preview links only. --}}
@php
  $lockedPreview = ($locked ?? false) === true;
  $compactNavigation = $compactNavigation ?? false;
  $activeView = match (true) {
    request()->routeIs('my.tennis.feature-preview') => request()->route('feature'),
    request()->routeIs('my.tennis.record') => request()->route('section') ?? 'record',
    default => $recordSection ?? null,
  };
  $previewUrl = fn ($feature) => route('my.tennis.feature-preview', ['player' => $navigationPlayer->id, 'feature' => $feature]);
@endphp
<style>.ct-player-navigation .btn{min-height:44px;display:inline-flex;align-items:center;color:#004177;border-color:#9ab4cf}.ct-player-navigation .btn[aria-current=page]{background:#004177;color:#fff}</style>
<nav @class(['ct-player-navigation record-nav d-flex flex-wrap gap-2 my-3', 'ct-player-navigation-compact' => $compactNavigation, 'ct-player-navigation-embedded' => $embeddedDashboard ?? false]) aria-label="Player Plus views" @if($lockedPreview) data-plus-locked-navigation @endif>
  @unless($embeddedDashboard ?? false)
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}">Overview</a>
  <a class="btn btn-outline-primary" href="{{ route('my.tennis', ['player' => $navigationPlayer->id]) }}#rating">@unless($compactNavigation)<x-ctr-mark class="me-2" />@endunless{{ $compactNavigation ? 'Rating' : 'Player rating' }}</a>
  @endunless
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl('record') : route('my.tennis.record', $navigationPlayer) }}" @if($lockedPreview) data-plus-feature="record" @endif @if($activeView === 'record') aria-current="page" @endif>{{ $compactNavigation ? 'Results' : 'Playing Record' }} @if($lockedPreview)<small>Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl('opponents') : route('my.tennis.record', ['player' => $navigationPlayer, 'section' => 'opponents']) }}" @if($lockedPreview) data-plus-feature="opponents" @endif @if($activeView === 'opponents') aria-current="page" @endif>{{ $compactNavigation ? 'Opponents' : 'Opponent Insights' }} @if($lockedPreview)<small>Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" @if($lockedPreview) data-plus-feature="pathway" @else data-pathway-navigation @endif href="{{ $lockedPreview ? $previewUrl('pathway') : route('my.tennis.pathway', $navigationPlayer) }}" @if($activeView === 'pathway' || request()->routeIs('my.tennis.pathway')) aria-current="page" @endif>{{ $compactNavigation ? 'Next steps' : 'Tennis pathway' }} @if($lockedPreview)<small>Plus preview</small>@endif</a>
  <a class="btn btn-outline-primary" href="{{ $lockedPreview ? $previewUrl('events') : route('my.tennis.event-history', $navigationPlayer) }}" @if($lockedPreview) data-plus-feature="events" @endif @if($activeView === 'events' || request()->routeIs('my.tennis.event-history')) aria-current="page" @endif>@unless($compactNavigation)<x-ctr-mark class="me-2" />@endunless{{ $compactNavigation ? 'Event ratings' : 'Past-event ratings' }} @if($lockedPreview)<small>Plus preview</small>@endif</a>
</nav>
