@extends(request()->header('X-My-Tennis-Navigation') === '1' ? 'layouts.my-tennis-fragment' : 'layouts.contentNavbarLayout')
@section('title', 'Your tennis pathway')
@section('content')
<div data-my-tennis-workspace class="container-xxl flex-grow-1 container-p-y ct-player-workspace" data-player-pathway-page>
  @include('frontend.my-tennis.profile-header', ['workspaceTab' => 'pathway'])
  <h2 class="h5">Progress</h2>
  @include('frontend.my-tennis.pathway', ['hidePathwayAdmin' => true, 'expandPathwayLevels' => true])
</div>
@endsection

@include('frontend.my-tennis.workspace-assets')
