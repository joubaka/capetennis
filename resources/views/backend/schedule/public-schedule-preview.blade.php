@extends('layouts.backend')
@section('title', 'Public schedule preview – '.$event->name)
@section('content')
<div class="container-xxl py-3"><h3>Public schedule preview</h3><p class="text-muted">Only published match times and currently public draws appear here. Private saved changes are excluded.</p><a class="btn btn-outline-primary mb-3" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id]+$scope) }}">Back to saved schedule</a>
<p class="small text-muted d-md-none">Swipe the match table sideways to see every column.</p><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Day / time</th><th>Venue / court</th><th>Draw</th><th>Participants</th></tr></thead><tbody>@forelse($rows as $row)<tr><td class="text-nowrap">{{ substr($row['scheduled_at'],0,16) }}</td><td>{{ $row['venue_name'] }} / {{ $row['court'] }}</td><td>{{ $row['draw_name'] }}</td><td>{{ implode(' / ',array_filter($row['participants'])) ?: 'Participants determined by draw' }}</td></tr>@empty<tr><td colspan="4" class="text-muted py-4">No public match times in this view.</td></tr>@endforelse</tbody></table></div></div></div>
@endsection
