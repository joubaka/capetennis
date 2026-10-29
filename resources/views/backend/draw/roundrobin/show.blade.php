@php
  $pageConfigs = ['myLayout' => 'vertical'];
@endphp
@extends('layouts.backend')

@section('title', 'Draw workspace — ' . $draw->drawName)

@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')

<link rel="stylesheet" href="{{ asset('assets/css/draw-roundrobin.css') }}?v={{ filemtime(public_path('assets/css/draw-roundrobin.css')) }}">


@php
  $roundRobinOnly = $draw->isRoundRobinOnly();
  $competitionRulesEditable = auth()->user()->can('editCompetitionRules', $draw);
  $assignmentService = app(\App\Services\Draw\GroupAssignmentService::class);
  $eligibleRoster = $assignmentService->eligible($draw)->with(['registration.players', 'categoryEvent.category'])->get()->map(fn ($entry) => [
    'id' => $entry->registration_id, 'name' => $entry->registration->display_name,
    'category_id' => $entry->category_event_id, 'category' => $entry->categoryEvent->category?->name ?? 'Players',
  ])->unique('id')->values();
  $suggestedBoxes = $eligibleRoster->count() >= 2 && $eligibleRoster->count() <= 4 ? 1 : 4;
  $currentBoxes = (int) (optional($draw->settings)->boxes ?: ($groups->count() ?: $suggestedBoxes));
@endphp
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/draw-workspace.css') }}?v={{ filemtime(public_path('assets/css/draw-workspace.css')) }}">
<div id="round-robin-app"
   data-draw-id="{{ $draw->id }}">

@include('backend.draw.partials.workspace-header', ['workspaceSurface' => 'roundrobin'])
@include('backend.draw.roundrobin.progression-status')

@if(!$draw->isTeamDraw() && in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true) && app(\App\Services\Draw\GroupAssignmentService::class)->fixturesNeedRegeneration($draw))
  <div class="alert alert-warning" role="alert">
    <strong>Saved groups differ from the generated matches.</strong>
    Review the saved groups and existing matches before continuing.
    <a href="{{ route('backend.draw.roundrobin.show', $draw) }}#groups">Open Players &amp; Groups</a>
  </div>
@endif

<div class="rr-readiness">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><strong id="rr-next-step">{{ $draw->drawFixtures->isEmpty() ? 'Start with your players and groups' : 'Your draw workspace' }}</strong><span class="small text-muted" id="rr-fixture-summary">{{ $draw->drawFixtures->count() }} fixtures · {{ $readiness['scored_count'] ?? 0 }} scored</span></div>
  <details class="mt-2"><summary>Readiness and draw status</summary><div class="mt-2">
    @foreach(($readiness['checks'] ?? []) as $check)
      <span class="badge {{ $check['ok'] ? 'bg-label-success' : 'bg-label-warning' }} me-1 mb-1">{{ $check['label'] }}</span>
    @endforeach
  </div></details>
</div>
<nav class="rr-workspace-nav" aria-label="Draw workspace">
  @unless($draw->isTeamDraw())<a href="{{ route('draw.setup.show', $draw) }}">Format</a>@endunless
  <button type="button" data-workspace="setup">Draw settings</button>
  <button type="button" data-workspace="players">{{ $draw->isTeamDraw() ? 'Players & Groups' : 'Players & Positions' }}</button>
  <button type="button" data-workspace="results">Draw &amp; Results</button>
  @if($draw->isTeamDraw())<button type="button" data-workspace="schedule" data-workspace-view="oop">Schedule</button>@endif
  <button type="button" data-workspace="rules">Rules &amp; notes</button>
