@extends('layouts/layoutMaster')
@section('title', 'Public schedule preview – '.$event->name)
@section('content')
<div class="container-xxl py-3" data-schedule-preview-page>
    <h3>Public schedule preview</h3>
    <p class="text-muted">
        @if($hideTimes)
            Preview with match times and venues hidden. The published draw remains available. Nothing has been changed.
        @elseif($afterPublish)
            Preview after publishing the saved schedule in this selection. Hidden events and draws remain hidden. Nothing has been published.
        @else
            What people currently see: published match times and currently public draws. Private saved changes are excluded.
        @endif
    </p>
    <p class="small text-muted">
        @if($hideTimes)
            Showing the full public draw with times and venues hidden.
        @elseif($afterPublish)
            Showing the full public draw. The date and venue selection determines which saved times are published in this preview.
        @else
            Showing the full public draw with currently published times.
        @endif
    </p>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a class="btn btn-outline-primary" href="{{ route('backend.event-venue-schedule.calendar',['event'=>$event->id]+$scope) }}">Back to saved schedule</a>
        <button type="button" class="btn {{ !$afterPublish && !$hideTimes ? 'btn-primary' : 'btn-outline-primary' }}" aria-pressed="{{ !$afterPublish && !$hideTimes ? 'true' : 'false' }}" aria-controls="schedule-preview-content"
           data-preview-url="{{ route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id] + $scope + ['after_publish' => 0]) }}">Currently public</button>
        <button type="button" class="btn {{ $afterPublish && !$hideTimes ? 'btn-primary' : 'btn-outline-primary' }}" aria-pressed="{{ $afterPublish && !$hideTimes ? 'true' : 'false' }}" aria-controls="schedule-preview-content"
           data-preview-url="{{ route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id] + $scope + ['after_publish' => 1]) }}">With times published</button>
        <button type="button" class="btn {{ $hideTimes ? 'btn-primary' : 'btn-outline-primary' }}" aria-pressed="{{ $hideTimes ? 'true' : 'false' }}" aria-controls="schedule-preview-content"
           data-preview-url="{{ route('backend.event-venue-schedule.calendar.preview', ['event' => $event->id] + $scope + ['hide_times' => 1]) }}">With times hidden</button>
        <span class="badge bg-label-secondary">{{ $hideTimes ? 'Times hidden' : ($afterPublish ? 'After publishing' : 'Currently public') }}</span>
    </div>
    <p class="small text-muted mb-2" data-preview-status role="status" aria-live="polite"></p>
    <div data-public-schedule-preview id="schedule-preview-content" aria-busy="false">
        @forelse($previewDraws as $preview)
            <section class="mb-4" aria-label="{{ $preview['draw']->drawName }}">
                @if($preview['fixtures']->first() instanceof \App\Models\TeamFixture)
                    @include('frontend.fixture.partials.team-schedule', ['draw' => $preview['draw'], 'fixtures' => $preview['fixtures'], 'enableLiveResults' => false])
                @else
                    @include('frontend.fixture.fixture-table', ['draw' => $preview['draw'], 'fixtures' => $preview['fixtures'], 'hideFixtureHeader' => false, 'fixtureTableId' => 'preview-fixtures-'.$preview['draw']->id])
                @endif
            </section>
        @empty
            <div class="card"><div class="card-body text-muted">{{ $hideTimes ? 'No public fixtures in this selection.' : ($afterPublish ? 'No public match times after publishing this selection.' : 'No public match times in this view.') }}</div></div>
        @endforelse
    </div>
</div>
@endsection

@section('page-script')
<script src="{{ asset('js/schedule-public-preview.js') }}?v={{ filemtime(public_path('js/schedule-public-preview.js')) }}"></script>
@endsection
