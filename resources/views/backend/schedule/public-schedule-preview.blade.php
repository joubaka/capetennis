@extends('layouts.backend')
@section('title', 'Public schedule preview – '.$event->name)
@section('content')
<div class="container-xxl py-3">
    <h3>Public schedule preview</h3>
    <p class="text-muted">
        @if($afterPublish)
            Preview after publishing the saved schedule in this selection. Hidden events and draws remain hidden. Nothing has been published.
        @else
            What people currently see: published match times and currently public draws. Private saved changes are excluded.
        @endif
    </p>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id]+$scope) }}">Back to saved schedule</a>
        <a class="btn {{ $afterPublish ? 'btn-primary' : 'btn-outline-primary' }}" role="button" aria-pressed="{{ $afterPublish ? 'true' : 'false' }}"
           href="{{ route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id] + $scope + ['after_publish' => $afterPublish ? 0 : 1]) }}">Preview after publishing</a>
        <span class="badge {{ $afterPublish ? 'bg-label-primary' : 'bg-label-secondary' }}">{{ $afterPublish ? 'After publishing' : 'Currently public' }}</span>
    </div>
    <p class="small text-muted d-md-none">Swipe the match table sideways to see every column.</p>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Day / time</th><th>Venue / court</th><th>Draw</th><th>Participants</th></tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr><td class="text-nowrap">{{ substr($row['scheduled_at'],0,16) }}</td><td>{{ $row['venue_name'] }} / {{ $row['court'] }}</td><td>{{ $row['draw_name'] }}</td><td><x-scheduled-participants :row="$row" /></td></tr>
        @empty
            <tr><td colspan="4" class="text-muted py-4">{{ $afterPublish ? 'No public match times after publishing this selection.' : 'No public match times in this view.' }}</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
</div>
@endsection
