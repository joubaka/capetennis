@extends('layouts/layoutMaster')

@section('title', isset($event) ? "Fixtures HQ — {$event->name}" : 'Fixtures HQ')

{{-- Vendor CSS --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('page-style')
  <style>
    .table-actions { min-width: 180px; }
    .fixture-row .scheduled { white-space: nowrap; }
  </style>
@endsection

{{-- Vendor JS --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Fixtures HQ{{ isset($event) ? ' — '.$event->name : '' }}</h4>
    <div>
      <a href="{{ route('backend.team-fixtures.create') }}" class="btn btn-outline-secondary">Full Create Page</a>
    </div>
  </div>

  {{-- Quick create --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="POST" action="{{ route('backend.team-fixtures.store') }}" class="row g-2 align-items-end">
        @csrf

        <div class="col-md-3">
          <label class="form-label">Draw</label>
          <select name="draw_id" class="form-select" required>
            <option value="">Select draw</option>
            @foreach($draws as $d)
              <option value="{{ $d->id }}">{{ $d->drawName }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Home Team</label>
          <select name="home_team_id" class="form-select select2" required>
            <option value="">Select home team</option>
            @foreach($teams as $t)
              <option value="{{ $t->id }}">{{ $t->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Away Team</label>
          <select name="away_team_id" class="form-select select2" required>
            <option value="">Select away team</option>
            @foreach($teams as $t)
              <option value="{{ $t->id }}">{{ $t->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">Scheduled At</label>
          <input name="scheduled_at" class="form-control flatpickr" placeholder="YYYY-MM-DD HH:mm" />
        </div>

        <div class="col-md-2">
          <label class="form-label">Venue</label>
          <select name="venue_id" class="form-select">
            <option value="">—</option>
            @foreach($venues as $v)
              <option value="{{ $v->id }}">{{ $v->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Round</label>
          <input name="round_nr" class="form-control" />
        </div>

        <div class="col-md-2">
          <label class="form-label">Tie</label>
          <input name="tie_nr" class="form-control" />
        </div>

        <div class="col-md-2">
          <button class="btn btn-success w-100">Create Fixture</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Fixtures table --}}
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Draw</th>
            <th>Round</th>
            <th>Tie</th>
            <th>Home</th>
            <th>Away</th>
            <th>Scheduled</th>
            <th>Venue</th>
            <th class="text-end table-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($fixtures as $fx)
            <tr class="fixture-row" id="fx-{{ $fx->id }}">
              <td>{{ $fx->id }}</td>
              <td>{{ optional($fx->draw)->drawName ?? '—' }}</td>
              <td>{{ $fx->round_nr ?? '—' }}</td>
              <td>{{ $fx->tie_nr ?? '—' }}</td>
              <td>{{ $fx->team1->pluck('name')->implode(' + ') ?: 'TBD' }}</td>
              <td>{{ $fx->team2->pluck('name')->implode(' + ') ?: 'TBD' }}</td>
              <td class="scheduled">
                {{ $fx->scheduled_at ? \Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i') : '—' }}
              </td>
              <td>{{ optional($fx->venue)->name ?? '—' }}</td>
              <td class="text-end">
                <div class="btn-group">
                  <a href="{{ route('backend.team-fixtures.show', $fx->id) }}" class="btn btn-sm btn-outline-secondary">Show</a>
                  <a href="{{ route('backend.team-fixtures.edit', $fx->id) }}" class="btn btn-sm btn-outline-info">Edit</a>
                  <button type="button"
                          class="btn btn-sm btn-primary open-score-modal"
                          data-id="{{ $fx->id }}"
                          data-home="{{ $fx->team1->pluck('name')->implode(' + ') }}"
                          data-away="{{ $fx->team2->pluck('name')->implode(' + ') }}"
                          data-bs-toggle="modal"
                          data-bs-target="#scoreModal">
                    Insert Scores
                  </button>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No fixtures found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Score Modal --}}
<div class="modal fade" id="scoreModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="scoreForm" method="POST" action="">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Insert / Update Scores</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2"><strong id="scoreFixtureTeams"></strong></p>
          <input type="hidden" id="scoreFixtureId" name="fixture_id" />
          <div class="row g-2">
            @for($i = 1; $i <= 3; $i++)
              <div class="col-12 d-flex gap-2 align-items-center">
                <div style="width:80px">Set {{ $i }}</div>
                <input type="number" name="set{{ $i }}_home" class="form-control" placeholder="Home" min="0" />
                <input type="number" name="set{{ $i }}_away" class="form-control" placeholder="Away" min="0" />
              </div>
            @endfor
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-primary" type="submit">Save Scores</button>
        </div>
      </div>
    </form>
  </div>
</div>

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    flatpickr('.flatpickr', { enableTime: true, dateFormat: "Y-m-d H:i" });
    $('.select2').select2({ width: '100%' });

    // Open score modal and set form action dynamically
    document.querySelectorAll('.open-score-modal').forEach(btn => {
      btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const home = this.dataset.home || 'Home';
        const away = this.dataset.away || 'Away';

        document.getElementById('scoreFixtureId').value = id;
        document.getElementById('scoreFixtureTeams').textContent = `${home} vs ${away}`;

        // set action to insertScore route
        const form = document.getElementById('scoreForm');
        form.action = `/backend/team-fixtures/${id}/insert-score`;
      });
    });
  });
</script>
@endsection
@endsection
