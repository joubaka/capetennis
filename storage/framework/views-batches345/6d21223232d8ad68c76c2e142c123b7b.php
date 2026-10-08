<?php
  $pageConfigs = ['myLayout' => 'vertical'];
?>


<?php $__env->startSection('title', 'Draw workspace — ' . $draw->drawName); ?>

<?php $__env->startSection('content'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/css/draw-roundrobin.css')); ?>?v=<?php echo e(filemtime(public_path('assets/css/draw-roundrobin.css'))); ?>">


<?php
  $currentBoxes = (int) (optional($draw->settings)->boxes ?: ($groups->count() ?: 4));
  $roundRobinOnly = $draw->isRoundRobinOnly();
  $competitionRulesEditable = auth()->user()->can('editCompetitionRules', $draw);
  $assignmentService = app(\App\Services\Draw\GroupAssignmentService::class);
  $eligibleRoster = $assignmentService->eligible($draw)->with(['registration.players', 'categoryEvent.category'])->get()->map(fn ($entry) => [
    'id' => $entry->registration_id, 'name' => $entry->registration->display_name,
    'category_id' => $entry->category_event_id, 'category' => $entry->categoryEvent->category?->name ?? 'Players',
  ])->unique('id')->values();
?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/css/draw-workspace.css')); ?>?v=<?php echo e(filemtime(public_path('assets/css/draw-workspace.css'))); ?>">
<div id="round-robin-app" 
   data-draw-id="<?php echo e($draw->id); ?>">

<?php echo $__env->make('backend.draw.partials.workspace-header', ['workspaceSurface' => 'roundrobin'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="rr-readiness">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><strong id="rr-next-step"><?php echo e($draw->drawFixtures->isEmpty() ? 'Start with your players and groups' : 'Your draw workspace'); ?></strong><span class="small text-muted" id="rr-fixture-summary"><?php echo e($draw->drawFixtures->count()); ?> fixtures · <?php echo e($readiness['scored_count'] ?? 0); ?> scored</span></div>
  <details class="mt-2"><summary>Readiness and draw status</summary><div class="mt-2">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($readiness['checks'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $check): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <span class="badge <?php echo e($check['ok'] ? 'bg-label-success' : 'bg-label-warning'); ?> me-1 mb-1"><?php echo e($check['label']); ?></span>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></details>
</div>
<nav class="rr-workspace-nav" aria-label="Draw workspace">
  <button type="button" data-workspace="players">Players &amp; Groups</button>
  <button type="button" data-workspace="results">Draw &amp; Results</button>
  <button type="button" data-workspace="schedule">Schedule</button>
  <button type="button" data-workspace="setup">Setup &amp; Rules</button>
</nav>
<div class="d-flex flex-wrap align-items-start gap-2 mb-3">
<ul class="nav nav-tabs mb-0 flex-grow-1" id="rrTabs" role="tablist">

  
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
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($roundRobinOnly)): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="main-bracket-tab" data-bs-toggle="tab" data-bs-target="#main-bracket-pane" type="button" role="tab">
      <i class="ti ti-tournament me-1"></i> Brackets
    </button>
  </li>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
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
<a class="btn btn-primary d-none" data-full-schedule-action href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'manual' => 1])); ?>">
  <i class="ti ti-calendar-cog me-1" aria-hidden="true"></i> Manage full schedule
