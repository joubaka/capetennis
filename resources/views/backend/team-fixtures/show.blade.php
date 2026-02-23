@extends('layouts/layoutMaster')

@section('content')
<div class="container-xxl">
  <a href="{{ url()->previous() }}" class="btn btn-link mb-3">&larr; Back</a>

  <div class="card mb-3">
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

        <dt class="col-sm-3">Home (Region)</dt>
        <dd class="col-sm-9">{{ $team_fixture->region1Name->short_name ?? $team_fixture->region1Name->region_name ?? 'TBD' }}</dd>

        <dt class="col-sm-3">Away (Region)</dt>
        <dd class="col-sm-9">{{ $team_fixture->region2Name->short_name ?? $team_fixture->region2Name->region_name ?? 'TBD' }}</dd>

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

  <div class="card">
    <div class="card-header">
      <h6 class="m-0">Match Players</h6>
    </div>
    <div class="card-body">
      @if($team_fixture->fixturePlayers->isEmpty())
        <div class="alert alert-info mb-0">No player rows linked to this fixture yet.</div>
      @else
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>#</th>
                <th>Home</th>
                <th></th>
                <th>Away</th>
              </tr>
            </thead>
            <tbody>
              @foreach($team_fixture->fixturePlayers as $i => $fp)
                @php
                  // profile player objects (nullable)
                  $homeProfile = $fp->player1 ?? null;
                  $awayProfile = $fp->player2 ?? null;
              dd($homeProfile,$awayProfile);
                  // no-profile lookup (fallback)
                  $homeNo = $fp->team1_no_profile_id ? \App\Models\NoProfileTeamPlayer::find($fp->team1_no_profile_id) : null;
                  $awayNo = $fp->team2_no_profile_id ? \App\Models\NoProfileTeamPlayer::find($fp->team2_no_profile_id) : null;

                  // region labels (prefer short_name)
                  $homeRegion = $team_fixture->region1Name->short_name ?? $team_fixture->region1Name->region_name ?? '—';
                  $awayRegion = $team_fixture->region2Name->short_name ?? $team_fixture->region2Name->region_name ?? '—';

                  $homeName = $homeProfile?->full_name
                    ?? ($homeNo?->name . ' ' . ($homeNo?->surname ?? ''))
                    ?? 'TBD';

                  $awayName = $awayProfile?->full_name
                    ?? ($awayNo?->name . ' ' . ($awayNo?->surname ?? ''))
                    ?? 'TBD';
                @endphp

                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td>
                    {{ $homeName }} <small class="text-muted">({{ $homeRegion }})</small>
                  </td>
                  <td class="text-center">vs</td>
                  <td>
                    {{ $awayName }} <small class="text-muted">({{ $awayRegion }})</small>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