</nav>
<div class="d-flex flex-wrap align-items-start gap-2 mb-3">
<ul class="nav nav-tabs mb-0 flex-grow-1" id="rrTabs" role="tablist">

  {{-- View tabs --}}
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="matrix-tab" data-bs-toggle="tab" data-bs-target="#matrix-pane" type="button" role="tab">
      <i class="ti ti-grid-dots me-1"></i> Round robin
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="oop-tab" data-bs-toggle="tab" data-bs-target="#oop-pane" type="button" role="tab">
      <i class="ti ti-list-details me-1"></i> Match list
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
      <i class="ti ti-tournament me-1"></i> Playoffs
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
<a class="btn btn-primary d-none" data-full-schedule-action href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'manual' => 1]) }}">
  <i class="ti ti-calendar-cog me-1" aria-hidden="true"></i> Schedule multiple draws
</a>
</div>

  {{-- ============================
       TAB CONTENT
     ============================ --}}
  <div class="tab-content" id="rrTabsContent">

    {{-- SETTINGS TAB --}}
    <div class="tab-pane fade" id="settings-pane" role="tabpanel">
      @if(!$competitionRulesEditable)
        <div class="alert alert-warning" role="status">Match format and playoff rules are locked because this draw is locked or its first result has been recorded.</div>
      @elseif($draw->published)
        <div class="alert alert-info" role="status">This draw is published. Match format and playoff rules remain editable until the first result is recorded.</div>
      @endif

      {{-- DRAW OVERVIEW --}}
      <details class="card mb-3 border-info"><summary class="p-3">Draw overview and group totals</summary><div>
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="ti ti-info-circle me-1"></i> Draw Overview</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            {{-- Total Players --}}
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-primary" id="rr-overview-player-count">
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
                <h3 class="mb-1 text-success" id="rr-overview-group-count">{{ $groups->count() }}</h3>
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
                <h3 class="mb-1 text-danger" id="rr-overview-match-count">
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
            <div class="row g-2" id="rr-overview-group-distribution">
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

      </details>
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


      @php
        $scoreFormats = \App\Domain\Draws\Services\TennisScoreFormat::catalog();
        $currentScoreFormat = $draw->settings?->score_format;
        $legacySets = max(1, min(5, (int) ($draw->settings?->num_sets ?: 3)));
      @endphp
      <div class="card mb-3" id="saved-draw-settings">
        <div class="card-header"><h5 class="mb-0">Current setup</h5></div>
        <div class="card-body">
          <p><strong>Groups:</strong> {{ $currentBoxes }} · <strong>Match scoring:</strong> {{ $scoreFormats[$currentScoreFormat]['label'] ?? "Legacy: maximum {$legacySets} sets" }}</p>
          @if($currentScoreFormat === 'timed')
            <p class="mb-2">{{ $draw->settings->match_duration_minutes }} minutes playing time + {{ $draw->settings->changeover_minutes }} minutes changeover.</p>
          @endif
          @unless($roundRobinOnly)
            <div id="rr-saved-playoff-summary">
            @php
              $unusedPlayoffs = collect($playoffConfig)->filter(fn ($playoff) => !($playoff['enabled'] ?? false) || app(\App\Services\Draw\PlayoffParticipationService::class)->isDormant($draw, $playoff));
            @endphp
            <ul class="mb-0">
              @foreach($playoffConfig as $playoff)
                @continue($unusedPlayoffs->contains('slug', $playoff['slug']))
                <li>{{ $playoff['name'] }} — {{ ($playoff['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}, {{ $playoff['size'] }} places, group positions {{ implode(', ', $playoff['positions'] ?? []) ?: 'none' }}</li>
              @endforeach
            </ul>
            @if($unusedPlayoffs->isNotEmpty())
              <details class="mt-2"><summary>Unused preset playoffs ({{ $unusedPlayoffs->count() }})</summary><ul class="mt-2 mb-0">
                @foreach($unusedPlayoffs as $playoff)
                  <li>{{ $playoff['name'] }} — {{ ($playoff['enabled'] ?? false) ? 'Awaiting qualifying positions' : 'Disabled' }}, group positions {{ implode(', ', $playoff['positions'] ?? []) ?: 'none' }}</li>
                @endforeach
              </ul></details>
            @endif
            </div>
          @endunless
        </div>
      </div>
      <form id="drawSettingsForm">
      @csrf
      {{-- BASIC SETTINGS --}}
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0">Change settings</h5>
        </div>
        <div class="card-body">
          <p class="text-muted">Start with your saved setup. Review the changes below, then save once.</p>

            <div class="row g-3">
              <input type="hidden" id="settings-boxes" value="{{ $currentBoxes }}">
              <div class="col-md-4"><label class="form-label fw-bold">Groups</label><p class="mb-0"><button type="button" class="btn btn-link p-0" data-open-workspace="players">Change groups and players</button></p></div>
              @php
                $scoreFormats = \App\Domain\Draws\Services\TennisScoreFormat::catalog();
                $currentScoreFormat = optional($draw->settings)->score_format;
                $legacySets = max(1, min(5, (int) (optional($draw->settings)->num_sets ?: 3)));
              @endphp
              <div class="col-md-5">
                <label class="form-label fw-bold" for="score-format">Match Scoring Format</label>
                <select name="score_format" id="score-format" class="form-select">
                  @if(!$currentScoreFormat)
                    <option value="" selected>Legacy: maximum {{ $legacySets }} sets (keep current)</option>
                  @endif
                  @foreach(\App\Domain\Draws\Services\TennisScoreFormat::groupedCatalog() as $groupLabel => $formats)
                    <optgroup label="{{ $groupLabel }}">
                      @foreach($formats as $key => $format)
                        @continue($key === 'timed' && $draw->isTeamDraw())
                        <option value="{{ $key }}" data-description="{{ $format['description'] }}" data-max-sets="{{ $format['max_sets'] }}"
                          @selected($currentScoreFormat === $key)>{{ $format['label'] }}</option>
                      @endforeach
                    </optgroup>
                  @endforeach
                </select>
                <div class="form-text" id="score-format-help">
                  {{ $currentScoreFormat ? $scoreFormats[$currentScoreFormat]['description'] : 'Choose a preset so score entry and validation use the same rules.' }}
                </div>
              </div>

            </div>
            <fieldset class="border rounded p-3 mt-3" id="timed-match-settings" @if($currentScoreFormat !== 'timed') hidden @endif>
              <legend class="float-none w-auto fs-6 px-1">Timed matches</legend>
              <div class="row g-3">
                <div class="col-sm-6"><label for="match-duration-minutes" class="form-label">Playing time (minutes)</label><input type="number" id="match-duration-minutes" class="form-control" min="1" max="240" step="1" value="{{ $draw->settings?->match_duration_minutes ?? 20 }}"></div>
                <div class="col-sm-6"><label for="changeover-minutes" class="form-label">Changeover gap (minutes)</label><input type="number" id="changeover-minutes" class="form-control" min="0" max="60" step="1" value="{{ $draw->settings?->changeover_minutes ?? 5 }}"></div>
              </div>
              <p class="small text-muted mt-2 mb-0" id="timed-slot-summary" aria-live="polite"></p>
              <p class="small mb-0 mt-2">Most games wins when time expires. Group ties are draws (win 2 points, draw 1, loss 0). A tied playoff is decided by one deciding point. Scheduling uses playing time plus the changeover gap; player rest remains separate.</p>
            </fieldset>
          @unless($roundRobinOnly)
          <hr class="my-4">

      {{-- PLAYOFF CONFIGURATION --}}
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ti ti-tournament me-1"></i> Playoff Configuration</h5>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-playoff">
            <i class="ti ti-plus"></i> Add Playoff
          </button>
        </div>
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

          <div class="d-flex flex-wrap gap-3 mb-3">
            <label class="form-check"><input type="checkbox" class="form-check-input" id="rr-show-disabled"><span class="form-check-label" id="rr-show-disabled-label">Show unused playoffs</span></label>
            <label class="form-check"><input type="checkbox" class="form-check-input" id="rr-show-future-positions"><span class="form-check-label">Show future positions</span></label>
          </div>
          <p class="small text-muted">Previews count saved player assignments. Preset positions and group order are preserved, including stages awaiting qualifying positions. Unused stages are hidden until they have qualifiers.</p>
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
                {{-- Rendered from the complete saved draft by setup-scripts. --}}
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
          </div>
          @endunless
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Changes preview</h5></div>
        <div class="card-body">
          <div id="draw-settings-changes" role="status" aria-live="polite"><p class="text-muted mb-0">No changes.</p></div>
          <p class="small text-muted mt-3">Changes apply to match scoring and playoff rules. Existing matches are not regenerated here.</p>
          <div id="draw-settings-error" class="text-danger mb-2" role="alert"></div>
          <button type="submit" class="btn btn-primary" id="btn-save-settings" disabled>Save changes</button>
        </div>
      </div>
      </form>
      @unless($roundRobinOnly)
      <details class="mb-3"><summary class="p-3">View full setup</summary>
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
            <h5 class="card-title mb-0"><i class="ti ti-broadcast me-1 text-primary"></i> Match list</h5>
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
    <h5 class="mb-3"><i class="ti ti-tournament me-1"></i> Playoff Brackets</h5>

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
      <button type="button" class="btn btn-primary btn-sm addVenues" id="rr-add-venues" data-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}">
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
            @if($draw->isTeamDraw())
              No venues assigned. Add a venue to enable scheduling.
            @else
              No draw venue defaults. Choose event venues and courts in Schedule this draw.
            @endif
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
        <a class="btn btn-outline-primary btn-sm" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'manual' => 1]) }}">
          <i class="ti ti-calendar-cog me-1" aria-hidden="true"></i> Schedule multiple draws
        </a>
        @if($draw->isTeamDraw())
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
          <i class="ti ti-calendar-plus me-1"></i> Schedule Matches
        </button>
        @else
        <a class="btn btn-primary btn-sm" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]]) }}">Schedule this draw</a>
        @endif
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
    $rulesService = app(\App\Domain\Draws\Services\DrawRulesService::class);
    $ruleSections = $rulesService->sections($draw);
    $printableRules = $rulesService->printable($draw);
    $selectedScoreFormat = optional($draw->settings)->score_format;
  @endphp
  @if(!$competitionRulesEditable)
    <div class="alert alert-warning" role="status">Tournament rules are locked because this draw is locked or its first result has been recorded.</div>
  @elseif($draw->published)
    <div class="alert alert-info" role="status">This draw is published. Rules remain editable until the first result is recorded.</div>
  @endif
  <div class="card">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0"><i class="ti ti-notes me-1"></i> Rules & Notes</h5>
        <small class="text-muted">Review the saved rules, then open a section to add notes. Print switches control your notes; match rules are always included.</small>
      </div>
      <button class="btn btn-success btn-sm" id="btn-save-notes">
        <i class="ti ti-device-floppy me-1"></i> Save All Notes
      </button>
    </div>
    <div class="card-body">
      @unless($selectedScoreFormat)
        <div class="alert alert-warning" role="status">Choose a Match Scoring Format in Settings before publishing scoring rules. No scoring format has been selected.</div>
      @endunless
      <script>window.RR_PRINTABLE_RULES = @json($printableRules);</script>
      <div class="row g-4">

        <div class="col-12">
          <fieldset class="card border mb-0">
            <div class="card-header py-2 bg-light">
              <legend class="h6 mb-0"><i class="ti ti-clock me-1 text-primary"></i> Player Schedule Visibility</legend>
            </div>
            <div class="card-body">
              @php
                $scheduleVisibility = optional($draw->settings)->schedule_visibility
                  ?? \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL;
              @endphp
              <p class="text-muted small">Choose how scheduled times appear on this draw's public page. The complete schedule remains available to administrators.</p>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-check border rounded p-3 ps-5 h-100">
                    <input class="form-check-input schedule-visibility" type="radio" name="schedule_visibility"
                      id="schedule-visibility-first-match" value="{{ \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH }}"
                      @checked(optional($draw->settings)->showsFirstMatchOnly())>
                    <label class="form-check-label fw-semibold" for="schedule-visibility-first-match">Show Each Player's First Match Only</label>
                    <div class="form-text">Show only each player's earliest upcoming assigned match. Their next time appears after that match is completed.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-check border rounded p-3 ps-5 h-100">
                    <input class="form-check-input schedule-visibility" type="radio" name="schedule_visibility"
                      id="schedule-visibility-full" value="{{ \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL }}"
                      @checked($scheduleVisibility === \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL)>
                    <label class="form-check-label fw-semibold" for="schedule-visibility-full">Show Full Schedule</label>
                    <div class="form-text">Show every published upcoming match time for the player in this draw.</div>
                  </div>
                </div>
              </div>
            </div>
          </fieldset>
        </div>

        @foreach($ruleSections as $section)
          <div class="col-12">
            <details class="card border rr-rule-section" @if(in_array($section['key'], ['general', 'match_format'], true)) open @endif>
              <summary class="p-3 fw-semibold">{{ $section['label'] }}
                @if($section['dormant'] ?? false)<span class="badge bg-label-secondary ms-2">No current qualifiers</span>
                @elseif($section['notes'] !== '')<span class="badge bg-label-primary ms-2">Notes added</span>@endif
              </summary>
              <div class="card-body p-3 pt-0">
                @unless($section['key'] === 'match_format')
                  <div class="form-check form-switch mb-3">
                    <input class="form-check-input notes-enabled" type="checkbox" id="print-note-{{ $section['key'] }}" data-key="{{ $section['key'] }}" @checked($section['print_notes'])>
                    <label class="form-check-label small text-muted" for="print-note-{{ $section['key'] }}">Print notes</label>
                  </div>
                @endunless
                @if($section['rules'] !== '')
                  <div class="mb-3" style="white-space:pre-line">{{ $section['rules'] }}</div>
                @endif
                @unless($section['key'] === 'match_format')
                  <label class="form-label small" for="event-note-{{ $section['key'] }}">Event-specific notes</label>
                  <textarea id="event-note-{{ $section['key'] }}" class="form-control notes-field" data-key="{{ $section['key'] }}" rows="3" placeholder="Add event-specific instructions...">{{ $section['notes'] }}</textarea>
                @endunless
              </div>
            </details>
          </div>
        @endforeach

      </div>
    </div>
  </div>
