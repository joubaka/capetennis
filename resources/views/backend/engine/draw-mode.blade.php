@extends('layouts/layoutMaster')

@section('title', 'Draw Engine Mode — Draw #' . $draw->id)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row mb-4">
    <div class="col-12">
      <h4 class="fw-bold py-3 mb-0">
        <span class="text-muted fw-light">Admin / Engine /</span> Draw #{{ $draw->id }} Engine Mode
      </h4>
      <p class="text-muted mb-0">
        Set engine mode for <strong>{{ $draw->drawName }}</strong>
        @if($draw->event) (Event: {{ $draw->event->name }}) @endif
      </p>
    </div>
  </div>

  @if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  @if($errors->any())
  <div class="alert alert-danger alert-dismissible fade show">
    {{ $errors->first() }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  <div class="row g-4 mb-4">

    {{-- Current mode card --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Current Effective Mode</p>
          @php $eff = $draw->effectiveEngineMode(); @endphp
          <span class="badge fs-5 bg-{{ $eff === 'canonical' ? 'success' : ($eff === 'hybrid' ? 'warning' : 'secondary') }}">
            {{ strtoupper($eff) }}
          </span>
          @if($draw->engine_mode)
            <p class="text-muted small mt-2 mb-0"><i class="bx bx-pin me-1"></i>Draw-level override: <strong>{{ $draw->engine_mode }}</strong></p>
          @elseif($draw->event?->engine_mode)
            <p class="text-muted small mt-2 mb-0"><i class="bx bx-transfer me-1"></i>Inherited from event: <strong>{{ $draw->event->engine_mode }}</strong></p>
          @else
            <p class="text-muted small mt-2 mb-0"><i class="bx bx-globe me-1"></i>Inherited from global config</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Safety check card --}}
    <div class="col-md-4">
      <div class="card h-100 border-{{ $safetyCheck['allowed'] ? 'success' : 'danger' }}">
        <div class="card-body">
          <p class="fw-semibold mb-1">Canonical Safety</p>
          @if($safetyCheck['allowed'])
            <span class="badge bg-success">SAFE</span>
            <p class="text-muted small mt-2 mb-0">No blocking mismatches. Canonical mode may be enabled.</p>
          @else
            <span class="badge bg-danger">BLOCKED</span>
            <p class="text-danger small mt-2 mb-0">{{ $safetyCheck['reason'] }}</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Rollback card --}}
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Emergency Rollback</p>
          <p class="text-muted small mb-3">Instantly force draw to LEGACY mode and mark all mismatches resolved.</p>
          <form method="POST" action="{{ route('engine.draw.rollback', $draw) }}"
                onsubmit="return confirm('Force draw #{{ $draw->id }} to LEGACY mode and mark all mismatches resolved?')">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm w-100">
              <i class="bx bx-undo me-1"></i> Rollback to Legacy
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>

  {{-- Set draw engine mode --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Set Draw Engine Mode Override</h5></div>
    <div class="card-body">
      <form method="POST" action="{{ route('engine.draw.update', $draw) }}">
        @csrf
        @method('PATCH')
        <div class="row align-items-end g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Engine Mode</label>
            <select name="engine_mode" class="form-select">
              <option value="" {{ ! $draw->engine_mode ? 'selected' : '' }}>— Inherit (event/global) —</option>
              <option value="legacy"    {{ $draw->engine_mode === 'legacy'    ? 'selected' : '' }}>Legacy</option>
              <option value="hybrid"    {{ $draw->engine_mode === 'hybrid'    ? 'selected' : '' }}>Hybrid (shadow)</option>
              <option value="canonical" {{ $draw->engine_mode === 'canonical' ? 'selected' : '' }}
                {{ ! $safetyCheck['allowed'] ? 'disabled' : '' }}>
                Canonical {{ ! $safetyCheck['allowed'] ? '(blocked — mismatches)' : '' }}
              </option>
            </select>
            <div class="form-text">Draw-level override takes precedence over event and global settings.</div>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">
              <i class="bx bx-save me-1"></i> Save Mode
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  @if($draw->event)
  {{-- Set event engine mode --}}
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Set Event Engine Mode Override — {{ $draw->event->name }}</h5></div>
    <div class="card-body">
      <form method="POST" action="{{ route('engine.event.update', $draw->event) }}">
        @csrf
        @method('PATCH')
        <div class="row align-items-end g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Event Engine Mode</label>
            <select name="engine_mode" class="form-select">
              <option value="" {{ ! $draw->event->engine_mode ? 'selected' : '' }}>— Inherit global config —</option>
              <option value="legacy"    {{ $draw->event->engine_mode === 'legacy'    ? 'selected' : '' }}>Legacy</option>
              <option value="hybrid"    {{ $draw->event->engine_mode === 'hybrid'    ? 'selected' : '' }}>Hybrid (shadow)</option>
              <option value="canonical" {{ $draw->event->engine_mode === 'canonical' ? 'selected' : '' }}>Canonical</option>
            </select>
            <div class="form-text">Applies to all draws in this event unless a draw-level override is set.</div>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">
              <i class="bx bx-save me-1"></i> Save Event Mode
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  @endif

  {{-- Run stats for this draw --}}
  @if($runStats->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Run History for Draw #{{ $draw->id }}</h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Mode</th><th class="text-end">Runs</th><th class="text-end">Canon OK</th><th class="text-end">Fallbacks</th><th class="text-end">Mismatches</th></tr>
        </thead>
        <tbody>
          @foreach($runStats as $row)
          <tr>
            <td><span class="badge bg-{{ $row->engine_mode === 'canonical' ? 'success' : ($row->engine_mode === 'hybrid' ? 'warning text-dark' : 'secondary') }}">{{ strtoupper($row->engine_mode) }}</span></td>
            <td class="text-end">{{ $row->total }}</td>
            <td class="text-end text-{{ $row->canon_ok == $row->total ? 'success' : 'warning' }}">{{ $row->canon_ok }}</td>
            <td class="text-end text-{{ $row->fallbacks > 0 ? 'warning' : 'muted' }}">{{ $row->fallbacks }}</td>
            <td class="text-end text-{{ $row->mismatches > 0 ? 'danger' : 'muted' }}">{{ $row->mismatches }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Unresolved mismatches --}}
  @if($recentMismatches->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Unresolved Mismatches for Draw #{{ $draw->id }}</h5>
      <span class="badge bg-danger">{{ $recentMismatches->count() }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Operation</th><th>Type</th><th>Severity</th></tr>
        </thead>
        <tbody>
          @foreach($recentMismatches as $m)
          <tr>
            <td><small>{{ $m->created_at->diffForHumans() }}</small></td>
            <td><code>{{ $m->operation_type }}</code></td>
            <td><span class="badge bg-label-warning">{{ $m->mismatch_type }}</span></td>
            <td>
              <span class="badge bg-{{ $m->severity === 'high' ? 'danger' : ($m->severity === 'medium' ? 'warning text-dark' : 'secondary') }}">
                {{ strtoupper($m->severity) }}
              </span>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @else
  <div class="alert alert-success mb-4">
    <i class="bx bx-check-circle me-1"></i> No unresolved mismatches for this draw.
  </div>
  @endif

  <div class="d-flex gap-2">
    <a href="{{ route('engine.debug') }}" class="btn btn-outline-secondary btn-sm">
      <i class="bx bx-arrow-back me-1"></i> Back to Engine Dashboard
    </a>
  </div>

</div>
@endsection
