<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sourceEvent): ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$finalization): ?><p class="alert alert-info" role="status">No teams are approved yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request()->boolean('source_choices') && $previewErrors): ?><div class="alert alert-danger" role="alert"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $previewErrors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="mb-0"><?php echo e($message); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<fieldset class="card mb-3"><div class="card-body"><legend class="h5">What do you want to import?</legend><label class="d-block mb-3"><input type="radio" name="source-choice-mode" value="approved_team" <?php if($importMode === 'approved_team'): echo 'checked'; endif; ?> <?php if(!$finalization): echo 'disabled'; endif; ?>> Approved selected team · <?php echo e($sourceEvent->name); ?></label><label class="d-block"><input type="radio" name="source-choice-mode" value="regions" <?php if($importMode !== 'approved_team' || !$finalization): echo 'checked'; endif; ?>> Source regions</label></div></fieldset>
<section class="card mb-3" data-source-mode="approved_team"><div class="card-body">
  <h4>Approved selected team · <?php echo e($sourceEvent->name); ?></h4>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($finalization): ?>
    <p>Approved <?php echo e($finalization->finalized_at->format('d M Y H:i')); ?> · <?php echo e(count($finalization->groups)); ?> age/gender <?php echo e(count($finalization->groups) === 1 ? 'group' : 'groups'); ?>. Includes the complete saved selection and its reserves.</p>
    <form method="GET" action="<?php echo e(route('backend.selection-import.index', $event)); ?>">
      <input type="hidden" name="source_event_id" value="<?php echo e($sourceEvent->id); ?>">
      <input type="hidden" name="import_mode" value="approved_team">
      <button class="btn btn-primary" style="min-height:44px">Review approved selected team</button>
    </form>
  <?php else: ?>
    <p class="mb-0">No teams are approved yet.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></section>
<form method="GET" action="<?php echo e(route('backend.selection-import.index', $event)); ?>" class="card mb-3" data-source-mode="regions"><div class="card-body">
  <input type="hidden" name="source_event_id" value="<?php echo e($sourceEvent->id); ?>">
  <input type="hidden" name="choose_regions" value="1">
  <input type="hidden" name="review_regions" value="1">
  <input type="hidden" name="import_mode" value="regions">
  <h4>Regions in <?php echo e($sourceEvent->name); ?></h4>
  <p>Check the regions to import. A finalized age/gender selection can combine several regions; select those regions together to keep its team and reserves intact.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $sourceRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sourceRegion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <?php ($available = isset($regionGroups[$sourceRegion->id])); ?>
  <label class="d-flex align-items-start gap-3 border rounded p-3 mb-2" for="source-region-<?php echo e($sourceRegion->id); ?>">
    <input type="checkbox" class="form-check-input mt-1" id="source-region-<?php echo e($sourceRegion->id); ?>" name="source_region_ids[]" value="<?php echo e($sourceRegion->id); ?>" <?php if(!$available): echo 'disabled'; endif; ?> <?php if($available && in_array((int)$sourceRegion->id, array_map('intval', (array)old('source_region_ids', $selectedRegionIds)), true)): echo 'checked'; endif; ?>>
    <span><strong><?php echo e($sourceRegion->region_name); ?></strong><br>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($available): ?><span class="badge bg-label-success">Included in finalized selection</span> <span class="small"><?php echo e(count($regionGroups[$sourceRegion->id])); ?> age/gender <?php echo e(count($regionGroups[$sourceRegion->id]) === 1 ? 'group' : 'groups'); ?></span>
      <?php else: ?><span class="badge bg-label-secondary">No finalized selection</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </span>
  </label>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No regions are linked to this source event.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$finalization): ?><p class="mt-3">Finalize the saved teams in the source event to make its selections available here.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <button class="btn btn-primary mt-3" style="min-height:44px" <?php if($regionGroups === []): echo 'disabled'; endif; ?>>Review selected regions</button>
</div></form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\partials\selection-import-source-choices.blade.php ENDPATH**/ ?>