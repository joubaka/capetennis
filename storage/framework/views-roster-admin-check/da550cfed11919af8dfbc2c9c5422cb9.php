<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
<?php $resultSetup = app(\App\Services\TeamResultRankingService::class)->setup($event); ?>
<div class="tab-pane fade result-workspace" id="tab-result-rank" role="tabpanel" aria-labelledby="result-rank-button" data-selection-url="<?php echo e(route('backend.team-result-selection.show', $event)); ?>">
  <div class="result-page-heading">
    <div><h5>Team selection results</h5><p>Compare singles results and build a draft team of up to 10 players.</p></div>
    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#result-ranks-setup" aria-expanded="false" aria-controls="result-ranks-setup"><i class="ti ti-adjustments-horizontal" aria-hidden="true"></i> Setup</button>
  </div>
  <div class="collapse" id="result-ranks-setup">
    <div class="result-setup">
      <fieldset><legend>Candidate regions</legend><div class="result-option-grid">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $resultSetup['regions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-region value="<?php echo e($region->id); ?>" checked><span><?php echo e($region->region_name); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="text-muted">No event regions configured.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></fieldset>
      <fieldset><legend>Exclude results involving regions</legend><div class="result-option-grid">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $resultSetup['regions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-excluded-region value="<?php echo e($region->id); ?>"><span><?php echo e($region->region_name); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div><p class="result-setup-note">Exclude the entire match when either side represents a checked region. Candidate regions above still control which players appear.</p></fieldset>
      <fieldset><legend>Match formats</legend><div class="result-format-grid">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $resultSetup['formats']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-format value="<?php echo e($format); ?>" checked><span><?php echo e($format === 'reverse_singles' ? 'Reverse Singles' : 'Singles'); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></fieldset>
      <p class="result-setup-note">Candidate regions choose players. Matches count unless a side's region is excluded from results. Save a draft to retain both choices.</p>
    </div>
  </div>
  <div class="result-layout">
    <aside class="result-group-rail" aria-label="Age group and gender">
      <h6>Age group &amp; gender</h6>
      <div class="result-group-grid">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $resultSetup['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <label class="result-choice result-group-choice"><input class="form-check-input category-radio" type="radio" name="category-radio" value="<?php echo e($group['key']); ?>" data-name="<?php echo e($group['name']); ?>" data-event_id="<?php echo e($event->id); ?>" <?php echo e($loop->first ? 'checked' : ''); ?>><span><?php echo e($group['name']); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="result-rail-empty">Age groups appear here when singles fixtures are available.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </aside>
    <section class="result-ranking-panel" aria-label="Player ranking and draft selection">
      <div class="result-ranking-header">
        <div><h5 id="category-name"></h5><span class="result-heading-hint">Band-first ranking · Compare adjacent bands · Select up to 10</span></div>
        <div class="result-draft-actions"><button type="button" class="btn btn-outline-primary" data-selection-load>Load draft</button><button type="button" class="btn btn-primary" data-selection-save>Save draft</button></div>
      </div>
      <div data-selection-status role="status" class="result-draft-status"></div>
      <div id="category-table" class="result-ranking-body"></div>
    </section>
  </div>
</div>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_partials\result-ranks.blade.php ENDPATH**/ ?>