</a>
</div>

  
  <div class="tab-content" id="rrTabsContent">

    
    <div class="tab-pane fade" id="settings-pane" role="tabpanel">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$competitionRulesEditable): ?>
        <div class="alert alert-warning" role="status">Match format and playoff rules are locked because this draw is locked or its first result has been recorded.</div>
      <?php elseif($draw->published): ?>
        <div class="alert alert-info" role="status">This draw is published. Match format and playoff rules remain editable until the first result is recorded.</div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      
      
      <div class="card mb-3 border-info">
        <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="ti ti-info-circle me-1"></i> Draw Overview</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-primary">
                  <?php
                    $totalPlayers = $groups->sum(function($group) {
                      return $group->registrations->count();
                    });
                  ?>
                  <?php echo e($totalPlayers); ?>

                </h3>
                <small class="text-muted fw-bold">Total Players</small>
              </div>
            </div>
            
            
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-success"><?php echo e($groups->count()); ?></h3>
                <small class="text-muted fw-bold">Groups</small>
              </div>
            </div>
            
            
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-warning">
                  <i class="ti ti-tournament"></i>
                </h3>
                <small class="text-muted fw-bold">Round Robin</small>
              </div>
            </div>
            
            
            <div class="col-md-3">
              <div class="text-center p-3 border rounded">
                <h3 class="mb-1 text-danger">
                  <?php
                    $totalMatches = 0;
                    foreach($groups as $group) {
                      $playersInGroup = $group->registrations->count();
                      if ($playersInGroup > 1) {
                        $totalMatches += ($playersInGroup * ($playersInGroup - 1)) / 2;
                      }
                    }
                  ?>
                  <?php echo e($totalMatches); ?>

                </h3>
                <small class="text-muted fw-bold">Total Matches</small>
              </div>
            </div>
          </div>
          
          
          <div class="mt-3">
            <h6 class="fw-bold mb-2">Group Distribution:</h6>
            <div class="row g-2">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col-auto">
                  <span class="badge 
                    <?php if($group->name == 'A'): ?> bg-primary
                    <?php elseif($group->name == 'B'): ?> bg-success
                    <?php elseif($group->name == 'C'): ?> bg-warning
                    <?php elseif($group->name == 'D'): ?> bg-danger
                    <?php else: ?> bg-dark
                    <?php endif; ?>">
                    Group <?php echo e($group->name); ?>: <?php echo e($group->registrations->count()); ?> players
                  </span>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-settings me-1"></i> Basic Settings</h5>
        </div>
        <div class="card-body">
          <form id="drawSettingsForm">
            <?php echo csrf_field(); ?>

            <div class="row g-3">
              <input type="hidden" id="settings-boxes" value="<?php echo e($currentBoxes); ?>">
              <div class="col-md-4"><label class="form-label fw-bold">Groups</label><p class="mb-0">Manage group sizes and players in <button type="button" class="btn btn-link p-0" data-open-workspace="players">Players &amp; Groups</button>.</p></div>
              <?php
                $scoreFormats = \App\Domain\Draws\Services\TennisScoreFormat::catalog();
                $currentScoreFormat = optional($draw->settings)->score_format;
                $legacySets = max(1, min(5, (int) (optional($draw->settings)->num_sets ?: 3)));
              ?>
              <div class="col-md-5">
                <label class="form-label fw-bold" for="score-format">Match Scoring Format</label>
                <select name="score_format" id="score-format" class="form-select" required>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$currentScoreFormat): ?>
                    <option value="" selected disabled>Legacy: maximum <?php echo e($legacySets); ?> sets — choose a format</option>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Domain\Draws\Services\TennisScoreFormat::groupedCatalog(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupLabel => $formats): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <optgroup label="<?php echo e($groupLabel); ?>">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $formats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($key); ?>" data-description="<?php echo e($format['description']); ?>" data-max-sets="<?php echo e($format['max_sets']); ?>"
                          <?php if($currentScoreFormat === $key): echo 'selected'; endif; ?>><?php echo e($format['label']); ?></option>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </optgroup>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <div class="form-text" id="score-format-help">
                  <?php echo e($currentScoreFormat ? $scoreFormats[$currentScoreFormat]['description'] : 'Choose a preset so score entry and validation use the same rules.'); ?>

                </div>
              </div>

              
              <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary" id="btn-save-settings">
                  <i class="ti ti-device-floppy me-1"></i> Save Settings
                </button>
              </div>
            </div>



          </form>
        </div>
      </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($roundRobinOnly)): ?>
      
      <div class="card mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ti ti-tournament me-1"></i> Playoff Configuration</h5>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-playoff">
            <i class="ti ti-plus"></i> Add Playoff
          </button>
        </div>
        <div class="card-body">
          <?php
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
          ?>

          
          <div class="row mb-4">
            <div class="col-md-8">
              <label class="form-label fw-bold"><i class="ti ti-template me-1"></i> Quick Setup - Load Preset Template</label>
              <div class="input-group">
                <select class="form-select" id="preset-selector">
                  <option value="">-- Select a preset template --</option>
                  <?php
                    // Only show presets matching current number of groups
                    $currentGroupTemplates = $groupedTemplates[$currentBoxes] ?? [];
                  ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($currentGroupTemplates) > 0): ?>
                    <optgroup label="<?php echo e($currentBoxes); ?> Group<?php echo e($currentBoxes > 1 ? 's' : ''); ?>">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $currentGroupTemplates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $preset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($key); ?>" 
                                data-config='<?php echo json_encode($preset['config'], 15, 512) ?>'
                                data-groups="<?php echo e($preset['groups'] ?? 4); ?>"
                                data-max-positions="<?php echo e($preset['max_positions'] ?? 10); ?>"
                                <?php echo e($savedPresetKey === $key ? 'selected' : ''); ?>>
                          <?php echo e($preset['name']); ?>

                        </option>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </optgroup>
                  <?php else: ?>
                    <option value="" disabled>No presets available for <?php echo e($currentBoxes); ?> group<?php echo e($currentBoxes > 1 ? 's' : ''); ?></option>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <button type="button" class="btn btn-success" id="btn-load-preset">
                  <i class="ti ti-download me-1"></i> Load
                </button>
              </div>
              <small class="text-muted">
                Showing presets for <?php echo e($currentBoxes); ?> group<?php echo e($currentBoxes > 1 ? 's' : ''); ?>. Change group count in Players &amp; Groups to see other presets.
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedPresetKey): ?>
                  <br><span class="badge bg-success mt-1">
                    <i class="ti ti-check me-1"></i> Currently using: <?php echo e($presetTemplates[$savedPresetKey]['name'] ?? $savedPresetKey); ?>

                  </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $playoffConfig; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $playoff): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr data-idx="<?php echo e($idx); ?>">
                  <td>
                    <div class="form-check form-switch">
                      <?php
                        // Only check if playoff is explicitly enabled AND has positions configured
                        $hasPositions = !empty($playoff['positions']);
                        $isEnabled = ($playoff['enabled'] ?? false) && $hasPositions;
                      ?>
                      <input class="form-check-input playoff-enabled" type="checkbox" 
                             <?php echo e($isEnabled ? 'checked' : ''); ?>

                             data-idx="<?php echo e($idx); ?>">
                    </div>
                  </td>
                  <td>
                    <input type="text" class="form-control form-control-sm playoff-name" 
                           value="<?php echo e($playoff['name']); ?>" data-idx="<?php echo e($idx); ?>" style="min-width: 150px;">
                  </td>
                  <td>
                    <select class="form-select form-select-sm playoff-size" data-idx="<?php echo e($idx); ?>" style="width: 80px;">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [2, 4, 8, 16, 32]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($size); ?>" <?php echo e(($playoff['size'] ?? 4) == $size ? 'selected' : ''); ?>>
                          <?php echo e($size); ?>

                        </option>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                  </td>
                  <td>
                    <div class="d-flex flex-wrap gap-1">
                      <?php $positions = $playoff['positions'] ?? []; ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = range(1, 10); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button type="button" 
                                class="btn btn-sm position-btn <?php echo e(in_array($pos, $positions) ? 'btn-primary' : 'btn-outline-secondary'); ?>"
                                data-idx="<?php echo e($idx); ?>" 
                                data-pos="<?php echo e($pos); ?>"
                                title="Position #<?php echo e($pos); ?> from each group">
                          #<?php echo e($pos); ?>

                        </button>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <small class="text-muted">Click to toggle positions</small>
                  </td>
                  <td>
                    <small class="text-muted playoff-preview" data-idx="<?php echo e($idx); ?>">
                      <?php
                        $posCount = count($positions);
                        $totalPlayers = $posCount * $currentBoxes;
                      ?>
                      <?php echo e($totalPlayers); ?> players
                    </small>
                  </td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-playoff" data-idx="<?php echo e($idx); ?>">
                      <i class="ti ti-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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

      
      <div class="card mb-3 border-info">
        <details class="rr-advanced"><summary>Advanced previews · player accounting and seeding</summary>
      <div class="card-header bg-info text-white">
          <h5 class="mb-0"><i class="ti ti-users me-1"></i> Player Accounting & Validation</h5>
          <small>Verify all players are accommodated in playoff draws</small>
        </div>
        <div class="card-body">
          <div id="player-accounting">
            
            <div class="text-muted">Loading player accounting...</div>
          </div>
        </div>
      </div>

      
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-git-branch me-1"></i> Player Flow Preview</h5>
        </div>
        <div class="card-body">
          <div id="playoff-flow-preview" class="d-flex flex-wrap gap-3">
            
            <div class="text-muted">Configure playoff draws above to see the flow preview.</div>
          </div>
        </div>
      </div>

      
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-map-pin me-1"></i> Detailed Seeding Chart</h5>
          <small class="text-muted">See exactly where each player position from each group goes</small>
        </div>
        <div class="card-body">
          <div id="playoff-seeding-chart">
            
            <div class="text-muted">Configure playoff draws above to see detailed seeding.</div>
          </div>
        </div>
      </div>

      
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-table me-1"></i> Complete Seeding Matrix</h5>
          <small class="text-muted">All positions from all groups with their seed numbers</small>
        </div>
        <div class="card-body">
          <div id="complete-seeding-matrix">
            
            <div class="text-muted">Configure playoff draws above to see complete seeding matrix.</div>
          </div>
        </div>
      </div>

      
      <div class="card mb-3">
        <div class="card-header bg-light">
          <h5 class="mb-0"><i class="ti ti-tournament me-1"></i> Bracket Seed Positions</h5>
          <small class="text-muted">Visual representation of where each seed is placed in brackets</small>
        </div>
        <div class="card-body">
          <div id="bracket-visualization">
            
            <div class="text-muted">Configure playoff draws above to see bracket structure.</div>
          </div>
        </div>
      </div>

      </details>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
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


