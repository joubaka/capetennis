@php
  $pageConfigs = ['myLayout' => 'vertical'];
@endphp
@extends('layouts.backend')

@section('title', 'Draw workspace — ' . $draw->drawName)

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/draw-roundrobin.css') }}?v={{ filemtime(public_path('assets/css/draw-roundrobin.css')) }}">


@php
  $currentBoxes = (int) (optional($draw->settings)->boxes ?: ($groups->count() ?: 4));
  $roundRobinOnly = $draw->isRoundRobinOnly();
  $assignmentService = app(\App\Services\Draw\GroupAssignmentService::class);
  $eligibleRoster = $assignmentService->eligible($draw)->with(['registration.players', 'categoryEvent.category'])->get()->map(fn ($entry) => [
    'id' => $entry->registration_id, 'name' => $entry->registration->display_name,
    'category_id' => $entry->category_event_id, 'category' => $entry->categoryEvent->category?->name ?? 'Players',
  ])->unique('id')->values();
@endphp
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/draw-workspace.css') }}?v={{ filemtime(public_path('assets/css/draw-workspace.css')) }}">
<div id="round-robin-app" 
   data-draw-id="{{ $draw->id }}">

@include('backend.draw.partials.workspace-header', ['workspaceSurface' => 'roundrobin'])

<div class="rr-readiness">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><strong id="rr-next-step">{{ $draw->drawFixtures->isEmpty() ? 'Start with your players and groups' : 'Your draw workspace' }}</strong><span class="small text-muted" id="rr-fixture-summary">{{ $draw->drawFixtures->count() }} fixtures · {{ $readiness['scored_count'] ?? 0 }} scored</span></div>
  <details class="mt-2"><summary>Readiness and draw status</summary><div class="mt-2">
    @foreach(($readiness['checks'] ?? []) as $check)
      <span class="badge {{ $check['ok'] ? 'bg-label-success' : 'bg-label-warning' }} me-1 mb-1">{{ $check['label'] }}</span>
    @endforeach
  </div></details>
</div>
<nav class="rr-workspace-nav" aria-label="Draw workspace">
  <button type="button" data-workspace="players">Players &amp; Groups</button>
  <button type="button" data-workspace="results">Draw &amp; Results</button>
  <button type="button" data-workspace="schedule">Schedule</button>
  <button type="button" data-workspace="setup">Setup &amp; Rules</button>
