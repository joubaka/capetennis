@extends('layouts/layoutMaster')

@section('title', $draw->drawName ?? 'Tournament draw')

@section('vendor-style')

@endsection

@section('vendor-script')



@endsection

@section('page-style')

@endsection

@section('page-script')


@endsection

@section('content')
@auth
  @can('view', $draw)
    <div class="m-2">
      <a href="{{ route('frontend.bracket.fixtures', $draw->id) }}" class="btn btn-primary btn-sm">
        Manage fixtures
      </a>
    </div>
  @endcan
@endauth

<div class="mb-3">
  <a href="{{ route('events.show', $draw->event_id) }}#event-draws-match-times" class="btn btn-sm btn-outline-secondary">
    <i class="ti ti-arrow-left me-1"></i>Back to tournament
  </a>
</div>
@php
  $legacyScheduleAvailable = app(\App\Services\PublicDrawPresentation::class)->summary($draw)['available'];
@endphp
<div class="alert {{ $legacyScheduleAvailable ? 'alert-success' : 'alert-info' }} py-2">
  <strong>{{ $draw->drawName ?? 'Tournament draw' }}</strong> —
  {{ $legacyScheduleAvailable ? 'draw and match times published' : 'draw published; match times to follow' }}.
</div>
<div>
  @include('frontend.draw.print')
</div>

@endsection
