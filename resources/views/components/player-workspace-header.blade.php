@props(['title', 'eyebrow' => 'Cape Tennis · Player Plus', 'subtitle' => null, 'heading' => 'h1', 'headingId' => null, 'workspace' => null, 'compact' => false])
@pushOnce('page-styles', 'player-workspace')
  <link rel="stylesheet" href="{{ asset('assets/css/player-workspace.css') }}?v={{ filemtime(public_path('assets/css/player-workspace.css')) }}">
@endPushOnce
@php($headingTag = in_array($heading, ['h1', 'h2'], true) ? $heading : 'h1')
<header {{ $attributes->class(['ct-workspace-header', 'ct-workspace-header-compact' => $compact]) }}>
  <div class="ct-workspace-header-main">
    <div class="ct-workspace-header-copy">
      <span class="ct-workspace-eyebrow">{{ $eyebrow }}</span>
      <{{ $headingTag }} class="ct-workspace-title" @if($headingId) id="{{ $headingId }}" @endif>{{ $title }}</{{ $headingTag }}>
      @if($subtitle)<p class="ct-workspace-subtitle">{{ $subtitle }}</p>@endif
      {{ $slot }}
    </div>
    @if(!$compact && (isset($summary) || ($workspace['player'] ?? null)))<div class="ct-workspace-header-summary">
      @if($workspace['canViewRating'] ?? false)@include('frontend.my-tennis.rating-snapshot', ['ratingSnapshot' => $workspace['snapshot']])
      @elseif(($workspace['related'] ?? false))@include('frontend.my-tennis.locked-rating-snapshot', ['ratingPlayer' => $workspace['player']])@endif
      @isset($summary){{ $summary }}@endisset
    </div>@endif
    @if(isset($actions) || $workspace)<div class="ct-workspace-header-actions">
      @if($workspace)@include('frontend.my-tennis.workspace-selector', ['compactSelector' => $compact])@endif
      @isset($actions){{ $actions }}@endisset
    </div>@endif
  </div>
  @if(($workspace['player'] ?? null) && (($workspace['related'] ?? false) || ($workspace['canViewRating'] ?? false)))
    <div class="ct-workspace-header-navigation">
      @include('frontend.my-tennis.player-navigation', ['navigationPlayer' => $workspace['player'], 'locked' => !$workspace['canViewRating'], 'compactNavigation' => $compact])
    </div>
  @endif
  @isset($navigation)<div class="ct-workspace-header-navigation">{{ $navigation }}</div>@endisset
  @if($compact && isset($summary))
    <div class="ct-workspace-header-summary">{{ $summary }}</div>
  @endif
</header>