<?php echo $__env->make('backend.draw.roundrobin.players-workspace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($roundRobinOnly)): ?>
<div class="tab-pane fade" id="main-bracket-pane" role="tabpanel">
    <h5 class="mb-3"><i class="ti ti-tournament me-1"></i> Playoff Brackets</h5>

    
    <div class="bracket-zoom-controls mb-2" id="bracket-zoom-bar">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="bracket-zoom-out" title="Zoom out">−</button>
      <span class="bracket-zoom-level" id="bracket-zoom-label">100%</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="bracket-zoom-in" title="Zoom in">+</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="bracket-zoom-reset" title="Reset zoom">↺</button>
      <span class="bracket-zoom-hint"><i class="ti ti-pinch me-1"></i>Pinch to zoom</span>
    </div>

    
    <div id="main-bracket-wrapper" class="overflow-auto" style="touch-action: pan-x pan-y;">
      <div id="bracket-zoom-inner">
        <div class="text-center text-muted py-5">
          <div class="spinner-border spinner-border-sm"></div>
          <div class="mt-2">Loading playoff brackets…</div>
        </div>
      </div>
    </div>
   

</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<div class="tab-pane fade" id="print-pane" role="tabpanel">
  <div class="card">
    <div class="card-header">
      <h5 class="card-title mb-0"><i class="ti ti-printer me-1"></i> Print Options</h5>
      <small class="text-muted"><?php echo e($roundRobinOnly ? 'Generate print-friendly round-robin fixtures, matrices and standings' : 'Generate print-friendly pages for fixtures, matrix, brackets and blank draws'); ?></small>
    </div>
    <div class="card-body">
      <div class="row g-4">

        
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($roundRobinOnly)): ?>
        
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
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
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
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($roundRobinOnly)): ?>
                <div class="form-check mb-1">
                  <input class="form-check-input pack-section" type="checkbox" id="pack-playoff-fixtures" checked>
                  <label class="form-check-label" for="pack-playoff-fixtures">Playoff Fixtures</label>
                </div>
                <div class="form-check mb-1">
                  <input class="form-check-input pack-section" type="checkbox" id="pack-brackets" checked>
                  <label class="form-check-label" for="pack-brackets">Blank Brackets</label>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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


