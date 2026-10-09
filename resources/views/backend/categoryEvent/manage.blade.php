@php
    $configData = Helper::appClasses();
@endphp

@extends('layouts.backend')

@section('title', 'Admin - Event Page')

@section('vendor-style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('vendor-script')

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  

@endsection


@section('page-script')
    <script src="{{ asset('assets/js/manage-category.js') }}"></script>
@endsection

@section('content')

    <style>
        #eligible-player-list {
            min-height: 150px;
            background-color: #f0f4f8;
            border: 2px dashed #bbb;
            border-radius: 6px;
            padding: 1rem;
        }

        .dropzone {
            min-height: 150px;
            padding: 1rem;
            background-color: #f9f9f9;
            border: 2px dashed #ccc;
            border-radius: 6px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .dropzone.border-primary {
            background-color: #eaf4ff;
            border-color: #007bff;
        }

        .dropzone .card.draggable-player {
            cursor: grab;
        }

        .dropzone .card.draggable-player.dragging {
            opacity: 0.6;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        }

        .dropzone .card-header {
            font-weight: bold;
        }


        .category-admin :is(.btn,.nav-link,input,select) { min-height:44px !important; }
        .category-admin .card-body { overflow-wrap:anywhere; }
    </style>
    <meta name="app-url" content="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div id="action-toast" class="toast position-fixed bottom-0 end-0 m-3" role="alert" data-bs-delay="2000">
        <div class="toast-body bg-success text-white">Toast Message</div>
    </div>

    <!-- Blade: manage.blade.php -->
    <div class="container category-admin">
        <h3>Manage Category: {{ $categoryEvent->category->name }}</h3><a href="{{ url()->previous() }}" class="btn btn-secondary mb-3">← Back</a>


        <p class="text-muted">{{ $eligibleRegistrations->count() }} unassigned registrations · {{ $categoryEvent->draws->count() }} draws. Publication is managed separately.</p>
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <ul class="nav nav-tabs mb-3" id="categoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $errors->any() ? '' : 'active' }}" id="players-tab" data-bs-toggle="tab" data-bs-target="#players"
                    type="button" role="tab">Players</button>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ $errors->any() ? 'active' : '' }}" id="settings-tab-link" data-bs-toggle="tab" href="#settings-tab" role="tab">Create draw</a>
            </li>

        </ul>

        <div class="tab-content" id="categoryTabsContent">
            <div class="tab-pane fade {{ $errors->any() ? '' : 'show active' }}" id="players" role="tabpanel">
                <div class="row">

                    <!-- Left Column: Eligible Players -->
                    <div class="col-md-6">
                        <h5>Eligible Players</h5><p class="small text-muted">Unassigned registrations in this category. Use the existing drag controls on a computer to move players between draws.</p>
                        <!-- Eligible Player List -->
                        <div id="eligible-player-list" class="dropzone border rounded p-3">
                            @forelse($eligibleRegistrations as $reg)
                                <div class="card mb-2 draggable-player" data-player-id="{{ $reg->id }}">
                                    <div class="card-body p-2">
                                        {{ $reg->players[0]->name }} {{ $reg->players[0]->surname }}
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted">No unassigned registrations in this category.</p>
                            @endforelse
                        </div>

                        <!-- Master template (hidden source of clean clones) -->
                        <div id="master-player-list" class="d-none">
                            @foreach ($allRegistrations as $reg)
                                <div class="card draggable-player-template" data-player-id="{{ $reg->id }}">
                                    <div class="card-body p-2">
                                        {{ $reg->players[0]->name }} {{ $reg->players[0]->surname }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                    </div>

                    <!-- Right Column: Draws -->
                    <div class="col-md-6">
                        <h5>Draws</h5>
                        @forelse ($categoryEvent->draws as $draw)
                            <div class="card mb-3 dropzone" data-draw-id="{{ $draw->id }}">
                                <div class="card-header">{{ $draw->drawName }}
                                    ({{ $draw->drawFormat->name ?? 'Unknown' }})</div>
                                <div class="card-body">
                                    @forelse($draw->registrations as $reg)
                                        <div class="card mb-2 draggable-player" data-player-id="{{ $reg->id }}"
                                            data-draw-id="{{ $draw->id }}">
                                            <div class="card-body p-2">
                                                {{ $reg->players[0]->name }} {{ $reg->players[0]->surname }}
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-muted small">No players assigned yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        @empty<div class="alert alert-light border">No draws in this category yet.</div>@endforelse
                    </div>

                </div>
            </div>
            <div class="tab-pane fade {{ $errors->any() ? 'show active' : '' }}" id="settings-tab" role="tabpanel" aria-labelledby="settings-tab-link">
              <div class="row mt-3">

                {{-- Left side: Settings form --}}
                <div class="col-md-6">
                  <form id="draw-settings-form" method="POST" action="{{ route('draws.generate', $categoryEvent) }}">
                    @csrf
                    <input type="hidden" name="category_event_id" value="{{ $categoryEvent->id }}">
                    <p class="small text-muted">Create an unpublished draw in this category. Players, fixtures and publication are managed separately.</p>
                    <div class="mb-3">
                      <label for="drawName" class="form-label">Draw Name</label>
                      <input type="text" class="form-control" id="drawName" name="draw_name" required maxlength="255" value="{{ old('draw_name') }}">
                    </div>
                    <div class="mb-3">
                      <label for="drawType" class="form-label">Draw Type</label>
                      <select class="form-select" id="drawType" name="draw_type" required>
                        @foreach ($drawTypes as $drawType)
                          <option value="{{ $drawType->id }}" @selected((string) old('draw_type') === (string) $drawType->id)>{{ $drawType->drawTypeName }}</option>
                        @endforeach
                      </select>
                    </div>
                    <button type="submit" class="btn btn-primary" @disabled(!auth()->user()->can('draw.create', $categoryEvent->event))>Create unpublished draw</button>
                    @if(!auth()->user()->can('draw.create', $categoryEvent->event))<p class="small text-muted mt-2">You do not have permission to create draws for this event.</p>@endif
                  </form>
                </div>

                {{-- Right side: Live preview --}}
                <div class="col-md-6">
                  <div class="card shadow-sm">
                    <div class="card-header">
                      <strong>Live Preview</strong>
                    </div>
                    <div class="card-body" id="draw-preview">
                      <h5 id="preview-name">Draw Name Preview</h5>
                      <p><strong>Type:</strong> <span id="preview-type">-</span></p>
                    </div>
                  </div>
                </div>

              </div>
            </div>

        </div>
    </div>



@endsection
