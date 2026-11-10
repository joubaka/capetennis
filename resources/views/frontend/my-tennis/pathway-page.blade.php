@extends(request()->header('X-My-Tennis-Navigation') === '1' ? 'layouts.my-tennis-fragment' : 'layouts.contentNavbarLayout')
@section('title', 'Your tennis pathway')
@section('content')
<div data-my-tennis-workspace class="container-xxl flex-grow-1 container-p-y ct-player-workspace" data-player-pathway-page>
  @php
    $pathwayHeaderSnapshot = $workspace['snapshot'];
    if (isset($pathway['move']) && $pathway['move']['score'] === null) {
      $badge = $pathway['badge'];
      $pathwayHeaderSnapshot = [
        'stage' => $badge['stage'],
        'points' => isset($badge['score'], $pathway['metrics']['date']) ? [[
          'score' => $badge['score'], 'date' => $pathway['metrics']['date'],
          'connection_status' => $badge['local'] ? 'disconnected' : null,
        ]] : [],
      ];
    }
  @endphp
  @include('frontend.my-tennis.profile-header', ['workspaceTab' => 'pathway', 'ratingSnapshot' => $pathwayHeaderSnapshot])
  @include('frontend.my-tennis.pathway', ['hidePathwayAdmin' => true])
</div>
@endsection

@include('frontend.my-tennis.workspace-assets')
