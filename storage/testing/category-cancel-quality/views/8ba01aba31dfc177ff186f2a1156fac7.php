<section class="region-organizer-card" aria-label="Regional organizer" data-region-organizers>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $organizerRegions ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organizer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php ($scope = $organizer['scope']); ?>
    <?php ($manager = $organizer['manager']); ?>
    <?php ($defaults = $organizer['defaults']); ?>
    <div data-region-organizer="<?php echo e($scope->region_id); ?>" <?php if(!$loop->first): ?> hidden <?php endif; ?>>
      <details data-organizer-editor <?php if($errors->has('manager_user_id') || $errors->has('manager_email')): ?> open <?php endif; ?>>
        <summary class="btn btn-outline-secondary" aria-label="Regional organizer settings">
          <i class="ti ti-user-cog" aria-hidden="true"></i> Organizer
        </summary>
        <div class="small mt-2">Regional organizer <strong><?php echo e($manager?->name ?: trim(($manager?->userName ?? '').' '.($manager?->userSurname ?? '')) ?: $manager?->email ?: 'Not assigned'); ?></strong></div>
        <div class="fw-semibold"><?php echo e($manager ? 'Change organizer' : 'Assign organizer'); ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($manager?->email): ?><div class="small text-muted region-organizer-identity"><?php echo e($manager->email); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span class="badge bg-label-<?php echo e($scope->managerAssignment ? 'primary' : 'secondary'); ?>"><?php echo e($scope->managerAssignment ? 'Custom assignment' : ($manager ? 'Series organizer default' : 'Not assigned')); ?></span>
        <p class="small text-muted mt-2">Assign an existing Cape Tennis user to <?php echo e($scope->region?->region_name); ?> for this event. A player profile is not required.</p>
        <form method="POST" action="<?php echo e(route('backend.team-selection.manager.assign', [$event, $scope])); ?>" class="row g-2 align-items-end">
          <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
          <div class="col-12 col-lg-6"><label class="form-label" for="workspace-region-manager-<?php echo e($scope->id); ?>">Select a system user</label><select id="workspace-region-manager-<?php echo e($scope->id); ?>" name="manager_user_id" class="form-select" data-organizer-user data-search-url="<?php echo e(route('backend.team-selection.users.search', $event)); ?>"><option value=""></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scope->managerAssignment && $manager): ?><option value="<?php echo e($manager->id); ?>" selected><?php echo e($manager->name ?: $manager->email); ?> · <?php echo e($manager->email); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
          <div class="col-12 col-sm-6 col-lg-3 d-grid"><button class="btn btn-primary">Assign to this region</button></div>
          <div class="col-12 col-sm-6 col-lg-3 d-grid"><button class="btn btn-outline-secondary" name="use_default" value="1" <?php if($defaults->count() !== 1): echo 'disabled'; endif; ?>>Use default</button></div>
        </form>
        <p class="small text-muted mt-2 mb-0">This grants access to this region. Existing event-wide or other-region access stays unchanged.</p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$scope->managerAssignment && $defaults->count() > 1): ?><div class="alert alert-warning mt-2 mb-0">Multiple default organizers were found. Assign the intended account explicitly.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user')): ?><a class="btn btn-sm btn-outline-secondary mt-3" href="<?php echo e(route('backend.team-selection.manager.preview', [$event, $scope])); ?>">Preview regional organizer view</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </details>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_region-organizer.blade.php ENDPATH**/ ?>