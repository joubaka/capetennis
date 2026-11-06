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
          'pathway' => ['Tournament options', route('my.tennis.pathway', $headerPlayer)],
      ];
  } elseif ($headerPlayer && $headerRelated) {
      foreach (['rating' => 'Rating', 'record' => 'Results', 'opponents' => 'Opponents', 'pathway' => 'Tournament options'] as $feature => $label) {
          $tabs[$feature] = [$label, route('my.tennis.feature-preview', ['player' => $headerPlayer, 'feature' => $feature])];
      }
  }
  $previewTabs = !$headerCanViewRating ? ['rating', 'record', 'opponents', 'pathway'] : [];
@endphp
<nav class="ct-my-tennis-nav" id="my-tennis-tabs" aria-label="My Tennis views">
  @foreach($tabs as $tab => [$label, $url])
    @php($isPreviewTab = in_array($tab, $previewTabs, true))
    <a @if($isPreviewTab) data-plus-preview-tab @else data-my-tennis-link @endif href="{{ $url }}" @if($tab === $activeTab) aria-current="page" @endif
      @if($tab === 'rating' && $headerCanViewRating) id="my-tennis-rating-tab" @endif><span>{{ $label }}</span>@if($isPreviewTab)<i class="ti ti-lock" aria-hidden="true"></i><span class="visually-hidden"> · Plus preview</span>@endif</a>
  @endforeach
</nav>
