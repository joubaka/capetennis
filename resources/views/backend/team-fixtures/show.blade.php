@extends('layouts/layoutMaster')

@section('content')
<div class="container-xxl">
  <a href="{{ url()->previous() }}" class="btn btn-link mb-3">&larr; Back</a>

  <div class="card">
    <div class="card-header">
      <h5 class="m-0">Fixture #{{ $team_fixture->id }}</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Event</dt>
        <dd class="col-sm-9">{{ optional(optional($team_fixture->draw)->event)->name ?? '—' }}</dd>

        <dt class="col-sm-3">Draw</dt>
        <dd class="col-sm-9">{{ optional($team_fixture->draw)->drawName ?? '—' }}</dd>

        <dt class="col-sm-3">Round / Tie</dt>
        <dd class="col-sm-9">
          {{ $team_fixture->round_name ?? $team_fixture->round }} /
          {{ $team_fixture->tie_name ?? $team_fixture->tie }}
        </dd>

        <dt class="col-sm-3">Home</dt>
        <dd class="col-sm-9">{{ optional($team_fixture->homeTeam)->name ?? 'TBD' }}</dd>

        <dt class="col-sm-3">Away</dt>
        <dd class="col-sm-9">{{ optional($team_fixture->awayTeam)->name ?? 'TBD' }}</dd>

        <dt class="col-sm-3">Scheduled</dt>
        <dd class="col-sm-9">
          @if($team_fixture->scheduled_at)
            {{ \Carbon\Carbon::parse($team_fixture->scheduled_at)->format('Y-m-d H:i') }}
          @else
            —
          @endif
        </dd>

        <dt class="col-sm-3">Venue</dt>
        <dd class="col-sm-9">{{ optional($team_fixture->venue)->name ?? '—' }}</dd>
      </dl>
    </div>
  </div>
</div>
@endsection
