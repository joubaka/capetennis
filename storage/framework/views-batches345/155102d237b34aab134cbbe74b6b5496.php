

<?php $__env->startSection('title', 'Player Disciplinary Record — ' . $player->full_name); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">

    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="ti ti-gavel me-2"></i>
                <?php echo e($player->full_name); ?>

                &mdash; Disciplinary Record
            </h4>
            <p class="text-muted mb-0">All violations and suspensions for this player</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route('backend.disciplinary.create', ['player_id' => $player->id])); ?>"
               class="btn btn-primary">
                <i class="ti ti-plus me-1"></i> Record Violation
            </a>
            <a href="<?php echo e(route('backend.disciplinary.index')); ?>" class="btn btn-outline-secondary">
                <i class="ti ti-arrow-left me-1"></i> All Violations
            </a>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-<?php echo e($status['suspended'] ? 'danger' : ($status['active_points'] >= $status['threshold'] ? 'warning' : 'success')); ?>">
                <div class="card-body text-center">
                    <h6 class="card-subtitle text-muted mb-2">Active Suspension Points</h6>
                    <h1 class="display-4 fw-bold <?php echo e($status['active_points'] >= $status['threshold'] ? 'text-danger' : ''); ?>">
                        <?php echo e($status['active_points']); ?>

                    </h1>
                    <p class="text-muted mb-2">of <?php echo e($status['threshold']); ?> point threshold</p>

                    
                    <?php
                        $pct = min(100, $status['threshold'] > 0 ? round($status['active_points'] / $status['threshold'] * 100) : 0);
                        $barClass = $pct >= 100 ? 'bg-danger' : ($pct >= 75 ? 'bg-warning' : 'bg-success');
                    ?>
                    <div class="progress" style="height:10px;">
                        <div class="progress-bar <?php echo e($barClass); ?>" style="width: <?php echo e($pct); ?>%"></div>
                    </div>

                    <div class="mt-3">
                        <?php echo $__env->make('backend.disciplinary._status_badge', [
                            'suspended'    => $status['suspended'],
                            'activePoints' => $status['active_points'],
                            'threshold'    => $status['threshold'],
                        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Next On-Court Consequence (PPS)</h6>
                    <p class="mb-0 fs-5 fw-semibold text-warning">
                        <i class="ti ti-alert-triangle me-1"></i><?php echo e($pps); ?>

                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Suspension History</h6>
                    <p class="mb-1">Total suspensions: <strong><?php echo e($suspensions->count()); ?></strong></p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status['suspended']): ?>
                        <p class="text-danger mb-0">
                            <i class="ti ti-ban me-1"></i>
                            Currently suspended until <strong><?php echo e($status['suspension_ends_at']); ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="text-success mb-0"><i class="ti ti-check me-1"></i>Not currently suspended</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status['suspended']): ?>
        <?php $activeSuspension = $suspensions->first(fn($s) => $s->is_active); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeSuspension): ?>
        <div class="alert alert-danger d-flex justify-content-between align-items-center mb-4">
            <div>
                <strong><i class="ti ti-ban me-1"></i>Active Suspension (<?php echo e($activeSuspension->suspension_number); ?><?php echo e($activeSuspension->suspension_number === 1 ? 'st' : ($activeSuspension->suspension_number === 2 ? 'nd' : 'th')); ?>)</strong>
                &mdash; <?php echo e($activeSuspension->duration_months); ?> months.
                Ends: <strong><?php echo e($activeSuspension->ends_at->format('d M Y')); ?></strong>
            </div>
            <button type="button" class="btn btn-sm btn-outline-light"
                    data-bs-toggle="modal" data-bs-target="#liftSuspensionModal">
                <i class="ti ti-lock-open me-1"></i> Lift Suspension
            </button>
        </div>

        
        <div class="modal fade" id="liftSuspensionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ti ti-lock-open me-2"></i>Lift Suspension</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="<?php echo e(route('backend.disciplinary.suspension.lift', $activeSuspension->id)); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <div class="modal-body">
                            <p class="text-muted mb-3">
                                You are about to lift the active suspension for <strong><?php echo e($player->full_name); ?></strong>.
                                This action cannot be undone.
                            </p>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Reason for lifting <span class="text-danger">*</span>
                                </label>
                                <textarea name="reason" class="form-control" rows="3" required
                                          placeholder="e.g. Suspension overturned on appeal — committee decision 30 Apr 2026"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="ti ti-lock-open me-1"></i> Confirm Lift
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Violations</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Penalty</th>
                        <th>Points</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $violations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="<?php echo e($v->is_expired ? 'text-muted' : ''); ?>">
                            <td><?php echo e($v->violation_date->format('d M Y')); ?></td>
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
                            <td><strong><?php echo e($v->points_assigned); ?></strong></td>
                            <td><?php echo e($v->expires_at->format('d M Y')); ?></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($v->is_expired): ?>
                                    <span class="badge bg-secondary">Expired</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e(Str::limit($v->notes, 60)); ?></td>
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
                            <td colspan="8" class="text-center text-muted py-4">No violations recorded.</td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suspensions->isNotEmpty()): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Suspension History</h5>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Triggered</th>
                        <th>Duration</th>
                        <th>Starts</th>
                        <th>Ends</th>
                        <th>Status</th>
                        <th>Lifted By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $suspensions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($s->suspension_number); ?></td>
                            <td><?php echo e($s->triggered_at->format('d M Y')); ?></td>
                            <td><?php echo e($s->duration_months); ?> months</td>
                            <td><?php echo e($s->starts_at->format('d M Y')); ?></td>
                            <td><?php echo e($s->ends_at->format('d M Y')); ?></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s->lifted_at): ?>
                                    <span class="badge bg-secondary">Lifted</span>
                                <?php elseif($s->is_active): ?>
                                    <span class="badge bg-danger">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-label-secondary">Served</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s->lifted_at): ?>
                                    <span class="text-muted small">
                                        <?php echo e($s->liftedBy->name ?? '—'); ?><br>
                                        <?php echo e($s->lifted_at->format('d M Y')); ?>

                                    </span>
                                <?php else: ?>
                                    —
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php echo e(Str::limit($s->notes, 60)); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\disciplinary\player.blade.php ENDPATH**/ ?>