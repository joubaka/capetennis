

<?php $__env->startSection('title', 'Draw Engine Observability Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="row mb-4">
    <div class="col-12">
      <h4 class="fw-bold py-3 mb-0">
        <span class="text-muted fw-light">Admin /</span> Draw Engine Observability
      </h4>
      <p class="text-muted mb-0">Read-only production safety dashboard. Legacy engine remains authoritative.</p>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo e(session('success')); ?>

    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Engine Mode</p>
          <?php $mode = config('capetennis_engine.mode', 'hybrid'); ?>
          <span class="badge bg-<?php echo e($mode === 'canonical' ? 'success' : ($mode === 'hybrid' ? 'warning' : 'secondary')); ?> fs-6">
            <?php echo e($mode === 'canonical' ? 'PRIMARY' : strtoupper($mode)); ?>

          </span>
          <p class="text-muted small mt-2 mb-0">Auto-fallback: <?php echo e(config('capetennis_engine.auto_fallback') ? 'ON' : 'OFF'); ?></p>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Primary engine confidence</p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($confidence['confidence_score'] !== null): ?>
            <?php $score = $confidence['confidence_score']; $cls = $score >= 98 ? 'success' : ($score >= 90 ? 'info' : ($score >= 75 ? 'warning' : 'danger')); ?>
            <h3 class="text-<?php echo e($cls); ?> mb-0"><?php echo e($score); ?>%</h3>
            <small class="text-muted"><?php echo e($confidence['confidence_label']); ?></small>
          <?php else: ?>
            <h3 class="text-muted mb-0">--</h3>
            <small class="text-muted">No run data yet</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Primary engine runs</p>
          <h3 class="mb-0"><?php echo e(number_format($runStats['canonical'])); ?></h3>
          <small class="text-muted">of <?php echo e(number_format($runStats['total'])); ?> total</small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="fw-semibold mb-1">Fallbacks / Failures</p>
          <h3 class="text-<?php echo e($runStats['fallbacks'] > 0 ? 'warning' : 'success'); ?> mb-0"><?php echo e($runStats['fallbacks']); ?></h3>
          <small class="text-muted"><?php echo e($runStats['failures']); ?> errors &bull; avg <?php echo e($runStats['avg_ms']); ?>ms</small>
        </div>
      </div>
    </div>
  </div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($confidence['canonical_runs'] > 0): ?>
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Confidence Breakdown</h5></div>
    <div class="card-body">
      <div class="row g-3 text-center">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
          ['Parity %',          $confidence['parity_pct'],         false],
          ['Mismatch %',        $confidence['mismatch_pct'],        true],
          ['Fallback %',        $confidence['fallback_pct'],        true],
          ['Progression OK %',  $confidence['progression_ok_pct'], false],
          ['Standings OK %',    $confidence['standings_ok_pct'],   false],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $val, $invert]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col">
          <p class="text-muted small mb-1"><?php echo e($label); ?></p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($val !== null): ?>
            <?php $ok = $invert ? $val <= 2 : $val >= 98; $cls = $ok ? 'success' : ($val >= 90 ? 'warning' : 'danger'); ?>
            <h4 class="text-<?php echo e($cls); ?> mb-0"><?php echo e($val); ?>%</h4>
          <?php else: ?>
            <h4 class="text-muted mb-0">--</h4>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($runsByOperation->isNotEmpty()): ?>
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Run Stats by Operation</h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Operation</th>
            <th class="text-end">Total</th>
            <th class="text-end">Canon OK</th>
            <th class="text-end">Fallbacks</th>
            <th class="text-end">Mismatches</th>
            <th class="text-end">Avg ms</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $runsByOperation; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><code><?php echo e($row->operation_type); ?></code></td>
            <td class="text-end"><?php echo e($row->total); ?></td>
            <td class="text-end text-<?php echo e($row->canon_ok == $row->total ? 'success' : 'warning'); ?>"><?php echo e($row->canon_ok); ?></td>
            <td class="text-end text-<?php echo e($row->fallbacks > 0 ? 'warning' : 'muted'); ?>"><?php echo e($row->fallbacks); ?></td>
            <td class="text-end text-<?php echo e($row->mismatches > 0 ? 'danger' : 'muted'); ?>"><?php echo e($row->mismatches); ?></td>
            <td class="text-end"><?php echo e($row->avg_ms); ?>ms</td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($topMismatchTypes) > 0): ?>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Top Mismatch Types</h5>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unresolvedHighSev > 0): ?>
        <span class="badge bg-danger"><?php echo e($unresolvedHighSev); ?> unresolved HIGH</span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Type</th><th>Operation</th><th class="text-end">Count</th></tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $topMismatchTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><span class="badge bg-label-danger"><?php echo e($row['mismatch_type']); ?></span></td>
            <td><code><?php echo e($row['operation_type']); ?></code></td>
            <td class="text-end fw-bold"><?php echo e($row['total']); ?></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recentEngineMismatches->isNotEmpty()): ?>
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Recent Unresolved Mismatches</h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Draw</th><th>Operation</th><th>Type</th><th>Severity</th></tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentEngineMismatches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><small><?php echo e($m->created_at->diffForHumans()); ?></small></td>
            <td><?php echo e($m->draw_id ?? '--'); ?></td>
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
    <i class="bx bx-check-circle me-1"></i> No unresolved mismatches. Primary and legacy engines are in parity.
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recentFailedRuns->isNotEmpty()): ?>
  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Recent primary-engine failures</h5></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Draw</th><th>Operation</th><th>Mode</th><th>Fallback?</th><th>Exception</th></tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentFailedRuns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $run): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><small><?php echo e($run->created_at->diffForHumans()); ?></small></td>
            <td><?php echo e($run->draw_id ?? '--'); ?></td>
            <td><code><?php echo e($run->operation_type); ?></code></td>
            <td><span class="badge bg-label-secondary"><?php echo e($run->engine_mode); ?></span></td>
            <td><?php echo $run->fallback_used ? '<span class="badge bg-warning text-dark">yes</span>' : '<span class="badge bg-secondary">no</span>'; ?></td>
            <td><small class="text-danger"><?php echo e(Str::limit($run->exception ?? '', 120)); ?></small></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recentFallbacks->isNotEmpty()): ?>
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Recent Fallbacks <span class="badge bg-warning text-dark"><?php echo e($totalFallbacks); ?></span></h5>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Operation</th><th>Draw</th><th>Error</th></tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentFallbacks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><small><?php echo e($log->created_at->diffForHumans()); ?></small></td>
            <td><code><?php echo e($log->operation); ?></code></td>
            <td><?php echo e($log->draw_id ?? '--'); ?></td>
            <td><small class="text-danger"><?php echo e($log->canonical_result['error'] ?? '--'); ?></small></td>
          </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="card">
    <div class="card-body">
      <h6 class="fw-bold mb-3">Actions</h6>
      <form method="POST" action="<?php echo e(route('engine.debug.clear')); ?>" onsubmit="return confirm('Clear ALL engine logs? This cannot be undone.')">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="bx bx-trash me-1"></i> Clear all engine logs
        </button>
      </form>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\engine\debug.blade.php ENDPATH**/ ?>