</div>

  </div> {{-- END TABS --}}

  @if(($recoveryCases ?? collect())->isNotEmpty())
    <div class="card border-danger mt-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti ti-history me-1 text-danger"></i>Recovery history</h5>
        <span class="badge bg-label-danger">Permanent audit record</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Case</th><th>Status</th><th>Impact</th><th>Reason</th><th>When</th><th></th></tr></thead>
          <tbody>
          @foreach($recoveryCases as $case)
            @php($impact = $case->impact ?? [])
            <tr>
              <td>#{{ $case->id }}</td>
              <td><span class="badge {{ $case->status === 'applied' ? 'bg-label-warning' : 'bg-label-secondary' }}">{{ ucfirst($case->status) }}</span></td>
              <td class="small">
                {{ $impact['playoff_fixtures'] ?? 0 }} playoff fixtures,
                {{ $impact['scored_playoff_fixtures'] ?? 0 }} played
              </td>
              <td class="small" style="min-width: 16rem">{{ $case->reason }}</td>
              <td class="small text-nowrap">{{ optional($case->applied_at)->format('Y-m-d H:i') }}</td>
              <td class="text-end">
                @if($case->status === 'applied' && auth()->user()->hasRole('super-user'))
                  <button type="button" class="btn btn-sm btn-outline-danger rr-restore-recovery"
                    data-url="{{ route('backend.draw.recovery.restore', [$draw, $case]) }}"
                    data-confirmation="RESTORE #{{ $case->id }}">
                    Restore before snapshot
                  </button>
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-footer small text-muted">
        Restores are super-user only, require a new reason and exact typed confirmation, and leave the draw locked and unpublished.
      </div>
    </div>
  @endif
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

        <label class="form-label fw-bold mb-1">{{ $selectedScoreFormat === 'timed' ? 'Games at time' : 'Set Scores' }}</label>
        <p class="small text-muted mb-3">
          {{ optional($draw->settings)->score_format
            ? \App\Domain\Draws\Services\TennisScoreFormat::rules($draw->settings->score_format)
            : 'Legacy scoring: enter a decisive result within the configured set limit.' }}
          @if($selectedScoreFormat === 'timed')
            {{ $draw->settings->match_duration_minutes }} minutes playing time; {{ $draw->settings->changeover_minutes }} minutes changeover.
          @endif
        </p>

        @for($setNumber = 1; $setNumber <= 5; $setNumber++)
          <div class="row g-2 mb-2 score-set-row" data-set-row="{{ $setNumber }}">
            <div class="col-12 fw-bold">{{ $selectedScoreFormat === 'timed' ? 'Games won' : 'Set '.$setNumber }}</div>
            <div class="col-6">
              <label class="form-label"><span id="set{{ $setNumber }}-p1-label">Player 1</span></label>
              <input type="number" min="0" max="999" inputmode="numeric" class="form-control" id="set{{ $setNumber }}-p1">
            </div>
            <div class="col-6">
              <label class="form-label"><span id="set{{ $setNumber }}-p2-label">Player 2</span></label>
              <input type="number" min="0" max="999" inputmode="numeric" class="form-control" id="set{{ $setNumber }}-p2">
            </div>
          </div>
        @endfor
        <div id="rrm-deciding-point" class="mt-3" hidden>
          <label for="rrm-deciding-winner" class="form-label">Who won the deciding point?</label>
          <select id="rrm-deciding-winner" class="form-select"><option value="">Choose the winner</option></select>
          <p class="form-text">The games remain tied. This point decides who advances in the playoff.</p>
        </div>

      </div>

      <div class="modal-footer justify-content-between">
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-danger" id="rrm-delete-score">
            <i class="ti ti-trash me-1"></i> Delete Score
          </button>
          <button type="button" class="btn btn-danger d-none" id="rrm-open-recovery">
            <i class="ti ti-history me-1"></i> Tournament recovery
          </button>
        </div>
        <div>
          <button type="submit" class="btn btn-primary">Save Score</button>
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>

    </form>
  </div>
