@extends('layouts/layoutMaster')

@section('title', 'Disciplinary Settings')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="ti ti-settings me-2"></i>Disciplinary Settings</h4>
            <p class="text-muted mb-0">Configure violation types, point weights, and suspension thresholds</p>
        </div>
        <a href="{{ route('backend.disciplinary.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Back to Log
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Threshold Settings ── --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="ti ti-adjustments me-2"></i>Threshold & Expiry Settings</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('backend.disciplinary.settings.update') }}" method="POST">
                @csrf

                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            Suspension Threshold (points)
                            <i class="ti ti-info-circle ms-1 text-muted" title="Player is suspended when active points reach or exceed this value"></i>
                        </label>
                        <input type="number" name="suspension_threshold"
                               class="form-control @error('suspension_threshold') is-invalid @enderror"
                               value="{{ old('suspension_threshold', $settings['suspension_threshold']->value ?? 12) }}"
                               min="1" max="1000">
                        @error('suspension_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            Points Expiry (days)
                        </label>
                        <input type="number" name="expiry_days"
                               class="form-control @error('expiry_days') is-invalid @enderror"
                               value="{{ old('expiry_days', $settings['expiry_days']->value ?? 365) }}"
                               min="1" max="3650">
                        @error('expiry_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            1st Suspension Duration (months)
                        </label>
                        <input type="number" name="first_suspension_months"
                               class="form-control @error('first_suspension_months') is-invalid @enderror"
                               value="{{ old('first_suspension_months', $settings['first_suspension_months']->value ?? 3) }}"
                               min="1" max="120">
                        @error('first_suspension_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            2nd+ Suspension Duration (months)
                        </label>
                        <input type="number" name="second_suspension_months"
                               class="form-control @error('second_suspension_months') is-invalid @enderror"
                               value="{{ old('second_suspension_months', $settings['second_suspension_months']->value ?? 6) }}"
                               min="1" max="120">
                        @error('second_suspension_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Violation Types ── --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ti ti-list me-2"></i>Violation Types</h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addTypeForm">
                <i class="ti ti-plus me-1"></i> Add Type
            </button>
        </div>

        {{-- Add new type form (collapsed by default) --}}
        <div class="collapse" id="addTypeForm">
            <div class="card-body border-bottom bg-light">
                <form action="{{ route('backend.disciplinary.violation-type.store') }}" method="POST">
                    @csrf
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   value="{{ old('name') }}" placeholder="e.g. Racket Abuse">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" class="form-select select2" required>
                                @foreach(\App\Models\ViolationType::$categories as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Points</label>
                            <input type="number" name="default_points" class="form-control" min="0" max="100"
                                   value="{{ old('default_points', 2) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" name="description" class="form-control" maxlength="500"
                                   value="{{ old('description') }}">
                        </div>
                        <div class="col-md-1 text-center">
                            <label class="form-label fw-semibold">Active</label>
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input" type="checkbox" name="active" value="1" checked>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="ti ti-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Default Points</th>
                        <th>Description</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($violationTypes as $vt)
                        <tr>
                            <form action="{{ route('backend.disciplinary.violation-type.update', $vt) }}"
                                  method="POST" id="vt-form-{{ $vt->id }}">
                                @csrf @method('PUT')
                                <td>
                                    <input type="text" name="name" class="form-control form-control-sm"
                                           value="{{ $vt->name }}" required maxlength="100">
                                </td>
                                <td>
                                    <select name="category" class="form-select form-select-sm select2">
                                        @foreach(\App\Models\ViolationType::$categories as $key => $label)
                                            <option value="{{ $key }}" @selected($vt->category === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="default_points" class="form-control form-control-sm"
                                           style="width:80px;" value="{{ $vt->default_points }}" min="0" max="100">
                                </td>
                                <td>
                                    <input type="text" name="description" class="form-control form-control-sm"
                                           value="{{ $vt->description }}" maxlength="500">
                                </td>
                                <td>
                                    <input type="hidden" name="active" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="active"
                                               value="1" {{ $vt->active ? 'checked' : '' }}>
                                    </div>
                                </td>
                            </form>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="submit" form="vt-form-{{ $vt->id }}"
                                            class="btn btn-sm btn-outline-primary" title="Save">
                                        <i class="ti ti-device-floppy"></i>
                                    </button>
                                    <form action="{{ route('backend.disciplinary.violation-type.destroy', $vt) }}"
                                          method="POST"
                                          onsubmit="return confirm('Delete this violation type?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No violation types configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Reference Table --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="ti ti-table me-2"></i>Code of Conduct Quick Reference</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">This table is displayed to players and coaches as a summary of the disciplinary system.</p>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Violation Category</th>
                            <th>Consequence</th>
                            <th>Suspension Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>On-Court Behaviour</td>
                            <td>Warning → Point → Game</td>
                            <td>2 Points</td>
                        </tr>
                        <tr>
                            <td>Late Withdrawal</td>
                            <td>Admin Review</td>
                            <td>3 Points</td>
                        </tr>
                        <tr>
                            <td>No Show</td>
                            <td>Automatic Entry Ban</td>
                            <td>5 Points</td>
                        </tr>
                        <tr>
                            <td>Aggressive Abuse</td>
                            <td>Immediate Default</td>
                            <td>8–12 Points</td>
                        </tr>
                        <tr class="table-warning">
                            <td colspan="2"><strong>Cumulative Total</strong></td>
                            <td><strong>{{ $settings['suspension_threshold']->value ?? 12 }} Points in {{ round(($settings['expiry_days']->value ?? 365) / 30) }} months → {{ $settings['first_suspension_months']->value ?? 3 }}-Month Suspension (1st offence)</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@section('page-script')
<script>
    $(function () {
        $('.select2').select2();
    });
</script>
@endsection

@endsection
