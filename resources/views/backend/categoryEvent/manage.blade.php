@php
    $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

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


    </style>
    <meta name="app-url" content="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div id="action-toast" class="toast position-fixed bottom-0 end-0 m-3" role="alert" data-bs-delay="2000">
        <div class="toast-body bg-success text-white">Toast Message</div>
    </div>

    <!-- Blade: manage.blade.php -->
    <div class="container">
        <h3>Manage Category: {{ $categoryEvent->category->name }}</h3><a href="{{ url()->previous() }}" class="btn btn-secondary mb-3">← Back</a>


        <ul class="nav nav-tabs mb-3" id="categoryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="players-tab" data-bs-toggle="tab" data-bs-target="#players"
                    type="button" role="tab">Players</button>
            </li>
            @if($categoryEvent->isDoubles())
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pairs-tab" data-bs-toggle="tab" data-bs-target="#pairs-tab-pane"
                    type="button" role="tab">
                    <i class="ti ti-users me-1"></i>Pairs
                </button>
            </li>
            @endif
            <li class="nav-item">
              <a class="nav-link" id="settings-tab-link" data-bs-toggle="tab" href="#settings-tab" role="tab">Settings</a>
            </li>

        </ul>

        <div class="tab-content" id="categoryTabsContent">
            <div class="tab-pane fade show active" id="players" role="tabpanel">
                <div class="row">

                    <!-- Left Column: Eligible Players -->
                    <div class="col-md-6">
                        <h5>Eligible Players</h5>
                        <!-- Eligible Player List -->
                        <div id="eligible-player-list" class="dropzone border rounded p-3">
                            @forelse($eligibleRegistrations as $reg)
                                <div class="card mb-2 draggable-player" data-player-id="{{ $reg->id }}">
                                    <div class="card-body p-2">
                                        {{ $reg->players[0]->name }} {{ $reg->players[0]->surname }}
                                    </div>
                                </div>
                            @empty
                                <p class="text-muted">All players are assigned to draws.</p>
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
                        @foreach ($categoryEvent->draws as $draw)
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
                        @endforeach
                    </div>

                </div>
            </div>
            {{-- ======================================================= --}}
            {{-- PAIRS TAB (doubles categories only)                      --}}
            {{-- ======================================================= --}}
            @if($categoryEvent->isDoubles())
            <div class="tab-pane fade" id="pairs-tab-pane" role="tabpanel" aria-labelledby="pairs-tab">
                <div class="d-flex justify-content-between align-items-center mb-3 mt-2">
                    <div>
                        <h5 class="mb-0">Doubles Pairs</h5>
                        <small class="text-muted">Admin-created pairs for {{ $categoryEvent->category->name }}</small>
                    </div>
                    @unless($categoryEvent->isLocked())
                    <button class="btn btn-primary btn-sm" id="addPairBtn">
                        <i class="ti ti-plus me-1"></i>Add Pair
                    </button>
                    @endunless
                </div>

                {{-- Locked notice --}}
                @if($categoryEvent->isLocked())
                <div class="alert alert-warning py-2">
                    <i class="ti ti-lock me-1"></i> Category is locked. Pairs cannot be added or removed.
                </div>
                @endif

                {{-- Pairs table --}}
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="pairs-table">
                        <thead class="table-light">
                            <tr>
                                <th>Pair Name</th>
                                <th>Player 1</th>
                                <th>Player 2</th>
                                <th>Status</th>
                                <th>In Draw</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="pairs-tbody">
                            <tr id="pairs-loading-row">
                                <td colspan="6" class="text-center text-muted py-3">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Loading…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="tab-pane fade" id="settings-tab" role="tabpanel" aria-labelledby="settings-tab-link">

                {{-- Left side: Settings form --}}
                <div class="col-md-6">
                  <form id="draw-settings-form">
                    <div class="mb-3">
                      <label for="drawName" class="form-label">Draw Name</label>
                      <input type="text" class="form-control" id="drawName" name="draw_name">
                    </div>
                    <div class="mb-3">
                      <label for="drawType" class="form-label">Draw Type</label>
                      <select class="form-select" id="drawType" name="draw_type">
                        <option value="round_robin">Round Robin</option>
                        <option value="knockout">Knockout</option>
                        <option value="feed_in">Feed-In</option>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label for="numRounds" class="form-label">Number of Rounds</label>
                      <input type="number" class="form-control" id="numRounds" name="num_rounds" min="1" value="1">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
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
                      <p><strong>Rounds:</strong> <span id="preview-rounds">-</span></p>
                    </div>
                  </div>
                </div>

              </div>
            </div>

        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- CREATE PAIR MODAL                                                --}}
    {{-- ================================================================ --}}
    @if($categoryEvent->isDoubles())
    <div class="modal fade" id="createPairModal" tabindex="-1" aria-labelledby="createPairModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPairModalLabel">
                        <i class="ti ti-users me-1"></i>Add Doubles Pair
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="pair-form-error" class="alert alert-danger d-none"></div>
                    <div class="mb-3">
                        <label for="pair-player1" class="form-label fw-semibold">Player 1 <span class="text-danger">*</span></label>
                        <select id="pair-player1" class="form-select pair-player-select" name="player1_id">
                            <option value="">— Select Player 1 —</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="pair-player2" class="form-label fw-semibold">Player 2 <span class="text-danger">*</span></label>
                        <select id="pair-player2" class="form-select pair-player-select" name="player2_id">
                            <option value="">— Select Player 2 —</option>
                        </select>
                    </div>
                    <small class="text-muted">
                        Only players not yet paired in this category are shown.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="createPairConfirm">
                        <span id="createPairSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                        Create Pair
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const categoryEventId = {{ $categoryEvent->id }};
        const csrfToken       = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const routes = {
            pairs:           `/event/category/${categoryEventId}/pairs`,
            eligiblePlayers: `/event/category/${categoryEventId}/pairs/eligible-players`,
            pairDestroy:     (pairId) => `/event/category/${categoryEventId}/pairs/${pairId}`,
        };

        // ---- Utilities ------------------------------------------------

        function showError(msg) {
            const el = document.getElementById('pair-form-error');
            el.textContent = msg;
            el.classList.remove('d-none');
        }

        function hideError() {
            document.getElementById('pair-form-error').classList.add('d-none');
        }

        // ---- Build a table row HTML -----------------------------------

        function buildRow(pair) {
            const p1 = pair.players[0] ?? {};
            const p2 = pair.players[1] ?? {};
            const removeBtn = pair.can_remove
                ? `<button class="btn btn-sm btn-outline-danger remove-pair-btn"
                        data-pair-id="${pair.id}"
                        data-pair-name="${pair.pair_name}">
                        <i class="ti ti-trash me-1"></i>Remove
                   </button>`
                : `<span class="text-muted small">${pair.in_draw ? 'In draw' : 'Locked'}</span>`;

            return `<tr id="pair-row-${pair.id}">
                <td><strong>${pair.pair_name}</strong></td>
                <td>${p1.name ?? ''} ${p1.surname ?? ''}</td>
                <td>${p2.name ?? ''} ${p2.surname ?? ''}</td>
                <td><span class="badge bg-success">${pair.status}</span></td>
                <td>${pair.in_draw ? '<span class="badge bg-info">Yes</span>' : '<span class="text-muted">No</span>'}</td>
                <td>${removeBtn}</td>
            </tr>`;
        }

        // ---- Load pairs into table ------------------------------------

        function loadPairs() {
            fetch(routes.pairs, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    const tbody = document.getElementById('pairs-tbody');
                    if (!data.pairs || data.pairs.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No pairs yet.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.pairs.map(buildRow).join('');
                })
                .catch(() => {
                    document.getElementById('pairs-tbody').innerHTML =
                        '<tr><td colspan="6" class="text-danger text-center">Failed to load pairs.</td></tr>';
                });
        }

        // ---- Load eligible players into selects ----------------------

        function loadEligiblePlayers() {
            return fetch(routes.eligiblePlayers, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    const options = data.players.map(p =>
                        `<option value="${p.id}">${p.surname}, ${p.name}</option>`
                    ).join('');
                    ['pair-player1', 'pair-player2'].forEach(id => {
                        const sel = document.getElementById(id);
                        sel.innerHTML = `<option value="">— Select Player —</option>${options}`;
                    });
                });
        }

        // ---- Open Create Pair modal ----------------------------------

        document.addEventListener('click', function (e) {
            if (e.target && e.target.id === 'addPairBtn') {
                hideError();
                loadEligiblePlayers().then(() => {
                    new bootstrap.Modal(document.getElementById('createPairModal')).show();
                });
            }
        });

        // ---- Create Pair submit -------------------------------------

        document.getElementById('createPairConfirm')?.addEventListener('click', function () {
            hideError();
            const p1 = document.getElementById('pair-player1').value;
            const p2 = document.getElementById('pair-player2').value;

            if (!p1 || !p2) { showError('Please select both players.'); return; }
            if (p1 === p2)   { showError('Player 1 and Player 2 must be different.'); return; }

            const spinner = document.getElementById('createPairSpinner');
            spinner.classList.remove('d-none');
            this.disabled = true;

            fetch(routes.pairs, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ player1_id: p1, player2_id: p2 }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('createPairModal')).hide();
                    loadPairs();
                } else {
                    showError(data.message ?? 'Could not create pair.');
                }
            })
            .catch(() => showError('Network error. Please try again.'))
            .finally(() => {
                spinner.classList.add('d-none');
                this.disabled = false;
            });
        });

        // ---- Remove Pair -------------------------------------------

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-pair-btn');
            if (!btn) return;

            const pairId   = btn.dataset.pairId;
            const pairName = btn.dataset.pairName;

            if (!confirm(`Remove pair "${pairName}"?\nThis will withdraw their entry. This cannot be undone.`)) return;

            fetch(routes.pairDestroy(pairId), {
                method: 'DELETE',
                headers: {
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById(`pair-row-${pairId}`);
                    if (row) row.remove();
                    const tbody = document.getElementById('pairs-tbody');
                    if (!tbody.querySelector('tr')) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No pairs yet.</td></tr>';
                    }
                } else {
                    alert(data.message ?? 'Could not remove pair.');
                }
            })
            .catch(() => alert('Network error. Please try again.'));
        });

        // ---- Auto-load when tab is activated -----------------------

        document.getElementById('pairs-tab')?.addEventListener('shown.bs.tab', function () {
            loadPairs();
        });

    })();
    </script>
    @endif

@endsection