</div>

<div class="modal fade" id="rrRecoveryModal" tabindex="-1" aria-labelledby="rr-recovery-title" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form id="rr-recovery-form" class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title text-white" id="rr-recovery-title"><i class="ti ti-alert-triangle me-1"></i>Tournament recovery</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger">
          This is a controlled rollback. A before-and-after snapshot will be retained. All playoff fixtures in this draw will be removed, and the draw will finish locked and unpublished for review.
        </div>
        <div id="rr-recovery-impact" class="mb-3"></div>
        <label class="form-label" for="rr-recovery-reason">Correction reason</label>
        <textarea class="form-control mb-3" id="rr-recovery-reason" rows="3" minlength="10" maxlength="2000" required></textarea>
        <label class="form-label" for="rr-recovery-confirmation">Type <code>RECOVER #{{ $draw->id }}</code> exactly</label>
        <input class="form-control" id="rr-recovery-confirmation" autocomplete="off" required>
        <div class="invalid-feedback d-block" id="rr-recovery-error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger" id="rr-recovery-apply"><i class="ti ti-history me-1"></i>Apply correction and reset playoffs</button>
      </div>
    </form>
  </div>
</div>

{{-- Venue Modal --}}
@include('backend.draw._modals.addVenueModal')

{{-- Schedule Modal --}}
<input type="hidden" id="drawId" value="{{ $draw->id }}">
@include('backend.headOffice.modals.scheduleModal')