</nav>
<ul class="nav nav-tabs mb-3" id="rrTabs" role="tablist">

  {{-- View tabs --}}
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="matrix-tab" data-bs-toggle="tab" data-bs-target="#matrix-pane" type="button" role="tab">
      <i class="ti ti-grid-dots me-1"></i> Matrix
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="oop-tab" data-bs-toggle="tab" data-bs-target="#oop-pane" type="button" role="tab">
      <i class="ti ti-list-details me-1"></i> Order of Play
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="standings-tab" data-bs-toggle="tab" data-bs-target="#standings-pane" type="button" role="tab">
      <i class="ti ti-chart-bar me-1"></i> Standings
    </button>
  </li>
  @unless($roundRobinOnly)
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="main-bracket-tab" data-bs-toggle="tab" data-bs-target="#main-bracket-pane" type="button" role="tab">
      <i class="ti ti-tournament me-1"></i> Brackets
    </button>
  </li>
  @endunless

  {{-- Admin tabs --}}
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups-pane" type="button" role="tab">
      <i class="ti ti-users me-1"></i> Players &amp; Groups
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab">
      <i class="ti ti-settings me-1"></i> Settings
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="print-tab" data-bs-toggle="tab" data-bs-target="#print-pane" type="button" role="tab">
      <i class="ti ti-printer me-1"></i> Print
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule-pane" type="button" role="tab">
      <i class="ti ti-calendar me-1"></i> Schedule &amp; Venues
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes-pane" type="button" role="tab">
      <i class="ti ti-notes me-1"></i> Rules &amp; Notes
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
              <input type="hidden" id="settings-boxes" value="{{ $currentBoxes }}">
              <div class="col-md-4"><label class="form-label fw-bold">Groups</label><p class="mb-0">Manage group sizes and players in <button type="button" class="btn btn-link p-0" data-open-workspace="players">Players &amp; Groups</button>.</p></div>
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



          </form>
        </div>
      </div>

      @unless($roundRobinOnly)
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

            // Merge group_order from preset template into stored playoff_config entries.
            // This ensures draws saved before group_order was introduced still get the
            // correct group ordering in the preview — without altering stored data.
            if ($savedPresetKey && isset($presetTemplates[$savedPresetKey]['config'])) {
                $templateConfig = collect($presetTemplates[$savedPresetKey]['config'])->keyBy('slug');
                $playoffConfig = array_map(function ($entry) use ($templateConfig) {
                    $slug = $entry['slug'] ?? null;
                    if ($slug && !isset($entry['group_order']) && isset($templateConfig[$slug]['group_order'])) {
                        $entry['group_order'] = $templateConfig[$slug]['group_order'];
                    }
                    return $entry;
                }, $playoffConfig);
            }
            
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
                Showing presets for {{ $currentBoxes }} group{{ $currentBoxes > 1 ? 's' : '' }}. Change group count in Players &amp; Groups to see other presets.
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
        <details class="rr-advanced"><summary>Advanced previews · player accounting and seeding</summary>
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

      </details>
      @endunless
    </div>

    {{-- ============================
         MATRIX TAB
       ============================ --}}
    <div class="tab-pane fade show active" id="matrix-pane" role="tabpanel">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-1"><i class="ti ti-grid-dots me-1 text-primary"></i> Round Robin Matrix</h5>
            <small id="rr-matrix-help" class="text-muted">Select a matchup to enter or update its result.</small>
          </div>
          <span id="rr-matrix-status" class="small text-muted" role="status" aria-live="polite"></span>
        </div>
        <div class="card-body p-0">
          <div id="rr-matrix-wrapper" class="p-2">
            <div class="text-center text-muted py-5" id="rr-matrix-loading">
              <div class="spinner-border spinner-border-sm"></div>
              <div class="mt-2">Loading round-robin grid…</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ============================
         ORDER OF PLAY TAB
       ============================ --}}
    <div class="tab-pane fade" id="oop-pane" role="tabpanel">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-0"><i class="ti ti-broadcast me-1 text-primary"></i> Live Operations</h5>
            <small class="text-muted">Find the next match, monitor courts, and enter results quickly.</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" id="rr-refresh-ops-btn"><i class="ti ti-refresh me-1"></i>Refresh</button>
            <button class="btn btn-sm btn-primary" id="rr-save-order-btn"><i class="ti ti-device-floppy me-1"></i>Save Order</button>
          </div>
        </div>
        <div class="card-body border-bottom bg-light py-2">
          <div class="row g-2">
            <div class="col-12 col-md-5"><input id="rr-ops-search" class="form-control form-control-sm" placeholder="Search player or match number…"></div>
            <div class="col-6 col-md-3"><select id="rr-ops-status" class="form-select form-select-sm"><option value="all">All statuses</option><option value="upcoming">Upcoming</option><option value="completed">Completed</option></select></div>
            <div class="col-6 col-md-3"><select id="rr-ops-court" class="form-select form-select-sm"><option value="all">All courts</option></select></div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="rr-order-table">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Player 1</th>
                  <th class="text-center">VS</th>
                  <th>Player 2</th>
                  <th class="text-center">Round</th>
                  <th class="text-center">Group</th>
                  <th class="text-center d-none d-sm-table-cell">Time</th>
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
         STANDINGS TAB
       ============================ --}}
<div class="tab-pane fade" id="standings-pane" role="tabpanel">

  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-chart-bar me-1 text-primary"></i> Standings</h5>
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


@include('backend.draw.roundrobin.players-workspace')

