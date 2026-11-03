@extends('layouts/contentNavbarLayout')
@section('title', 'Explore Player Plus')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y ct-player-workspace">
  <x-player-workspace-header :title="$ratingPlayer->full_name.' · Player Plus'" subtitle="Explore the tools included with Player Plus.">
    <x-slot:navigation>
      @include('frontend.my-tennis.player-navigation', ['navigationPlayer' => $ratingPlayer, 'locked' => true])
    </x-slot:navigation>
  </x-player-workspace-header>
  <div class="card p-4" data-player-plus-preview-page>
    @include('frontend.my-tennis.feature-preview')
  </div>
</div>
@endsection
