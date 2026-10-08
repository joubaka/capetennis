

<?php $__env->startSection('title', 'Platform Health'); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .health-section { margin-bottom: 1.5rem; }
  .health-section .card-header { font-weight: 600; font-size: 1rem; display: flex; align-items: center; gap: .5rem; }
  .badge-ok       { background-color: #28a745; color: #fff; font-size: .75rem; padding: .25em .55em; border-radius: 4px; }
  .badge-warn     { background-color: #fd7e14; color: #fff; font-size: .75rem; padding: .25em .55em; border-radius: 4px; }
  .badge-critical { background-color: #dc3545; color: #fff; font-size: .75rem; padding: .25em .55em; border-radius: 4px; }
  .health-row td  { vertical-align: middle; font-size: .875rem; }
  .health-value   { font-weight: 600; }
  .health-detail  { color: #666; font-size: .8rem; }
  .summary-bar    { border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; gap: 2rem; align-items: center; }
  .summary-ok     { background: #d4edda; border-left: 5px solid #28a745; }
  .summary-warn   { background: #fff3cd; border-left: 5px solid #ffc107; }
  .summary-crit   { background: #f8d7da; border-left: 5px solid #dc3545; }
  .stat-box       { text-align: center; }
  .stat-box .num  { font-size: 1.6rem; font-weight: 700; line-height: 1; }
  .stat-box .lbl  { font-size: .75rem; color: #555; }
  .refresh-note   { font-size: .75rem; color: #888; }
  .section-icon   { font-size: 1.1rem; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">

  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0">Platform Health Dashboard</h4>
      <small class="text-muted">Operational status snapshot &mdash; auto-refreshes every 60 s</small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?php echo e(route('platform.health.api')); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">JSON API</a>
      <button onclick="location.reload()" class="btn btn-sm btn-outline-primary">↺ Refresh</button>
    </div>
  </div>

  
  <?php
    $barClass = $summary['critical'] > 0 ? 'summary-crit'
              : ($summary['warn'] > 0 ? 'summary-warn' : 'summary-ok');
    $barIcon  = $summary['critical'] > 0 ? '🔴' : ($summary['warn'] > 0 ? '🟡' : '🟢');
    $barLabel = $summary['critical'] > 0
              ? "{$summary['critical']} critical issue(s) — action required"
              : ($summary['warn'] > 0 ? "{$summary['warn']} warning(s) — review recommended" : 'All systems healthy');
  ?>
  <div class="summary-bar <?php echo e($barClass); ?>">
    <span style="font-size:1.8rem"><?php echo e($barIcon); ?></span>
    <div class="flex-grow-1">
      <strong><?php echo e($barLabel); ?></strong>
      <div class="refresh-note">Last checked: <?php echo e(now()->format('d M Y H:i:s')); ?></div>
    </div>
    <div class="stat-box"><div class="num text-danger"><?php echo e($summary['critical']); ?></div><div class="lbl">Critical</div></div>
    <div class="stat-box"><div class="num text-warning"><?php echo e($summary['warn']); ?></div><div class="lbl">Warnings</div></div>
    <div class="stat-box"><div class="num text-success"><?php echo e($summary['ok']); ?></div><div class="lbl">Passing</div></div>
  </div>

  <div class="row">

    
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">⚙️</span> Engine Health
          <?php echo $__env->make('backend.platform._health_badge', ['items' => $engine], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="card-body p-0">
          <?php echo $__env->make('backend.platform._health_table', ['items' => $engine], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">💰</span> Financial Health
          <?php echo $__env->make('backend.platform._health_badge', ['items' => $financial], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="card-body p-0">
          <?php echo $__env->make('backend.platform._health_table', ['items' => $financial], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">🎾</span> Draw Health
          <?php echo $__env->make('backend.platform._health_badge', ['items' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="card-body p-0">
          <?php echo $__env->make('backend.platform._health_table', ['items' => $draw], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-6 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">📋</span> Registration Health
          <?php echo $__env->make('backend.platform._health_badge', ['items' => $registration], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="card-body p-0">
          <?php echo $__env->make('backend.platform._health_table', ['items' => $registration], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-4 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">📬</span> Queue Health
          <?php echo $__env->make('backend.platform._health_badge', ['items' => $queue], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="card-body p-0">
          <?php echo $__env->make('backend.platform._health_table', ['items' => $queue], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-8 health-section">
      <div class="card h-100">
        <div class="card-header">
          <span class="section-icon">🖥️</span> System Health
          <?php echo $__env->make('backend.platform._health_badge', ['items' => $system], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="card-body p-0">
          <?php echo $__env->make('backend.platform._health_table', ['items' => $system], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    </div>

  </div>

  
  <div class="card mt-2">
    <div class="card-header fw-semibold">🛠 Quick Integrity Actions</div>
    <div class="card-body">
      <p class="text-muted small mb-2">Run these commands on the server to investigate or resolve issues found above.</p>
      <div class="row g-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
          ['php artisan schema:integrity-check',                        'Full integrity check (read-only)'],
          ['php artisan draw:integrity-check',                          'Draw + fixture integrity'],
          ['php artisan finance:integrity-check',                       'Financial integrity'],
          ['php artisan data:cleanup-duplicate-payfast-ids --dry-run',  'PayFast duplicate review'],
          ['php artisan data:cleanup-duplicate-fixture-results --dry-run', 'Duplicate results preview'],
          ['php artisan data:cleanup-orphan-fixtures --dry-run',        'Orphan fixtures preview'],
          ['php artisan platform:preflight',                            'Pre-deploy safety check'],
          ['php artisan platform:health-check',                         'CLI health check'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$cmd, $label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-md-6">
          <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
            <code class="flex-grow-1" style="font-size:.78rem"><?php echo e($cmd); ?></code>
            <small class="text-muted text-nowrap"><?php echo e($label); ?></small>
          </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
  // Auto-refresh every 60 seconds
  setTimeout(() => location.reload(), 60000);
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\platform\health.blade.php ENDPATH**/ ?>