<?php $__env->startSection('title', 'API Connections'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="mb-1"><i class="ti ti-plug-connected me-2 text-primary"></i>API Connections</h4>
    <p class="text-muted mb-0">Academies and websites with Cape Tennis API access, based on their key and actual API traffic.</p>
  </div>
  <a href="<?php echo e(route('backend.superadmin.index')); ?>" class="btn btn-outline-secondary btn-sm align-self-start">
    <i class="ti ti-arrow-left me-1"></i>Super Admin
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">Configured</div><div class="fs-3 fw-bold"><?php echo e($summary['total']); ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100 border-success"><div class="card-body"><div class="text-success small">Connected</div><div class="fs-3 fw-bold text-success"><?php echo e($summary['active']); ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100 border-warning"><div class="card-body"><div class="text-warning small">Connecting</div><div class="fs-3 fw-bold text-warning"><?php echo e($summary['connecting']); ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="card h-100 border-danger"><div class="card-body"><div class="text-danger small">Needs review</div><div class="fs-3 fw-bold text-danger"><?php echo e($summary['attention']); ?></div></div></div></div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="<?php echo e(route('superadmin.api-integrations.index')); ?>" class="row g-2 align-items-end">
      <div class="col-12 col-md-5 col-lg-3">
        <label for="status" class="form-label">Filter by status</label>
        <select id="status" name="status" class="form-select">
          <option value="">All connections</option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
            'active' => 'Active', 'connected' => 'Connected', 'connecting' => 'Trying to connect',
            'awaiting_connection' => 'Awaiting first connection', 'needs_attention' => 'Needs attention',
            'rate_limited' => 'Rate limited', 'inactive' => 'No recent activity', 'expired' => 'Expired'
          ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($value); ?>" <?php if($selectedStatus === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
      </div>
      <div class="col-auto"><button class="btn btn-primary" type="submit">Apply</button></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedStatus !== ''): ?>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="<?php echo e(route('superadmin.api-integrations.index')); ?>">Clear</a></div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Academy / website</th>
          <th>Status</th>
          <th>Last successful link</th>
          <th>Latest attempt</th>
          <th class="text-end">Requests (24h)</th>
          <th>Key expiry</th>
        </tr>
      </thead>
      <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $integrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $integration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td>
              <div class="fw-semibold"><?php echo e($integration['name']); ?></div>
              <div class="small text-muted">Managed by <?php echo e($integration['owner']?->name ?? 'Cape Tennis'); ?></div>
            </td>
            <td style="min-width: 220px">
              <span class="badge bg-label-<?php echo e($integration['status_colour']); ?>"><?php echo e($integration['status_label']); ?></span>
              <div class="small text-muted mt-1"><?php echo e($integration['status_detail']); ?></div>
            </td>
            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($integration['latest_success_at']): ?>
                <div><?php echo e($integration['latest_success_at']->format('d M Y, H:i')); ?></div>
                <div class="small text-muted"><?php echo e($integration['latest_success_at']->diffForHumans()); ?></div>
              <?php else: ?>
                <span class="text-muted">Never</span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($integration['latest_attempt_at']): ?>
                <div><?php echo e($integration['latest_attempt_at']->format('d M Y, H:i')); ?></div>
                <div class="small text-muted">
                  HTTP <?php echo e($integration['latest_status_code'] ?? '—'); ?>

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($integration['latest_endpoint']): ?> · <?php echo e(str($integration['latest_endpoint'])->afterLast('.')->replace('_', ' ')->title()); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              <?php elseif($integration['last_used_at']): ?>
                <div><?php echo e($integration['last_used_at']->format('d M Y, H:i')); ?></div>
                <div class="small text-muted">Key use detected</div>
              <?php else: ?>
                <span class="text-muted">No attempt detected</span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
            <td class="text-end fw-semibold"><?php echo e(number_format($integration['requests_last_24_hours'])); ?></td>
            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($integration['expires_at']): ?>
                <div><?php echo e($integration['expires_at']->format('d M Y')); ?></div>
                <div class="small text-muted"><?php echo e($integration['expires_at']->diffForHumans()); ?></div>
              <?php else: ?>
                <span class="text-warning">No expiry set</span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="6" class="text-center py-5">
            <i class="ti ti-plug-off fs-1 text-muted d-block mb-2"></i>
            <div class="fw-semibold">No API connections found</div>
            <div class="small text-muted">No integration key matches this filter.</div>
          </td></tr>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-4 mb-0">
  <i class="ti ti-info-circle me-1"></i>
  A connection is only marked active after Cape Tennis records a successful API request. API keys and secrets are never displayed here.
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\api-integrations.blade.php ENDPATH**/ ?>