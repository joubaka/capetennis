<?php $__env->startSection('title', 'Practice camp planning'); ?>
<?php $__env->startSection('content'); ?>
<div data-camp-workflow class="container-xxl flex-grow-1 container-p-y">
  <h1 class="h3">Practice camp planning</h1>
  <p>Prepare a private invitation list from existing regions, teams and rosters.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?><div class="alert alert-success" role="status"><?php echo e(session('status')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('role', 'super-user')): ?>
  <div class="card mb-4" data-super-admin-only><div class="card-body">
    <h2 class="h5">Cavaliers Practice Camp 2027</h2>
    <p>Provisional dates: <strong>7–9 January 2027</strong>. Venue, fees, capacity and contacts still to be confirmed.</p>
    <form method="POST" action="<?php echo e(route('backend.practice-camps.store')); ?>"><?php echo csrf_field(); ?>
      <button class="btn btn-primary">Create or open the private 2027 draft</button>
    </form>
    <p class="small text-muted mt-2 mb-0">Registration stays closed. This does not publish the event or send invitations.</p>
  </div></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card mb-4"><div class="card-body"><h2 class="h5">Manage another camp</h2>
    <p>Existing camps keep their current entry behaviour until you save camp settings. Regional invitations are optional.</p>
    <a class="btn btn-outline-primary mb-3" href="<?php echo e(route('backend.events.create')); ?>">Create a new event — choose Camp</a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $campEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><form class="mb-2" method="POST" action="<?php echo e(route('backend.camps.attach', $campEvent)); ?>"><?php echo csrf_field(); ?><button class="btn btn-outline-secondary"><?php echo e($campEvent->name); ?> · Camp settings</button></form><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
      <div><h2 class="h5 mb-1"><?php echo e($plan->event->name); ?></h2><span class="badge bg-label-secondary">Private draft</span></div>
      <a class="btn btn-outline-primary" href="<?php echo e(route('backend.practice-camps.show', $plan)); ?>">Open camp planner</a>
    </div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p>No camp drafts yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo e($plans->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<?php echo $__env->make('backend.practice-camps.feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\practice-camps\index.blade.php ENDPATH**/ ?>