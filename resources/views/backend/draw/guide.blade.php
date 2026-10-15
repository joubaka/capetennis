@extends('layouts.backend')
@section('title', 'Set up draw – '.$draw->drawName)
@section('content')
@include('backend.draw.partials.guide-content')
@include('backend.draw.partials.guide-modal')
@endsection
