@extends('layouts/layoutMaster')

@section('title', 'Record Violation')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="ti ti-gavel me-2"></i>Record Violation</h4>
            <p class="text-muted mb-0">Log a new disciplinary violation against a player</p>
        </div>
        <a href="{{ route('backend.disciplinary.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Back
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('backend.disciplinary.store') }}" method="POST">
                @csrf

                <div class="row g-4">
                    {{-- Player --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Player <span class="text-danger">*</span></label>
                        <select name="player_id" class="form-select @error('player_id') is-invalid @enderror" required>
                            <option value="">— Select Player —</option>
                            @foreach($players as $p)
                                <option value="{{ $p->id }}"
                                    @selected(old('player_id', $selectedPlayer?->id) == $p->id)>
                                    {{ $p->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('player_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Violation Type --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Violation Type <span class="text-danger">*</span></label>
                        <select name="violation_type_id" id="violation_type_id"
                                class="form-select @error('violation_type_id') is-invalid @enderror" required>
                            <option value="">— Select Type —</option>
                            @foreach($violationTypes as $vt)
                                <option value="{{ $vt->id }}"
                                        data-points="{{ $vt->default_points }}"
                                        @selected(old('violation_type_id') == $vt->id)>
                                    {{ $vt->name }} ({{ $vt->default_points }} pts)
                                </option>
                            @endforeach
                        </select>
                        @error('violation_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Violation Date --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Violation Date <span class="text-danger">*</span></label>
                        <input type="date" name="violation_date"
                               class="form-control @error('violation_date') is-invalid @enderror"
                               value="{{ old('violation_date', date('Y-m-d')) }}"
                               max="{{ date('Y-m-d') }}" required>
                        @error('violation_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Points Assigned --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Points Assigned <span class="text-danger">*</span></label>
                        <input type="number" name="points_assigned" id="points_assigned"
                               class="form-control @error('points_assigned') is-invalid @enderror"
                               value="{{ old('points_assigned', 0) }}" min="0" max="100" required>
                        <div class="form-text">Auto-filled from violation type; can be overridden.</div>
                        @error('points_assigned')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Penalty Type --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Penalty Type</label>
                        <select name="penalty_type" class="form-select @error('penalty_type') is-invalid @enderror">
                            <option value="">— None / Not Applicable —</option>
                            <option value="warning" @selected(old('penalty_type') === 'warning')>Warning</option>
                            <option value="point"   @selected(old('penalty_type') === 'point')>Point Penalty</option>
                            <option value="game"    @selected(old('penalty_type') === 'game')>Game Penalty</option>
                            <option value="default" @selected(old('penalty_type') === 'default')>Default</option>
                        </select>
                        @error('penalty_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Event (optional) --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Related Event <small class="text-muted">(optional)</small></label>
                        <select name="event_id" class="form-select @error('event_id') is-invalid @enderror">
                            <option value="">— None —</option>
                            @foreach($events as $e)
                                <option value="{{ $e->id }}" @selected(old('event_id') == $e->id)>
                                    {{ $e->name ?? 'Event #' . $e->id }}
                                </option>
                            @endforeach
                        </select>
                        @error('event_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Notes --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror"
                                  rows="3" placeholder="Optional: describe the incident...">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Save Violation
                    </button>
                    <a href="{{ route('backend.disciplinary.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>

@section('page-script')
<script>
    document.getElementById('violation_type_id').addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        const pts = selected.dataset.points;
        if (pts !== undefined) {
            document.getElementById('points_assigned').value = pts;
        }
    });
</script>
@endsection

@endsection
