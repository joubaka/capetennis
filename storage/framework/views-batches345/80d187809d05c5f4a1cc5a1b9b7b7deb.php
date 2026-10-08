

<?php $__env->startSection('title', 'Draw Engine Mode — Draw #' . $draw->id); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row mb-4">
    <div class="col-12">
      <h4 class="fw-bold py-3 mb-0">
        <span class="text-muted fw-light">Admin / Engine /</span> Draw #<?php echo e($draw->id); ?> Engine Mode
      </h4>
      <p class="text-muted mb-0">
        Set engine mode for <strong><?php echo e($draw->drawName); ?></strong>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->event): ?> (Event: <?php echo e($draw->event->name); ?>) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </p>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?php echo e(session('success')); ?>

    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?php echo e($errors->first()); ?>

    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="row g-4 mb-4">

    
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Current Effective Mode</p>
          <?php $eff = $draw->effectiveEngineMode(); ?>
          <span class="badge fs-5 bg-<?php echo e($eff === 'canonical' ? 'success' : ($eff === 'hybrid' ? 'warning' : 'secondary')); ?>">
            <?php echo e($eff === 'canonical' ? 'PRIMARY' : strtoupper($eff)); ?>

          </span>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->engine_mode): ?>
            <p class="text-muted small mt-2 mb-0"><i class="bx bx-pin me-1"></i>Draw-level override: <strong><?php echo e($draw->engine_mode); ?></strong></p>
          <?php elseif($draw->event?->engine_mode): ?>
            <p class="text-muted small mt-2 mb-0"><i class="bx bx-transfer me-1"></i>Inherited from event: <strong><?php echo e($draw->event->engine_mode); ?></strong></p>
          <?php else: ?>
            <p class="text-muted small mt-2 mb-0"><i class="bx bx-globe me-1"></i>Inherited from global config</p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-4">
      <div class="card h-100 border-<?php echo e($safetyCheck['allowed'] ? 'success' : 'danger'); ?>">
        <div class="card-body">
          <p class="fw-semibold mb-1">Primary engine safety</p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($safetyCheck['allowed']): ?>
            <span class="badge bg-success">SAFE</span>
            <p class="text-muted small mt-2 mb-0">No blocking mismatches. The primary engine may be enabled.</p>
          <?php else: ?>
            <span class="badge bg-danger">BLOCKED</span>
            <p class="text-danger small mt-2 mb-0"><?php echo e($safetyCheck['reason']); ?></p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Emergency Rollback</p>
          <p class="text-muted small mb-3">Instantly force draw to LEGACY mode and mark all mismatches resolved.</p>
          <form method="POST" action="<?php echo e(route('engine.draw.rollback', $draw)); ?>"
                onsubmit="return confirm('Force draw #<?php echo e($draw->id); ?> to LEGACY mode and mark all mismatches resolved?')">
            <?php echo csrf_field(); ?>
            <button type="submit" class="btn btn-danger btn-sm w-100">
              <i class="bx bx-undo me-1"></i> Rollback to Legacy
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>

  
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Set Draw Engine Mode Override</h5></div>
    <div class="card-body">
      <form method="POST" action="<?php echo e(route('engine.draw.update', $draw)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>
        <div class="row align-items-end g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Engine Mode</label>
            <select name="engine_mode" class="form-select">
              <option value="" <?php echo e(! $draw->engine_mode ? 'selected' : ''); ?>>— Inherit (event/global) —</option>
              <option value="legacy"    <?php echo e($draw->engine_mode === 'legacy'    ? 'selected' : ''); ?>>Legacy</option>
              <option value="hybrid"    <?php echo e($draw->engine_mode === 'hybrid'    ? 'selected' : ''); ?>>Hybrid (shadow)</option>
              <option value="canonical" <?php echo e($draw->engine_mode === 'canonical' ? 'selected' : ''); ?>

                <?php echo e(! $safetyCheck['allowed'] ? 'disabled' : ''); ?>>
                Primary <?php echo e(! $safetyCheck['allowed'] ? '(blocked — mismatches)' : ''); ?>

              </option>
            </select>
            <div class="form-text">Draw-level override takes precedence over event and global settings.</div>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">
              <i class="bx bx-save me-1"></i> Save Mode
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->event): ?>
  
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Set Event Engine Mode Override — <?php echo e($draw->event->name); ?></h5></div>
    <div class="card-body">
      <form method="POST" action="<?php echo e(route('engine.event.update', $draw->event)); ?>">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>
        <div class="row align-items-end g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Event Engine Mode</label>
            <select name="engine_mode" class="form-select">
              <option value="" <?php echo e(! $draw->event->engine_mode ? 'selected' : ''); ?>>— Inherit global config —</option>
              <option value="legacy"    <?php echo e($draw->event->engine_mode === 'legacy'    ? 'selected' : ''); ?>>Legacy</option>
              <option value="hybrid"    <?php echo e($draw->event->engine_mode === 'hybrid'    ? 'selected' : ''); ?>>Hybrid (shadow)</option>
              <option value="canonical" <?php echo e($draw->event->engine_mode === 'canonical' ? 'selected' : ''); ?>>Primary</option>
            </select>
            <div class="form-text">Applies to all draws in this event unless a draw-level override is set.</div>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">
              <i class="bx bx-save me-1"></i> Save Event Mode
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($runStats->isNotEmpty()): ?>
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Run History for Draw #<?php echo e($draw->id); ?></h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Mode</th><th class="text-end">Runs</th><th class="text-end">Canon OK</th><th class="text-end">Fallbacks</th><th class="text-end">Mismatches</th></tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $runStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><span class="badge bg-<?php echo e($row->engine_mode === 'canonical' ? 'success' : ($row->engine_mode === 'hybrid' ? 'warning text-dark' : 'secondary')); ?>"><?php echo e($row->engine_mode === 'canonical' ? 'PRIMARY' : strtoupper($row->engine_mode)); ?></span></td>
            <td class="text-end"><?php echo e($row->total); ?></td>
            <td class="text-end text-<?php echo e($row->canon_ok == $row->total ? 'success' : 'warning'); ?>"><?php echo e($row->canon_ok); ?></td>
            <td class="text-end text-<?php echo e($row->fallbacks > 0 ? 'warning' : 'muted'); ?>"><?php echo e($row->fallbacks); ?></td>
            <td class="text-end text-<?php echo e($row->mismatches > 0 ? 'danger' : 'muted'); ?>"><?php echo e($row->mismatches); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recentMismatches->isNotEmpty()): ?>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Unresolved Mismatches for Draw #<?php echo e($draw->id); ?></h5>
      <span class="badge bg-danger"><?php echo e($recentMismatches->count()); ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Operation</th><th>Type</th><th>Severity</th></tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentMismatches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><small><?php echo e($m->created_at->diffForHumans()); ?></small></td>
            <td><code><?php echo e($m->operation_type); ?></code></td>
            <td><span class="badge bg-label-warning"><?php echo e($m->mismatch_type); ?></span></td>
            <td>
              <span class="badge bg-<?php echo e($m->severity === 'high' ? 'danger' : ($m->severity === 'medium' ? 'warning text-dark' : 'secondary')); ?>">
                <?php echo e(strtoupper($m->severity)); ?>

              </span>
            </td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="alert alert-success mb-4">
    <i class="bx bx-check-circle me-1"></i> No unresolved mismatches for this draw.
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="d-flex gap-2">
    <a href="<?php echo e(route('engine.debug')); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bx bx-arrow-back me-1"></i> Back to Engine Dashboard
    </a>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\engine\draw-mode.blade.php ENDPATH**/ ?>