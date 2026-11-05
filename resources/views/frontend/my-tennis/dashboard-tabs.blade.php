@php
  $activeTab = $workspaceTab ?? 'profile';
  $tabs = ['profile' => ['Profile', route('my.tennis', $headerQuery + ['tab' => 'profile'])]];
  if (($selectedPlayer ?? null) || ($workspace['related'] ?? false)) {
      $tabs['activity'] = ['Activity', route('my.tennis', $headerQuery + ['tab' => 'activity'])];
  }
  if ($headerPlayer && $headerCanViewRating) {
      $tabs += [
          'rating' => ['Rating', $headerRatingUrl],
          'record' => ['Results', route('my.tennis.record', $headerPlayer)],
          'opponents' => ['Opponents', route('my.tennis.record', ['player' => $headerPlayer, 'section' => 'opponents'])],
          'pathway' => ['Progress', route('my.tennis.pathway', $headerPlayer)],
      ];
  }
@endphp
<nav class="ct-my-tennis-nav" id="my-tennis-tabs" aria-label="My Tennis views">
  @foreach($tabs as $tab => [$label, $url])
    <a href="{{ $url }}" @if($tab === $activeTab) aria-current="page" @endif
      @if($tab === 'rating') id="my-tennis-rating-tab" @endif @if($tab === 'pathway') data-pathway-navigation @endif>{{ $label }}</a>
  @endforeach
</nav>
