@php
  $headerPlayer = $selectedPlayer ?? ($ratingPlayer ?? ($workspace['player'] ?? null));
  $headerCanViewRating = $canViewRating ?? ($workspace['canViewRating'] ?? false);
  $headerSnapshot = $ratingSnapshot ?? ($workspace['snapshot'] ?? null);
  $headerRelated = $workspace['related'] ?? isset($selectedPlayer);
  $headerSubtitle = !$headerRelated && $headerPlayer && ($headerCanViewRating || isset($ratingPreviewPlayer))
      ? 'Admin preview'
      : null;
  $headerQuery = ['player' => $headerPlayer?->id, 'preview_non_plus' => ($previewNonPlus ?? false) ? 1 : null];
  $headerRatingUrl = route('my.tennis', $headerQuery + ['tab' => 'rating']);
@endphp

<x-player-workspace-header compact :title="$headerPlayer?->full_name ?? 'My Tennis'" eyebrow="My Tennis"
  heading-id="my-tennis-player-heading" :subtitle="$headerSubtitle" data-player-profile-header>
  @if($headerPlayer)
    @if($headerCanViewRating)
      @include('frontend.my-tennis.profile-rating-badge', ['badgeSnapshot' => $headerSnapshot, 'badgeUrl' => $headerRatingUrl])
    @elseif(in_array($workspaceTab ?? 'profile', ['profile', 'activity', 'manage'], true))
      <div class="ct-my-tennis-rating-badge" data-profile-plus-preview>
        <x-ctr-mark size="heading" />
        <a href="{{ app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? route('ctr.index', ['player' => $headerPlayer->id]) : route('player-plus.player', $headerPlayer) }}">Explore Player Plus</a>
      </div>
    @endif
  @endif
  <x-slot:actions>
    @if(isset($workspace['players']) || isset($players))
      @include('frontend.my-tennis.player-selector', [
        'players' => $workspace['players'] ?? $players,
        'selectedPlayer' => $headerRelated ? $headerPlayer : null,
        'ratingPreviewPlayer' => !$headerRelated && $headerCanViewRating ? $headerPlayer : ($ratingPreviewPlayer ?? null),
      ])
    @endif
    <div class="ct-my-tennis-utilities">
      <a data-my-tennis-link href="{{ route('my.tennis', $headerQuery + ['tab' => 'manage']) }}" class="ct-my-tennis-secondary-link" @if(($workspaceTab ?? null) === 'manage') aria-current="page" @endif>Manage players</a>
      @if($headerPlayer && $headerCanViewRating)
        <a href="{{ route('player-plus.player', $headerPlayer) }}" class="ct-my-tennis-secondary-link">Plus membership</a>
      @endif
    </div>
  </x-slot:actions>
  <x-slot:navigation>
    @include('frontend.my-tennis.dashboard-tabs')
  </x-slot:navigation>
</x-player-workspace-header>
