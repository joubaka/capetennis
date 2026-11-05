@extends('layouts/contentNavbarLayout')
@section('title', 'Your tennis pathway')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y ct-player-workspace" data-player-pathway-page>
  <x-player-workspace-header compact :workspace="$workspace ?? null" title="Your next steps" :subtitle="$ratingPlayer->full_name">
  </x-player-workspace-header>
  @include('frontend.my-tennis.pathway', ['hidePathwayAdmin' => true])
</div>
@endsection
@section('page-script')
<script src="{{ asset('assets/js/player-pathway.js') }}?v={{ filemtime(public_path('assets/js/player-pathway.js')) }}"></script>
<script>document.addEventListener('DOMContentLoaded', () => { const panel = document.querySelector('[data-player-pathway-page]'); if (panel) new window.PlayerPathwayUI(panel, () => {}); });</script>
@endsection
