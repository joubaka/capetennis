@extends('layouts/contentNavbarLayout')
@section('title', 'Your tennis pathway')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y" data-player-pathway-page>
  <a href="{{ route('my.tennis', ['player' => $ratingPlayer->id]) }}#rating">Back to player rating</a>
  <header class="pathway-page-hero mt-3"><span><i class="ti ti-route me-1" aria-hidden="true"></i> Player Plus · Your next step</span><h1 class="h3 mt-2">{{ $ratingPlayer->full_name }} · Development pathway</h1>
  <p class="mb-0">See where your latest badge places you and the next benchmarks to work towards.</p></header>
  <style>
    [data-player-pathway-page] .pathway-page-hero { background:linear-gradient(115deg,#172e45,#004177);color:#e0ebf5;padding:clamp(1.25rem,3vw,2rem);border-radius:16px;margin-bottom:1.25rem; }
    [data-player-pathway-page] .pathway-page-hero h1 { color:#fff; }
    [data-player-pathway-page] .pathway-page-hero > span { color:#ff9f43;font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase; }
  </style>
  @include('frontend.my-tennis.player-navigation', ['navigationPlayer' => $ratingPlayer])
  @include('frontend.my-tennis.pathway', ['hidePathwayAdmin' => true])
</div>
@endsection
@section('page-script')
<script src="{{ asset('assets/js/player-pathway.js') }}?v={{ filemtime(public_path('assets/js/player-pathway.js')) }}"></script>
<script>document.addEventListener('DOMContentLoaded', () => { const panel = document.querySelector('[data-player-pathway-page]'); if (panel) new window.PlayerPathwayUI(panel, () => {}); });</script>
@endsection