@unless($roundRobinOnly)
<div class="tab-pane fade" id="main-bracket-pane" role="tabpanel">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
        <h5 class="mb-0"><i class="ti ti-tournament me-1"></i> Playoff Brackets</h5>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-success" id="btn-generate-main-bracket" data-rr-destructive>
              <i class="ti ti-refresh me-1"></i> Generate All Playoffs
          </button>
        </div>
    </div>

    {{-- Zoom Controls --}}
    <div class="bracket-zoom-controls mb-2" id="bracket-zoom-bar">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="bracket-zoom-out" title="Zoom out">−</button>
      <span class="bracket-zoom-level" id="bracket-zoom-label">100%</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="bracket-zoom-in" title="Zoom in">+</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="bracket-zoom-reset" title="Reset zoom">↺</button>
      <span class="bracket-zoom-hint"><i class="ti ti-pinch me-1"></i>Pinch to zoom</span>
    </div>

    {{-- Main Bracket Container (loaded via AJAX) --}}
    <div id="main-bracket-wrapper" class="overflow-auto" style="touch-action: pan-x pan-y;">
      <div id="bracket-zoom-inner">
        <div class="text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm"></div>
          <div class="mt-2">Loading playoff brackets…</div>
        </div>
      </div>
    </div>
   

</div>
@endunless

{{-- ============================
     PRINT TAB
   ============================ --}}
<div class="tab-pane fade" id="print-pane" role="tabpanel">
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-printer me-1"></i> Print Options</h5>
      <small class="text-muted">{{ $roundRobinOnly ? 'Generate print-friendly round-robin fixtures, matrices and standings' : 'Generate print-friendly pages for fixtures, matrix, brackets and blank draws' }}</small>
    </div>
    <div class="card-body">
      <div class="row g-4">

        {{-- Print Fixtures --}}
        <div class="col-6 col-md-3">
          <div class="card border h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
              <i class="ti ti-list-details mb-3" style="font-size: 2.5rem; color: #0d6efd;"></i>
              <h6 class="fw-bold mb-1">Order of Play</h6>
              <p class="text-muted small mb-3">All fixtures with stage, round and scores.</p>
              <button class="btn btn-primary btn-sm" id="btn-print-fixtures">
                <i class="ti ti-printer me-1"></i> Print Fixtures
              </button>
            </div>
          </div>
        </div>

        {{-- Print Matrix --}}
        <div class="col-6 col-md-3">
          <div class="card border h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
              <i class="ti ti-grid-dots mb-3" style="font-size: 2.5rem; color: #198754;"></i>
              <h6 class="fw-bold mb-1">Round Robin Matrix</h6>
              <p class="text-muted small mb-2">Matrix grid with all scores per group.</p>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="chk-print-standings">
                <label class="form-check-label small" for="chk-print-standings">Include Standings</label>
              </div>
              <button class="btn btn-success btn-sm" id="btn-print-matrix">
                <i class="ti ti-printer me-1"></i> Print Matrix
              </button>
            </div>
          </div>
        </div>

        @unless($roundRobinOnly)
        {{-- Print Bracket (with names) --}}
        <div class="col-6 col-md-3">
          <div class="card border h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
              <i class="ti ti-tournament mb-3" style="font-size: 2.5rem; color: #0d6efd;"></i>
              <h6 class="fw-bold mb-1">Playoff Bracket</h6>
              <p class="text-muted small mb-3">Full bracket with player names and scores.</p>
              <button class="btn btn-primary btn-sm" id="btn-print-bracket">
                <i class="ti ti-printer me-1"></i> Print Bracket
              </button>
            </div>
          </div>
        </div>

        {{-- Print Empty Bracket --}}
        <div class="col-6 col-md-3">
          <div class="card border h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
              <i class="ti ti-tournament mb-3" style="font-size: 2.5rem; color: #6f42c1;"></i>
              <h6 class="fw-bold mb-1">Empty Bracket</h6>
              <p class="text-muted small mb-3">Blank structure — no names, for manual use.</p>
              <button class="btn btn-outline-dark btn-sm" id="btn-print-empty-bracket">
                <i class="ti ti-printer me-1"></i> Print Empty Bracket
              </button>
            </div>
          </div>
        </div>
        @endunless

        {{-- Print Combined (Matrix + Fixtures on 1 page) --}}
        <div class="col-6 col-md-3">
          <div class="card border h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
              <i class="ti ti-layout-rows mb-3" style="font-size: 2.5rem; color: #e65100;"></i>
              <h6 class="fw-bold mb-1">Matrix + Fixtures</h6>
              <p class="text-muted small mb-3">Matrix on top, fixtures below — one page.</p>
              <button class="btn btn-warning btn-sm" id="btn-print-combined">
                <i class="ti ti-printer me-1"></i> Print Combined
              </button>
            </div>
          </div>
        </div>

        {{-- Print Draw Pack (everything) --}}
        <div class="col-6 col-md-3">
          <div class="card border border-dark h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-3">
              <i class="ti ti-package mb-2" style="font-size: 2.5rem; color: #212529;"></i>
              <h6 class="fw-bold mb-2">Draw Pack</h6>
              <div class="text-start w-100 px-2 mb-2" style="font-size:12px;">
                <div class="form-check mb-1">
                  <input class="form-check-input pack-section" type="checkbox" id="pack-notes" checked>
                  <label class="form-check-label" for="pack-notes">Rules &amp; Notes</label>
                </div>
                <div class="form-check mb-1">
                  <input class="form-check-input pack-section" type="checkbox" id="pack-matrix" checked>
                  <label class="form-check-label" for="pack-matrix">RR Matrix</label>
                </div>
                <div class="form-check mb-1">
                  <input class="form-check-input pack-section" type="checkbox" id="pack-rr-fixtures" checked>
                  <label class="form-check-label" for="pack-rr-fixtures">RR Fixtures</label>
                </div>
                @unless($roundRobinOnly)
                <div class="form-check mb-1">
                  <input class="form-check-input pack-section" type="checkbox" id="pack-playoff-fixtures" checked>
                  <label class="form-check-label" for="pack-playoff-fixtures">Playoff Fixtures</label>
                </div>
                <div class="form-check mb-1">
                  <input class="form-check-input pack-section" type="checkbox" id="pack-brackets" checked>
                  <label class="form-check-label" for="pack-brackets">Blank Brackets</label>
                </div>
                @endunless
              </div>
              <button class="btn btn-dark btn-sm" id="btn-print-draw-pack">
                <i class="ti ti-printer me-1"></i> Print Draw Pack
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- ============================
     SCHEDULE & VENUES TAB
   ============================ --}}
