@extends('layouts/layoutMaster')

@section('title', 'Player Disciplinary Record — ' . $player->full_name)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="ti ti-gavel me-2"></i>
                {{ $player->full_name }}
                &mdash; Disciplinary Record
            </h4>
            <p class="text-muted mb-0">All violations and suspensions for this player</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('backend.disciplinary.create', ['player_id' => $player->id]) }}"
               class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Record Violation
            </a>
            <a href="{{ route('backend.disciplinary.index') }}" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> All Violations
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Status Card --}}
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-{{ $status['suspended'] ? 'danger' : ($status['active_points'] >= $status['threshold'] ? 'warning' : 'success') }}">
                <div class="card-body text-center">
                    <h6 class="card-subtitle text-muted mb-2">Active Suspension Points</h6>
                    <h1 class="display-4 fw-bold {{ $status['active_points'] >= $status['threshold'] ? 'text-danger' : '' }}">
                        {{ $status['active_points'] }}
                    </h1>
                    <p class="text-muted mb-2">of {{ $status['threshold'] }} point threshold</p>

                    {{-- Progress bar --}}
                    @php
                        $pct = min(100, $status['threshold'] > 0 ? round($status['active_points'] / $status['threshold'] * 100) : 0);
                        $barClass = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                    @endphp
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar {{ $barClass }}" style="width: {{ $pct }}%"></div>
                    </div>

                    <div class="mt-3">
                        @include('backend.disciplinary._status_badge', [
                            'suspended'    => $status['suspended'],
                            'activePoints' => $status['active_points'],
                            'threshold'    => $status['threshold'],
                        ])
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Next On-Court Consequence (PPS)</h6>
                    <p class="mb-0 fs-5 fw-semibold text-warning">
                        <i class="ti ti-alert-triangle me-1"></i>{{ $pps }}
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Suspension History</h6>
                    <p class="mb-1">Total suspensions: <strong>{{ $suspensions->count() }}</strong></p>
                    @if($status['suspended'])
                        <p class="text-danger mb-0">
                            <i class="ti ti-ban me-1"></i>
                            Currently suspended until <strong>{{ $status['suspension_ends_at'] }}</strong>
                        </p>
                    @else
                        <p class="text-success mb-0"><i class="ti ti-check me-1"></i>Not currently suspended</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Active Suspension Banner --}}
    @if($status['suspended'])
        @php $activeSuspension = $suspensions->first(fn($s) => $s->is_active); @endphp
        @if($activeSuspension)
        <div class="alert alert-danger d-flex justify-content-between align-items-center mb-4">
            <div>
                <strong><i class="ti ti-ban me-1"></i>Active Suspension ({{ $activeSuspension->suspension_number }}{{ $activeSuspension->suspension_number === 1 ? 'st' : ($activeSuspension->suspension_number === 2 ? 'nd' : 'th') }})</strong>
                &mdash; {{ $activeSuspension->duration_months }} months.
                Ends: <strong>{{ $activeSuspension->ends_at->format('d M Y') }}</strong>
            </div>
            <form action="{{ route('backend.disciplinary.suspension.lift', $activeSuspension->id) }}"
                  method="POST"
                  onsubmit="return confirm('Lift this suspension? This cannot be undone.');">
                @csrf
                <input type="hidden" name="reason" value="">
                <button type="submit" class="btn btn-sm btn-outline-light">
                    <i class="ti ti-lock-open me-1"></i> Lift Suspension
                </button>
            </form>
        </div>
        @endif
    @endif

    {{-- Violations Table --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Violations</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Penalty</th>
                        <th>Points</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($violations as $v)
                        <tr class="{{ $v->is_expired ? 'text-muted' : '' }}">
                            <td>{{ $v->violation_date->format('d M Y') }}</td>
                            <td>
                                <span class="badge bg-label-{{ match($v->violationType->category ?? '') {
                                    'on_court' => 'warning',
                                    'withdrawal' => 'info',
                                    'no_show' => 'danger',
                                    'abuse' => 'danger',
                                    default => 'secondary'
                                } }}">
                                    {{ $v->violationType->name ?? '—' }}
                                </span>
                            </td>
                            <td>{{ $v->penalty_type ? ucfirst($v->penalty_type) : '—' }}</td>
                            <td><strong>{{ $v->points_assigned }}</strong></td>
                            <td>{{ $v->expires_at->format('d M Y') }}</td>
                            <td>
                                @if($v->is_expired)
                                    <span class="badge bg-secondary">Expired</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($v->notes, 60) }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('backend.disciplinary.violation.edit', $v->id) }}"
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="ti ti-pencil"></i>
                                    </a>
                                    <form action="{{ route('backend.disciplinary.violation.destroy', $v->id) }}"
                                          method="POST"
                                          onsubmit="return confirm('Remove this violation?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No violations recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Suspension History --}}
    @if($suspensions->isNotEmpty())
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Suspension History</h5>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Triggered</th>
                        <th>Duration</th>
                        <th>Starts</th>
                        <th>Ends</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($suspensions as $s)
                        <tr>
                            <td>{{ $s->suspension_number }}</td>
                            <td>{{ $s->triggered_at->format('d M Y') }}</td>
                            <td>{{ $s->duration_months }} months</td>
                            <td>{{ $s->starts_at->format('d M Y') }}</td>
                            <td>{{ $s->ends_at->format('d M Y') }}</td>
                            <td>
                                @if($s->lifted_at)
                                    <span class="badge bg-secondary">Lifted</span>
                                @elseif($s->is_active)
                                    <span class="badge bg-danger">Active</span>
                                @else
                                    <span class="badge bg-label-secondary">Served</span>
                                @endif
                            </td>
                            <td>{{ Str::limit($s->notes, 60) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
