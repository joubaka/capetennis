@extends('layouts/layoutMaster')

@section('title', 'Disciplinary Log')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="ti ti-gavel me-2"></i>Disciplinary Log</h4>
            <p class="text-muted mb-0">All recorded violations across all players</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('backend.disciplinary.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Record Violation
            </a>
            <a href="{{ route('backend.disciplinary.settings') }}" class="btn btn-outline-secondary">
                <i class="ti ti-settings me-1"></i> Settings
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Player</label>
                    <select name="player_id" class="form-select">
                        <option value="">All Players</option>
                        @foreach($players as $p)
                            <option value="{{ $p->id }}" @selected(request('player_id') == $p->id)>
                                {{ $p->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Violation Type</label>
                    <select name="violation_type_id" class="form-select">
                        <option value="">All Types</option>
                        @foreach($violationTypes as $vt)
                            <option value="{{ $vt->id }}" @selected(request('violation_type_id') == $vt->id)>
                                {{ $vt->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Player</th>
                        <th>Type</th>
                        <th>Penalty</th>
                        <th>Points</th>
                        <th>Status</th>
                        <th>Recorded By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($violations as $v)
                        <tr class="{{ $v->is_expired ? 'text-muted' : '' }}">
                            <td>{{ $v->violation_date->format('d M Y') }}</td>
                            <td>
                                <a href="{{ route('backend.disciplinary.player', $v->player_id) }}">
                                    {{ $v->player->full_name }}
                                </a>
                            </td>
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
                            <td>
                                <strong>{{ $v->points_assigned }}</strong>
                            </td>
                            <td>
                                @if($v->is_expired)
                                    <span class="badge bg-secondary">Expired</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                            <td>{{ $v->recorder->name ?? '—' }}</td>
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
                            <td colspan="8" class="text-center text-muted py-4">No violations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $violations->links() }}
        </div>
    </div>

</div>
@endsection
