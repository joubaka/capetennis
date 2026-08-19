@extends('layouts/layoutMaster')

@section('title', 'Create Team Rubber')

@section('content')
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-1">Create Team Rubber</h4>
      <p class="text-muted mb-0">The rubber will be attached to a team tie in the selected draw.</p>
    </div>
    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Back</a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <div class="card">
    <div class="card-body">
      <form method="POST" action="{{ route('backend.team-fixtures.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6">
          <label class="form-label" for="draw_id">Draw</label>
          <select class="form-select" id="draw_id" name="draw_id" required>
            <option value="">Select draw</option>
            @foreach($draws as $draw)
              <option value="{{ $draw->id }}" @selected(old('draw_id') == $draw->id)>{{ $draw->drawName }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="round_nr">Round</label>
          <input class="form-control" id="round_nr" name="round_nr" type="number" min="1" value="{{ old('round_nr', 1) }}" required>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="tie_nr">Tie</label>
          <input class="form-control" id="tie_nr" name="tie_nr" type="number" min="1" value="{{ old('tie_nr', 1) }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="home_team_id">Home team</label>
          <select class="form-select" id="home_team_id" name="home_team_id" required>
            <option value="">Select team</option>
            @foreach($teams as $team)<option value="{{ $team->id }}" @selected(old('home_team_id') == $team->id)>{{ $team->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="away_team_id">Away team</label>
          <select class="form-select" id="away_team_id" name="away_team_id" required>
            <option value="">Select team</option>
            @foreach($teams as $team)<option value="{{ $team->id }}" @selected(old('away_team_id') == $team->id)>{{ $team->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fixture_type">Rubber type</label>
          <select class="form-select" id="fixture_type" name="fixture_type">
            <option value="1">Singles</option><option value="2">Doubles</option>
            <option value="3">Mixed doubles</option><option value="4">Reverse singles</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="scheduled_at">Scheduled time</label>
          <input class="form-control" id="scheduled_at" name="scheduled_at" type="datetime-local" value="{{ old('scheduled_at') }}">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="venue_id">Venue</label>
          <select class="form-select" id="venue_id" name="venue_id"><option value="">Unscheduled</option>
            @foreach($venues as $venue)<option value="{{ $venue->id }}" @selected(old('venue_id') == $venue->id)>{{ $venue->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Create rubber</button></div>
      </form>
    </div>
  </div>
</div>
@endsection
