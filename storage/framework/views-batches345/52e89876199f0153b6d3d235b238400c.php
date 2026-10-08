

<?php $__env->startSection('title', $series->name . ' – Series Settings'); ?>


<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .settings-section-title {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #a0aec0;
    margin-bottom: .75rem;
  }
  .toggle-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: .75rem 1rem;
    border-radius: .5rem;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
  }
  .toggle-row + .toggle-row { margin-top: .5rem; }
  .toggle-row .toggle-info { flex: 1; }
  .toggle-row .toggle-info strong { font-size: .875rem; display: block; margin-bottom: .15rem; }
  .toggle-row .toggle-info small { color: #6c757d; font-size: .78rem; }
  .ranking-publication-action { flex: 0 0 auto; }
  @media (max-width: 575.98px) {
    .ranking-publication-row { flex-direction: column; }
    .ranking-publication-action { width: 100%; }
  }
  /* Tab nav styling */
  .settings-tabs .nav-link {
    color: #6c757d;
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    padding: .6rem 1.1rem;
    font-weight: 500;
    font-size: .9rem;
  }
  .settings-tabs .nav-link.active {
    color: #696cff;
    border-bottom-color: #696cff;
    background: transparent;
  }
  .settings-tabs .nav-link i { font-size: 1rem; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0 fw-bold">Series Settings</h4>
      <div class="text-muted small mt-1">
        <i class="ti ti-tournament me-1"></i><?php echo e($series->name); ?> &middot; <?php echo e($series->year); ?>

      </div>
    </div>
    <a href="<?php echo e(route('series.show', $series)); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left me-1"></i>Back to Series
    </a>
  </div>

  
  <div class="card shadow-sm">

    
    <div class="card-header border-bottom p-0">
      <ul class="nav settings-tabs px-3" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="tab-general" data-bs-toggle="tab" href="#pane-general" role="tab">
            <i class="ti ti-settings me-1"></i>General
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="tab-points" data-bs-toggle="tab" href="#pane-points" role="tab">
            <i class="ti ti-trophy me-1"></i>Points
          </a>
        </li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($series->ranking_lists->isNotEmpty()): ?>
        <li class="nav-item">
          <a class="nav-link" id="tab-categories" data-bs-toggle="tab" href="#pane-categories" role="tab">
            <i class="ti ti-category me-1"></i>Categories
          </a>
        </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
    </div>

    
    <div class="tab-content">

      
      <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
        <div class="card-body" style="max-width: 640px;">
          <form id="series-settings-form">
            <?php echo csrf_field(); ?>

            <div class="settings-section-title">Identity</div>
            <div class="row g-3 mb-4">
              <div class="col-8">
                <label class="form-label fw-semibold small mb-1">Series Name</label>
                <input type="text" name="name" class="form-control" required value="<?php echo e($series->name); ?>">
              </div>
              <div class="col-4">
                <label class="form-label fw-semibold small mb-1">Year</label>
                <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?php echo e($series->year); ?>">
              </div>
            </div>

            <div class="settings-section-title">Scoring</div>
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Best Results Counted</label>
                <input type="number" name="best_num_of_scores" class="form-control" min="1" required value="<?php echo e($series->best_num_of_scores); ?>">
                <div class="form-text">Series-wide default.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Events Required for Public Ranking &amp; Team Selection</label>
                <input type="number" name="minimum_events_for_team_selection" class="form-control" min="1" max="99" required value="<?php echo e($series->minimum_events_for_team_selection ?? 1); ?>">
                <div class="form-text">Counts actual results from the events selected for each ranking list. Players below this number remain in the admin ranking but are omitted from the public ranking and team selection.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Rank Type</label>
                <select name="rank_type" class="form-select" required
                  <?php echo e($series->points_template_created ? 'disabled title="Rank type cannot be changed after points have been applied."' : ''); ?>>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($type->id); ?>" <?php echo e((int)$series->rank_type === (int)$type->id ? 'selected' : ''); ?>>
                      <?php echo e($type->type); ?>

                    </option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($series->points_template_created): ?>
                  <div class="form-text text-warning"><i class="ti ti-lock me-1"></i>Locked – points template already created.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </div>

            <div class="settings-section-title">Participant Ranking Review</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label fw-semibold small mb-1">Default Reply Window</label>
                <div class="input-group">
                  <input type="number" name="ranking_review_default_hours" class="form-control" min="1" max="720" value="<?php echo e($series->ranking_review_default_hours ?: 24); ?>">
                  <span class="input-group-text">hours</span>
                </div>
                <div class="form-text">Prefills the optional Share Rankings modal. The exact cutoff remains editable before sending.</div>
              </div>
            </div>

            <div class="settings-section-title">Ranking Rules Preset</div>
            <div class="row g-3 mb-4">
              <div class="col-md-7">
                <label class="form-label fw-semibold small mb-1" for="ranking_rule_preset_id">Apply Preset</label>
                <select class="form-select" id="ranking_rule_preset_id" name="ranking_rule_preset_id">
                  <option value="">Custom rules</option>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingRulePresets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $preset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($preset->id); ?>" <?php echo e((int) $series->ranking_rule_preset_id === (int) $preset->id ? 'selected' : ''); ?>>
                      <?php echo e($preset->name); ?><?php echo e($preset->is_system ? ' (built-in)' : ''); ?>

                    </option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <div class="form-text">Selecting a preset fills the ranking fields below. Save Settings to apply it to this series.</div>
              </div>
              <div class="col-md-5">
                <label class="form-label fw-semibold small mb-1" for="save_preset_name">Save Current Rules as Preset</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="save_preset_name" maxlength="120" placeholder="Preset name">
                  <button type="button" class="btn btn-outline-primary" id="save-preset-btn">Save</button>
                </div>
                <div class="form-text">Your saved presets can be reused on other series.</div>
              </div>
            </div>

            <div class="settings-section-title">Visibility & Rules</div>

            <div class="toggle-row ranking-publication-row">
              <div class="toggle-info">
                <strong>
                  Ranking Publication
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeRankingStatus === 'published'): ?>
                    <span class="badge bg-label-success ms-1">Published</span>
                  <?php elseif($activeRankingStatus === 'reviewed'): ?>
                    <span class="badge bg-label-info ms-1">Reviewed</span>
                  <?php elseif($activeRankingStatus === 'calculated'): ?>
                    <span class="badge bg-label-warning ms-1">Calculated</span>
                  <?php else: ?>
                    <span class="badge bg-label-secondary ms-1">Not ready</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </strong>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeRankingStatus === 'published'): ?>
                  <small>The current ranking run is published and can be shown publicly.</small>
                <?php elseif($activeRankingStatus === 'reviewed'): ?>
                  <small>The ranking has been reviewed and is ready to publish.</small>
                <?php elseif($activeRankingStatus === 'calculated'): ?>
                  <small>Review the calculated ranking before publishing it.</small>
                <?php else: ?>
                  <small>Build the ranking list before it can be reviewed and published.</small>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeRankingStatus === 'reviewed'): ?>
                <button type="button"
                        class="btn btn-success btn-sm ranking-publication-action ranking-lifecycle-action"
                        data-url="<?php echo e(route('ranking.series.ranking.publish', $series)); ?>"
                        data-confirm="<?php echo e($reviewCampaign ? 'Close the participant review and publish these rankings? No ranking email will be sent.' : 'Publish this reviewed ranking? No ranking email will be sent.'); ?>">
                  <i class="ti ti-world-upload me-1"></i><?php echo e($reviewCampaign ? 'Finalize & Publish' : 'Publish Rankings'); ?>

                </button>
              <?php elseif($activeRankingStatus === 'calculated'): ?>
                <button type="button"
                        class="btn btn-info btn-sm ranking-publication-action ranking-lifecycle-action"
                        data-url="<?php echo e(route('ranking.series.ranking.review', $series)); ?>"
                        data-confirm="Mark this calculated ranking as reviewed?">
                  <i class="ti ti-check me-1"></i>Mark Reviewed
                </button>
              <?php else: ?>
                <a href="<?php echo e(route('ranking.series.list', $series)); ?>"
                   class="btn btn-outline-primary btn-sm ranking-publication-action">
                  <i class="ti ti-list me-1"></i><?php echo e($activeRankingStatus === 'published' ? 'View Rankings' : 'Manage Rankings'); ?>

                </a>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="toggle-row mt-2">
              <div class="toggle-info">
                <strong>Public Leaderboard Visible</strong>
                <small>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasPublishedRanking): ?>
                    Show or hide the published ranking on the public website.
                  <?php else: ?>
                    This becomes available after a reviewed ranking is published.
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </small>
              </div>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="leaderboard_published" id="leaderboard_published"
                       <?php echo e($hasPublishedRanking && $series->leaderboard_published ? 'checked' : ''); ?>

                       <?php echo e($hasPublishedRanking ? '' : 'disabled'); ?>>
              </div>
            </div>

            <div class="toggle-row mt-2">
              <div class="toggle-info">
                <strong>Auto-Award Rule</strong>
                <small>A player who wins 2 of 3 legs is automatically awarded 1st place for any unplayed leg.</small>
              </div>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="auto_award_rule" id="auto_award_rule"
                       <?php echo e(($series->auto_award_rule ?? true) ? 'checked' : ''); ?>>
              </div>
            </div>

            <div class="mt-4 mb-2">
              <div class="fw-semibold">Tiebreak Rules</div>
              <div class="form-text">Applied in this order after the best-results total. Rebuild the rankings after changing these settings.</div>
            </div>

            <div class="toggle-row mt-2">
              <div class="toggle-info">
                <strong>1. Use Third-Event Score</strong>
                <small>If best-results totals are equal, rank the player with the higher next excluded score first. A player without another score has 0.</small>
              </div>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="use_third_score_tiebreak" id="use_third_score_tiebreak"
                       <?php echo e(($series->use_third_score_tiebreak ?? true) ? 'checked' : ''); ?>>
              </div>
            </div>

            <div class="toggle-row mt-2">
              <div class="toggle-info">
                <strong>2. Use Latest-Played-Leg Finishing Position</strong>
                <small>If totals and third-event scores are still equal, use the latest linked leg played by either tied player. A recorded finish ranks ahead of no finish; if neither played that leg, move back to the previous leg. Equal finishes remain tied for an administrator to decide. Automatic awards are not actual finishes.</small>
              </div>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="use_last_leg_position_tiebreak" id="use_last_leg_position_tiebreak"
                       <?php echo e(($series->use_last_leg_position_tiebreak ?? false) ? 'checked' : ''); ?>>
              </div>
            </div>

            <div class="toggle-row mt-2">
              <div class="toggle-info">
                <strong>3. Use Latest Head-to-Head</strong>
                <small>If two players are still tied, use their most recent eligible match in a linked series event. Only playoff matches count when a round robin continues to playoffs; group matches count when round robin is the only phase. The match must include a completed standard full set reaching six games and an administrator must confirm the decision before review.</small>
              </div>
              <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" name="use_head_to_head_tiebreak" id="use_head_to_head_tiebreak"
                       <?php echo e(($series->use_head_to_head_tiebreak ?? true) ? 'checked' : ''); ?>>
              </div>
            </div>

          </form>
        </div>
        <div class="card-footer bg-white border-top d-flex justify-content-end">
          <button type="button" class="btn btn-primary" id="save-series-btn">
            <i class="ti ti-device-floppy me-1"></i>Save Settings
          </button>
        </div>
      </div>

      
      <div class="tab-pane fade" id="pane-points" role="tabpanel">
        <div class="card-body pb-0">
          <p class="text-muted small mb-3">Define points awarded per finishing position (1–<?php echo e(count($positions)); ?>).</p>
        </div>
        <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th class="ps-4" style="width:130px;">Position</th>
                <th>Points Awarded</th>
              </tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $positions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pos): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $point = optional($series->points->firstWhere('position', $pos))->score ?? 0; ?>
                <tr>
                  <td class="ps-4 align-middle">
                    <span class="badge bg-label-secondary">#<?php echo e($pos); ?></span>
                  </td>
                  <td class="align-middle py-1">
                    <input type="number" class="form-control form-control-sm point-input"
                           data-position="<?php echo e($pos); ?>" min="0" value="<?php echo e($point); ?>"
                           style="max-width:120px;">
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-white border-top d-flex justify-content-end">
          <button type="button" class="btn btn-success" id="save-points-btn">
            <i class="ti ti-device-floppy me-1"></i>Save Points
          </button>
        </div>
      </div>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($series->ranking_lists->isNotEmpty()): ?>
      <div class="tab-pane fade" id="pane-categories" role="tabpanel">
        <div class="card-body pb-0">
          <p class="text-muted small mb-3">
            Override the number of best results counted per category. Leave blank to use the series default
            <strong>(<?php echo e($series->best_num_of_scores); ?>)</strong>.
          </p>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-4">Category</th>
                <th style="width:220px;">Best N Events</th>
                <th style="width:70px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $series->ranking_lists; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td class="ps-4 align-middle"><?php echo e($rl->category?->name ?? 'Category #'.$rl->category_id); ?></td>
                <td class="align-middle py-1">
                  <input type="number" class="form-control form-control-sm cat-best-input"
                         data-id="<?php echo e($rl->id); ?>" min="1" max="99"
                         placeholder="<?php echo e($series->best_num_of_scores); ?> (default)"
                         value="<?php echo e($rl->best_num_of_scores ?? ''); ?>">
                </td>
                <td class="align-middle">
                  <button class="btn btn-sm btn-outline-success btn-save-cat-best" data-id="<?php echo e($rl->id); ?>" title="Save this row">
                    <i class="ti ti-device-floppy"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-white border-top d-flex justify-content-end">
          <button class="btn btn-info btn-sm" id="btn-save-all-cat-best">
            <i class="ti ti-device-floppy me-1"></i>Save All Categories
          </button>
        </div>
      </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
  toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    timeOut: 2500
  };

  // ── General Settings ──────────────────────────────────
  const rankingRulePresets = <?php echo json_encode($rankingRulePresets->mapWithKeys(fn ($preset) => [
    $preset->id => $preset->rules, ]), 512) ?>;
  const presetSelect = document.getElementById('ranking_rule_preset_id');
  const presetRuleInputs = [
    document.querySelector('[name="best_num_of_scores"]'),
    document.querySelector('[name="minimum_events_for_team_selection"]'),
    document.getElementById('auto_award_rule'),
    document.getElementById('use_third_score_tiebreak'),
    document.getElementById('use_last_leg_position_tiebreak'),
    document.getElementById('use_head_to_head_tiebreak'),
  ];

  presetSelect.addEventListener('change', () => {
    const rules = rankingRulePresets[presetSelect.value];
    if (!rules) return;

    document.querySelector('[name="best_num_of_scores"]').value = rules.best_num_of_scores;
    document.querySelector('[name="minimum_events_for_team_selection"]').value = rules.minimum_events_for_team_selection || 1;
    document.getElementById('auto_award_rule').checked = Boolean(rules.auto_award_rule);
    document.getElementById('use_third_score_tiebreak').checked = Boolean(rules.use_third_score_tiebreak);
    document.getElementById('use_last_leg_position_tiebreak').checked = Boolean(rules.use_last_leg_position_tiebreak);
    document.getElementById('use_head_to_head_tiebreak').checked = Boolean(rules.use_head_to_head_tiebreak);
  });

  presetRuleInputs.forEach(input => input.addEventListener('input', () => {
    presetSelect.value = '';
  }));

  const saveSeriesSettings = (savePresetName = '') => {
    const btn = document.getElementById('save-series-btn');
    const presetBtn = document.getElementById('save-preset-btn');
    const leaderboardToggle = document.getElementById('leaderboard_published');
    btn.disabled = true;
    presetBtn.disabled = true;

    const payload = {
      name:                 document.querySelector('[name="name"]').value,
      year:                 document.querySelector('[name="year"]').value,
      best_num_of_scores:   document.querySelector('[name="best_num_of_scores"]').value,
      minimum_events_for_team_selection: document.querySelector('[name="minimum_events_for_team_selection"]').value,
      rank_type:            document.querySelector('[name="rank_type"]:not([disabled])') ? document.querySelector('[name="rank_type"]').value : null,
      auto_award_rule:      document.getElementById('auto_award_rule').checked ? 1 : 0,
      use_third_score_tiebreak: document.getElementById('use_third_score_tiebreak').checked ? 1 : 0,
      use_last_leg_position_tiebreak: document.getElementById('use_last_leg_position_tiebreak').checked ? 1 : 0,
      use_head_to_head_tiebreak: document.getElementById('use_head_to_head_tiebreak').checked ? 1 : 0,
      ranking_rule_preset_id: presetSelect.value || null,
      ranking_review_default_hours: document.querySelector('[name="ranking_review_default_hours"]').value,
    };

    if (savePresetName) payload.save_preset_name = savePresetName;

    if (!leaderboardToggle.disabled) {
      payload.leaderboard_published = leaderboardToggle.checked ? 1 : 0;
    }

    fetch('<?php echo e(route('ranking.series.update', $series)); ?>', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
      },
      body: JSON.stringify(payload)
    })
    .then(async response => {
      const contentType = response.headers.get('content-type') || '';
      const payload = contentType.includes('application/json')
        ? await response.json()
        : { message: `The server could not save the ranking settings (HTTP ${response.status}).` };
      if (!response.ok) {
        const validationMessage = payload.errors
          ? Object.values(payload.errors).flat().find(Boolean)
          : null;
        throw new Error(validationMessage || payload.message || 'Failed to save series settings');
      }
      return payload;
    })
    .then(r => {
      toastr.success(r.message || 'Series settings saved');
      if (savePresetName) window.location.reload();
    })
    .catch(error => toastr.error(error.message || 'Failed to save series settings'))
    .finally(() => {
      btn.disabled = false;
      presetBtn.disabled = false;
    });
  };

  document.getElementById('save-series-btn').addEventListener('click', () => saveSeriesSettings());
  document.getElementById('save-preset-btn').addEventListener('click', () => {
    const input = document.getElementById('save_preset_name');
    const name = input.value.trim();
    if (!name) {
      toastr.error('Enter a name for the ranking-rule preset.');
      input.focus();
      return;
    }

    saveSeriesSettings(name);
  });

  document.querySelectorAll('.ranking-lifecycle-action').forEach(button => {
    button.addEventListener('click', async () => {
      if (!window.confirm(button.dataset.confirm)) return;

      button.disabled = true;
      try {
        const response = await fetch(button.dataset.url, {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
          }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Ranking action failed');
        toastr.success(payload.message);
        location.reload();
      } catch (error) {
        toastr.error(error.message || 'Ranking action failed');
        button.disabled = false;
      }
    });
  });

  // ── Points Allocation ─────────────────────────────────
  document.getElementById('save-points-btn').addEventListener('click', () => {
    const btn = document.getElementById('save-points-btn');
    btn.disabled = true;

    const points = [];
    document.querySelectorAll('.point-input').forEach(input => {
      points.push({ position: Number(input.dataset.position), score: Number(input.value || 0) });
    });

    fetch('<?php echo e(route('ranking.points.update', $series)); ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>' },
      body: JSON.stringify({ points })
    })
    .then(r => r.json())
    .then(r => toastr.success(r.message || 'Points saved successfully'))
    .catch(() => toastr.error('Failed to save points'))
    .finally(() => btn.disabled = false);
  });

  // ── Per-Category Best-N ───────────────────────────────
  const catBestUrl = '<?php echo e(route('series.category-best-num', $series)); ?>';
  const csrfToken  = '<?php echo e(csrf_token()); ?>';

  function saveCatBest(payload, btn) {
    if (btn) btn.disabled = true;
    fetch(catBestUrl, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ category_best: payload })
    })
    .then(r => r.json())
    .then(r => toastr.success(r.message || 'Saved'))
    .catch(() => toastr.error('Failed to save'))
    .finally(() => { if (btn) btn.disabled = false; });
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-save-cat-best');
    if (!btn) return;
    const id  = btn.dataset.id;
    const val = document.querySelector(`.cat-best-input[data-id="${id}"]`).value;
    saveCatBest({ [id]: val || null }, btn);
  });

  const saveAllBtn = document.getElementById('btn-save-all-cat-best');
  if (saveAllBtn) {
    saveAllBtn.addEventListener('click', function () {
      const payload = {};
      document.querySelectorAll('.cat-best-input').forEach(input => {
        payload[input.dataset.id] = input.value || null;
      });
      saveCatBest(payload, saveAllBtn);
    });
  }

  // ── Persist active tab across page loads ──────────────
  const tabLinks = document.querySelectorAll('.settings-tabs .nav-link');
  const savedTab = localStorage.getItem('seriesSettingsTab');
  if (savedTab) {
    const target = document.querySelector(`.settings-tabs .nav-link[href="${savedTab}"]`);
    if (target) bootstrap.Tab.getOrCreateInstance(target).show();
  }
  tabLinks.forEach(link => {
    link.addEventListener('shown.bs.tab', () => {
      localStorage.setItem('seriesSettingsTab', link.getAttribute('href'));
    });
  });
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\series-settings.blade.php ENDPATH**/ ?>