@extends('layouts/layoutMaster')

@section('title', ($draw->drawName ?? 'Tournament') . ' matches')

@section('content')
@if($draw->published)
  @include('frontend.fixtures.partials.live-results-status')
@endif
<div @if($draw->published) data-live-results="individual-fixtures" @endif>
  @include('frontend.fixture.fixture-table')
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
