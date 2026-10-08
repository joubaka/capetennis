

<?php $__env->startSection('title', 'Disciplinary Settings'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="ti ti-settings me-2"></i>Disciplinary Settings</h4>
            <p class="text-muted mb-0">Configure violation types, point weights, and suspension thresholds</p>
        </div>
        <a href="<?php echo e(route('backend.disciplinary.index')); ?>" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> Back to Log
        </a>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo e(session('error')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="ti ti-adjustments me-2"></i>Threshold & Expiry Settings</h5>
        </div>
        <div class="card-body">
            <form action="<?php echo e(route('backend.disciplinary.settings.update')); ?>" method="POST">
                <?php echo csrf_field(); ?>

                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            Suspension Threshold (points)
                            <i class="ti ti-info-circle ms-1 text-muted" title="Player is suspended when active points reach or exceed this value"></i>
                        </label>
                        <input type="number" name="suspension_threshold"
                               class="form-control <?php $__errorArgs = ['suspension_threshold'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               value="<?php echo e(old('suspension_threshold', $settings['suspension_threshold']->value ?? 12)); ?>"
                               min="1" max="1000">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['suspension_threshold'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            Points Expiry (days)
                        </label>
                        <input type="number" name="expiry_days"
                               class="form-control <?php $__errorArgs = ['expiry_days'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               value="<?php echo e(old('expiry_days', $settings['expiry_days']->value ?? 365)); ?>"
                               min="1" max="3650">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['expiry_days'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            1st Suspension Duration (months)
                        </label>
                        <input type="number" name="first_suspension_months"
                               class="form-control <?php $__errorArgs = ['first_suspension_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               value="<?php echo e(old('first_suspension_months', $settings['first_suspension_months']->value ?? 3)); ?>"
                               min="1" max="120">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['first_suspension_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">
                            2nd+ Suspension Duration (months)
                        </label>
                        <input type="number" name="second_suspension_months"
                               class="form-control <?php $__errorArgs = ['second_suspension_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               value="<?php echo e(old('second_suspension_months', $settings['second_suspension_months']->value ?? 6)); ?>"
                               min="1" max="120">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['second_suspension_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="ti ti-list me-2"></i>Violation Types</h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#addTypeForm">
                <i class="ti ti-plus me-1"></i> Add Type
            </button>
        </div>

        
        <div class="collapse" id="addTypeForm">
            <div class="card-body border-bottom bg-light">
                <form action="<?php echo e(route('backend.disciplinary.violation-type.store')); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="name" class="form-control" required maxlength="100"
                                   value="<?php echo e(old('name')); ?>" placeholder="e.g. Racket Abuse">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" class="form-select select2" required>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Models\ViolationType::$categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($key); ?>" <?php if(old('category') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Points</label>
                            <input type="number" name="default_points" class="form-control" min="0" max="100"
                                   value="<?php echo e(old('default_points', 2)); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" name="description" class="form-control" maxlength="500"
                                   value="<?php echo e(old('description')); ?>">
                        </div>
                        <div class="col-md-1 text-center">
                            <label class="form-label fw-semibold">Active</label>
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input" type="checkbox" name="active" value="1" checked>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="ti ti-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Default Points</th>
                        <th>Description</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $violationTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <form action="<?php echo e(route('backend.disciplinary.violation-type.update', $vt)); ?>"
                                  method="POST" id="vt-form-<?php echo e($vt->id); ?>">
                                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                                <td>
                                    <input type="text" name="name" class="form-control form-control-sm"
                                           value="<?php echo e($vt->name); ?>" required maxlength="100">
                                </td>
                                <td>
                                    <select name="category" class="form-select form-select-sm select2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Models\ViolationType::$categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($key); ?>" <?php if($vt->category === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="default_points" class="form-control form-control-sm"
                                           style="width:80px;" value="<?php echo e($vt->default_points); ?>" min="0" max="100">
                                </td>
                                <td>
                                    <input type="text" name="description" class="form-control form-control-sm"
                                           value="<?php echo e($vt->description); ?>" maxlength="500">
                                </td>
                                <td>
                                    <input type="hidden" name="active" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="active"
                                               value="1" <?php echo e($vt->active ? 'checked' : ''); ?>>
                                    </div>
                                </td>
                            </form>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="submit" form="vt-form-<?php echo e($vt->id); ?>"
                                            class="btn btn-sm btn-outline-primary" title="Save">
                                        <i class="ti ti-device-floppy"></i>
                                    </button>
                                    <form action="<?php echo e(route('backend.disciplinary.violation-type.destroy', $vt)); ?>"
                                          method="POST"
                                          onsubmit="return confirm('Delete this violation type?');">
                                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No violation types configured.</td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="ti ti-table me-2"></i>Code of Conduct Quick Reference</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-3">Summary of active violation types and their suspension-point values. Edit the violation types above to update this table.</p>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Violation Type</th>
                            <th>Category</th>
                            <th>Suspension Points</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $violationTypes->sortBy(['category', 'default_points']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="<?php echo e($vt->active ? '' : 'text-muted'); ?>">
                            <td><?php echo e($vt->name); ?></td>
                            <td><?php echo e($vt->category_label); ?></td>
                            <td><strong><?php echo e($vt->default_points); ?></strong></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($vt->active): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <tr class="table-warning">
                            <td colspan="2"><strong>Cumulative Total → Suspension</strong></td>
                            <td colspan="2"><strong><?php echo e($settings['suspension_threshold']->value ?? 12); ?> points within <?php echo e(round(($settings['expiry_days']->value ?? 365) / 30)); ?> months → <?php echo e($settings['first_suspension_months']->value ?? 3); ?>-month suspension (1st offence), <?php echo e($settings['second_suspension_months']->value ?? 6); ?>-month suspension (2nd+ offence)</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php $__env->startSection('page-script'); ?>
<script>
    $(function () {
        $('.select2').select2();
    });
</script>
<?php $__env->stopSection(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\disciplinary\settings.blade.php ENDPATH**/ ?>