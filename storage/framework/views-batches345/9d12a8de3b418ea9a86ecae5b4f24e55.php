

<?php $__env->startSection('title', 'Disciplinary Log'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>" />
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="ti ti-gavel me-2"></i>Disciplinary Log</h4>
            <p class="text-muted mb-0">All recorded violations across all players</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route('backend.disciplinary.create')); ?>" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Record Violation
            </a>
            <a href="<?php echo e(route('backend.disciplinary.settings')); ?>" class="btn btn-outline-secondary">
                <i class="ti ti-settings me-1"></i> Settings
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Player</label>
                    <select name="player_id" class="form-select select2">
                        <option value="">All Players</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($p->id); ?>" <?php if(request('player_id') == $p->id): echo 'selected'; endif; ?>>
                                <?php echo e($p->full_name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Violation Type</label>
                    <select name="violation_type_id" class="form-select select2">
                        <option value="">All Types</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $violationTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($vt->id); ?>" <?php if(request('violation_type_id') == $vt->id): echo 'selected'; endif; ?>>
                                <?php echo e($vt->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo e(request('date_from')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo e(request('date_to')); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                Showing <?php echo e($violations->firstItem() ?? 0); ?>–<?php echo e($violations->lastItem() ?? 0); ?> of <?php echo e($violations->total()); ?> result(s)
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Player</th>
                        <th>Type</th>
                        <th>Penalty</th>
                        <th>Points</th>
                        <th>Status</th>
                        <th>Event</th>
                        <th>Recorded By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $violations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="<?php echo e($v->is_expired ? 'text-muted' : ''); ?>">
                            <td><?php echo e($v->violation_date->format('d M Y')); ?></td>
                            <td>
                                <a href="<?php echo e(route('backend.disciplinary.player', $v->player_id)); ?>">
                                    <?php echo e($v->player->full_name); ?>

                                </a>
                            </td>
                            <td>
                                <span class="badge bg-label-<?php echo e(match($v->violationType->category ?? '') {
                                    'on_court' => 'warning',
                                    'withdrawal' => 'info',
                                    'no_show' => 'danger',
                                    'abuse' => 'danger',
                                    default => 'secondary'
                                }); ?>">
                                    <?php echo e($v->violationType->name ?? '—'); ?>

                                </span>
                            </td>
                            <td><?php echo e($v->penalty_type ? ucfirst($v->penalty_type) : '—'); ?></td>
                            <td>
                                <strong><?php echo e($v->points_assigned); ?></strong>
                            </td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($v->is_expired): ?>
                                    <span class="badge bg-secondary">Expired</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e($v->event?->name ?? '—'); ?></td>
                            <td><?php echo e($v->recorder->name ?? '—'); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?php echo e(route('backend.disciplinary.violation.edit', $v->id)); ?>"
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="ti ti-pencil"></i>
                                    </a>
                                    <form action="<?php echo e(route('backend.disciplinary.violation.destroy', $v->id)); ?>"
                                          method="POST"
                                          onsubmit="return confirm('Remove this violation?');">
                                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No violations found.</td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <?php echo e($violations->links()); ?>

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

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\disciplinary\index.blade.php ENDPATH**/ ?>