<div class="tab-pane fade" id="schedule-pane" role="tabpanel">
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0"><i class="ti ti-map-pin me-1 text-primary"></i> Venues</h5>
        <small class="text-muted">Manage venues assigned to this draw</small>
      </div>
      <button type="button" class="btn btn-primary btn-sm addVenues" data-id="{{ $draw->id }}" data-bs-toggle="modal" data-bs-target="#basicModal">
        <i class="ti ti-plus me-1"></i> Add Venue
      </button>
    </div>
    <div class="card-body">
      <div id="rr-venues-list">
        @forelse($draw->venues as $venue)
          <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
            <div>
              <strong>{{ $venue->name }}</strong>
              <span class="badge bg-label-info ms-2">{{ $venue->pivot->num_courts }} court{{ $venue->pivot->num_courts != 1 ? 's' : '' }}</span>
            </div>
            <button class="btn btn-sm btn-outline-danger deleteVenue" data-id="{{ $draw->id }}" data-venue="{{ $venue->id }}">
              <i class="ti ti-trash me-1"></i> Remove
            </button>
          </div>
        @empty
          <div class="text-muted text-center py-3">
            <i class="ti ti-map-pin-off fs-3 d-block mb-2"></i>
            No venues assigned. Add a venue to enable scheduling.
          </div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0"><i class="ti ti-calendar me-1 text-primary"></i> Schedule</h5>
        <small class="text-muted">Assign times, venues and courts to matches</small>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
          <i class="ti ti-calendar-plus me-1"></i> Schedule Matches
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0" id="rr-schedule-table">
          <thead class="table-light">
            <tr>
              <th>M#</th>
              <th>Player 1</th>
              <th class="text-center">vs</th>
              <th>Player 2</th>
              <th class="text-center">Venue</th>
              <th class="text-center">Court</th>
              <th class="text-center">Time</th>
            </tr>
          </thead>
          <tbody id="rr-schedule-body">
            {{-- Populated by JS --}}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- ============================
     RULES & NOTES TAB
   ============================ --}}
