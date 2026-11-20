@extends('layouts.backend')
@section('title', 'Host event proposals')
@section('content')
@include('backend.marketing.header')
<div class="card"><div class="card-body"><h2 class="h5">Host event proposals</h2>
<form method="get" class="d-flex flex-wrap gap-2 mb-3"><label class="visually-hidden" for="status">Proposal status</label><select class="form-select w-auto" id="status" name="status">@foreach(['pending', 'approved', 'rejected', 'all'] as $value)<option value="{{ $value }}" @selected($status === $value)>{{ ucfirst($value) }}</option>@endforeach</select><button class="btn btn-outline-primary">Filter</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Event</th><th>Organiser</th><th>Dates</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($applications as $application)<tr><td>{{ $application->event_name }}<br><small>{{ $application->town }}</small></td><td>{{ $application->academy }}</td><td class="text-nowrap">{{ $application->start_date->format('d M Y') }}</td><td>{{ ucfirst($application->status) }}@if($application->event)<br><small>{{ $application->event->published ? 'Event published' : 'Event draft created' }}</small>@endif</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('superadmin.marketing.applications.show', $application) }}">Review</a></td></tr>@empty<tr><td colspan="5">No proposals match this status.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $applications->links() }}</div>
</div></div>
@endsection
