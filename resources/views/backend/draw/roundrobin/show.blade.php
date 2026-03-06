@extends('layouts/contentNavbarLayout')

@section('title', 'Round Robin — ' . ($draw->name ?? 'Draw'))

@section('content')
<style>
  /* ==============================================
   BASE TABLE STYLE
   ============================================== */
  .rr-matrix-table {
    border-collapse: collapse !important;
    table-layout: fixed !important;
    background: #ffffff !important;
    width: max-content !important; /* important: allow full name width */
  }

  /* Matrix must scroll */
  .rr-matrix-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    width: 100%;
    padding-bottom: 5px;
  }

  /* ----------------------------------------------
   Smaller boxes: 32 × 32
   ---------------------------------------------- */
  .rr-matrix-table td.rr-score-cell,
  .rr-matrix-table td {
    padding: 0 !important;
    height: 32px !important;
    width: 32px !important;
    min-width: 32px !important;
    max-width: 32px !important;
    text-align: center;
    vertical-align: middle;
    border: 1px solid #dcdcdc !important;
    font-size: 12px !important;
    background: #ffffff !important;
  }

    /* ==============================================
   DIAGONAL BLACK
   ============================================== */
    .rr-matrix-table td.bg-light {
      background: #000 !important;
      border: 1px solid #fff !important;
    }

  /* ==============================================
   HEADER — allow full name width
   ============================================== */
  .rr-matrix-table thead th {
    padding: 6px 10px !important;
    background: #0a3566 !important;
    color: #fff !important;
    font-weight: 600;
    font-size: 12px !important;
    white-space: nowrap !important; /* important */
    width:200px;
  }

  /* ==============================================
   LEFT NAMES — allow full width
   ============================================== */
  .rr-matrix-table tbody th {
    background: #0b722e !important;
    color: #fff !important;
    font-weight: 600;
    font-size: 13px !important;
    padding: 6px 12px !important;
    white-space: nowrap !important; /* important */
  }

  /* ==============================================
   SCORE COLORS
   ============================================== */
  .rr-matrix-table .rr-win {
    color: #00a859 !important;
    font-weight: bold;
  }

  .rr-matrix-table .rr-loss {
    color: #d32f2f !important;
    font-weight: bold;
  }

  /* ==============================================
   SORTABLE / DRAG-DROP STYLES
   ============================================== */
  .rr-sortable {
    min-height: 50px;
    border: 2px dashed transparent;
    border-radius: 4px;
    transition: all 0.2s ease;
  }

  .rr-sortable.drop-zone-active {
    border-color: #0d6efd;
    background: rgba(13, 110, 253, 0.05);
  }

  .rr-sortable.sortable-chosen {
    border-color: #0d6efd;
    background: #e7f1ff;
  }

  .rr-sortable .list-group-item {
    cursor: grab !important;
    transition: all 0.2s ease;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    touch-action: none;
  }
  
  .rr-sortable .list-group-item:active {
    cursor: grabbing !important;
  }

  .rr-sortable .list-group-item:hover {
    background: #f8f9fa;
    transform: translateX(2px);
  }

  .rr-sortable .list-group-item.sortable-ghost {
    opacity: 0.3;
    background: #e3f2fd !important;
    border: 2px dashed #2196f3 !important;
  }
  
  .rr-sortable .list-group-item.sortable-chosen {
    background: #e7f1ff !important;
    border-color: #0d6efd !important;
    box-shadow: 0 2px 8px rgba(13, 110, 253, 0.3);
  }
  
  .rr-sortable .list-group-item.sortable-drag {
    background: #fff !important;
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    opacity: 0.9 !important;
    transform: rotate(2deg);
  }
  
  /* Ensure fallback clone is visible */
  .sortable-fallback {
    opacity: 0.8 !important;
    background: #fff !important;
    box-shadow: 0 6px 16px rgba(0,0,0,0.25) !important;
  }

  .rr-group {
    border: 2px dashed #dee2e6;
    transition: all 0.2s ease;
  }

  .rr-group.drop-zone-active {
    border-color: #198754;
    background: rgba(25, 135, 84, 0.05);
  }

  .rr-group:empty::after {
    content: 'Drop players here';
    display: block;
    text-align: center;
    color: #adb5bd;
    padding: 20px;
    font-size: 12px;
  }

  /* ==============================================
   BRACKET VISUALIZATION STYLES
   ============================================== */
  .bracket-container {
    display: inline-block;
    padding: 15px;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    margin: 10px;
    min-width: 200px;
  }
  
  .bracket-round {
    display: inline-block;
    vertical-align: top;
  }
  
  .bracket-matchup {
    margin: 8px 0;
    position: relative;
    background: white;
    border-radius: 6px;
    padding: 8px;
    border: 1px solid #dee2e6;
  }
  
  .bracket-seed {
    background: white;
    border: 2px solid #0d6efd;
    border-radius: 4px;
    padding: 6px 10px;
    margin: 1px 0;
    min-width: 160px;
    font-size: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .bracket-seed.winner {
    background: #d1ecf1;
    border-color: #0dcaf0;
  }
  
  .bracket-seed-num {
    font-weight: bold;
    color: #0d6efd;
    min-width: 35px;
    font-size: 13px;
  }
  
  .bracket-seed-source {
    font-size: 12px;
    color: #fff;
    background: #198754;
    padding: 2px 8px;
    border-radius: 3px;
    font-weight: bold;
  }
  
  .bracket-connector {
    position: absolute;
    right: -20px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 2px;
    background: #dee2e6;
  }





</style>


<div id="round-robin-app" 
   data-draw-id="{{ $draw->id }}">

{{-- ============================
     DRAW NAVIGATOR / SELECTOR
   ============================ --}}
<div class="card mb-3 border-primary">
  <div class="card-body py-2">
    <div class="d-flex justify-content-between align-items-center">
        
      {{-- Current Draw Info --}}
      <div>
        <h5 class="mb-0">
          <i class="ti ti-tournament me-2 text-primary"></i>
          <strong>{{ $draw->drawName ?? 'Unnamed Draw' }}</strong>
        </h5>
        <small class="text-muted">
          {{ $draw->category->name ?? 'No Category' }} 
          @ {{ $draw->event->name ?? 'Unknown Event' }}
          <span class="badge bg-label-info ms-2">Draw ID: {{ $draw->id }}</span>
        </small>
      </div>

      {{-- Draw Switcher Dropdown --}}
      <div class="d-flex align-items-center gap-2">
        @php
          $eventDraws = $draw->event->draws ?? collect();
        @endphp
          
        @if($eventDraws->count() > 1)
          <div class="dropdown">
            <button class="btn btn-outline-primary btn-sm dropdown-toggle" 
                    type="button" 
                    data-bs-toggle="dropdown">
              <i class="ti ti-switch-horizontal me-1"></i>
              Switch Draw ({{ $eventDraws->count() }} total)
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><h6 class="dropdown-header">Event Draws</h6></li>
              @foreach($eventDraws as $eventDraw)
                <li>
                  <a class="dropdown-item {{ $eventDraw->id == $draw->id ? 'active' : '' }}" 
                     href="{{ route('backend.draw.roundrobin.show', $eventDraw->id) }}">
                    @if($eventDraw->id == $draw->id)
                      <i class="ti ti-check me-1"></i>
                    @endif
                    {{ $eventDraw->drawName ?? 'Draw #' . $eventDraw->id }}
                    <small class="text-muted ms-2">({{ $eventDraw->groups->count() ?? 0 }} groups)</small>
                  </a>
                </li>
              @endforeach
            </ul>
          </div>
        @endif

        <a href="{{ route('headOffice.show', $draw->event_id) }}" 
           class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i> Back to Event
        </a>
      </div>

    </div>
  </div>
</div>

{{-- ============================
     TAB NAVIGATION
   ============================ --}}
 <ul class="nav nav-tabs mb-3" id="rrTabs" role="tablist">

  <li class="nav-item" role="presentation">
    <button class="nav-link active"
            id="matrix-tab"
            data-bs-toggle="tab"
            data-bs-target="#matrix-pane"
            type="button" role="tab">
      Matrix
    </button>
  </li>

  <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="oop-tab"
            data-bs-toggle="tab"
            data-bs-target="#oop-pane"
            type="button" role="tab">
      Order of Play
    </button>
  </li>

 

  <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="standings-tab"
            data-bs-toggle="tab"
            data-bs-target="#standings-pane"
            type="button" role="tab">
      Standings
    </button>
  </li>
   <li class="nav-item" role="presentation">
    <button class="nav-link"
            id="main-bracket-tab"
            data-bs-toggle="tab"
            data-bs-target="#main-bracket-pane"
            type="button"
            role="tab">
      Main Bracket
    </button>
</li>
<li class="nav-item" role="presentation">
  <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab">
    Settings
  </button>
</li>
<li class="nav-item" role="presentation">
  <button class="nav-link"
          id="groups-tab"
          data-bs-toggle="tab"
          data-bs-target="#groups-pane"
          type="button" role="tab">
    Players & Groups
  </button>
</li>

</ul>

  {{-- ============================
       TAB CONTENT
     ============================ --}}
  <div class="tab-content" id="rrTabsContent">

    {{-- SETTINGS TAB --}}
    <div class="tab-pane fade" id="settings-pane" role="tabpanel">
      
      {{-- DRAW OVERVIEW --}}
      <div class="card mb-3 border-info">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="ti ti-info-circle me-1"></i> Draw Overview</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            {{-- Total Players --}}
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-primary">
                  @php
                    $totalPlayers = $groups->sum(function($group) {
                      return $group->registrations->count();
                    });
                  @endphp
                  {{ $totalPlayers }}
                </h3>
                <small class="text-muted fw-bold">Total Players</small>
              </div>
            </div>
            
            {{-- Groups --}}
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-success">{{ $groups->count() }}</h3>
                <small class="text-muted fw-bold">Groups</small>
              </div>
            </div>
            
            {{-- Draw Type --}}
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-warning">
                  <i class="ti ti-tournament"></i>
                </h3>
                <small class="text-muted fw-bold">Round Robin</small>
              </div>
            </div>
            
            {{-- Total Matches --}}
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-danger">
                  @php
                    $totalMatches = 0;
                    foreach($groups as $group) {
                      $playersInGroup = $group->registrations->count();
                      if ($playersInGroup > 1) {
                        $totalMatches += ($playersInGroup * ($playersInGroup - 1)) / 2;
                      }
                    }
                  @endphp
                  {{ $totalMatches }}
                </h3>
                <small class="text-muted fw-bold">Total Matches</small>
              </div>
            </div>
          </div>
          
          {{-- Group Breakdown --}}
          <div class="mt-3">
            <h6 class="fw-bold mb-2">Group Distribution:</h6>
            <div class="row g-2">
              @foreach($groups as $group)
                <div class="col-auto">
                  <span class="badge 
                    @if($group->name == 'A') bg-primary
                    @elseif($group->name == 'B') bg-success
                    @elseif($group->name == 'C') bg-warning
                    @elseif($group->name == 'D') bg-danger
                    @else bg-dark
                    @endif">
                    Group {{ $group->name }}: {{ $group->registrations->count() }} players
                  </span>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      {{-- BASIC SETTINGS --}}
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-settings me-1"></i> Basic Settings</h5>
        </div>
        <div class="card-body">
          <form id="drawSettingsForm">
            @csrf

            <div class="row g-3">
              {{-- Number of Groups --}}
              <div class="col-md-3">
                <label class="form-label fw-bold">Number of Groups</label>
                <select name="boxes" id="settings-boxes" class="form-select">
                  @php
                    $currentBoxes = optional($draw->settings)->boxes ?? $groups->count();
                  @endphp
                  @foreach(range(1,8) as $n)
                    <option value="{{ $n }}" {{ $currentBoxes == $n ? 'selected' : '' }}>
                      {{ $n }} Group{{ $n > 1 ? 's' : '' }}
                    </option>
                  @endforeach
                </select>
                <small class="text-muted">Current: {{ $groups->count() }} groups</small>
              </div>

              {{-- Number of Sets --}}
              <div class="col-md-3">
                <label class="form-label fw-bold">Sets per Match</label>
                <select name="num_sets" class="form-select">
                  @php $currentSets = optional($draw->settings)->num_sets ?? 3; @endphp
                  @foreach([1, 2, 3, 5] as $n)
                    <option value="{{ $n }}" {{ $currentSets == $n ? 'selected' : '' }}>
                      Best of {{ $n }}
                    </option>
                  @endforeach
                </select>
              </div>

              {{-- Save Button --}}
              <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary" id="btn-save-settings">
                  <i class="ti ti-device-floppy me-1"></i> Save Settings
                </button>
              </div>
            </div>

            <div class="alert alert-warning mt-3 mb-0">
              <i class="ti ti-alert-triangle me-1"></i>
              <strong>Note:</strong> Changing the number of groups will recreate all groups. 
              All players currently in groups will be moved to <strong>Group A</strong>.
            </div>

          </form>
        </div>
      </div>

      {{-- PLAYOFF CONFIGURATION --}}
      <div class="card mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ti ti-tournament me-1"></i> Playoff Configuration</h5>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-playoff">
            <i class="ti ti-plus"></i> Add Playoff
          </button>
        </div>
        <div class="card-body">
          @php
            $playoffConfig = optional($draw->settings)->playoff_config ?? \App\Models\DrawSetting::defaultPlayoffConfig($currentBoxes);
            $presetTemplates = \App\Models\DrawSetting::getPresetTemplates();
            $savedPresetKey = optional($draw->settings)->preset_key; // Get saved preset key
            
            // Group templates by number of groups BUT keep original keys
            $groupedTemplates = [];
            foreach ($presetTemplates as $key => $template) {
                $numGroups = $template['groups'] ?? 1;
                if (!isset($groupedTemplates[$numGroups])) {
                    $groupedTemplates[$numGroups] = [];
                }
                $groupedTemplates[$numGroups][$key] = $template; // Keep original key!
            }
            ksort($groupedTemplates); // Sort by number of groups
          @endphp

          {{-- PRESET SELECTOR --}}
          <div class="row mb-4">
            <div class="col-md-8">
              <label class="form-label fw-bold"><i class="ti ti-template me-1"></i> Quick Setup - Load Preset Template</label>
              <div class="input-group">
                <select class="form-select" id="preset-selector">
                  <option value="">-- Select a preset template --</option>
                  @php
                    // Only show presets matching current number of groups
                    $currentGroupTemplates = $groupedTemplates[$currentBoxes] ?? [];
                  @endphp
                  @if(count($currentGroupTemplates) > 0)
                    <optgroup label="{{ $currentBoxes }} Group{{ $currentBoxes > 1 ? 's' : '' }}">
                      @foreach($currentGroupTemplates as $key => $preset)
                        <option value="{{ $key }}" 
                                data-config='@json($preset['config'])'
                                data-groups="{{ $preset['groups'] ?? 4 }}"
                                data-max-positions="{{ $preset['max_positions'] ?? 10 }}"
                                {{ $savedPresetKey === $key ? 'selected' : '' }}>
                          {{ $preset['name'] }}
                        </option>
                      @endforeach
                    </optgroup>
                  @else
                    <option value="" disabled>No presets available for {{ $currentBoxes }} group{{ $currentBoxes > 1 ? 's' : '' }}</option>
                  @endif
                </select>
                <button type="button" class="btn btn-success" id="btn-load-preset">
                  <i class="ti ti-download me-1"></i> Load
                </button>
              </div>
              <small class="text-muted">
                Showing presets for {{ $currentBoxes }} group{{ $currentBoxes > 1 ? 's' : '' }}. Change group count in Basic Settings to see other presets.
                @if($savedPresetKey)
                  <br><span class="badge bg-success mt-1">
                    <i class="ti ti-check me-1"></i> Currently using: {{ $presetTemplates[$savedPresetKey]['name'] ?? $savedPresetKey }}
                  </span>
                @endif
              </small>
            </div>
          </div>

          <hr class="my-3">

          <div class="table-responsive">
            <table class="table table-sm table-hover" id="playoff-config-table">
              <thead class="table-light">
                <tr>
                  <th>Enabled</th>
                  <th>Playoff Name</th>
                  <th>Size</th>
                  <th>Group Positions</th>
                  <th>Preview</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="playoff-config-body">
                @foreach($playoffConfig as $idx => $playoff)
                <tr data-idx="{{ $idx }}">
                  <td>
                    <div class="form-check form-switch">
                      @php
                        // Only check if playoff is explicitly enabled AND has positions configured
                        $hasPositions = !empty($playoff['positions']);
                        $isEnabled = ($playoff['enabled'] ?? false) && $hasPositions;
                      @endphp
                      <input class="form-check-input playoff-enabled" type="checkbox" 
                             {{ $isEnabled ? 'checked' : '' }}
                             data-idx="{{ $idx }}">
                    </div>
                  </td>
                  <td>
                    <input type="text" class="form-control form-control-sm playoff-name" 
                           value="{{ $playoff['name'] }}" data-idx="{{ $idx }}" style="min-width: 150px;">
                  </td>
                  <td>
                    <select class="form-select form-select-sm playoff-size" data-idx="{{ $idx }}" style="width: 80px;">
                      @foreach([2, 4, 8, 16, 32] as $size)
                        <option value="{{ $size }}" {{ ($playoff['size'] ?? 4) == $size ? 'selected' : '' }}>
                          {{ $size }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <div class="d-flex flex-wrap gap-1">
                      @php $positions = $playoff['positions'] ?? []; @endphp
                      @foreach(range(1, 10) as $pos)
                        <button type="button" 
                                class="btn btn-sm position-btn {{ in_array($pos, $positions) ? 'btn-primary' : 'btn-outline-secondary' }}"
                                data-idx="{{ $idx }}" 
                                data-pos="{{ $pos }}"
                                title="Position #{{ $pos }} from each group">
                          #{{ $pos }}
                        </button>
                      @endforeach
                    </div>
                    <small class="text-muted">Click to toggle positions</small>
                  </td>
                  <td>
                    <small class="text-muted playoff-preview" data-idx="{{ $idx }}">
                      @php
                        $posCount = count($positions);
                        $totalPlayers = $posCount * $currentBoxes;
                      @endphp
                      {{ $totalPlayers }} players
                    </small>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-playoff" data-idx="{{ $idx }}">
                      <i class="ti ti-trash"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
              <small class="text-muted">
                <i class="ti ti-info-circle"></i>
                Click position buttons to toggle which group positions feed into each playoff draw.
                Example: If #1 and #2 are selected with 4 groups, 8 players will enter that playoff.
              </small>
            </div>
            <button type="button" class="btn btn-success" id="btn-save-playoff-config">
              <i class="ti ti-device-floppy me-1"></i> Save Playoff Config
            </button>
          </div>

        </div>
      </div>

      {{-- PLAYER ACCOUNTING --}}
      <div class="card mb-3 border-info">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="ti ti-users me-1"></i> Player Accounting & Validation</h5>
          <small>Verify all players are accommodated in playoff draws</small>
        </div>
        <div class="card-body">
          <div id="player-accounting">
            {{-- Will be populated by JS --}}
            <div class="text-muted">Loading player accounting...</div>
          </div>
        </div>
      </div>

      {{-- VISUAL MAPPING --}}
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-git-branch me-1"></i> Player Flow Preview</h5>
        </div>
        <div class="card-body">
          <div id="playoff-flow-preview" class="d-flex flex-wrap gap-3">
            {{-- Will be populated by JS --}}
            <div class="text-muted">Configure playoff draws above to see the flow preview.</div>
          </div>
        </div>
      </div>

      {{-- DETAILED SEEDING CHART --}}
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-map-pin me-1"></i> Detailed Seeding Chart</h5>
          <small class="text-muted">See exactly where each player position from each group goes</small>
        </div>
        <div class="card-body">
          <div id="playoff-seeding-chart">
            {{-- Will be populated by JS --}}
            <div class="text-muted">Configure playoff draws above to see detailed seeding.</div>
          </div>
        </div>
      </div>

      {{-- COMPLETE SEEDING MATRIX --}}
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-table me-1"></i> Complete Seeding Matrix</h5>
          <small class="text-muted">All positions from all groups with their seed numbers</small>
        </div>
        <div class="card-body">
          <div id="complete-seeding-matrix">
            {{-- Will be populated by JS --}}
            <div class="text-muted">Configure playoff draws above to see complete seeding matrix.</div>
          </div>
        </div>
      </div>

      {{-- BRACKET VISUALIZATION --}}
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-tournament me-1"></i> Bracket Seed Positions</h5>
          <small class="text-muted">Visual representation of where each seed is placed in brackets</small>
        </div>
        <div class="card-body">
          <div id="bracket-visualization">
            {{-- Will be populated by JS --}}
            <div class="text-muted">Configure playoff draws above to see bracket structure.</div>
          </div>
        </div>
      </div>

    </div>

    {{-- ============================
         TAB 1 — MATRIX + STANDINGS
       ============================ --}}
    <div class="tab-pane fade show active" 
         id="matrix-pane" 
         role="tabpanel">
      <div class="row"> 
        <div class="col-12">
        <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Round Robin Matrix</h5>
          <small class="text-muted">Who plays who + results</small>
        </div>
        <div class="card-body p-0">
          <div id="rr-matrix-wrapper" class="p-2">
            <div class="text-center text-muted py-5" id="rr-matrix-loading">
              <div class="spinner-border spinner-border-sm"></div>
              <div class="mt-2">Loading round-robin grid…</div>
            </div>
          </div>
        </div>
      </div></div>
        
      </div>
    

    

    </div>

    {{-- ============================
         TAB 2 — ORDER OF PLAY
       ============================ --}}
    <div class="tab-pane fade" 
         id="oop-pane" 
         role="tabpanel">
       <div class="col-12">
            <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0">Order of Play</h5>
          <button class="btn btn-sm btn-primary" id="rr-save-order-btn">
            <i class="ti ti-device-floppy"></i> Save Order
          </button>
        </div>
        <div class="card-body p-0">
         <table class="table table-sm table-hover mb-0" id="rr-order-table">
    <thead class="table-light">
        <tr>
            <th>ID</th>
            <th>Player 1</th>
            <th class="text-center">VS</th>
            <th>Player 2</th>
            <th class="text-center">Round</th>
            <th class="text-center">Time</th>
            <th class="text-center">Score</th>
            <th class="text-center">Actions</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

        </div>
      </div>
        </div>
     
    </div>

    {{-- ============================
         TAB 3 — SCORES
       ============================ --}}
    <div class="tab-pane fade" 
         id="scores-pane" 
         role="tabpanel">

      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">Scores</h5>
          <small class="text-muted">Select match from OOP tab</small>
        </div>

        <div class="card-body">
          <form id="rr-score-form">
            @csrf
            <input type="hidden" name="fixture_id" id="rr-score-fixture-id">

            <div class="mb-2">
              <label class="form-label">Match</label>
              <div id="rr-score-match-label" class="fw-bold small text-muted">
                Select a match from Order of Play…
              </div>
            </div>

            <div class="row g-2 align-items-center">
              <div class="col-5">
                <label class="form-label small mb-1">Home score</label>
                <input type="text" class="form-control form-control-sm"
                       name="home_score" id="rr-home-score"
                       placeholder="6-4 6-3">
              </div>
              <div class="col-5">
                <label class="form-label small mb-1">Away score</label>
                <input type="text" class="form-control form-control-sm"
                       name="away_score" id="rr-away-score"
                       placeholder="4-6 3-6">
              </div>
              <div class="col-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-success w-100">
                  <i class="ti ti-device-floppy"></i>
                </button>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>
  {{-- ============================
     TAB 4 — STANDINGS
   ============================ --}}
<div class="tab-pane fade" id="standings-pane" role="tabpanel">

  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0">Standings</h5>
    </div>

    <div class="card-body">
      <div id="rr-standings-wrapper">
        <div class="text-center text-muted py-4" id="rr-standings-loading">
          <div class="spinner-border spinner-border-sm"></div>
          <div class="mt-2">Loading standings…</div>
        </div>
      </div>
    </div>
  </div>

</div>


 <div class="tab-pane fade" id="groups-pane" role="tabpanel">

  {{-- Header with Draw Info --}}
    <div class="alert alert-primary d-flex justify-content-between align-items-center mb-3">
    <div>
      <i class="ti ti-info-circle me-1"></i>
      <strong>Assigning players to:</strong> 
      <span class="badge bg-primary ms-1">{{ $draw->drawName ?? 'Unnamed Draw' }}</span>
      <span class="text-muted ms-2" id="groups-count-label">| {{ $groups->count() }} Groups</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      {{-- Number of Groups Selector --}}
      <div class="d-flex align-items-center">
        <label class="form-label mb-0 me-2 text-nowrap fw-bold">Groups:</label>
        <select id="groups-tab-boxes" class="form-select form-select-sm" style="width: 90px;">
          @foreach(range(1,8) as $n)
            <option value="{{ $n }}" {{ $currentBoxes == $n ? 'selected' : '' }}>
              {{ $n }} {{ $n > 1 ? 'Groups' : 'Group' }}
            </option>
          @endforeach
        </select>
      </div>
      
      @if(optional($draw->event)->isTeam())
        <button class="btn btn-sm btn-outline-primary" id="btn-import-teams">
          <i class="ti ti-upload"></i> Import from Teams
        </button>
      @endif
      <button class="btn btn-sm btn-success" id="btn-regenerate-fixtures">
        <i class="ti ti-refresh"></i> Regenerate Fixtures
      </button>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
      <h5 class="mb-0">
        <i class="ti ti-users me-1"></i> Assign Players to Groups
      </h5>
      <button class="btn btn-sm btn-primary" id="btn-save-groups">
        <i class="ti ti-device-floppy"></i> Save Group Assignments
      </button>
    </div>

    <div class="card-body">
      <div class="row">

        {{-- LEFT SIDE: Available Players from Categories --}}
        <div class="col-md-4">
          <div class="card border">
            <div class="card-header bg-secondary text-white py-2">
              <h6 class="mb-0"><i class="ti ti-list me-1"></i> Available Players</h6>
              <small>Drag players to groups on the right</small>
            </div>
            <div class="card-body p-2" style="max-height: 500px; overflow-y: auto;">

              @php
                // If draw is linked to a specific category event, only show that category's registrations.
                $sourceCategoryEvents = $categoryEvents;
                if ($draw->category_event_id) {
                  $sourceCategoryEvents = $categoryEvents->where('id', $draw->category_event_id);
                }
              @endphp

              @forelse($sourceCategoryEvents as $ce)
                <div class="mb-3">
                  <div class="fw-bold text-primary small mb-1">
                    <i class="ti ti-category me-1"></i> {{ $ce->category->name ?? 'Unknown Category' }}
                    <span class="badge bg-secondary">{{ $ce->registrations->count() }}</span>
                  </div>

                  <ul class="list-group list-group-flush rr-sortable" 
                      data-category-event-id="{{ $ce->id }}"
                      data-type="source">
                    @foreach($ce->registrations as $reg)
                      @php
                        $player = $reg->players->first();
                        $display = $player ? $player->full_name : 'Unknown Player';
                        // Check if already assigned to a group in this draw
                        $isAssigned = $groups->contains(fn($g) => $g->registrations->contains('id', $reg->id));
                      @endphp

                      @if(!$isAssigned)
                        <li class="list-group-item list-group-item-action py-1 px-2" 
                            data-id="{{ $reg->id }}"
                            data-player-name="{{ $display }}">
                          <small>{{ $display }}</small>
                        </li>
                      @endif
                    @endforeach
                  </ul>
                </div>
              @empty
                <div class="text-muted text-center py-4">
                  <i class="ti ti-info-circle fs-3 d-block mb-2"></i>
                  No categories found for this event.
                  <br><small>Use "Import from Teams" or add categories first.</small>
                </div>
              @endforelse

            </div>
          </div>
        </div>

        {{-- RIGHT SIDE: Draw Groups (A, B, C, D) --}}
        <div class="col-md-8">
          <div class="row">

            @forelse($groups as $group)
              <div class="col-6 mb-3">
                <div class="card border h-100">
                  <div class="card-header py-2 
                    @if($group->name == 'A') bg-primary text-white
                    @elseif($group->name == 'B') bg-success text-white
                    @elseif($group->name == 'C') bg-warning text-dark
                    @elseif($group->name == 'D') bg-danger text-white
                    @else bg-dark text-white
                    @endif">
                    <h6 class="mb-0">
                      <i class="ti ti-users-group me-1"></i> Group {{ $group->name }}
                      <span class="badge bg-light text-dark float-end">
                        {{ $group->registrations->count() }} players
                      </span>
                    </h6>
                  </div>
                  <div class="card-body p-2" style="min-height: 150px;">
                    <ul class="list-group list-group-flush rr-sortable rr-group"
                        data-group-id="{{ $group->id }}"
                        data-type="target">

                      @foreach($group->registrations as $reg)
                        @php
                          $player = $reg->players->first();
                          $display = $player ? $player->full_name : 'Unknown Player';
                        @endphp

                        <li class="list-group-item list-group-item-action py-1 px-2" 
                            data-id="{{ $reg->id }}"
                            data-player-name="{{ $display }}">
                          <small>{{ $display }}</small>
                          <button type="button" class="btn btn-sm btn-link text-danger float-end p-0 btn-remove-from-group" 
                                  data-id="{{ $reg->id }}">
                            <i class="ti ti-x"></i>
                          </button>
                        </li>
                      @endforeach

                    </ul>
                    @if($group->registrations->isEmpty())
                      <div class="text-muted text-center py-3 empty-group-placeholder">
                        <small>Drop players here</small>
                      </div>
                    @endif
                  </div>
                </div>
              </div>
            @empty
              <div class="col-12">
                <div class="alert alert-warning">
                  <i class="ti ti-alert-triangle me-1"></i>
                  No groups found for this draw. Groups should be auto-created.
                </div>
              </div>
            @endforelse

          </div>
        </div>

      </div>
    </div>

    <div class="card-footer bg-light">
      <small class="text-muted">
        <i class="ti ti-info-circle me-1"></i>
        Drag players from categories on the left into groups on the right. 
        Click "Save Group Assignments" to persist changes, then "Regenerate Fixtures" to create round-robin matches.
      </small>
    </div>
  </div>

</div>




   <!-- =========================================
     Brackets
========================================= -->

  <div class="tab-pane fade" id="main-bracket-pane" role="tabpanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="ti ti-tournament me-1"></i> Playoff Brackets</h5>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-success" id="btn-generate-main-bracket">
              <i class="ti ti-refresh me-1"></i> Generate All Playoffs
          </button>
        </div>
    </div>

    {{-- Main Bracket Container (loaded via AJAX) --}}
    <div id="main-bracket-wrapper">
      <div class="text-center text-muted py-5">
        <div class="spinner-border spinner-border-sm"></div>
        <div class="mt-2">Loading playoff brackets…</div>
      </div>
    </div>
   
</div>

  </div> {{-- END TABS --}}
</div> {{-- END APP --}}
<!-- =========================================
      SCORE ENTRY MODAL
========================================= -->
<div class="modal fade" id="rrScoreModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="rr-score-modal-form" class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="rrm-match-label">Enter Score</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <input type="hidden" id="rrm-fixture-id">

        <label class="form-label fw-bold mb-2">Set Scores</label>

        <!-- SET 1 -->
        <div class="row g-2 mb-2">
          <div class="col-12 fw-bold">Set 1</div>
          <div class="col-6">
            <label class="form-label"><span id="set1-p1-label">Player 1</span></label>
            <input type="number" min="0" class="form-control" id="set1-p1">
          </div>
          <div class="col-6">
            <label class="form-label"><span id="set1-p2-label">Player 2</span></label>
            <input type="number" min="0" class="form-control" id="set1-p2">
          </div>
        </div>

        <!-- SET 2 -->
        <div class="row g-2 mb-2">
          <div class="col-12 fw-bold">Set 2</div>
          <div class="col-6">
            <label class="form-label"><span id="set2-p1-label">Player 1</span></label>
            <input type="number" min="0" class="form-control" id="set2-p1">
          </div>
          <div class="col-6">
            <label class="form-label"><span id="set2-p2-label">Player 2</span></label>
            <input type="number" min="0" class="form-control" id="set2-p2">
          </div>
        </div>

        <!-- SET 3 -->
        <div class="row g-2 mb-2">
          <div class="col-12 fw-bold">Set 3</div>
          <div class="col-6">
            <label class="form-label"><span id="set3-p1-label">Player 1</span></label>
            <input type="number" min="0" class="form-control" id="set3-p1">
          </div>
          <div class="col-6">
            <label class="form-label"><span id="set3-p2-label">Player 2</span></label>
            <input type="number" min="0" class="form-control" id="set3-p2">
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save Score</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
      </div>

    </form>
  </div>
</div>




@endsection



@section('page-script')

<script>
    window.RR_FIXTURES  = @json($rrFixtures);
    window.RR_GROUPS    = @json($groupsjson);   // THE ONLY CORRECT ONE
    window.RR_OOP       = @json($oops);
    window.RR_STANDINGS = @json($standings);

    window.RR_SAVE_SCORE_URL = "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}";

    window.EVENT_ID = {{ $draw->event_id }};
    const DRAW_ID   = {{ $draw->id }};
</script>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<!-- ADD THIS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Save Draw Settings (AJAX)
$('#drawSettingsForm').on('submit', function(e) {
    e.preventDefault();
    
    const $btn = $('#btn-save-settings');
    const oldText = $btn.html();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
    
    $.ajax({
        url: `${APP_URL}/backend/draw/${DRAW_ID}/settings`,
        method: 'POST',
        data: $(this).serialize(),
        success: function(response) {
            if (response.success) {
                toastr.success(response.message || 'Settings saved successfully!');
                
                // Reload page to show new groups
                setTimeout(() => location.reload(), 800);
            } else {
                toastr.error(response.message || 'Failed to save settings.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error saving settings.');
        },
        complete: function() {
            $btn.prop('disabled', false).html(oldText);
        }
    });
});

// ============================================================
// PLAYOFF CONFIGURATION HANDLERS
// ============================================================

// Store playoff config in memory
let playoffConfig = @json($playoffConfig ?? []);
let numGroups = {{ $currentBoxes ?? 4 }};
let maxPositions = 10; // Default, will be updated when template is loaded

// Initialize saved preset key on page load
window.currentPresetKey = '{{ $savedPresetKey ?? '' }}';

// Debug: Log all available preset keys
console.log('📋 [INIT] Available preset keys in dropdown:');
$('#preset-selector option[value!=""]').each(function() {
    console.log('  -', $(this).val(), ':', $(this).text());
});

// If a preset is saved, try to load its maxPositions
if (window.currentPresetKey) {
    console.log('🔍 [INIT] Looking for saved preset:', window.currentPresetKey);
    const $savedOption = $('#preset-selector option[value="' + window.currentPresetKey + '"]');
    if ($savedOption.length > 0) {
        const savedMaxPos = parseInt($savedOption.data('max-positions')) || 10;
        maxPositions = savedMaxPos;
        console.log('✅ [INIT] Loaded saved preset:', window.currentPresetKey, '| maxPositions:', maxPositions);
    } else {
        // Preset key doesn't match any dropdown option (old/invalid data)
        console.warn('⚠️ [INIT] Saved preset key not found in dropdown:', window.currentPresetKey);
        console.warn('⚠️ [INIT] Available keys are listed above. Please update database or clear preset_key.');
        window.currentPresetKey = ''; // Clear invalid key
    }
}

// Toggle position button
$(document).on('click', '.position-btn', function() {
    const $btn = $(this);
    const idx = $btn.data('idx');
    const pos = $btn.data('pos');
    
    // Get actual player counts
    const groupPlayerCounts = @json($groups->map(function($g) { return $g->registrations->count(); })->toArray());
    const maxPlayersInGroup = groupPlayerCounts.length > 0 ? Math.max(...groupPlayerCounts) : 0;
    
    // Check if position exists in ANY group
    const groupsWithThisPosition = groupPlayerCounts.filter(count => count >= pos).length;
    
    // Validate position selection - only block if position doesn't exist in ANY group
    if (pos > maxPlayersInGroup && !$btn.hasClass('btn-primary')) {
        // Position doesn't exist in any group
        Swal.fire({
            title: 'Position Not Available!',
            html: `<p>Position <strong>#${pos}</strong> doesn't exist in any group.</p>
                   <p class="text-danger">Largest group has only <strong>${maxPlayersInGroup} players</strong>.</p>
                   <p>You can only select positions <strong>#1 to #${maxPlayersInGroup}</strong>.</p>`,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
        return; // Prevent selection
    }
    
    // Show warning if position doesn't exist in all groups (but allow selection)
    if (groupsWithThisPosition < numGroups && !$btn.hasClass('btn-primary')) {
        Swal.fire({
            title: 'Partial Position Warning',
            html: `<p>Position <strong>#${pos}</strong> exists in only <strong>${groupsWithThisPosition} of ${numGroups}</strong> groups.</p>
                   <p class="text-warning">This will bring <strong>${groupsWithThisPosition} players</strong> (not ${numGroups}).</p>
                   <p class="text-muted small">To get ${numGroups} players, add more players to smaller groups.</p>
                   <p><strong>Continue anyway?</strong></p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, select it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ffc107'
        }).then((result) => {
            if (result.isConfirmed) {
                togglePositionSelection($btn, idx, pos);
            }
        });
        return;
    }
    
    // Direct toggle for valid positions or deselection
    togglePositionSelection($btn, idx, pos);
});

// Helper function to toggle position selection
function togglePositionSelection($btn, idx, pos) {
    // Toggle button state
    $btn.toggleClass('btn-primary btn-outline-secondary');
    
    // Update config
    if (!playoffConfig[idx].positions) {
        playoffConfig[idx].positions = [];
    }
    
    const posIdx = playoffConfig[idx].positions.indexOf(pos);
    if (posIdx === -1) {
        playoffConfig[idx].positions.push(pos);
    } else {
        playoffConfig[idx].positions.splice(posIdx, 1);
    }
    
    // Sort positions
    playoffConfig[idx].positions.sort((a, b) => a - b);
    
    // Update preview
    updatePlayoffPreview(idx);
    updateFlowPreview();
}

// Update playoff name
$(document).on('change', '.playoff-name', function() {
    const idx = $(this).data('idx');
    playoffConfig[idx].name = $(this).val();
    updateFlowPreview();
});

// Update playoff size
$(document).on('change', '.playoff-size', function() {
    const idx = $(this).data('idx');
    playoffConfig[idx].size = parseInt($(this).val());
    updateFlowPreview();
});

// Toggle playoff enabled
$(document).on('change', '.playoff-enabled', function() {
    const idx = $(this).data('idx');
    playoffConfig[idx].enabled = $(this).is(':checked');
    updateFlowPreview();
});

// Remove playoff draw
$(document).on('click', '.btn-remove-playoff', function() {
    const idx = $(this).data('idx');
    
    Swal.fire({
        title: 'Remove this playoff draw?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            playoffConfig.splice(idx, 1);
            renderPlayoffTable();
            updateFlowPreview();
        }
    });
});

// Add new playoff draw
$('#btn-add-playoff').on('click', function() {
    const newIdx = playoffConfig.length;
    playoffConfig.push({
        name: 'New Playoff Draw',
        slug: 'new-' + newIdx,
        size: 4,
        positions: [],
        enabled: true
    });
    renderPlayoffTable();
    updateFlowPreview();
});

// Load preset template
$('#btn-load-preset').on('click', function() {
    const $select = $('#preset-selector');
    const $option = $select.find(':selected');
    const presetKey = $option.val(); // Store the preset key
    const configJson = $option.data('config');
    const presetMaxPos = parseInt($option.data('max-positions')) || 10;
    const presetGroups = parseInt($option.data('groups')) || numGroups;
    
    if (!configJson || configJson.length === 0) {
        toastr.warning('Please select a preset template first.');
        return;
    }
    
    Swal.fire({
        title: 'Load Preset?',
        html: `This will replace your current playoff configuration.<br><br>` +
              `<small class="text-muted">Template is for <strong>${presetGroups} group(s)</strong> with positions up to <strong>#${presetMaxPos}</strong></small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, load it',
        confirmButtonColor: '#198754'
    }).then((result) => {
        if (result.isConfirmed) {
            playoffConfig = JSON.parse(JSON.stringify(configJson)); // Deep copy
            maxPositions = presetMaxPos; // Update max positions for this template
            
            // Store preset key for saving
            window.currentPresetKey = presetKey;
            
            // ✅ AUTO-CLEANUP: Remove invalid positions based on actual players
            const groupPlayerCounts = @json($groups->map(function($g) { return $g->registrations->count(); })->toArray());
            const maxPlayersInGroup = groupPlayerCounts.length > 0 ? Math.max(...groupPlayerCounts) : 0;
            
            let cleanedCount = 0;
            let uncheckedCount = 0;
            
            playoffConfig.forEach((playoff, idx) => {
                const positions = playoff.positions || [];
                const validPositions = positions.filter(pos => pos <= maxPlayersInGroup);
                
                // Track what was cleaned
                if (validPositions.length !== positions.length) {
                    const removed = positions.filter(pos => pos > maxPlayersInGroup);
                    console.log(`🧹 [PRESET] Cleaned ${playoff.name}: removed positions`, removed);
                    cleanedCount += removed.length;
                    playoffConfig[idx].positions = validPositions;
                }
                
                // Uncheck playoffs with no valid positions
                if (validPositions.length === 0 && playoff.enabled) {
                    console.log(`❌ [PRESET] Unchecked ${playoff.name}: no valid positions`);
                    playoffConfig[idx].enabled = false;
                    uncheckedCount++;
                }
            });
            
            // Show notification if cleanup happened
            if (cleanedCount > 0 || uncheckedCount > 0) {
                let message = 'Preset loaded! ';
                if (cleanedCount > 0) {
                    message += `Removed ${cleanedCount} invalid position(s). `;
                }
                if (uncheckedCount > 0) {
                    message += `Unchecked ${uncheckedCount} playoff(s) with no valid positions. `;
                }
                message += 'Review and save when ready.';
                toastr.info(message, 'Preset Auto-Cleaned', { timeOut: 5000 });
            } else {
                toastr.success('Preset loaded! Position buttons adjusted. Remember to save when done.');
            }
            
            renderPlayoffTable();
            updateFlowPreview();
        }
    });
});

// Save playoff config
$('#btn-save-playoff-config').on('click', function() {
    const $btn = $(this);
    const oldText = $btn.html();
    
    // Validate before saving - check for invalid positions
    const groupPlayerCounts = @json($groups->map(function($g) { return $g->registrations->count(); })->toArray());
    const maxPlayersInGroup = groupPlayerCounts.length > 0 ? Math.max(...groupPlayerCounts) : 0;
    
    let hasInvalidPositions = false;
    let invalidDetails = [];
    
    playoffConfig.forEach((playoff, idx) => {
        const positions = playoff.positions || [];
        positions.forEach(pos => {
            if (pos > maxPlayersInGroup) {
                hasInvalidPositions = true;
                invalidDetails.push({
                    playoff: playoff.name,
                    position: pos
                });
            }
        });
    });
    
    // Block save if invalid positions exist
    if (hasInvalidPositions) {
        Swal.fire({
            title: 'Cannot Save - Invalid Positions!',
            html: `<p class="text-danger"><strong>You have selected positions that don't exist in any group:</strong></p>
                   <ul class="text-start">
                   ${invalidDetails.map(d => `<li>${d.playoff}: Position <strong>#${d.position}</strong></li>`).join('')}
                   </ul>
                   <p class="text-muted">Maximum valid position: <strong>#${maxPlayersInGroup}</strong></p>
                   <p>Please remove these positions before saving.</p>`,
            icon: 'error',
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    // IMPORTANT: Filter out playoff draws with empty positions
    // Backend validation requires positions array to be non-empty
    const validPlayoffConfig = playoffConfig.filter(playoff => {
        const positions = playoff.positions || [];
        return positions.length > 0; // Only include playoffs with at least 1 position
    });
    
    if (validPlayoffConfig.length === 0) {
        Swal.fire({
            title: 'No Valid Playoffs!',
            html: `<p>All playoff draws have 0 positions selected.</p>
                   <p>Please select at least one position for at least one playoff draw before saving.</p>`,
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#ffc107'
        });
        return;
    }
    
    console.log('💾 [SAVE] Saving playoff config:', {
        total: playoffConfig.length,
        valid: validPlayoffConfig.length,
        filtered: playoffConfig.length - validPlayoffConfig.length
    });
    
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
    
    // Get current preset key (from dropdown or stored when loaded)
    const presetKey = $('#preset-selector').val() || window.currentPresetKey || null;
    
    $.ajax({
        url: `${APP_URL}/backend/draw/${DRAW_ID}/playoff-config`,
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            _token: '{{ csrf_token() }}',
            playoff_config: validPlayoffConfig,  // Send only valid playoffs
            preset_key: presetKey
        }),
        success: function(response) {
            if (response.success) {
                // Update local config to match what was saved
                playoffConfig = validPlayoffConfig;
                
                toastr.success('Playoff configuration saved!');
                
                // Store the saved preset key
                if (presetKey) {
                    window.currentPresetKey = presetKey;
                    console.log('✅ [SAVE] Preset key saved:', presetKey);
                }
                
                // Re-render to show updated config
                renderPlayoffTable();
                updateFlowPreview();
            } else {
                toastr.error(response.message || 'Failed to save.');
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Error saving configuration.');
        },
        complete: function() {
            $btn.prop('disabled', false).html(oldText);
        }
    });
});

// Update preview for a single playoff row
function updatePlayoffPreview(idx) {
    const config = playoffConfig[idx];
    const posCount = (config.positions || []).length;
    const totalPlayers = posCount * numGroups;
    const $preview = $(`.playoff-preview[data-idx="${idx}"]`);
    const size = config.size || 4;
    
    let statusClass = 'text-muted';
    let statusIcon = '';
    if (totalPlayers > size) {
        statusClass = 'text-danger fw-bold';
        statusIcon = '⚠️ ';
    } else if (totalPlayers === size) {
        statusClass = 'text-success fw-bold';
        statusIcon = '✓ ';
    }
    
    
    $preview.html(`<span class="${statusClass}">${statusIcon}${totalPlayers} players</span>`);
}

// Render the entire playoff table
function renderPlayoffTable() {
    let html = '';
    // Use dynamic maxPositions (set when loading preset, default 10)
    const positionsToShow = maxPositions || 10;
    
    // Get actual player counts for validation
    const groupPlayerCounts = @json($groups->map(function($g) { return $g->registrations->count(); })->toArray());
    const minPlayersInGroup = groupPlayerCounts.length > 0 ? Math.min(...groupPlayerCounts) : 0;
    const maxPlayersInGroup = groupPlayerCounts.length > 0 ? Math.max(...groupPlayerCounts) : 0;
    const actualTotalPlayers = {{ $totalPlayers ?? 0 }};
    
    playoffConfig.forEach((playoff, idx) => {
        const positions = playoff.positions || [];
        const totalPlayers = positions.length * numGroups;
        const size = playoff.size || 4;
        
        let statusClass = 'text-muted';
        let statusIcon = '';
        if (totalPlayers > size) {
            statusClass = 'text-danger fw-bold';
            statusIcon = '⚠️ ';
        } else if (totalPlayers === size) {
            statusClass = 'text-success fw-bold';
            statusIcon = '✓ ';
        }
        
        html += `
        <tr data-idx="${idx}">
          <td>
            <div class="form-check form-switch">
              <input class="form-check-input playoff-enabled" type="checkbox" 
                     ${playoff.enabled && positions.length > 0 ? 'checked' : ''}
                     data-idx="${idx}">
            </div>
          </td>
          <td>
            <input type="text" class="form-control form-control-sm playoff-name" 
                   value="${playoff.name}" data-idx="${idx}" style="min-width: 150px;">
          </td>
          <td>
            <select class="form-select form-select-sm playoff-size" data-idx="${idx}" style="width: 80px;">
              ${[2, 4, 8, 16, 32].map(size => 
                `<option value="${size}" ${playoff.size == size ? 'selected' : ''}>${size}</option>`
              ).join('')}
            </select>
          </td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              ${Array.from({length: positionsToShow}, (_, i) => i + 1).map(pos => {
                const isSelected = positions.includes(pos);
                const groupsWithPosition = groupPlayerCounts.filter(count => count >= pos).length;
                const isFullyInvalid = pos > maxPlayersInGroup; // Doesn't exist in ANY group
                const isPartial = pos > minPlayersInGroup && pos <= maxPlayersInGroup; // Exists in SOME groups
                
                let btnClass, tooltip, style = '';
                
                if (isSelected) {
                  btnClass = 'btn-primary';
                  tooltip = isPartial ? 
                    `Position #${pos} - Only ${groupsWithPosition}/${numGroups} groups (partial)` :
                    `Position #${pos} from each group`;
                } else if (isFullyInvalid) {
                  btnClass = 'btn-outline-danger';
                  tooltip = `Position #${pos} not available (max ${maxPlayersInGroup} players in largest group)`;
                  style = 'opacity: 0.3; cursor: not-allowed;';
                } else if (isPartial) {
                  btnClass = 'btn-outline-warning';
                  tooltip = `Position #${pos} exists in ${groupsWithPosition}/${numGroups} groups (partial)`;
                } else {
                  btnClass = 'btn-outline-secondary';
                  tooltip = `Position #${pos} from each group`;
                }
                
                return `<button type="button" 
                        class="btn btn-sm position-btn ${btnClass}"
                        data-idx="${idx}" 
                        data-pos="${pos}"
                        title="${tooltip}"
                        ${style ? `style="${style}"` : ''}>
                  #${pos}${isFullyInvalid ? '✗' : isPartial ? '⚠' : ''}
                </button>`;
              }).join('')}
            </div>
            <small class="text-muted">
              <strong>Available:</strong> #1-${maxPlayersInGroup}
              ${minPlayersInGroup !== maxPlayersInGroup ? 
                ` | <span class="text-warning">Partial: #${minPlayersInGroup + 1}-#${maxPlayersInGroup}</span>` : ''}
            </small>
            ${actualTotalPlayers === 0 ? '<br><small class="text-danger">⚠️ No players assigned yet!</small>' : ''}
          </td>
          <td>
            <small class="playoff-preview ${statusClass}" data-idx="${idx}">
              ${statusIcon}${totalPlayers} players
            </small>
          </td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-playoff" data-idx="${idx}">
              <i class="ti ti-trash"></i>
            </button>
          </td>
        </tr>`;
    });
    $('#playoff-config-body').html(html);
}

// Update flow preview - MASTER SYNC FUNCTION
function updateFlowPreview() {
    console.log('🔄 [SYNC] updateFlowPreview called - numGroups:', numGroups, 'maxPositions:', maxPositions);
    
    // Update player accounting first
    updatePlayerAccounting();
    
    const $preview = $('#playoff-flow-preview');
    let html = '';
    
    // Group names
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    // Build flow diagram
    html += '<div class="d-flex align-items-start gap-4 flex-wrap">';
    
    // Groups column
    html += '<div class="border rounded p-3 bg-light">';
    html += '<h6 class="fw-bold mb-2">Groups</h6>';
    groupNames.forEach(name => {
        html += `<div class="badge bg-primary mb-1 d-block">Group ${name}</div>`;
    });
    html += '</div>';
    
    // Arrow
    html += '<div class="d-flex align-items-center"><i class="ti ti-arrow-right fs-4 text-muted"></i></div>';
    
    // Playoff draws
    html += '<div class="d-flex flex-wrap gap-2">';
    playoffConfig.filter(p => p.enabled).forEach(playoff => {
        const positions = playoff.positions || [];
        const totalPlayers = positions.length * numGroups;
        const posText = positions.map(p => '#' + p).join(', ') || 'None';
        
        html += `
        <div class="border rounded p-3 ${totalPlayers > playoff.size ? 'border-danger' : 'border-success'}">
          <h6 class="fw-bold mb-2">${playoff.name}</h6>
          <div class="small">
            <div><strong>Size:</strong> ${playoff.size} players</div>
            <div><strong>From:</strong> ${posText}</div>
            <div><strong>Total:</strong> ${totalPlayers} players</div>
            ${totalPlayers > playoff.size ? 
              '<span class="badge bg-danger">⚠ Too many players!</span>' : 
              totalPlayers < playoff.size ? 
              '<span class="badge bg-warning">⚠ Needs more players</span>' :
              '<span class="badge bg-success">✓ Perfect fit</span>'
            }
          </div>
        </div>`;
    });
    html += '</div>';
    
    html += '</div>';
    
    $preview.html(html);
    
    console.log('✅ [SYNC] Flow preview updated, triggering seeding chart update...');
    
    // Update detailed seeding chart (which cascades to matrix and bracket viz)
    updateSeedingChart();
}

// Update player accounting and validation
function updatePlayerAccounting() {
    console.log('👥 [ACCOUNTING] Calculating player distribution...');
    
    const $accounting = $('#player-accounting');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    // Use ACTUAL player count from the draw, not theoretical maximum
    const actualTotalPlayers = {{ $totalPlayers ?? 0 }};
    const groupPlayerCounts = @json($groups->map(function($g) { return $g->registrations->count(); })->toArray());
    
    // Calculate actual positions available (smallest group determines max valid position)
    const minPlayersInGroup = groupPlayerCounts.length > 0 ? Math.min(...groupPlayerCounts) : 0;
    const maxValidPosition = minPlayersInGroup; // Can't use position #5 if a group only has 4 players
    
    console.log('📊 [ACCOUNTING] Actual players:', actualTotalPlayers, '| Min per group:', minPlayersInGroup, '| Max valid position:', maxValidPosition);
    
    
    
    // Calculate players in each enabled playoff
    const enabledPlayoffs = playoffConfig.filter(p => p.enabled);
    let totalAccommodated = 0;
    let playoffBreakdown = [];
    let partialPositions = []; // Track positions that don't exist in all groups
    
    // Track which positions are used
    let positionsUsed = new Set();
    
    enabledPlayoffs.forEach(playoff => {
        const positions = playoff.positions || [];
        let actualPlayers = 0;
        let partialDetails = [];
        
        positions.forEach(pos => {
            positionsUsed.add(pos);
            
            // Count how many groups actually have this position
            let groupsWithPosition = 0;
            groupPlayerCounts.forEach(count => {
                if (pos <= count) {
                    groupsWithPosition++;
                }
            });
            
            actualPlayers += groupsWithPosition;
            
            // Track partial positions
            if (groupsWithPosition < numGroups && groupsWithPosition > 0) {
                partialDetails.push({
                    pos: pos,
                    groups: groupsWithPosition,
                    total: numGroups
                });
                if (!partialPositions.find(p => p.pos === pos)) {
                    partialPositions.push({
                        pos: pos,
                        groups: groupsWithPosition,
                        total: numGroups
                    });
                }
            }
        });
        
        totalAccommodated += actualPlayers;
        
        playoffBreakdown.push({
            name: playoff.name,
            size: playoff.size,
            positions: positions,
            players: actualPlayers,
            partialDetails: partialDetails,
            status: actualPlayers === playoff.size ? 'perfect' : 
                    actualPlayers > playoff.size ? 'overflow' : 'underflow'
        });
    });
    
    // Calculate unallocated positions (only count positions that actually exist in at least one group)
    const unallocatedPositions = [];
    for (let pos = 1; pos <= maxValidPosition; pos++) {
        if (!positionsUsed.has(pos)) {
            // Count how many groups have this position
            const groupsWithPos = groupPlayerCounts.filter(count => count >= pos).length;
            if (groupsWithPos > 0) {
                unallocatedPositions.push({
                    pos: pos,
                    count: groupsWithPos
                });
            }
        }
    }
    const unallocatedPlayers = unallocatedPositions.reduce((sum, p) => sum + p.count, 0);
    
    
    // Build HTML
    let html = '';
    
    // Warning if no players assigned
    if (actualTotalPlayers === 0) {
        html += '<div class="alert alert-danger">';
        html += '<i class="ti ti-alert-circle me-1"></i> ';
        html += '<strong>No Players Assigned!</strong> Please go to "Players & Groups" tab to assign players before configuring playoffs.';
        html += '</div>';
        $accounting.html(html);
        return;
    }
    
    // Summary Stats
    html += '<div class="row g-3 mb-4">';
    
    // ACTUAL Total Players
    html += '<div class="col-md-3">';
    html += '<div class="card border-primary">';
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">Actual Players in Draw</h6>';
    html += `<h2 class="mb-0 text-primary">${actualTotalPlayers}</h2>`;
    html += `<small class="text-muted">${numGroups} groups | ${minPlayersInGroup}-${Math.max(...groupPlayerCounts)} per group</small>`;
    html += '</div></div></div>';
    
    // Accommodated
    html += '<div class="col-md-3">';
    html += '<div class="card border-success">';
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">In Playoff Draws</h6>';
    html += `<h2 class="mb-0 text-success">${totalAccommodated}</h2>`;
    html += `<small class="text-muted">${enabledPlayoffs.length} playoff draw${enabledPlayoffs.length !== 1 ? 's' : ''}</small>`;
    html += '</div></div></div>';
    
    // Unallocated
    html += '<div class="col-md-3">';
    html += `<div class="card border-${unallocatedPlayers > 0 ? 'warning' : 'secondary'}">`;
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">Not in Playoffs</h6>';
    html += `<h2 class="mb-0 text-${unallocatedPlayers > 0 ? 'warning' : 'secondary'}">${unallocatedPlayers}</h2>`;
    html += `<small class="text-muted">${unallocatedPositions.length} position${unallocatedPositions.length !== 1 ? 's' : ''} unused</small>`;
    html += '</div></div></div>';
    
    
    // Status
    const allAccommodated = unallocatedPlayers === 0 && partialPositions.length === 0;
    const hasWarnings = partialPositions.length > 0;
    html += '<div class="col-md-3">';
    html += `<div class="card border-${allAccommodated ? 'success' : hasWarnings ? 'warning' : 'secondary'}">`;
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">Status</h6>';
    html += `<h2 class="mb-0">${allAccommodated ? '✓' : hasWarnings ? '⚠️' : '○'}</h2>`;
    html += `<small class="text-${allAccommodated ? 'success' : hasWarnings ? 'warning' : 'secondary'} fw-bold">`;
    html += allAccommodated ? 'Valid' : hasWarnings ? 'Partial' : 'Incomplete';
    html += '</small>';
    html += '</div></div></div>';
    
    html += '</div>';
    
    
    
    // Detailed Breakdown
    if (enabledPlayoffs.length > 0) {
        html += '<div class="table-responsive mb-3">';
        html += '<table class="table table-sm table-bordered">';
        html += '<thead class="table-light">';
        html += '<tr>';
        html += '<th>Playoff Draw</th>';
        html += '<th class="text-center">Bracket Size</th>';
        html += '<th class="text-center">Positions Used</th>';
        html += '<th class="text-center">Players Assigned</th>';
        html += '<th class="text-center">Status</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        playoffBreakdown.forEach(playoff => {
            const statusClass = playoff.status === 'perfect' ? 'table-success' : 
                              playoff.status === 'overflow' ? 'table-danger' : 'table-warning';
            const statusIcon = playoff.status === 'perfect' ? '✓' : 
                             playoff.status === 'overflow' ? '⚠️' : '⚠️';
            const statusText = playoff.status === 'perfect' ? 'Perfect Match' : 
                             playoff.status === 'overflow' ? `${playoff.players - playoff.size} over capacity` : 
                             `${playoff.size - playoff.players} slots empty`;
            
            html += `<tr class="${statusClass}">`;
            html += `<td><strong>${playoff.name}</strong>`;
            if (playoff.partialDetails.length > 0) {
                html += ` <span class="badge bg-warning text-dark">⚠ ${playoff.partialDetails.length} partial</span>`;
            }
            html += `</td>`;
            html += `<td class="text-center">${playoff.size}</td>`;
            html += `<td class="text-center">`;
            playoff.positions.forEach(pos => {
                const groupsWithPos = groupPlayerCounts.filter(count => count >= pos).length;
                const isPartial = groupsWithPos < numGroups;
                html += `<span class="badge ${isPartial ? 'bg-warning text-dark' : 'bg-primary'} me-1" 
                              title="${groupsWithPos}/${numGroups} groups">#${pos}</span>`;
            });
            html += `</td>`;
            html += `<td class="text-center"><strong>${playoff.players}</strong>`;
            if (playoff.partialDetails.length > 0) {
                const partialStr = playoff.partialDetails.map(p => `#${p.pos}:${p.groups}/${p.total}`).join(', ');
                html += ` <small class="text-warning d-block">(${partialStr})</small>`;
            }
            html += `</td>`;
            html += `<td class="text-center">${statusIcon} ${statusText}</td>`;
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
    }
    
    // Partial positions info
    if (partialPositions.length > 0) {
        html += '<div class="alert alert-info mb-3">';
        html += '<i class="ti ti-info-circle me-1"></i> ';
        html += '<strong>Partial Positions:</strong> ';
        partialPositions.forEach(p => {
            html += `Position <strong>#${p.pos}</strong> exists in <strong>${p.groups}/${p.total}</strong> groups (${p.groups} players). `;
        });
        html += '<br>These positions are valid but won\'t provide the full number of players.';
        html += '</div>';
    }
    
    // Unallocated Positions Warning
    if (unallocatedPlayers > 0) {
        html += '<div class="alert alert-warning mb-0">';
        html += '<i class="ti ti-alert-triangle me-1"></i> ';
        html += `<strong>Warning:</strong> ${unallocatedPlayers} player${unallocatedPlayers !== 1 ? 's' : ''} not assigned to any playoff. `;
        html += '<br><strong>Unused positions:</strong> ';
        unallocatedPositions.forEach(p => {
            html += `#${p.pos} (${p.count} player${p.count !== 1 ? 's' : ''}) `;
        });
        html += '</div>';
    } else if (partialPositions.length === 0) {
        html += '<div class="alert alert-success mb-0">';
        html += '<i class="ti ti-check-circle me-1"></i> ';
        html += `<strong>All Clear!</strong> All ${actualTotalPlayers} players are accommodated in playoff draws.`;
        html += '</div>';
    }
    
    
    $accounting.html(html);
    
    console.log('✅ [ACCOUNTING] Player accounting updated:', {
        total: actualTotalPlayers,
        accommodated: totalAccommodated,
        unallocated: unallocatedPlayers
    });
}

// Generate detailed seeding chart - CASCADES TO MATRIX AND BRACKET
function updateSeedingChart() {
    console.log('📊 [SYNC] updateSeedingChart called');
    
    const $chart = $('#playoff-seeding-chart');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    let html = '';
    
    // Filter enabled playoffs
    const enabledPlayoffs = playoffConfig.filter(p => p.enabled);
    
    if (enabledPlayoffs.length === 0) {
        $chart.html('<div class="text-muted">No enabled playoff draws configured.</div>');
        updateCompleteSeedingMatrix(); // Still update the complete matrix
        console.log('⚠️ [SYNC] No enabled playoffs, matrix updated');
        return;
    }
    
    html += '<div class="table-responsive">';
    html += '<table class="table table-bordered table-sm">';
    html += '<thead class="table-light">';
    html += '<tr>';
    html += '<th class="text-center">Playoff Draw</th>';
    html += '<th class="text-center">Bracket Position</th>';
    html += '<th class="text-center">From Groups (Position)</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    enabledPlayoffs.forEach(playoff => {
        const positions = playoff.positions || [];
        const totalPlayers = positions.length * numGroups;
        
        // Calculate seeding for this playoff
        let seeds = [];
        
        // For each position selected (e.g., #1, #2, #3)
        positions.forEach(position => {
            // For each group
            groupNames.forEach(groupName => {
                seeds.push({
                    group: groupName,
                    position: position
                });
            });
        });
        
        // Now show each seed and where it goes in the bracket
        if (seeds.length > 0) {
            html += `<tr class="table-primary"><td colspan="3"><strong>${playoff.name}</strong> (${playoff.size}-player draw)</td></tr>`;
            
            seeds.forEach((seed, idx) => {
                const bracketPosition = idx + 1;
                const statusClass = bracketPosition > playoff.size ? 'table-danger' : '';
                
                html += `<tr class="${statusClass}">`;
                html += `<td>${playoff.name}</td>`;
                html += `<td class="text-center"><strong>Seed ${bracketPosition}</strong></td>`;
                html += `<td class="text-center">Group <strong>${seed.group}</strong> position <strong>#${seed.position}</strong></td>`;
                html += `</tr>`;
            });
        } else {
            html += `<tr><td colspan="3" class="text-muted text-center"><em>${playoff.name} - No positions selected</em></td></tr>`;
        }
    });
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    html += '<div class="alert alert-info mt-3 mb-0">';
    html += '<i class="ti ti-info-circle me-1"></i> ';
    html += '<strong>How to read:</strong> Each row shows where a specific player will be seeded in the playoff bracket. ';
    html += 'For example, "Seed 1: Group A position #1" means the player who finishes 1st in Group A will be seeded 1st in that playoff draw.';
    html += '</div>';
    
    $chart.html(html);
    
    console.log('✅ [SYNC] Seeding chart updated, cascading to matrix and bracket...');
    
    // Update complete seeding matrix
    updateCompleteSeedingMatrix();
    
    // Update bracket visualization
    updateBracketVisualization();
    
    console.log('✅ [SYNC] All visualizations synced');
}

// Generate complete seeding matrix showing all positions
function updateCompleteSeedingMatrix() {
    console.log('📋 [SYNC] updateCompleteSeedingMatrix called');
    
    const $matrix = $('#complete-seeding-matrix');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    let html = '';
    
    // Determine max positions to show (default to 10 or use maxPositions)
    const maxPos = maxPositions || 10;
    
    html += '<div class="table-responsive">';
    html += '<table class="table table-bordered table-sm table-striped">';
    html += '<thead class="table-dark">';
    html += '<tr>';
    html += '<th class="text-center">Group Position</th>';
    
    // Header for each group
    groupNames.forEach(groupName => {
        html += `<th class="text-center">Group ${groupName}</th>`;
    });
    html += '<th class="text-center">Seed Range</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    // For each position (1st, 2nd, 3rd, etc.)
    for (let pos = 1; pos <= maxPos; pos++) {
        html += '<tr>';
        html += `<td class="text-center fw-bold">Position #${pos}</td>`;
        
        let seedStart = null;
        let seedEnd = null;
        
        // For each group, calculate the seed number
        groupNames.forEach((groupName, groupIdx) => {
            // Calculate seed number: (position - 1) * numGroups + groupIdx + 1
            const seedNum = (pos - 1) * numGroups + groupIdx + 1;
            
            if (seedStart === null) seedStart = seedNum;
            seedEnd = seedNum;
            
            // Check if this position is used in any enabled playoff
            let isUsed = false;
            let usedIn = [];
            
            playoffConfig.filter(p => p.enabled).forEach(playoff => {
                if ((playoff.positions || []).includes(pos)) {
                    isUsed = true;
                    usedIn.push(playoff.name);
                }
            });
            
            const cellClass = isUsed ? 'table-success' : '';
            const tooltip = usedIn.length > 0 ? `Used in: ${usedIn.join(', ')}` : 'Not used in any playoff';
            
            html += `<td class="text-center ${cellClass}" title="${tooltip}">`;
            html += `<strong>Seed ${seedNum}</strong>`;
            if (isUsed) {
                html += ` <span class="badge bg-success" style="font-size: 8px;">✓</span>`;
            }
            html += `</td>`;
        });
        
        // Seed range column
        html += `<td class="text-center text-muted"><small>${seedStart}-${seedEnd}</small></td>`;
        html += '</tr>';
    }
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    html += '<div class="row mt-3">';
    html += '<div class="col-md-6">';
    html += '<div class="alert alert-success mb-0">';
    html += '<i class="ti ti-info-circle me-1"></i> ';
    html += '<strong>Legend:</strong> ';
    html += '<span class="badge bg-success me-2">✓</span> = Position is used in an enabled playoff draw<br>';
    html += '<strong>Seed Formula:</strong> Seed # = (Position - 1) × Groups + Group Order';
    html += '</div>';
    html += '</div>';
    
    html += '<div class="col-md-6">';
    html += '<div class="alert alert-info mb-0">';
    html += '<strong>Example:</strong> With 4 groups (A, B, C, D):<br>';
    html += '• Position #1 from Group A = <strong>Seed 1</strong><br>';
    html += '• Position #1 from Group B = <strong>Seed 2</strong><br>';
    html += '• Position #2 from Group A = <strong>Seed 5</strong><br>';
    html += '• Position #2 from Group B = <strong>Seed 6</strong>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    
    $matrix.html(html);
    
    console.log('✅ [SYNC] Complete seeding matrix updated');
}

// Generate bracket visualization showing seed positions and matchups
function updateBracketVisualization() {
    const $viz = $('#bracket-visualization');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    let html = '';
    
    const enabledPlayoffs = playoffConfig.filter(p => p.enabled);
    
    if (enabledPlayoffs.length === 0) {
        $viz.html('<div class="text-muted">No enabled playoff draws configured.</div>');
        return;
    }
    
    html += '<div class="d-flex flex-wrap gap-3">';
    
    enabledPlayoffs.forEach(playoff => {
        const positions = playoff.positions || [];
        const size = playoff.size;
        
        // Calculate seeds
        let seeds = [];
        positions.forEach(position => {
            groupNames.forEach(groupName => {
                seeds.push({
                    group: groupName,
                    position: position
                });
            });
        });
        
        // Generate standard bracket matchups based on size
        const matchups = generateBracketMatchups(size);
        
        html += '<div class="bracket-container">';
        html += `<h6 class="fw-bold mb-3 text-center">${playoff.name}</h6>`;
        html += `<div class="text-center mb-3"><span class="badge bg-primary">${size}-Player Draw</span></div>`;
        
        if (seeds.length === 0) {
            html += '<div class="text-muted text-center small">No positions selected</div>';
        } else {
            if (seeds.length > size) {
                html += '<div class="alert alert-danger py-1 px-2 mb-2 small">⚠️ Too many! Only first ' + size + ' used</div>';
            } else if (seeds.length < size) {
                html += '<div class="alert alert-warning py-1 px-2 mb-2 small">⚠️ ' + (size - seeds.length) + ' byes needed</div>';
            }
            
            // Show first round matchups
            html += '<div class="bracket-round">';
            html += '<div class="text-center fw-bold mb-2 small text-muted">R1 Matchups</div>';
            
            matchups.forEach((matchup) => {
                const seed1 = seeds[matchup.seed1 - 1];
                const seed2 = seeds[matchup.seed2 - 1];
                
                html += '<div class="bracket-matchup">';
                
                // Seed 1
                html += '<div class="bracket-seed">';
                html += `<span class="bracket-seed-num">#${matchup.seed1}</span>`;
                if (seed1) {
                    html += `<span class="bracket-seed-source">${seed1.group}${seed1.position}</span>`;
                } else {
                    html += '<span class="bracket-seed-source text-danger">BYE</span>';
                }
                html += '</div>';
                
                // VS
                html += '<div class="text-center text-muted" style="font-size: 10px; margin: 1px 0;">vs</div>';
                
                // Seed 2
                html += '<div class="bracket-seed">';
                html += `<span class="bracket-seed-num">#${matchup.seed2}</span>`;
                if (seed2) {
                    html += `<span class="bracket-seed-source">${seed2.group}${seed2.position}</span>`;
                } else {
                    html += '<span class="bracket-seed-source text-danger">BYE</span>';
                }
                html += '</div>';
                
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        html += '</div>';
    });
    
    html += '</div>';
    
    html += '<div class="alert alert-info mt-3 mb-0">';
    html += '<i class="ti ti-info-circle me-1"></i> ';
    html += '<strong>Reading the brackets:</strong> Each matchup shows seed numbers (#1, #2, etc.) and their source position. ';
    html += 'For example, <code>#1 (A1)</code> means Seed 1 is from Group A Position #1. ';
    html += 'Standard tennis seeding ensures top seeds don\'t meet until later rounds.';
    html += '</div>';
    
    $viz.html(html);
}

// Generate standard bracket matchups based on draw size
function generateBracketMatchups(size) {
    const matchups = [];
    
    switch(size) {
        case 2:
            matchups.push({seed1: 1, seed2: 2});
            break;
        case 4:
            matchups.push({seed1: 1, seed2: 4});
            matchups.push({seed1: 2, seed2: 3});
            break;
        case 8:
            matchups.push({seed1: 1, seed2: 8});
            matchups.push({seed1: 4, seed2: 5});
            matchups.push({seed1: 2, seed2: 7});
            matchups.push({seed1: 3, seed2: 6});
            break;
        case 16:
            matchups.push({seed1: 1, seed2: 16});
            matchups.push({seed1: 8, seed2: 9});
            matchups.push({seed1: 4, seed2: 13});
            matchups.push({seed1: 5, seed2: 12});
            matchups.push({seed1: 2, seed2: 15});
            matchups.push({seed1: 7, seed2: 10});
            matchups.push({seed1: 3, seed2: 14});
            matchups.push({seed1: 6, seed2: 11});
            break;
        case 32:
            // Standard 32-draw seeding
            matchups.push({seed1: 1, seed2: 32});
            matchups.push({seed1: 16, seed2: 17});
            matchups.push({seed1: 8, seed2: 25});
            matchups.push({seed1: 9, seed2: 24});
            matchups.push({seed1: 4, seed2: 29});
            matchups.push({seed1: 13, seed2: 20});
            matchups.push({seed1: 5, seed2: 28});
            matchups.push({seed1: 12, seed2: 21});
            matchups.push({seed1: 2, seed2: 31});
            matchups.push({seed1: 15, seed2: 18});
            matchups.push({seed1: 7, seed2: 26});
            matchups.push({seed1: 10, seed2: 23});
            matchups.push({seed1: 3, seed2: 30});
            matchups.push({seed1: 14, seed2: 19});
            matchups.push({seed1: 6, seed2: 27});
            matchups.push({seed1: 11, seed2: 22});
            break;
    }
    
    return matchups;
}

// Initialize flow preview
$(document).ready(function() {
    console.log('==========================================');
    console.log('🚀 [INIT] Round Robin Playoff System');
    console.log('📊 Initial State:');
    console.log('  - numGroups:', numGroups);
    console.log('  - maxPositions:', maxPositions);
    console.log('  - playoffConfig length:', playoffConfig.length);
    console.log('  - savedPresetKey:', window.currentPresetKey || 'none');
    console.log('  - dropdown value:', $('#preset-selector').val());
    console.log('==========================================');
    
    // Just render the table with the database config (no auto-cleanup)
    renderPlayoffTable();
    
    // Validate configuration on load
    validateDrawConfiguration();
    
    // Initial render of all visualizations
    updateFlowPreview();
    
    console.log('✅ [INIT] All visualizations initialized');
});

// Validate draw configuration on page load
function validateDrawConfiguration() {
    console.log('🔍 [VALIDATION] Checking draw configuration...');
    
    const warnings = [];
    const errors = [];
    
    // 1. Check if actual groups match settings
    const actualGroups = {{ $groups->count() }};
    const settingsGroups = {{ $currentBoxes ?? 4 }};
    
    if (actualGroups !== settingsGroups) {
        warnings.push({
            type: 'group-mismatch',
            message: `Group count mismatch: ${actualGroups} groups exist, but settings show ${settingsGroups}. Consider updating settings.`
        });
        console.warn('⚠️ [VALIDATION] Group count mismatch:', actualGroups, 'vs', settingsGroups);
    } else {
        console.log('✅ [VALIDATION] Group count matches:', actualGroups);
    }
    
    // 2. Check total players
    const totalPlayers = {{ $totalPlayers ?? 0 }};
    console.log('📊 [VALIDATION] Total players in draw:', totalPlayers);
    
    if (totalPlayers === 0) {
        warnings.push({
            type: 'no-players',
            message: 'No players assigned to groups yet. Go to "Players & Groups" tab to assign players.'
        });
    }
    
    // 3. Check if playoff config exists
    if (!playoffConfig || playoffConfig.length === 0) {
        warnings.push({
            type: 'no-playoffs',
            message: 'No playoff draws configured. Consider setting up playoff brackets.'
        });
    } else {
        console.log('✅ [VALIDATION] Playoff config exists:', playoffConfig.length, 'draws');
    }
    
    // 4. Check if groups have uneven player counts
    const groupCounts = @json($groups->map(function($g) { return $g->registrations->count(); })->toArray());
    const maxCount = Math.max(...groupCounts);
    const minCount = Math.min(...groupCounts);
    const difference = maxCount - minCount;
    
    if (difference > 2 && totalPlayers > 0) {
        warnings.push({
            type: 'uneven-groups',
            message: `Groups have uneven player counts (${minCount}-${maxCount}). Consider redistributing for fairness.`
        });
        console.warn('⚠️ [VALIDATION] Uneven group distribution:', groupCounts);
    }
    
    // 5. Check draw type
    const drawType = '{{ $draw->drawType->name ?? 'Unknown' }}';
    console.log('📋 [VALIDATION] Draw type:', drawType);
    
    // Display warnings if any
    if (warnings.length > 0 || errors.length > 0) {
        displayValidationMessages(warnings, errors);
    } else {
        console.log('✅ [VALIDATION] All checks passed!');
    }
}

// Display validation messages
function displayValidationMessages(warnings, errors) {
    let html = '';
    
    if (errors.length > 0) {
        html += '<div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">';
        html += '<strong><i class="ti ti-alert-triangle me-1"></i> Configuration Errors:</strong><ul class="mb-0 mt-2">';
        errors.forEach(err => {
            html += `<li>${err.message}</li>`;
        });
        html += '</ul>';
        html += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        html += '</div>';
    }
    
    if (warnings.length > 0) {
        html += '<div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">';
        html += '<strong><i class="ti ti-info-circle me-1"></i> Configuration Warnings:</strong><ul class="mb-0 mt-2">';
        warnings.forEach(warn => {
            html += `<li>${warn.message}</li>`;
        });
        html += '</ul>';
        html += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        html += '</div>';
    }
    
    // Insert after the Draw Overview card
    $('.card.border-info').after(html);
}

// ============================================================
// GROUPS TAB - CHANGE NUMBER OF GROUPS (AUTO-SAVE)
// ============================================================
$('#groups-tab-boxes').on('change', function() {
    const newBoxes = parseInt($(this).val());
    const $select = $(this);
    const currentVal = {{ $currentBoxes ?? 4 }};
    
    if (newBoxes == currentVal) return;
    
    Swal.fire({
        title: 'Change Number of Groups?',
        html: `<p>This will change from <strong>${currentVal}</strong> to <strong>${newBoxes}</strong> groups.</p>
               <p class="text-warning"><i class="ti ti-alert-triangle"></i> All players will be moved to <strong>Group A</strong>.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, change groups',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#198754'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading state
            $select.prop('disabled', true);
            
            Swal.fire({
                title: 'Updating Groups...',
                html: 'Please wait while groups are being recreated.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            // Save via AJAX
            $.ajax({
                url: `${APP_URL}/backend/draw/${DRAW_ID}/settings`,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    boxes: newBoxes
                },
                success: function(response) {
                    if (response.success) {
                        Swal.close();
                        toastr.success('Groups updated successfully!');
                        
                        console.log('✅ [SYNC] Groups update successful, syncing all components...');
                        
                        // Update the groups UI without page reload
                        updateGroupsUI(response.groups, newBoxes);
                        
                        // Update label
                        $('#groups-count-label').text(`| ${response.groups_count} Groups`);
                        
                        // Sync settings tab selector
                        $('#settings-boxes').val(newBoxes);
                        
                        // CRITICAL: Update numGroups for playoff preview
                        numGroups = newBoxes;
                        console.log('📊 [SYNC] numGroups updated to:', numGroups);
                        
                        // Trigger full visualization sync
                        updateFlowPreview();
                        
                        // Re-initialize sortable with force reinit after DOM updates
                        setTimeout(function() {
                            initGroupsSortable(true);
                        }, 100);
                        
                        console.log('✅ [SYNC] All components synced successfully');
                    } else {
                        Swal.fire('Error', response.message || 'Failed to update groups.', 'error');
                        $select.val(currentVal); // Revert
                    }
                },
                error: function(xhr) {
                    Swal.fire('Error', xhr.responseJSON?.message || 'Failed to update groups.', 'error');
                    $select.val(currentVal); // Revert
                },
                complete: function() {
                    $select.prop('disabled', false);
                }
            });
        } else {
            // User cancelled, revert selection
            $select.val(currentVal);
        }
    });
});

// Update groups UI dynamically
function updateGroupsUI(groups, numGroups) {
    const groupColors = {
        'A': 'bg-primary text-white',
        'B': 'bg-success text-white',
        'C': 'bg-warning text-dark',
        'D': 'bg-danger text-white',
        'E': 'bg-info text-white',
        'F': 'bg-secondary text-white',
        'G': 'bg-dark text-white',
        'H': 'bg-primary text-white'
    };
    
    let html = '';
    
    groups.forEach(group => {
        const colorClass = groupColors[group.name] || 'bg-dark text-white';
        const playerCount = group.players ? group.players.length : 0;
        
        html += `
        <div class="col-6 mb-3">
            <div class="card border h-100">
                <div class="card-header py-2 ${colorClass}">
                    <h6 class="mb-0">
                        <i class="ti ti-users-group me-1"></i> Group ${group.name}
                        <span class="badge bg-light text-dark float-end">
                            ${playerCount} players
                        </span>
                    </h6>
                </div>
                <div class="card-body p-2" style="min-height: 150px;">
                    <ul class="list-group list-group-flush rr-sortable rr-group"
                        data-group-id="${group.id}"
                        data-type="target">
                        ${group.players && group.players.length > 0 ? 
                            group.players.map(player => `
                                <li class="list-group-item list-group-item-action py-1 px-2" 
                                    data-id="${player.id}"
                                    data-player-name="${player.name}">
                                    <small>${player.name}</small>
                                    <button type="button" class="btn btn-sm btn-link text-danger float-end p-0 btn-remove-from-group" 
                                            data-id="${player.id}">
                                        <i class="ti ti-x"></i>
                                    </button>
                                </li>
                            `).join('') : ''
                        }
                    </ul>
                    ${playerCount === 0 ? 
                        '<div class="text-muted text-center py-3 empty-group-placeholder"><small>Drop players here</small></div>' : ''
                    }
                </div>
            </div>
        </div>`;
    });
    
    // Replace the groups container content
    $('#groups-pane .col-md-8 .row').html(html);
}

// Sync groups selector between Settings tab and Groups tab
$('#settings-boxes').on('change', function() {
    const newBoxes = parseInt($(this).val());
    console.log('⚙️ [SYNC] Settings boxes changed to:', newBoxes);
    
    // Sync Groups tab selector
    $('#groups-tab-boxes').val(newBoxes);
    
    // Update numGroups variable
    numGroups = newBoxes;
    
    // Update all visualizations
    console.log('🔄 [SYNC] Triggering visualization updates...');
    updateFlowPreview();
});

$(document).on('click', '#btn-import-teams', function () {
    const url = `${APP_URL}/backend/event/${EVENT_ID}/import-teams`;

    Swal.fire({
        title: 'Import Teams?',
        text: 'This will create categories and registrations for all teams.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, import'
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post(url, {}, function (response) {
            toastr.success(response.message);
            location.reload();
        }).fail(function () {
            toastr.error('Import failed.');
        });
    });
});

// Regenerate RR Fixtures for this draw
$(document).on('click', '#btn-regenerate-fixtures', function () {
    Swal.fire({
        title: 'Regenerate Fixtures?',
        html: 'This will <strong>delete existing fixtures</strong> and create new round-robin matches based on current group assignments.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, regenerate',
        confirmButtonColor: '#28a745'
    }).then((result) => {
        if (!result.isConfirmed) return;

        // Show loading
        Swal.fire({
            title: 'Generating...',
            text: 'Please wait while fixtures are being created.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.post(`${APP_URL}/backend/draw/${DRAW_ID}/regenerate-rr`, {}, function (response) {
            Swal.close();
            toastr.success(response.message || 'Fixtures regenerated successfully');
            location.reload();
        }).fail(function (xhr) {
            Swal.close();
            toastr.error(xhr.responseJSON?.message || 'Failed to regenerate fixtures.');
        });
    });
});

// Remove player from group (move back to source)
$(document).on('click', '.btn-remove-from-group', function (e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $item = $(this).closest('li');
    const regId = $item.data('id');
    const playerName = $item.data('player-name');
    
    // Find the first source list and append the item there
    const $sourceList = $('.rr-sortable[data-type="source"]').first();
    if ($sourceList.length) {
        $item.find('.btn-remove-from-group').remove(); // Remove the X button
        $sourceList.append($item);
        toastr.info(`${playerName} removed from group`);
        
        // Update empty placeholder visibility
        updateEmptyPlaceholders();
    }
});

// Update empty group placeholders
function updateEmptyPlaceholders() {
    $('.rr-group').each(function() {
        const $group = $(this);
        const $placeholder = $group.siblings('.empty-group-placeholder');
        const hasItems = $group.children('li').length > 0;
        
        if (hasItems) {
            $placeholder.hide();
        } else {
            if ($placeholder.length === 0) {
                $group.after('<div class="text-muted text-center py-3 empty-group-placeholder"><small>Drop players here</small></div>');
            } else {
                $placeholder.show();
            }
        }
    });
}


// Use jQuery for tab events (more reliable)
$('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (event) {
    const tabId = $(event.target).attr('id');
    console.log('📑 [TAB] Switched to tab:', tabId);
    
    if (tabId === 'matrix-tab') {
        if (window.__RR_MATRIX_RENDERED !== true) {
            if (typeof window.RR_INIT === 'function') {
                window.RR_INIT();
            }
            window.__RR_MATRIX_RENDERED = true;
        }
    }

    if (tabId === 'oop-tab') {
        if (typeof window.renderOrderOfPlay === 'function') {
            window.renderOrderOfPlay();
        }
    }

    if (tabId === 'standings-tab') {
        if (typeof window.renderStandings === 'function') {
            window.renderStandings();
        }
    }
    
    if (tabId === 'groups-tab') {
        console.log('📑 [TAB] Groups tab activated - __GROUPS_SORTABLE_INIT:', window.__GROUPS_SORTABLE_INIT, '__SORTABLE_INIT_PENDING:', window.__SORTABLE_INIT_PENDING);
        // Initialize Sortable on groups tab activation
        if (!window.__GROUPS_SORTABLE_INIT && !window.__SORTABLE_INIT_PENDING) {
            console.log('📑 [TAB] Calling initGroupsSortable...');
            setTimeout(function() {
                initGroupsSortable(true);
            }, 150);
        } else {
            console.log('📑 [TAB] Sortable already initialized or pending');
        }
    }
});


// Initialize Sortable for drag-and-drop
// Store sortable instances for cleanup
window.__SORTABLE_INSTANCES = [];
window.__GROUPS_SORTABLE_INIT = false;
window.__SORTABLE_INIT_PENDING = false; // Prevent multiple simultaneous inits

function initGroupsSortable(forceReinit = false) {
    // Prevent multiple simultaneous initializations
    if (window.__SORTABLE_INIT_PENDING) {
        console.log('⏳ [SORTABLE] Init already pending, skipping...');
        return;
    }
    
    console.log('========================================');
    console.log('🔧 [SORTABLE] initGroupsSortable called');
    console.log('🔧 [SORTABLE] forceReinit:', forceReinit);
    console.log('🔧 [SORTABLE] Current instances:', window.__SORTABLE_INSTANCES ? window.__SORTABLE_INSTANCES.length : 0);
    console.log('🔧 [SORTABLE] __GROUPS_SORTABLE_INIT:', window.__GROUPS_SORTABLE_INIT);
    
    // Check if Sortable library is loaded
    if (typeof Sortable === 'undefined') {
        console.error('❌ [SORTABLE] Sortable library is NOT loaded!');
        return;
    }
    console.log('✅ [SORTABLE] Sortable library is loaded');
    
    // Mark init as pending
    window.__SORTABLE_INIT_PENDING = true;
    
    // Always destroy existing instances when called
    if (window.__SORTABLE_INSTANCES && window.__SORTABLE_INSTANCES.length > 0) {
        console.log('🗑️ [SORTABLE] Destroying', window.__SORTABLE_INSTANCES.length, 'existing instances');
        window.__SORTABLE_INSTANCES.forEach(function(instance, idx) {
            if (instance && typeof instance.destroy === 'function') {
                try {
                    instance.destroy();
                } catch(e) {
                    // Ignore errors during destroy
                }
            }
        });
    }
    window.__SORTABLE_INSTANCES = [];
    window.__GROUPS_SORTABLE_INIT = false;
    
    // Find elements within the groups pane specifically
    const groupsPane = document.getElementById('groups-pane');
    
    if (!groupsPane) {
        console.error('❌ [SORTABLE] Groups pane element not found!');
        window.__SORTABLE_INIT_PENDING = false;
        return;
    }
    
    const sortableElements = groupsPane.querySelectorAll('.rr-sortable');
    console.log('📋 [SORTABLE] Found .rr-sortable elements:', sortableElements.length);
    
    if (sortableElements.length === 0) {
        console.error('❌ [SORTABLE] No .rr-sortable elements found in groups pane!');
        window.__SORTABLE_INIT_PENDING = false;
        return;
    }
    
    sortableElements.forEach(function(el, index) {
        console.log('🔨 [SORTABLE] Initializing element', index, '- type:', el.dataset.type, '- children:', el.children.length);
        
        // Add draggable attribute to all children
        Array.from(el.children).forEach(function(child) {
            child.setAttribute('draggable', 'true');
        });
        
        try {
            const instance = new Sortable(el, {
                group: 'shared-players',
                animation: 200,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                fallbackOnBody: true,
                swapThreshold: 0.3,  // Reduced from 0.65 - less sticky
                forceFallback: false,  // Changed to false - better native drag
                fallbackTolerance: 5,  // Increased from 3
                delay: 0,
                delayOnTouchOnly: false,
                touchStartThreshold: 5,  // Increased from 3
                dragoverBubble: false,
                removeCloneOnHide: true,
                preventOnFilter: false,
                filter: '.btn-remove-from-group',
                onChoose: function(evt) {
                    console.log('📌 [DRAG] onChoose - item selected');
                    // Add visual feedback
                    $(evt.item).css('opacity', '0.7');
                },
                onUnchoose: function(evt) {
                    $(evt.item).css('opacity', '1');
                },
                onStart: function(evt) {
                    console.log('🎯 [DRAG] onStart - item:', evt.item.innerText.trim().substring(0, 30));
                    // Highlight all drop zones
                    $('.rr-sortable').addClass('drop-zone-active');
                },
                onEnd: function(evt) {
                    console.log('🏁 [DRAG] onEnd - from:', evt.from.dataset.type, 'to:', evt.to.dataset.type);
                    
                    // Remove drop zone highlighting
                    $('.rr-sortable').removeClass('drop-zone-active');
                    $(evt.item).css('opacity', '1');
                    
                    const $item = $(evt.item);
                    const $target = $(evt.to);
                    
                    // If dropped into a group, add remove button if not present
                    if ($target.hasClass('rr-group') && $item.find('.btn-remove-from-group').length === 0) {
                        $item.append(`
                            <button type="button" class="btn btn-sm btn-link text-danger float-end p-0 btn-remove-from-group" 
                                    data-id="${$item.data('id')}">
                                <i class="ti ti-x"></i>
                            </button>
                        `);
                    }
                    
                    // If dropped back to source, remove the button
                    if ($target.data('type') === 'source') {
                        $item.find('.btn-remove-from-group').remove();
                    }
                    
                    updateEmptyPlaceholders();
                }
            });
            window.__SORTABLE_INSTANCES.push(instance);
            console.log('✅ [SORTABLE] Instance', index, 'created successfully');
        } catch(e) {
            console.error('❌ Error creating Sortable for element', index, ':', e);
        }
    });
    
    window.__GROUPS_SORTABLE_INIT = true;
    window.__SORTABLE_INIT_PENDING = false;
    console.log('✅ [SORTABLE] COMPLETE - Initialized', window.__SORTABLE_INSTANCES.length, 'sortable instances');
    console.log('========================================');
}


// Auto-init if groups tab is already active
$(document).ready(function() {
    console.log('📄 [READY] Document ready - checking groups tab');
    
    // Check if groups tab is active on page load
    if ($('#groups-tab').hasClass('active') || $('#groups-pane').hasClass('show')) {
        console.log('📌 [READY] Groups tab is already active on load');
        setTimeout(function() {
            initGroupsSortable(true);
        }, 200);
    } else {
        console.log('📌 [READY] Groups tab is NOT active on load - will init when tab is clicked');
    }
});

</script>

<script src="{{ asset('assets/js/draw-roundrobin1.js') }}"></script>

@endsection

