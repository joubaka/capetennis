@extends('layouts.backend')

@section('title', 'Edit Team Rubber')

@section('content')
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Edit rubber #{{ $team_fixture->id }}</h4>
      <p class="text-muted mb-0">{{ $team_fixture->homeTeam?->name ?? 'Home' }} vs {{ $team_fixture->awayTeam?->name ?? 'Away' }}</p></div>
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Back</a>
  </div>
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form method="POST" action="{{ route('backend.team-fixtures.update', $team_fixture) }}">
    @csrf @method('PUT')
    <div class="card mb-3"><div class="card-header">Schedule</div><div class="card-body row g-3">
      <div class="col-md-4"><label class="form-label" for="scheduled_at">Time</label><input class="form-control" type="datetime-local" id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at', $team_fixture->scheduled_at?->format('Y-m-d\TH:i')) }}"></div>
      <div class="col-md-4"><label class="form-label" for="venue_id">Venue</label><select class="form-select" id="venue_id" name="venue_id"><option value="">Unassigned</option>@foreach($venues as $venue)<option value="{{ $venue->id }}" @selected(old('venue_id', $team_fixture->venue_id) == $venue->id)>{{ $venue->name }}</option>@endforeach</select></div>
      <div class="col-md-2"><label class="form-label" for="court_label">Court</label><input class="form-control" id="court_label" name="court_label" value="{{ old('court_label', $team_fixture->court_label) }}"></div>
      <div class="col-md-2"><label class="form-label" for="duration_min">Minutes</label><input class="form-control" type="number" min="10" max="480" id="duration_min" name="duration_min" value="{{ old('duration_min', $team_fixture->duration_min) }}"></div>
    </div></div>
    <div class="card"><div class="card-header">Score</div><div class="card-body row g-3">
      @for($set = 1; $set <= 3; $set++)
        @php($result = $team_fixture->fixtureResults->firstWhere('set_nr', $set))
        <div class="col-md-4"><label class="form-label">Set {{ $set }}</label><div class="input-group"><input class="form-control" type="number" min="0" name="set{{ $set }}_home" value="{{ old('set'.$set.'_home', $result?->team1_score) }}"><span class="input-group-text">–</span><input class="form-control" type="number" min="0" name="set{{ $set }}_away" value="{{ old('set'.$set.'_away', $result?->team2_score) }}"></div></div>
      @endfor
      <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Save changes</button></div>
    </div></div>
  </form>
</div>
@endsection
