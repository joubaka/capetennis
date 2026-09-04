@extends('layouts.backend')

@section('title', 'Draw Engine Observability Dashboard')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row mb-4">
    <div class="col-12">
      <h4 class="fw-bold py-3 mb-0">
        <span class="text-muted fw-light">Admin /</span> Draw Engine Observability
      </h4>
      <p class="text-muted mb-0">Read-only production safety dashboard. Legacy engine remains authoritative.</p>
    </div>
  </div>

  @if(session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  @endif

  {{-- ---- Status cards ------------------------------------------------ --}}
  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Engine Mode</p>
          @php $mode = config('capetennis_engine.mode', 'hybrid'); @endphp
          <span class="badge bg-{{ $mode === 'canonical' ? 'success' : ($mode === 'hybrid' ? 'warning' : 'secondary') }} fs-6">
            {{ strtoupper($mode) }}
          </span>
          <p class="text-muted small mt-2 mb-0">Auto-fallback: {{ config('capetennis_engine.auto_fallback') ? 'ON' : 'OFF' }}</p>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Canonical Confidence</p>
          @if($confidence['confidence_score'] !== null)
            @php $score = $confidence['confidence_score']; $cls = $score >= 98 ? 'success' : ($score >= 90 ? 'info' : ($score >= 75 ? 'warning' : 'danger')); @endphp
            <h3 class="text-{{ $cls }} mb-0">{{ $score }}%</h3>
            <small class="text-muted">{{ $confidence['confidence_label'] }}</small>
          @else
            <h3 class="text-muted mb-0">--</h3>
            <small class="text-muted">No run data yet</small>
          @endif
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Canonical Runs</p>
          <h3 class="mb-0">{{ number_format($runStats['canonical']) }}</h3>
          <small class="text-muted">of {{ number_format($runStats['total']) }} total</small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Fallbacks / Failures</p>
          <h3 class="text-{{ $runStats['fallbacks'] > 0 ? 'warning' : 'success' }} mb-0">{{ $runStats['fallbacks'] }}</h3>
          <small class="text-muted">{{ $runStats['failures'] }} errors &bull; avg {{ $runStats['avg_ms'] }}ms</small>
        </div>
      </div>
    </div>
  </div>

  {{-- ---- Confidence breakdown --------------------------------------- --}}
  @if($confidence['canonical_runs'] > 0)
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Confidence Breakdown</h5></div>
    <div class="card-body">
      <div class="row g-3 text-center">
        @foreach([
          ['Parity %',          $confidence['parity_pct'],         false],
          ['Mismatch %',        $confidence['mismatch_pct'],        true],
          ['Fallback %',        $confidence['fallback_pct'],        true],
          ['Progression OK %',  $confidence['progression_ok_pct'], false],
          ['Standings OK %',    $confidence['standings_ok_pct'],   false],
        ] as [$label, $val, $invert])
        <div class="col">
          <p class="text-muted small mb-1">{{ $label }}</p>
          @if($val !== null)
            @php $ok = $invert ? $val <= 2 : $val >= 98; $cls = $ok ? 'success' : ($val >= 90 ? 'warning' : 'danger'); @endphp
            <h4 class="text-{{ $cls }} mb-0">{{ $val }}%</h4>
          @else
            <h4 class="text-muted mb-0">--</h4>
          @endif
        </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif

  {{-- ---- Per-operation run stats ------------------------------------ --}}
  @if($runsByOperation->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Run Stats by Operation</h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Operation</th>
            <th class="text-end">Total</th>
            <th class="text-end">Canon OK</th>
            <th class="text-end">Fallbacks</th>
            <th class="text-end">Mismatches</th>
            <th class="text-end">Avg ms</th>
          </tr>
        </thead>
        <tbody>
          @foreach($runsByOperation as $row)
          <tr>
            <td><code>{{ $row->operation_type }}</code></td>
            <td class="text-end">{{ $row->total }}</td>
            <td class="text-end text-{{ $row->canon_ok == $row->total ? 'success' : 'warning' }}">{{ $row->canon_ok }}</td>
            <td class="text-end text-{{ $row->fallbacks > 0 ? 'warning' : 'muted' }}">{{ $row->fallbacks }}</td>
            <td class="text-end text-{{ $row->mismatches > 0 ? 'danger' : 'muted' }}">{{ $row->mismatches }}</td>
            <td class="text-end">{{ $row->avg_ms }}ms</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ---- Top mismatch types ----------------------------------------- --}}
  @if(count($topMismatchTypes) > 0)
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Top Mismatch Types</h5>
      @if($unresolvedHighSev > 0)
        <span class="badge bg-danger">{{ $unresolvedHighSev }} unresolved HIGH</span>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Type</th><th>Operation</th><th class="text-end">Count</th></tr>
        </thead>
        <tbody>
          @foreach($topMismatchTypes as $row)
          <tr>
            <td><span class="badge bg-label-danger">{{ $row['mismatch_type'] }}</span></td>
            <td><code>{{ $row['operation_type'] }}</code></td>
            <td class="text-end fw-bold">{{ $row['total'] }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ---- Recent unresolved mismatches ------------------------------- --}}
  @if($recentEngineMismatches->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Recent Unresolved Mismatches</h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Draw</th><th>Operation</th><th>Type</th><th>Severity</th></tr>
        </thead>
        <tbody>
          @foreach($recentEngineMismatches as $m)
          <tr>
            <td><small>{{ $m->created_at->diffForHumans() }}</small></td>
            <td>{{ $m->draw_id ?? '--' }}</td>
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
    <i class="bx bx-check-circle me-1"></i> No unresolved mismatches. Canonical and legacy in parity.
  </div>
  @endif

  {{-- ---- Recent canonical failures ---------------------------------- --}}
  @if($recentFailedRuns->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Recent Canonical Failures</h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Draw</th><th>Operation</th><th>Mode</th><th>Fallback?</th><th>Exception</th></tr>
        </thead>
        <tbody>
          @foreach($recentFailedRuns as $run)
          <tr>
            <td><small>{{ $run->created_at->diffForHumans() }}</small></td>
            <td>{{ $run->draw_id ?? '--' }}</td>
            <td><code>{{ $run->operation_type }}</code></td>
            <td><span class="badge bg-label-secondary">{{ $run->engine_mode }}</span></td>
            <td>{!! $run->fallback_used ? '<span class="badge bg-warning text-dark">yes</span>' : '<span class="badge bg-secondary">no</span>' !!}</td>
            <td><small class="text-danger">{{ Str::limit($run->exception ?? '', 120) }}</small></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ---- Recent fallbacks (comparison log) -------------------------- --}}
  @if($recentFallbacks->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Recent Fallbacks <span class="badge bg-warning text-dark">{{ $totalFallbacks }}</span></h5>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Operation</th><th>Draw</th><th>Error</th></tr>
        </thead>
        <tbody>
          @foreach($recentFallbacks as $log)
          <tr>
            <td><small>{{ $log->created_at->diffForHumans() }}</small></td>
            <td><code>{{ $log->operation }}</code></td>
            <td>{{ $log->draw_id ?? '--' }}</td>
            <td><small class="text-danger">{{ $log->canonical_result['error'] ?? '--' }}</small></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ---- Actions ---------------------------------------------------- --}}
  <div class="card">
    <div class="card-body">
      <h6 class="fw-bold mb-3">Actions</h6>
      <form method="POST" action="{{ route('engine.debug.clear') }}" onsubmit="return confirm('Clear ALL engine logs? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="bx bx-trash me-1"></i> Clear all engine logs
        </button>
      </form>
    </div>
  </div>

</div>
@endsection