<div class="tab-pane fade" id="schedule-pane" role="tabpanel">
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0"><i class="ti ti-map-pin me-1 text-primary"></i> Venues</h5>
        <small class="text-muted">Manage venues assigned to this draw</small>
      </div>
      <button type="button" class="btn btn-primary btn-sm addVenues" id="rr-add-venues" data-id="<?php echo e($draw->id); ?>" data-draw-name="<?php echo e($draw->drawName); ?>">
        <i class="ti ti-plus me-1"></i> Add Venue
      </button>
    </div>
    <div class="card-body">
      <div id="rr-venues-list">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draw->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
            <div>
              <strong><?php echo e($venue->name); ?></strong>
              <span class="badge bg-label-info ms-2"><?php echo e($venue->pivot->num_courts); ?> court<?php echo e($venue->pivot->num_courts != 1 ? 's' : ''); ?></span>
            </div>
            <button class="btn btn-sm btn-outline-danger deleteVenue" data-id="<?php echo e($draw->id); ?>" data-venue="<?php echo e($venue->id); ?>">
              <i class="ti ti-trash me-1"></i> Remove
            </button>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="text-muted text-center py-3">
            <i class="ti ti-map-pin-off fs-3 d-block mb-2"></i>
            No venues assigned. Add a venue to enable scheduling.
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
        <a class="btn btn-outline-primary btn-sm" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'manual' => 1])); ?>">
          <i class="ti ti-calendar-cog me-1" aria-hidden="true"></i> Manage full schedule
        </a>
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
            
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


