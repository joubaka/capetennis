<?php $__env->startSection('title', 'User account statements'); ?>
<?php $__env->startSection('content'); ?>
<?php if (isset($component)) { $__componentOriginal6ccefb989a1afce853acb3cdbc40307e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.page-header','data' => ['title' => 'User account statements','eyebrow' => 'Super Admin','subtitle' => 'Find a payer account and download its entry fee and clothing payment history.','icon' => 'ti-file-invoice']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'User account statements','eyebrow' => 'Super Admin','subtitle' => 'Find a payer account and download its entry fee and clothing payment history.','icon' => 'ti-file-invoice']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $attributes = $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $component = $__componentOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<div class="card"><div class="card-body">
    <a href="<?php echo e(route('backend.dashboard')); ?>" class="btn btn-outline-secondary mb-3">Back to dashboard</a>
    <form action="<?php echo e(route('superadmin.account-statements.index')); ?>" method="get" class="row g-3 align-items-end mb-4">
        <div class="col-12 col-md-8"><label for="statement-user-search" class="form-label">Search name or email</label><input id="statement-user-search" name="search" class="form-control" maxlength="100" value="<?php echo e($search); ?>" placeholder="Username, first name, surname or email"></div>
        <div class="col-12 col-md-4 d-flex flex-wrap gap-2"><button class="btn btn-primary">Search</button><a href="<?php echo e(route('superadmin.account-statements.index')); ?>" class="btn btn-outline-secondary">Clear</a></div>
    </form>
    <p class="small text-muted"><?php echo e($users->total()); ?> matching accounts. Users do not need a wallet to have a statement.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Account</th><th>Email</th><th>Statement</th></tr></thead><tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($user->name); ?><br><small><?php echo e(trim(($user->userName ?? '').' '.($user->userSurname ?? ''))); ?></small></td><td class="text-break"><?php echo e($user->email); ?></td><td><a class="btn btn-outline-primary" href="<?php echo e(route('superadmin.account-statements.show', $user)); ?>">View statement</a></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="3">No accounts match this search.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody></table></div>
    <?php echo e($users->links('pagination::bootstrap-5')); ?>

</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\account-statements\index.blade.php ENDPATH**/ ?>