<div class="tab-pane fade" id="notes-pane" role="tabpanel">
  @php
    $drawNotes = optional($draw->settings)->notes ?? [];
    $playoffConfig = optional($draw->settings)->playoff_config ?? [];
    $enabledBrackets = collect($playoffConfig)->where('enabled', true)->values();
    $defaultGeneralNotes = "General Rules\n\nPlayers must be ready to play at their scheduled time.\nA 5-minute warm-up is allowed before the match starts.\nStandard ITF tennis rules apply unless otherwise specified by the tournament organizer.\nThe tournament referee's decision is final in all disputes.";
    $defaultRRNotes = "Round Robin Match Format\n\nMatches consist of 1 set starting from 0–0.\nThe first player/team to 4 games wins the set.\nAt 3–3, a tiebreaker is played.\nAdvantage scoring applies in all games.";
    $defaultPlayoffNotes = "Top Bracket Match Format\n\nMatches are played as Best of 3 sets.\nEach set starts at 2–2.\nAdvantage scoring applies in all games.\nIf a third set is required, it is played as a 10-point match tiebreak.";
    $defaultBracketNotes = "Other Brackets Match Format\n\nMatches consist of 1 full set starting from 0–0.\nThe first player/team to 6 games wins the set.\nAt 6–6, a tiebreaker is played.\nAdvantage scoring applies in all games.";
  @endphp
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0"><i class="ti ti-notes me-1"></i> Rules & Notes</h5>
        <small class="text-muted">Edit rules for each section. These will appear on printed draw packs.</small>
      </div>
      <button class="btn btn-success btn-sm" id="btn-save-notes">
        <i class="ti ti-device-floppy me-1"></i> Save All Notes
      </button>
    </div>
    <div class="card-body">
      <div class="row g-4">

        {{-- General Rules --}}
        <div class="col-md-6">
          <div class="card border h-100">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="ti ti-info-circle me-1 text-primary"></i> General Rules</h6>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input notes-enabled" type="checkbox" checked>
                <label class="form-check-label small text-muted">Print</label>
              </div>
            </div>
            <div class="card-body p-2">
              <textarea class="form-control notes-field" data-key="general" rows="6" placeholder="Enter general event rules...">{{ $drawNotes['general'] ?? $defaultGeneralNotes }}</textarea>
            </div>
          </div>
        </div>

        {{-- Round Robin Scoring --}}
        <div class="col-md-6">
          <div class="card border h-100">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="ti ti-tournament me-1 text-success"></i> Round Robin Scoring Rules</h6>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input notes-enabled" type="checkbox" checked>
                <label class="form-check-label small text-muted">Print</label>
              </div>
            </div>
            <div class="card-body p-2">
              <textarea class="form-control notes-field" data-key="round_robin" rows="6" placeholder="e.g. Best of 3 sets, tiebreak at 6-all, 10-point match tiebreak in 3rd...">{{ $drawNotes['round_robin'] ?? $defaultRRNotes }}</textarea>
            </div>
          </div>
        </div>

        @unless($roundRobinOnly)
        {{-- Playoff Rules --}}
        <div class="col-md-6">
          <div class="card border h-100">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="ti ti-trophy me-1 text-warning"></i> Playoff Rules</h6>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input notes-enabled" type="checkbox" checked>
                <label class="form-check-label small text-muted">Print</label>
              </div>
            </div>
            <div class="card-body p-2">
              <textarea class="form-control notes-field" data-key="playoffs" rows="6" placeholder="e.g. Single elimination, 3rd/4th playoff for losers of semis...">{{ $drawNotes['playoffs'] ?? $defaultPlayoffNotes }}</textarea>
            </div>
          </div>
        </div>

        {{-- Per-bracket rules for each enabled bracket --}}
        @foreach($enabledBrackets as $bracket)
          <div class="col-md-6">
            <div class="card border h-100">
              <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                  <i class="ti ti-brackets me-1 text-info"></i>
                  {{ $bracket['name'] ?? 'Bracket' }} Rules
                  <span class="badge bg-secondary ms-1" style="font-size: 10px;">{{ $bracket['slug'] }}</span>
                </h6>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input notes-enabled" type="checkbox" checked>
                  <label class="form-check-label small text-muted">Print</label>
                </div>
              </div>
              <div class="card-body p-2">
                <textarea class="form-control notes-field" data-key="bracket_{{ $bracket['slug'] }}" rows="5"
                  placeholder="Rules specific to {{ $bracket['name'] ?? 'this bracket' }}...">{{ $drawNotes['bracket_' . $bracket['slug']] ?? (($bracket['slug'] ?? '') === 'main' ? $defaultPlayoffNotes : $defaultBracketNotes) }}</textarea>
              </div>
            </div>
          </div>
        @endforeach
        @endunless

      </div>
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

      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger" id="rrm-delete-score">
          <i class="ti ti-trash me-1"></i> Delete Score
        </button>
        <div>
          <button type="submit" class="btn btn-primary">Save Score</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>

    </form>
  </div>