<div class="tab-pane fade" id="notes-pane" role="tabpanel">
  <?php
    $drawNotes = optional($draw->settings)->notes ?? [];
    $notesPrint = optional($draw->settings)->notes_print ?? [];
    $playoffConfig = optional($draw->settings)->playoff_config ?? [];
    $enabledBrackets = collect($playoffConfig)->where('enabled', true)->values();
    $defaultGeneralNotes = "General Rules\n\nPlayers must be ready to play at their scheduled time.\nA 5-minute warm-up is allowed before the match starts.\nStandard ITF tennis rules apply unless otherwise specified by the tournament organizer.\nThe tournament referee's decision is final in all disputes.";
    $selectedScoreFormat = optional($draw->settings)->score_format;
    $formatRules = $selectedScoreFormat
      ? \App\Domain\Draws\Services\TennisScoreFormat::rules($selectedScoreFormat)
      : 'Legacy scoring is active. Select an enforceable Match Scoring Format in Settings before publishing these rules.';
    $defaultRRNotes = "Round Robin Match Format\n\n{$formatRules}";
    $defaultPlayoffNotes = "Playoff Match Format\n\n{$formatRules}";
    $defaultBracketNotes = "Bracket Match Format\n\n{$formatRules}";
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$competitionRulesEditable): ?>
    <div class="alert alert-warning" role="status">Tournament rules are locked because this draw is locked or its first result has been recorded.</div>
  <?php elseif($draw->published): ?>
    <div class="alert alert-info" role="status">This draw is published. Rules remain editable until the first result is recorded.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
      <div class="alert alert-primary d-flex align-items-start gap-2" role="status">
        <i class="ti ti-scoreboard fs-5 mt-1" aria-hidden="true"></i>
        <div>
          <strong>Enforced match format:</strong>
          <?php echo e($selectedScoreFormat
            ? \App\Domain\Draws\Services\TennisScoreFormat::get($selectedScoreFormat)['label']
            : 'Legacy scoring — choose a preset in Settings'); ?>

          <div class="small mt-1"><?php echo e($formatRules); ?></div>
        </div>
      </div>
      <div class="row g-4">

        <div class="col-12">
          <fieldset class="card border mb-0">
            <div class="card-header py-2 bg-light">
              <legend class="h6 mb-0"><i class="ti ti-clock me-1 text-primary"></i> Player Schedule Visibility</legend>
            </div>
            <div class="card-body">
              <?php
                $scheduleVisibility = optional($draw->settings)->schedule_visibility
                  ?? \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL;
              ?>
              <p class="text-muted small">Choose how scheduled times appear on this draw's public page. The complete schedule remains available to administrators.</p>
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="form-check border rounded p-3 ps-5 h-100">
                    <input class="form-check-input schedule-visibility" type="radio" name="schedule_visibility"
                      id="schedule-visibility-first-match" value="<?php echo e(\App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH); ?>"
                      <?php if(optional($draw->settings)->showsFirstMatchOnly()): echo 'checked'; endif; ?>>
                    <label class="form-check-label fw-semibold" for="schedule-visibility-first-match">Show Each Player's First Match Only</label>
                    <div class="form-text">Show only each player's earliest upcoming assigned match. Their next time appears after that match is completed.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-check border rounded p-3 ps-5 h-100">
                    <input class="form-check-input schedule-visibility" type="radio" name="schedule_visibility"
                      id="schedule-visibility-full" value="<?php echo e(\App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL); ?>"
                      <?php if($scheduleVisibility === \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL): echo 'checked'; endif; ?>>
                    <label class="form-check-label fw-semibold" for="schedule-visibility-full">Show Full Schedule</label>
                    <div class="form-text">Show every published upcoming match time for the player in this draw.</div>
                  </div>
                </div>
              </div>
            </div>
          </fieldset>
        </div>

        
        <div class="col-md-6">
          <div class="card border h-100">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="ti ti-info-circle me-1 text-primary"></i> General Rules</h6>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input notes-enabled" type="checkbox" id="print-note-general" data-key="general" <?php if($notesPrint['general'] ?? true): echo 'checked'; endif; ?>>
                <label class="form-check-label small text-muted" for="print-note-general">Print</label>
              </div>
            </div>
            <div class="card-body p-2">
              <textarea class="form-control notes-field" data-key="general" rows="6" placeholder="Enter general event rules..."><?php echo e($drawNotes['general'] ?? $defaultGeneralNotes); ?></textarea>
            </div>
          </div>
        </div>

        
        <div class="col-md-6">
          <div class="card border h-100">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="ti ti-tournament me-1 text-success"></i> Round Robin Scoring Rules</h6>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input notes-enabled" type="checkbox" id="print-note-round-robin" data-key="round_robin" <?php if($notesPrint['round_robin'] ?? true): echo 'checked'; endif; ?>>
                <label class="form-check-label small text-muted" for="print-note-round-robin">Print</label>
              </div>
            </div>
            <div class="card-body p-2">
              <textarea class="form-control notes-field" data-key="round_robin" rows="6" placeholder="e.g. Best of 3 sets, tiebreak at 6-all, 10-point match tiebreak in 3rd..."><?php echo e($drawNotes['round_robin'] ?? $defaultRRNotes); ?></textarea>
            </div>
          </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($roundRobinOnly)): ?>
        
        <div class="col-md-6">
          <div class="card border h-100">
            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="ti ti-trophy me-1 text-warning"></i> Playoff Rules</h6>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input notes-enabled" type="checkbox" id="print-note-playoffs" data-key="playoffs" <?php if($notesPrint['playoffs'] ?? true): echo 'checked'; endif; ?>>
                <label class="form-check-label small text-muted" for="print-note-playoffs">Print</label>
              </div>
            </div>
            <div class="card-body p-2">
              <textarea class="form-control notes-field" data-key="playoffs" rows="6" placeholder="e.g. Single elimination, 3rd/4th playoff for losers of semis..."><?php echo e($drawNotes['playoffs'] ?? $defaultPlayoffNotes); ?></textarea>
            </div>
          </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $enabledBrackets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bracket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="col-md-6">
            <div class="card border h-100">
              <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                  <i class="ti ti-brackets me-1 text-info"></i>
                  <?php echo e($bracket['name'] ?? 'Bracket'); ?> Rules
                  <span class="badge bg-secondary ms-1" style="font-size: 10px;"><?php echo e($bracket['slug']); ?></span>
                </h6>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input notes-enabled" type="checkbox" id="print-note-bracket-<?php echo e($bracket['slug']); ?>" data-key="bracket_<?php echo e($bracket['slug']); ?>" <?php if($notesPrint['bracket_' . $bracket['slug']] ?? true): echo 'checked'; endif; ?>>
                  <label class="form-check-label small text-muted" for="print-note-bracket-<?php echo e($bracket['slug']); ?>">Print</label>
                </div>
              </div>
              <div class="card-body p-2">
                <textarea class="form-control notes-field" data-key="bracket_<?php echo e($bracket['slug']); ?>" rows="5"
                  placeholder="Rules specific to <?php echo e($bracket['name'] ?? 'this bracket'); ?>..."><?php echo e($drawNotes['bracket_' . $bracket['slug']] ?? (($bracket['slug'] ?? '') === 'main' ? $defaultPlayoffNotes : $defaultBracketNotes)); ?></textarea>
              </div>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      </div>
    </div>
  </div>
