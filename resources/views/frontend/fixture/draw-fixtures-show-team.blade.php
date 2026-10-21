@extends('layouts/layoutMaster')

@section('title', ($draw->drawName ?? 'Tournament') . ' matches')

@section('content')
@include('frontend.fixture.partials.team-schedule')
@endsection
