@extends('layouts.backend')

@section('title', 'Teams · Invitations')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('page-style')
@include('backend.team-selection._workspace-style')
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
@include('backend.team-selection._workspace-content')
@endsection

@section('page-script')
@include('backend.team-selection._workspace-script')
@endsection