</div>

  </div> 

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($recoveryCases ?? collect())->isNotEmpty()): ?>
    <div class="card border-danger mt-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ti ti-history me-1 text-danger"></i>Recovery history</h5>
        <span class="badge bg-label-danger">Permanent audit record</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Case</th><th>Status</th><th>Impact</th><th>Reason</th><th>When</th><th></th></tr></thead>
          <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recoveryCases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $case): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php ($impact = $case->impact ?? []); ?>
            <tr>
              <td>#<?php echo e($case->id); ?></td>
              <td><span class="badge <?php echo e($case->status === 'applied' ? 'bg-label-warning' : 'bg-label-secondary'); ?>"><?php echo e(ucfirst($case->status)); ?></span></td>
              <td class="small">
                <?php echo e($impact['playoff_fixtures'] ?? 0); ?> playoff fixtures,
                <?php echo e($impact['scored_playoff_fixtures'] ?? 0); ?> played
              </td>
              <td class="small" style="min-width: 16rem"><?php echo e($case->reason); ?></td>
              <td class="small text-nowrap"><?php echo e(optional($case->applied_at)->format('Y-m-d H:i')); ?></td>
              <td class="text-end">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->status === 'applied' && auth()->user()->hasRole('super-user')): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger rr-restore-recovery"
                    data-url="<?php echo e(route('backend.draw.recovery.restore', [$draw, $case])); ?>"
                    data-confirmation="RESTORE #<?php echo e($case->id); ?>">
                    Restore before snapshot
                  </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer small text-muted">
        Restores are super-user only, require a new reason and exact typed confirmation, and leave the draw locked and unpublished.
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div> 
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

        <label class="form-label fw-bold mb-1">Set Scores</label>
        <p class="small text-muted mb-3">
          <?php echo e(optional($draw->settings)->score_format
            ? \App\Domain\Draws\Services\TennisScoreFormat::rules($draw->settings->score_format)
            : 'Legacy scoring: enter a decisive result within the configured set limit.'); ?>

        </p>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($setNumber = 1; $setNumber <= 5; $setNumber++): ?>
          <div class="row g-2 mb-2 score-set-row" data-set-row="<?php echo e($setNumber); ?>">
            <div class="col-12 fw-bold">Set <?php echo e($setNumber); ?></div>
            <div class="col-6">
              <label class="form-label"><span id="set<?php echo e($setNumber); ?>-p1-label">Player 1</span></label>
              <input type="number" min="0" max="999" inputmode="numeric" class="form-control" id="set<?php echo e($setNumber); ?>-p1">
            </div>
            <div class="col-6">
              <label class="form-label"><span id="set<?php echo e($setNumber); ?>-p2-label">Player 2</span></label>
              <input type="number" min="0" max="999" inputmode="numeric" class="form-control" id="set<?php echo e($setNumber); ?>-p2">
            </div>
          </div>
        <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
        <label class="form-label" for="rr-recovery-confirmation">Type <code>RECOVER #<?php echo e($draw->id); ?></code> exactly</label>
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