</div>

{{-- Venue Modal --}}
@include('backend.draw._modals.addVenueModal')

{{-- Schedule Modal --}}
<input type="hidden" id="drawId" value="{{ $draw->id }}">
@include('backend.headOffice.modals.scheduleModal')

@endsection



@section('page-script')

<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>

<script>
    window.RR_ROSTER = @json($eligibleRoster);
    window.RR_ASSIGNMENT_REVISION = @json($assignmentService->revision($draw));
    window.RR_CAN_ASSIGN = @json(auth()->user()->can('modifyGroups', $draw));
    window.RR_CAN_GENERATE = @json(auth()->user()->can('generateFixtures', $draw));
    window.RR_CAN_SCORE = @json(auth()->user()->can('saveScore', $draw));
    window.RR_CAN_SCHEDULE = @json(auth()->user()->can('modifySchedule', $draw));
    window.RR_FIXTURES  = @json($rrFixtures);
    window.RR_GROUPS    = @json($groupsjson);   // THE ONLY CORRECT ONE
    window.RR_OOP       = @json($oops);
    window.RR_STANDINGS = @json($standings);

    window.RR_DRAW_LOCKED    = {{ $draw->locked ? 'true' : 'false' }};
    window.RR_DRAW_PUBLISHED = {{ $draw->published ? 'true' : 'false' }};
    window.RR_ENGINE_MODE    = "{{ $draw->engine_mode ?? 'legacy' }}";

    // Canonical permissions object — mirrors DrawMutationPolicy::for($draw)->toArray()
    // All frontend lock checks should read from here, not just RR_DRAW_LOCKED.
    window.RR_PERMISSIONS = @json(\App\Services\Draw\DrawMutationPolicy::for($draw)->toArray());

    window.RR_SAVE_SCORE_URL   = "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}";
    window.RR_DELETE_SCORE_URL = "{{ route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID']) }}";

    // Canonical RR API routes
    window.RR_ROUTES = {
        hub:           "{{ route('api.draws.hub', $draw) }}",
        scoreStore:    "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}",
        scoreDelete:   "{{ route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID']) }}",
        groupsSave:    "{{ route('api.draws.groups.save', $draw) }}",
        scheduleSave:  "{{ route('api.draws.schedule.save', $draw) }}",
        scheduleSummary: "{{ route('api.draws.schedule.summary', $draw) }}",

        // Legacy web routes (still active during transition)
        legacyScoreStore:  "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}",
        legacyScoreDelete: "{{ route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID']) }}",
        groupsData:    "{{ route('backend.draw.groups-data', $draw) }}",
        availablePlayers: "{{ route('backend.draw.available-players', $draw) }}",
        regenerateRR:  "{{ route('backend.draw.regenerate-rr', $draw) }}",
        toggleLock:    "{{ route('backend.draw.toggle-lock', $draw) }}",
        saveGroups:    "{{ route('backend.draw.save-groups', $draw) }}",
        generateMainBracket:  "{{ route('backend.draw.generate-main-bracket', $draw) }}",
        generatePlateBracket: "{{ route('backend.draw.generate-second-third-bracket', $draw) }}",
        mainBracket:   "{{ route('backend.draw.main-bracket', $draw) }}",
        plateBracket:  "{{ route('backend.draw.plate-bracket', $draw) }}",
    };

    window.EVENT_ID = {{ $draw->event_id }};
    const DRAW_ID   = {{ $draw->id }};
