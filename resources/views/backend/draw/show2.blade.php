@php
    $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')



@section('vendor-style')

@endsection

@section('page-style')

@endsection

@section('vendor-script')

@endsection

@section('page-script')
    <script src="{{ asset('assets/js/manage-draw.js') }}"></script>




@endsection


@section('title', 'Manage Draw: ' . $draw->drawName)



@section('content')
    <div class="container mt-4">
        <h3 class="mb-4">Manage Draw: {{ $draw->drawName }}</h3><a href="{{ url()->previous() }}"
            class="btn btn-secondary mb-3">← Back</a>


        <ul class="nav nav-tabs mb-3" id="drawTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings"
                    type="button" role="tab">
                    Settings
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="regs-tab" data-bs-toggle="tab" data-bs-target="#registrations" type="button"
                    role="tab">
                    Registrations
                </button>
            </li>
        </ul>

        <div class="tab-content" id="drawTabsContent">

            <!-- Settings Tab -->
            <div class="tab-pane fade show active" id="settings" role="tabpanel">
                <div class="row">

                    <!-- Editable Settings Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <form id="drawSettingsForm" data-draw-id="{{ $draw->id }}">
                                    @csrf
                                    <select name="draw_type_id" id="draw_type_id" class="form-select">
                                        @foreach ($drawTypes as $type)
                                            <option value="{{ $type->id }}"
                                                {{ optional($draw->settings)->draw_type_id == $type->id ? 'selected' : '' }}>
                                                {{ $type->drawTypeName }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <div class="mb-3">
                                        <label class="form-label">Boxes</label>
                                        <select name="boxes" id="boxes" class="form-select">
                                            @foreach (range(1, 16) as $b)
                                                <option value="{{ $b }}"
                                                    {{ optional($draw->settings)->boxes == $b ? 'selected' : '' }}>
                                                    {{ $b }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Playoff Size</label>
                                        <select name="playoff_size" id="playoff_size" class="form-select">
                                            @foreach ([2, 4, 6, 8, 16] as $ps)
                                                <option value="{{ $ps }}"
                                                    {{ optional($draw->settings)->playoff_size == $ps ? 'selected' : '' }}>
                                                    {{ $ps }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Number of Sets</label>
                                        <select name="num_sets" id="num_sets" class="form-select">
                                            @foreach (range(1, 5) as $set)
                                                <option value="{{ $set }}"
                                                    {{ optional($draw->settings)->num_sets == $set ? 'selected' : '' }}>
                                                    {{ $set }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Draw Format</label>
                                        <select name="draw_format_id" id="draw_format_id" class="form-select">
                                            @foreach ($drawFormats as $format)
                                                <option value="{{ $format->id }}"
                                                    {{ optional($draw->settings)->draw_format_id == $format->id ? 'selected' : '' }}>
                                                    {{ $format->name }}
                                                </option>
                                            @endforeach
                                        </select>

                                    </div>


                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Preview Column -->
                    <div class="col-md-6">
                        <div class="card border border-info">
                            <div class="card-header bg-info text-white">
                                Live Settings Preview
                            </div>
                            <div class="card-body">
                                <ul class="list-group" id="settingsPreview">
                                    <li class="list-group-item">Draw Format:
                                        <strong>{{ $draw->drawFormat->name ?? 'N/A' }}</strong></li>
                                    <li class="list-group-item">Type: <span id="preview_type">
                                            {{ optional(optional($draw->settings)->drawType)->name ?? (optional($draw->settings)->draw_type_id ?? 'N/A') }}

                                        </span></li>
                                    <li class="list-group-item">
                                        Boxes: <span
                                            id="preview_boxes">{{ optional($draw->settings)->boxes ?? 'N/A' }}</span>
                                    </li>

                                    <li class="list-group-item">
                                        Playoff Size: <span
                                            id="preview_playoff">{{ optional($draw->settings)->playoff_size ?? 'N/A' }}</span>
                                    </li>

                                    <li class="list-group-item">
                                        Sets: <span
                                            id="preview_sets">{{ optional($draw->settings)->num_sets ?? 'N/A' }}</span>
                                    </li>

                                    <li class="list-group-item">
                                        Format: <span id="preview_format">
                                            {{ optional(optional($draw->settings)->drawFormat)->name ?? (optional($draw->settings)->draw_format_id ?? 'N/A') }}
                                        </span>

                                    </li>

                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Registrations Tab -->
            <div class="tab-pane fade" id="registrations" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5>Assigned Registrations</h5>
                        @if ($draw->registrations->count())
                            <ul class="list-group list-group-flush">
                                @foreach ($draw->registrations as $reg)
                                    <li class="list-group-item d-flex justify-content-between">
                                        {{ $reg->players[0]->name }} {{ $reg->players[0]->surname }}
                                        <span class="text-muted small">#{{ $reg->id }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <hr>
                            @include('backend.draw.formats.monrad')
                        @else
                            <p class="text-muted">No players assigned to this draw.</p>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