</div>
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
    window.RR_CAN_EDIT_COMPETITION_RULES = @json($competitionRulesEditable);
    window.RR_FIXTURES  = @json($rrFixtures);
    window.RR_GROUPS    = @json($groupsjson);   // THE ONLY CORRECT ONE
    window.RR_OOP       = @json($oops);
    window.RR_STANDINGS = @json($standings);
    window.RR_ALL_VENUES = @json($venues->map(fn ($venue) => ['id' => $venue->id, 'name' => $venue->name])->values());

    window.RR_DRAW_LOCKED    = {{ $draw->locked ? 'true' : 'false' }};
    window.RR_DRAW_PUBLISHED = {{ $draw->published ? 'true' : 'false' }};
    window.RR_ENGINE_MODE    = "{{ $draw->engine_mode ?? 'legacy' }}";

    // Canonical permissions object — mirrors DrawMutationPolicy::for($draw)->toArray()
    // All frontend lock checks should read from here, not just RR_DRAW_LOCKED.
    window.RR_PERMISSIONS = @json(\App\Services\Draw\DrawMutationPolicy::for($draw)->toArray());

    window.RR_SAVE_SCORE_URL   = "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}";
    window.RR_DELETE_SCORE_URL = "{{ route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID']) }}";
    window.RR_SCORE_FORMAT = @json($draw->settings?->score_format);
    window.RR_MATCH_MINUTES = @json($draw->settings?->match_duration_minutes);
    window.RR_CHANGEOVER_MINUTES = @json($draw->settings?->changeover_minutes);
    window.RR_SCORE_MAX_SETS = {{ optional($draw->settings)->score_format
      ? \App\Domain\Draws\Services\TennisScoreFormat::maxSets($draw->settings->score_format)
      : max(1, min(5, (int) (optional($draw->settings)->num_sets ?: 3))) }};
    window.RR_RECOVERY = {
      preview: @json(route('backend.draw.recovery.preview', $draw)),
      apply: @json(route('backend.draw.recovery.apply', $draw)),
      confirmation: @json('RECOVER #'.$draw->id)
    };

    // Canonical RR API routes
    window.RR_ROUTES = {
        hub:           "{{ route('api.draws.hub', $draw) }}",
        scoreStore:    "{{ route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID']) }}",
        scoreDelete:   "{{ route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID']) }}",
        groupsSave:    "{{ route('api.draws.groups.save', $draw) }}",
        scheduleSave:  "{{ route('api.draws.schedule.save', $draw) }}",
        scheduleSummary: "{{ route('api.draws.schedule.summary', $draw) }}",
        venueCreate:   "{{ route('backend.event-venue-schedule.venues', $draw->event_id) }}",

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

    if (!window.RR_CAN_EDIT_COMPETITION_RULES) {
      $(function () {
        $('#drawSettingsForm :input, #btn-add-playoff, #btn-load-preset, #btn-save-playoff-config, #playoff-config-table :input, #btn-save-notes, .notes-field')
          .prop('disabled', true);
      });
    }
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
<script src="{{ asset('assets/js/admin/roundrobin/matrix.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/matrix.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/scores.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/scores.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/standings.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/standings.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/oop.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/oop.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/groups.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/groups.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/schedule.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/schedule.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/brackets.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/brackets.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/state-badges.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/state-badges.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/workspace.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/workspace.js')) }}"></script>
<script src="{{ asset('assets/js/admin/roundrobin/init.js') }}?v={{ md5_file(public_path('assets/js/admin/roundrobin/init.js')) }}"></script>

@include('backend.draw.roundrobin.setup-scripts')





@include('backend.draw.roundrobin.print-scripts')
<script src="{{ asset('js/draw-progression.js') }}?v={{ filemtime(public_path('js/draw-progression.js')) }}"></script>



<script>
// ---- SAVE NOTES ----
(function($) {
  $(document).ready(function() {
    $('#btn-save-notes').on('click', function() {
      var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving…');
      var notes = {};
      var notesPrint = {};
      $('.notes-field').each(function() {
        notes[$(this).data('key')] = $(this).val();
      });
      $('.notes-enabled').each(function() {
        notesPrint[$(this).data('key')] = $(this).is(':checked') ? 1 : 0;
      });
      $.post(APP_URL + '/backend/draw/' + DRAW_ID + '/notes', {
        notes: notes,
        notes_print: notesPrint,
        schedule_visibility: $('.schedule-visibility:checked').val()
      })
        .done(function(res) {
          window.RR_PRINTABLE_RULES = res.printable_rules;
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