</script>

<script>
// ─── UI STATE HELPERS — thin shims, real logic in state-badges.js ─
window.rrToast = function(message, type) {
    if (window.AdminToast) { AdminToast.show(message, type === 'danger' ? 'error' : (type || 'success')); return; }
    if (typeof toastr !== 'undefined') { toastr[type === 'danger' ? 'error' : (type || 'success')](message); }
};
window.rrApplyDrawState = function(locked, published) {
    if (window.RRStateBadges) { RRStateBadges.apply(locked, published); return; }
    // Fallback until module boots
    $('#badge-locked').toggleClass('d-none', !locked);
    $('#badge-published').toggleClass('d-none', !published);
};
</script>

<script>
// ─── AJAX REFRESH HELPERS — delegate to modules when available ────
function refreshGroupsUI() { return window.RRGroups ? RRGroups.refresh() : Promise.resolve(); }
function refreshAvailablePlayersUI() { return refreshGroupsUI(); }
function refreshGroupsAndPlayers()  { if (window.RRGroups)   RRGroups.refreshGroupsAndPlayers(); }
function refreshVenuesUI()          { if (window.RRSchedule) RRSchedule.refreshVenuesUI(); }
</script>

{{-- toastr already loaded by layout; Sortable and SweetAlert2 loaded here --}}
<script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>

{{-- ═══════════════════════════════════════════════════════════
     Admin Core — shared utilities
     ═══════════════════════════════════════════════════════════ --}}
<script src="{{ asset('assets/js/admin/core/api.js') }}"></script>
<script src="{{ asset('assets/js/admin/core/toast.js') }}"></script>
<script src="{{ asset('assets/js/admin/core/modal.js') }}"></script>
<script src="{{ asset('assets/js/admin/core/loading.js') }}"></script>
<script src="{{ asset('assets/js/admin/core/confirm.js') }}"></script>
<script src="{{ asset('assets/js/admin/core/routes.js') }}"></script>
<script src="{{ asset('assets/js/admin/core/state.js') }}"></script>

{{-- ═══════════════════════════════════════════════════════════
     RR Page Modules
     ═══════════════════════════════════════════════════════════ --}}
<script src="{{ asset('assets/js/admin/roundrobin/matrix.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/matrix.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/scores.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/scores.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/standings.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/standings.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/oop.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/oop.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/groups.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/groups.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/schedule.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/schedule.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/brackets.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/brackets.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/state-badges.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/state-badges.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/workspace.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/workspace.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/init.js') }}?v={{ filemtime(public_path('assets/js/admin/roundrobin/init.js')) }}"></script>

@include('backend.draw.roundrobin.setup-scripts')





@include('backend.draw.roundrobin.print-scripts')



<script>
// ---- SAVE NOTES ----
(function($) {
  $(document).ready(function() {
    $('#btn-save-notes').on('click', function() {
      var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving…');
      var notes = {};
      $('.notes-field').each(function() {
        notes[$(this).data('key')] = $(this).val();
      });
      $.post(APP_URL + '/backend/draw/' + DRAW_ID + '/notes', { notes: notes })
        .done(function(res) {
          toastr.success(res.message || 'Notes saved');
        })
        .fail(function(xhr) {
          toastr.error(xhr.responseJSON?.message || 'Failed to save notes');
        })
        .always(function() {
          $btn.prop('disabled', false).html('<i class="ti ti-device-floppy me-1"></i> Save All Notes');
        });
    });
  });
})(jQuery);
</script>

@endsection
