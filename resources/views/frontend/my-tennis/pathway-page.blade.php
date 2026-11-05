@extends('layouts/contentNavbarLayout')
@section('title', 'Your tennis pathway')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y ct-player-workspace" data-player-pathway-page>
  @include('frontend.my-tennis.profile-header', ['workspaceTab' => 'pathway'])
  <h2 class="h5">Progress</h2>
  @include('frontend.my-tennis.pathway', ['hidePathwayAdmin' => true])
</div>
@endsection
@section('page-script')
<script src="{{ asset('assets/js/player-pathway.js') }}?v={{ filemtime(public_path('assets/js/player-pathway.js')) }}"></script>
<script>document.addEventListener('DOMContentLoaded', () => { const panel = document.querySelector('[data-player-pathway-page]'); if (panel) new window.PlayerPathwayUI(panel, () => {}); });</script>
@endsection
