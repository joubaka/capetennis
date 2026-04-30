@extends('layouts/layoutMaster')

@section('title', 'Edit Violation')

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
            <h4 class="mb-1"><i class="ti ti-pencil me-2"></i>Edit Violation</h4>
            <p class="text-muted mb-0">
                Player: <strong>{{ $violation->player->full_name }}</strong>
            </p>
        </div>
        <a href="{{ route('backend.disciplinary.player', $violation->player_id) }}"
           class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Back to Player
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('backend.disciplinary.violation.update', $violation->id) }}" method="POST">
                @csrf @method('PUT')

                <div class="row g-4">
                    {{-- Violation Type --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Violation Type <span class="text-danger">*</span></label>
                        <select name="violation_type_id" id="violation_type_id"
                                class="form-select select2 @error('violation_type_id') is-invalid @enderror" required>
                            <option value="">— Select Type —</option>
                            @foreach($violationTypes as $vt)
                                <option value="{{ $vt->id }}"
                                        data-points="{{ $vt->default_points }}"
                                        @selected(old('violation_type_id', $violation->violation_type_id) == $vt->id)>
                                    {{ $vt->name }} ({{ $vt->default_points }} pts)
                                </option>
                            @endforeach
                        </select>
                        @error('violation_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Date --}}
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Violation Date <span class="text-danger">*</span></label>
                        <input type="date" name="violation_date"
                               class="form-control @error('violation_date') is-invalid @enderror"
                               value="{{ old('violation_date', $violation->violation_date->format('Y-m-d')) }}"
                               max="{{ date('Y-m-d') }}" required>
                        @error('violation_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Points --}}
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Points Assigned <span class="text-danger">*</span></label>
                        <input type="number" name="points_assigned" id="points_assigned"
                               class="form-control @error('points_assigned') is-invalid @enderror"
                               value="{{ old('points_assigned', $violation->points_assigned) }}"
                               min="0" max="100" required>
                        @error('points_assigned')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Penalty Type --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Penalty Type</label>
                        <select name="penalty_type" class="form-select select2">
                            <option value="">— None —</option>
                            @foreach(['warning', 'point', 'game', 'default'] as $pt)
                                <option value="{{ $pt }}"
                                        @selected(old('penalty_type', $violation->penalty_type) === $pt)>
                                    {{ ucfirst($pt) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Event --}}
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Related Event <small class="text-muted">(optional)</small></label>
                        <select name="event_id" class="form-select select2">
                            <option value="">— None —</option>
                            @foreach($events as $e)
                                <option value="{{ $e->id }}"
                                        @selected(old('event_id', $violation->event_id) == $e->id)>
                                    {{ $e->name ?? 'Event #' . $e->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Notes --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $violation->notes) }}</textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Update Violation
                    </button>
                    <a href="{{ route('backend.disciplinary.player', $violation->player_id) }}"
                       class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>

@section('page-script')
<script>
    $(function () {
        $('.select2').select2();

        $('#violation_type_id').on('change', function () {
            const selected = this.options[this.selectedIndex];
            const pts = selected.dataset.points;
            if (pts !== undefined) {
                document.getElementById('points_assigned').value = pts;
            }
        });
    });
</script>
@endsection

@endsection
