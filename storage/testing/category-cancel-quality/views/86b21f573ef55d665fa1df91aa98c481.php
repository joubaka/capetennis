<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
<?php
  $resultSetup = app(\App\Services\TeamResultRankingService::class)->setup($event);
  $savedResultSettings = $event->team_result_settings;
  $finalization = \App\Models\EventSelectionFinalization::active()->where('event_id', $event->id)->first(['groups']);
  $lockedGroups = $finalization?->groups ?? [];
?>
<div class="tab-pane fade result-workspace" id="tab-result-rank" role="tabpanel" aria-labelledby="result-rank-button" data-settings-saved="<?php echo e($savedResultSettings !== null ? '1' : '0'); ?>" data-settings-url="<?php echo e(route('backend.team-result-settings.store', $event)); ?>" data-selection-url="<?php echo e(route('backend.team-result-selection.show', $event)); ?>">
  <div class="result-page-heading">
    <div><h5>Calculated tournament order</h5><p>This order is calculated from singles results and the filters in Setup. The saved team has its own selection order.</p></div>
    <div class="d-flex flex-wrap align-items-start gap-2">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($finalization): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $resultSetup['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($lockedGroups[$group['key']])): ?>
            <a class="<?php echo \Illuminate\Support\Arr::toCssClasses(['btn btn-primary', 'd-none' => !$loop->first]); ?>" data-locked-team-group="<?php echo e($group['key']); ?>"
               href="<?php echo e(route('backend.selection-preview.show', ['event' => $event, 'age' => $group['age'], 'gender' => \Illuminate\Support\Str::after($group['key'], '-')])); ?>">Open locked team · <?php echo e($group['name']); ?></a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span class="<?php echo \Illuminate\Support\Arr::toCssClasses(['text-muted small', 'd-none' => isset($lockedGroups[$resultSetup['groups']->first()['key'] ?? ''])]); ?>" data-locked-team-unavailable>No locked team for this group.</span>
      <?php else: ?>
        <button type="button" class="btn btn-primary" data-selection-preview="<?php echo e(route('backend.selection-preview.show', $event)); ?>">Preview Selections</button>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#result-ranks-setup" aria-expanded="false" aria-controls="result-ranks-setup"><i class="ti ti-adjustments-horizontal" aria-hidden="true"></i> Setup</button>
    </div>
  </div>
  <div class="collapse" id="result-ranks-setup">
    <div class="result-setup">
      <fieldset><legend>Candidate regions</legend><div class="result-option-grid">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $resultSetup['regions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-region value="<?php echo e($region->id); ?>" <?php if(!isset($savedResultSettings['region_ids']) || in_array($region->id, $savedResultSettings['region_ids'])): echo 'checked'; endif; ?>><span><?php echo e($region->region_name); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="text-muted">No event regions configured.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></fieldset>
      <fieldset><legend>Exclude results involving regions</legend><div class="result-option-grid">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $resultSetup['regions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-excluded-region value="<?php echo e($region->id); ?>" <?php if(in_array($region->id, $savedResultSettings['excluded_result_region_ids'] ?? [])): echo 'checked'; endif; ?>><span><?php echo e($region->region_name); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div><p class="result-setup-note">Exclude the entire match when either side represents a checked region. Candidate regions above still control which players appear.</p></fieldset>
      <fieldset><legend>Match formats</legend><div class="result-format-grid">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $resultSetup['formats']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-format value="<?php echo e($format); ?>" <?php if(!isset($savedResultSettings['formats']) || in_array($format, $savedResultSettings['formats'])): echo 'checked'; endif; ?>><span><?php echo e($format === 'reverse_singles' ? 'Reverse Singles' : 'Singles'); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></fieldset>
      <p class="result-setup-note">Candidate regions choose players. Matches count unless a side's region is excluded from results. Save event settings to reuse these choices for every age group and gender when reopening this event. Saved drafts keep their own setup.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2"><button type="button" class="btn btn-primary" data-result-settings-save>Save event settings</button><span data-result-settings-status role="status" aria-live="polite">Event settings ready.</span></div>
  </div>
  <div class="result-layout">
    <aside class="result-group-rail" aria-label="Age group and gender">
      <label class="result-age-label">Age group &amp; gender<select class="form-select" data-result-group-select aria-label="Choose age group and gender">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $resultSetup['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($group['key']); ?>"><?php echo e($group['name']); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select></label>
      <div class="result-group-grid" hidden>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $resultSetup['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <label class="result-choice result-group-choice"><input class="form-check-input category-radio" type="radio" name="category-radio" value="<?php echo e($group['key']); ?>" data-name="<?php echo e($group['name']); ?>" data-event_id="<?php echo e($event->id); ?>" <?php echo e($loop->first ? 'checked' : ''); ?>><span><?php echo e($group['name']); ?></span></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="result-rail-empty">Age groups appear here when singles fixtures are available.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <div class="result-order-rail">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($finalization): ?>
          <h6>Suggested order from calculation</h6>
          <p>The locked team shows the saved players, order and reserves.</p>
        <?php else: ?>
          <h6>Draft ranking order</h6>
          <p>Drag or use ↑ ↓. Tick up to 12. Changes and optional comments save automatically to your private draft.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <ol data-draft-order aria-label="<?php echo e($finalization ? 'Suggested player ranking' : 'Draft player ranking'); ?>"></ol>
      </div>
    </aside>
    <section class="result-ranking-panel" aria-label="Player ranking and draft selection">
      <div class="result-ranking-header">
        <div><h5 id="category-name"></h5><span class="result-heading-hint">Band-first ranking · Compare adjacent bands · Select up to 12</span></div>
        <span class="result-heading-hint">Calculated result evidence</span>
      </div>
      <div id="category-table" class="result-ranking-body"></div>
    </section>
  </div>
  <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['result-persistent-actions', 'd-none' => $finalization !== null]); ?>" aria-label="Private draft autosave controls">
    <div class="result-draft-progress" aria-live="polite" aria-atomic="true"><strong data-draft-category></strong><strong data-draft-count>0 / 12 selected</strong><span data-draft-state>Private draft</span><small data-draft-reasons></small></div>
    <div class="result-draft-actions"><button type="button" class="btn btn-outline-primary" data-draft-open>Reorder players</button><button type="button" class="btn btn-outline-primary" data-selection-load>Load draft</button><button type="button" class="btn btn-primary" data-selection-save>Save draft</button></div>
    <div data-selection-status role="status" class="result-action-status"></div><button type="button" class="btn btn-outline-primary" data-selection-retry hidden>Retry draft check</button>
  </div>
  <div class="offcanvas offcanvas-start result-draft-drawer" tabindex="-1" id="result-draft-drawer" aria-labelledby="result-draft-drawer-label">
    <div class="offcanvas-header"><h5 id="result-draft-drawer-label">Arrange private draft</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close draft order"></button></div>
    <div class="offcanvas-body" data-draft-drawer-body></div>
    <div class="result-drawer-footer"><span data-draft-count></span><span data-draft-reasons></span><div data-selection-status role="status"></div><button type="button" class="btn btn-outline-primary" data-selection-retry hidden>Retry draft check</button><button type="button" class="btn btn-primary w-100" data-selection-save>Save draft</button></div>
  </div>
</div>
<?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_partials\result-ranks.blade.php ENDPATH**/ ?>