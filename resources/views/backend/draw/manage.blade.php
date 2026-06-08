@extends('layouts/layoutMaster')

@section('title', 'Engine — ' . $draw->drawName)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

@section('page-script')
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
<script>
$(function () {
  const csrf = $('meta[name="csrf-token"]').attr('content');

  function saveAssigned() {
    const players = [];
    $('#assigned-players .draggable-player').each(function () {
      players.push($(this).data('player-id'));
    });
    $.post("{{ route('draws.players.update', $draw->id) }}", {
      _token: csrf,
      players: players
    }).done(function (res) {
      toastr.success(res.message || 'Players updated.');
    }).fail(function () {
      toastr.error('Failed to update players.');
    });
  }

  if (typeof Sortable !== 'undefined') {
    ['eligible-players', 'assigned-players'].forEach(function (id) {
      Sortable.create(document.getElementById(id), {
        group: 'players',
        animation: 150,
        onEnd: saveAssigned
      });
    });
  }
});
</script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1">
        <i class="ti ti-adjustments me-1 text-danger"></i> Engine — {{ $draw->drawName }}
      </h4>
      <small class="text-muted">{{ optional(optional($draw->categoryEvent)->category)->name ?? '' }}</small>
    </div>
    <a href="{{ url()->previous() }}" class="btn btn-label-secondary">
      <i class="ti ti-arrow-left me-1"></i> Back
    </a>
  </div>

  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#settings">
        <i class="ti ti-settings me-1"></i> Settings
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#players">
        <i class="ti ti-users me-1"></i> Players
      </a>
    </li>
  </ul>

  <div class="tab-content">

    {{-- SETTINGS TAB --}}
    <div class="tab-pane fade show active" id="settings">
      <div class="card">
        <div class="card-body" style="max-width: 500px;">
          <form method="POST" action="{{ route('backend.draw.update-settings', $draw->id) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label fw-bold">Draw Name</label>
              <input type="text" class="form-control" name="name" value="{{ $draw->drawName }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Draw Type</label>
              <select class="form-select" name="draw_type">
                <option value="1" {{ ($draw->drawType_id ?? '') == 1 ? 'selected' : '' }}>Knockout</option>
                <option value="2" {{ ($draw->drawType_id ?? '') == 2 ? 'selected' : '' }}>Feed-In</option>
                <option value="3" {{ ($draw->drawType_id ?? '') == 3 ? 'selected' : '' }}>Round Robin</option>
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label fw-bold">Sets</label>
              <input type="number" name="num_sets" value="{{ $draw->num_sets ?? 3 }}" min="1" max="5" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i> Save Settings
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- PLAYERS TAB --}}
    <div class="tab-pane fade" id="players">
      <div class="row g-4">
        <div class="col-12 col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <h6 class="mb-0"><i class="ti ti-list me-1 text-primary"></i> Eligible Players</h6>
              <span class="badge bg-label-primary">{{ $eligibleRegistrations->count() }}</span>
            </div>
            <div class="card-body p-2">
              <ul id="eligible-players" style="min-height: 60px; list-style: none; padding: 0; margin: 0;">
                @forelse ($eligibleRegistrations as $reg)
                  <li class="list-group-item list-group-item-action draggable-player mb-1 rounded" data-player-id="{{ $reg->id }}" style="cursor: grab;">
                    <i class="ti ti-grip-vertical me-2 text-muted"></i>{{ $reg->displayName() }}
                  </li>
                @empty
                  <li class="list-group-item text-muted text-center">No eligible players</li>
                @endforelse
              </ul>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <h6 class="mb-0"><i class="ti ti-tournament me-1 text-success"></i> Assigned to Draw</h6>
              <span class="badge bg-label-success">{{ $draw->registrations->count() }}</span>
            </div>
            <div class="card-body p-2">
              <ul id="assigned-players" style="min-height: 60px; list-style: none; padding: 0; margin: 0;">
                @forelse ($draw->registrations as $reg)
                  <li class="list-group-item list-group-item-action draggable-player mb-1 rounded" data-player-id="{{ $reg->id }}" style="cursor: grab;">
                    <i class="ti ti-grip-vertical me-2 text-muted"></i>{{ $reg->displayName() }}
                  </li>
                @empty
                  <li class="list-group-item text-muted text-center">No players assigned yet</li>
                @endforelse
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
