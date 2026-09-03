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
  const drawId = @json($draw->id);
  const categoryId = @json($draw->category_event_id);

  function request(url, data, method = 'POST') {
    return $.ajax({ url, method, data: Object.assign({_token: csrf}, data) });
  }

  function refreshCounts() {
    $('#eligible-count').text($('#eligible-players .draggable-player:visible').length);
    $('#assigned-count').text($('#assigned-players .draggable-player').length);
  }

  $('#player-search').on('input', function () {
    const query = this.value.toLowerCase();
    $('#eligible-players .draggable-player').each(function () {
      $(this).toggle($(this).text().toLowerCase().includes(query));
    });
  });

  $('#add-selected-players').on('click', function () {
    const ids = $('#eligible-players input[name="registration_ids[]"]:checked').map(function () { return this.value; }).get();
    if (!ids.length) return toastr.warning('Select at least one player.');
    request("{{ route('admin.draws.addPlayerToDraw') }}", {draw_id: drawId, player_ids: ids})
      .done(function (res) { toastr.success(res.message); window.location.reload(); })
      .fail(function (xhr) { toastr.error(xhr.responseJSON?.message || 'Could not add players.'); });
  });

  $('#add-all-players').on('click', function () {
    if (!confirm('Add all eligible players from this category to the draw?')) return;
    request("{{ route('admin.draws.addCategoryPlayers') }}", {draw_id: drawId, category_id: categoryId})
      .done(function (res) { toastr.success(res.message); window.location.reload(); })
      .fail(function (xhr) { toastr.error(xhr.responseJSON?.message || 'Could not add category players.'); });
  });

  $(document).on('click', '.remove-player', function () {
    const row = $(this).closest('.draggable-player');
    request("{{ route('admin.draws.removePlayer') }}", {draw_id: drawId, registration_id: row.data('player-id')}, 'DELETE')
      .done(function (res) { row.remove(); refreshCounts(); toastr.success(res.message); })
      .fail(function (xhr) { toastr.error(xhr.responseJSON?.message || 'Could not remove player.'); });
  });

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
  @if($draw->category_event_id)
  <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
    <span>Flexible Monrad: place players directly into different starting rounds.</span>
    <a class="btn btn-primary btn-sm" href="{{ route('flexible-monrad.show', $draw) }}">Open Monrad editor</a>
    <a class="btn btn-outline-primary btn-sm" href="{{ route('flexible-monrad.demo') }}">Try the demo</a>
  </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1">
        <i class="ti ti-adjustments me-1 text-danger"></i> Engine — {{ $draw->drawName }}
      </h4>
      <small class="text-muted">{{ optional(optional($draw->categoryEvent)->category)->name ?? '' }}</small>
    </div>
    <a href="{{ route('category.manage', $draw->category_event_id) }}" class="btn btn-label-secondary">
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
            @if(! $draw->usesFlexibleMonrad())
            <div class="mb-3">
              <label class="form-label fw-bold">Draw Type</label>
              <select class="form-select" name="draw_type">
                @foreach ($drawTypes as $drawType)
                  <option value="{{ $drawType->id }}" {{ (string) ($draw->drawType_id ?? '') === (string) $drawType->id ? 'selected' : '' }}>
                    {{ $drawType->drawTypeName }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label fw-bold">Sets</label>
              <input type="number" name="num_sets" value="{{ optional($draw->settings)->num_sets ?? 3 }}" min="1" max="5" class="form-control">
            </div>
            @else
              <p class="text-muted">Starting positions and results are managed in the Monrad editor.</p>
            @endif
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
              <span id="eligible-count" class="badge bg-label-primary">{{ $eligibleRegistrations->count() }}</span>
              <input id="player-search" type="search" class="form-control form-control-sm ms-auto" style="max-width: 180px" placeholder="Search players">
              <button id="add-selected-players" type="button" class="btn btn-sm btn-primary">Add selected</button>
              <button id="add-all-players" type="button" class="btn btn-sm btn-outline-primary">Add all</button>
            </div>
            <div class="card-body p-2">
              <ul id="eligible-players" style="min-height: 60px; list-style: none; padding: 0; margin: 0;">
                @forelse ($eligibleRegistrations as $reg)
                  <li class="list-group-item list-group-item-action draggable-player mb-1 rounded d-flex align-items-center gap-2" data-player-id="{{ $reg->id }}" style="cursor: grab;">
                    <input type="checkbox" name="registration_ids[]" value="{{ $reg->id }}" aria-label="Select {{ $reg->players->first()->full_name ?? 'player' }}">
                    <i class="ti ti-grip-vertical me-2 text-muted"></i>{{ $reg->players->first()->full_name ?? '—' }}
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
              <span id="assigned-count" class="badge bg-label-success">{{ $draw->registrations->count() }}</span>
            </div>
            <div class="card-body p-2">
              <ul id="assigned-players" style="min-height: 60px; list-style: none; padding: 0; margin: 0;">
                @forelse ($draw->registrations as $reg)
                  <li class="list-group-item list-group-item-action draggable-player mb-1 rounded d-flex align-items-center gap-2" data-player-id="{{ $reg->id }}" style="cursor: grab;">
                    <i class="ti ti-grip-vertical me-2 text-muted"></i>{{ $reg->players->first()->full_name ?? '—' }}
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto remove-player">Remove</button>
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
