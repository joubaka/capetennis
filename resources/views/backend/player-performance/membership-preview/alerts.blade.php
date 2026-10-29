@php($configData = Helper::appClasses())
@extends('layouts.backend')
@section('title', 'Draw alerts · private membership preview')
@section('content')
<div class="mp-preview">
@include('backend.player-performance.membership-preview.chrome')
@include('backend.player-performance.membership-preview.alerts-simulator')
</div>
@endsection
