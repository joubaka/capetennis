@extends(($liveResultsFragment ?? false) ? 'frontend.fixtures.partials.live-results-layout' : 'layouts/layoutMaster')

@section('title', ($draw->drawName ?? 'Tournament') . ' matches')

@section('content')
@include('frontend.fixture.partials.team-schedule')
@endsection
