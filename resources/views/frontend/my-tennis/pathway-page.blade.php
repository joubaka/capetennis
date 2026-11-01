@extends('layouts/contentNavbarLayout')
@section('title', 'Your tennis pathway')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y" data-player-pathway-page>
  <a href="{{ route('my.tennis', ['player' => $ratingPlayer->id]) }}#rating">Back to player rating</a>
  <h1 class="h3 mt-3">{{ $ratingPlayer->full_name }} · Development pathway</h1>
  <p>See where your latest badge places you and the next benchmarks to work towards.</p>
  @include('frontend.my-tennis.pathway', ['hidePathwayAdmin' => true])
</div>
@endsection
