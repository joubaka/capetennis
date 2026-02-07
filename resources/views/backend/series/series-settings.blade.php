@extends('layouts/layoutMaster')

@section('title', $series->name . ' – Series Settings')

{{-- Vendor styles --}}
@section('vendor-style')
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
@endsection

{{-- Vendor scripts --}}
@section('vendor-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
@endsection

@section('page-style')
<style>
  .setting-label {
    font-weight: 600;
  }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Series Settings</h4>
        <div class="text-muted">{{ $series->name }} ({{ $series->year }})</div>
      </div>

      <a href="{{ route('series.show', $series) }}"
         class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>
        Back to Series
      </a>
    </div>
  </div>

  <div class="row g-3">

    {{-- BASIC SETTINGS --}}
    <div class="col-xl-6">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Basic Settings</h5>
        </div>

        <div class="card-body">
          <form id="series-settings-form">
            @csrf

            <div class="mb-3">
              <label class="form-label setting-label">
                Best Results Counted
              </label>
              <input type="number"
                     name="best_num_of_scores"
                     class="form-control"
                     min="1"
                     required
                     value="{{ $series->best_num_of_scores }}">
            </div>

            <div class="mb-3">
              <label class="form-label setting-label">
                Rank Type
              </label>
              <select name="rank_type" class="form-select" required>
                @foreach($rankTypes as $type)
                  <option value="{{ $type->id }}"
                    {{ (int)$series->rank_type === (int)$type->id ? 'selected' : '' }}>
                    {{ $type->type }}
                  </option>
                @endforeach
              </select>
            </div>

            <button type="button" class="btn btn-primary" id="save-series-btn">
              <i class="ti ti-device-floppy me-1"></i>
              Save Settings
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- POINTS ALLOCATION --}}
    <div class="col-xl-6">
      <div class="card h-100 border-start border-primary border-3">
        <div class="card-header">
          <h5 class="mb-0">Points Allocation</h5>
        </div>

        <div class="card-body">
          <p class="text-muted mb-3">
            Define points awarded per finishing position (1–{{ count($positions) }}).
          </p>

          <table class="table table-sm table-bordered">
            <thead class="table-light">
              <tr>
                <th style="width:120px;">Position</th>
                <th>Points</th>
              </tr>
            </thead>
            <tbody>
              @foreach($positions as $pos)
                @php
                  $point = optional(
                    $series->points->firstWhere('position', $pos)
                  )->score ?? 0;
                @endphp
                <tr>
                  <td><strong>#{{ $pos }}</strong></td>
                  <td>
                    <input type="number"
                           class="form-control point-input"
                           data-position="{{ $pos }}"
                           min="0"
                           value="{{ $point }}">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

          <button type="button" class="btn btn-success mt-2" id="save-points-btn">
            <i class="ti ti-device-floppy me-1"></i>
            Save Points
          </button>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@section('page-script')
<script>
  toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    timeOut: 2500
  };

  // -------------------------
  // Save Series Settings (JSON)
  // -------------------------
  document.getElementById('save-series-btn').addEventListener('click', () => {
    const btn = document.getElementById('save-series-btn');
    btn.disabled = true;

    fetch('{{ route('ranking.series.update', $series) }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({
        best_num_of_scores: document.querySelector('[name="best_num_of_scores"]').value,
        rank_type: document.querySelector('[name="rank_type"]').value
      })
    })
    .then(res => res.json())
    .then(res => toastr.success(res.message || 'Series settings saved'))
    .catch(() => toastr.error('Failed to save series settings'))
    .finally(() => btn.disabled = false);
  });

  // -------------------------
  // Save Points Allocation (JSON)
  // -------------------------
  document.getElementById('save-points-btn').addEventListener('click', () => {
    const btn = document.getElementById('save-points-btn');
    btn.disabled = true;

    const points = [];
    document.querySelectorAll('.point-input').forEach(input => {
      points.push({
        position: Number(input.dataset.position),
        score: Number(input.value || 0)
      });
    });

    fetch('{{ route('ranking.points.update', $series) }}', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
      },
      body: JSON.stringify({ points })
    })
    .then(res => res.json())
    .then(res => toastr.success(res.message || 'Points saved successfully'))
    .catch(() => toastr.error('Failed to save points'))
    .finally(() => btn.disabled = false);
  });
</script>
@endsection