<?php echo $__env->make('backend.draw._modals.addVenueModal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<input type="hidden" id="drawId" value="<?php echo e($draw->id); ?>">
<?php echo $__env->make('backend.headOffice.modals.scheduleModal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->stopSection(); ?>



<?php $__env->startSection('page-script'); ?>

<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.css')); ?>">
<script src="<?php echo e(asset('assets/vendor/libs/flatpickr/flatpickr.js')); ?>"></script>

<script>
    window.RR_ROSTER = <?php echo json_encode($eligibleRoster, 15, 512) ?>;
    window.RR_ASSIGNMENT_REVISION = <?php echo json_encode($assignmentService->revision($draw), 15, 512) ?>;
    window.RR_CAN_ASSIGN = <?php echo json_encode(auth()->user()->can('modifyGroups', $draw), 512) ?>;
    window.RR_CAN_GENERATE = <?php echo json_encode(auth()->user()->can('generateFixtures', $draw), 512) ?>;
    window.RR_CAN_SCORE = <?php echo json_encode(auth()->user()->can('saveScore', $draw), 512) ?>;
    window.RR_CAN_SCHEDULE = <?php echo json_encode(auth()->user()->can('modifySchedule', $draw), 512) ?>;
    window.RR_CAN_EDIT_COMPETITION_RULES = <?php echo json_encode($competitionRulesEditable, 15, 512) ?>;
    window.RR_FIXTURES  = <?php echo json_encode($rrFixtures, 15, 512) ?>;
    window.RR_GROUPS    = <?php echo json_encode($groupsjson, 15, 512) ?>;   // THE ONLY CORRECT ONE
    window.RR_OOP       = <?php echo json_encode($oops, 15, 512) ?>;
    window.RR_STANDINGS = <?php echo json_encode($standings, 15, 512) ?>;
    window.RR_ALL_VENUES = <?php echo json_encode($venues->map(fn ($venue) => ['id' => $venue->id, 'name' => $venue->name])->values(), 512) ?>;

    window.RR_DRAW_LOCKED    = <?php echo e($draw->locked ? 'true' : 'false'); ?>;
    window.RR_DRAW_PUBLISHED = <?php echo e($draw->published ? 'true' : 'false'); ?>;
    window.RR_ENGINE_MODE    = "<?php echo e($draw->engine_mode ?? 'legacy'); ?>";

    // Canonical permissions object — mirrors DrawMutationPolicy::for($draw)->toArray()
    // All frontend lock checks should read from here, not just RR_DRAW_LOCKED.
    window.RR_PERMISSIONS = <?php echo json_encode(\App\Services\Draw\DrawMutationPolicy::for($draw)->toArray(), 15, 512) ?>;

    window.RR_SAVE_SCORE_URL   = "<?php echo e(route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID'])); ?>";
    window.RR_DELETE_SCORE_URL = "<?php echo e(route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID'])); ?>";
    window.RR_SCORE_MAX_SETS = <?php echo e(optional($draw->settings)->score_format
      ? \App\Domain\Draws\Services\TennisScoreFormat::maxSets($draw->settings->score_format)
      : max(1, min(5, (int) (optional($draw->settings)->num_sets ?: 3)))); ?>;
    window.RR_RECOVERY = {
      preview: <?php echo json_encode(route('backend.draw.recovery.preview', $draw), 512) ?>,
      apply: <?php echo json_encode(route('backend.draw.recovery.apply', $draw), 512) ?>,
      confirmation: <?php echo json_encode('RECOVER #'.$draw->id, 15, 512) ?>
    };

    // Canonical RR API routes
    window.RR_ROUTES = {
        hub:           "<?php echo e(route('api.draws.hub', $draw)); ?>",
        scoreStore:    "<?php echo e(route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID'])); ?>",
        scoreDelete:   "<?php echo e(route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID'])); ?>",
        groupsSave:    "<?php echo e(route('api.draws.groups.save', $draw)); ?>",
        scheduleSave:  "<?php echo e(route('api.draws.schedule.save', $draw)); ?>",
        scheduleSummary: "<?php echo e(route('api.draws.schedule.summary', $draw)); ?>",
        venueCreate:   "<?php echo e(route('backend.event-venue-schedule.venues', $draw->event_id)); ?>",

        // Legacy web routes (still active during transition)
        legacyScoreStore:  "<?php echo e(route('backend.roundrobin.score.store', ['fixture' => 'FIXTURE_ID'])); ?>",
        legacyScoreDelete: "<?php echo e(route('backend.roundrobin.score.delete', ['fixture' => 'FIXTURE_ID'])); ?>",
        groupsData:    "<?php echo e(route('backend.draw.groups-data', $draw)); ?>",
        availablePlayers: "<?php echo e(route('backend.draw.available-players', $draw)); ?>",
        regenerateRR:  "<?php echo e(route('backend.draw.regenerate-rr', $draw)); ?>",
        toggleLock:    "<?php echo e(route('backend.draw.toggle-lock', $draw)); ?>",
        saveGroups:    "<?php echo e(route('backend.draw.save-groups', $draw)); ?>",
        generateMainBracket:  "<?php echo e(route('backend.draw.generate-main-bracket', $draw)); ?>",
        generatePlateBracket: "<?php echo e(route('backend.draw.generate-second-third-bracket', $draw)); ?>",
        mainBracket:   "<?php echo e(route('backend.draw.main-bracket', $draw)); ?>",
        plateBracket:  "<?php echo e(route('backend.draw.plate-bracket', $draw)); ?>",
    };

    window.EVENT_ID = <?php echo e($draw->event_id); ?>;
    const DRAW_ID   = <?php echo e($draw->id); ?>;

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


<script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>


<script src="<?php echo e(asset('assets/js/admin/core/api.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/core/toast.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/core/modal.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/core/loading.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/core/confirm.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/core/routes.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/core/state.js')); ?>"></script>


<script src="<?php echo e(asset('assets/js/admin/roundrobin/matrix.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/matrix.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/scores.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/scores.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/standings.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/standings.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/oop.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/oop.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/groups.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/groups.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/schedule.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/schedule.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/brackets.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/brackets.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/state-badges.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/state-badges.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/workspace.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/workspace.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/admin/roundrobin/init.js')); ?>?v=<?php echo e(md5_file(public_path('assets/js/admin/roundrobin/init.js'))); ?>"></script>

<?php echo $__env->make('backend.draw.roundrobin.setup-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>





<?php echo $__env->make('backend.draw.roundrobin.print-scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>



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

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\roundrobin\show.blade.php ENDPATH**/ ?>