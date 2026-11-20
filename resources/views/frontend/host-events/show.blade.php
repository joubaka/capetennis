@extends('layouts/contentNavbarLayout')
@section('title', 'Your event proposal')
@section('content')
<div class="container-xxl container-p-y" style="max-width:950px;overflow-wrap:anywhere">
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
<div class="card"><div class="card-body"><span class="badge bg-label-primary">Proposal #{{ $application->id }} · {{ ucfirst($application->status) }}</span><h1 class="h4 mt-3">{{ $application->event_name }}</h1>
<p>{{ $application->academy }} · {{ $application->venue }}, {{ $application->town }}</p><p>{{ $application->start_date->format('d M Y') }}–{{ $application->end_date->format('d M Y') }} · {{ $application->courts }} courts proposed</p>
<p>Cape Tennis reviews proposals before preparing an event. Approval is separate from certification, publication and opening registration.</p>
@include('frontend.host-events.court-plan')
@if($application->event_id && $application->event?->published)<a class="btn btn-primary" href="{{ route('events.show', $application->event_id) }}">View published event</a>@endif
<a class="btn btn-outline-secondary" href="{{ route('host-events.index') }}">Host programme</a>
</div></div></div>
@endsection
