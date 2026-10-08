<?php $__env->startSection('title', 'Audit Event'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-shield-check me-2 text-primary"></i><?php echo e($auditEvent->action); ?></h4>
      <p class="text-muted mb-0"><?php echo e($auditEvent->event_uuid); ?></p>
    </div>
    <a href="<?php echo e(route('superadmin.audit.index')); ?>" class="btn btn-outline-secondary btn-sm">Back to Audit Centre</a>
  </div>

  <div class="alert alert-<?php echo e($integrityValid ? 'success' : 'danger'); ?> d-flex align-items-center gap-2">
    <i class="ti <?php echo e($integrityValid ? 'ti-shield-check' : 'ti-shield-x'); ?>"></i>
    <?php echo e($integrityValid ? 'Integrity hash verified for this record.' : 'Integrity verification failed. Treat this record as potentially altered and investigate immediately.'); ?>

  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-5">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">Context</h5></div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-5">When</dt><dd class="col-7"><?php echo e($auditEvent->occurred_at?->timezone(config('app.timezone'))->format('d M Y H:i:s.u T')); ?></dd>
            <dt class="col-5">User</dt><dd class="col-7"><?php echo e($auditEvent->actor_name ?? 'System / anonymous'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($auditEvent->actor_id): ?>(#<?php echo e($auditEvent->actor_id); ?>)<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd>
            <dt class="col-5">Email</dt><dd class="col-7"><?php echo e($auditEvent->actor_email ?? '—'); ?></dd>
            <dt class="col-5">Roles</dt><dd class="col-7"><?php echo e(implode(', ', $auditEvent->actor_roles ?? []) ?: '—'); ?></dd>
            <dt class="col-5">Outcome</dt><dd class="col-7"><?php echo e(ucfirst($auditEvent->outcome)); ?></dd>
            <dt class="col-5">Subject</dt><dd class="col-7"><?php echo e($auditEvent->subject_type ? class_basename($auditEvent->subject_type).' #'.$auditEvent->subject_id : '—'); ?><br><small class="text-muted"><?php echo e($auditEvent->subject_label); ?></small></dd>
            <dt class="col-5">Event ID</dt><dd class="col-7"><?php echo e($auditEvent->event_id ?? '—'); ?></dd>
            <dt class="col-5">Route</dt><dd class="col-7"><code><?php echo e($auditEvent->route_name ?? '—'); ?></code></dd>
            <dt class="col-5">Request</dt><dd class="col-7"><code class="text-break"><?php echo e($auditEvent->request_id ?? '—'); ?></code></dd>
            <dt class="col-5">Page</dt><dd class="col-7" class="text-break"><?php echo e($auditEvent->http_method); ?> <?php echo e($auditEvent->path); ?></dd>
            <dt class="col-5">Previous page</dt><dd class="col-7 text-break"><?php echo e($auditEvent->referrer ?? '—'); ?></dd>
            <dt class="col-5">IP address</dt><dd class="col-7"><code><?php echo e($auditEvent->ip_address ?? '—'); ?></code></dd>
            <dt class="col-5">Device</dt><dd class="col-7"><small><?php echo e($auditEvent->user_agent ?? '—'); ?></small></dd>
            <dt class="col-5">Reason</dt><dd class="col-7"><?php echo e($auditEvent->reason ?? '—'); ?></dd>
          </dl>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-7">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Before and after</h5></div>
        <div class="card-body row g-3">
          <div class="col-12 col-lg-6"><h6>Before</h6><pre class="bg-light border rounded p-3 small overflow-auto" style="max-height:420px"><?php echo e(json_encode($auditEvent->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—'); ?></pre></div>
          <div class="col-12 col-lg-6"><h6>After</h6><pre class="bg-light border rounded p-3 small overflow-auto" style="max-height:420px"><?php echo e(json_encode($auditEvent->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—'); ?></pre></div>
          <div class="col-12"><h6>Metadata</h6><pre class="bg-light border rounded p-3 small overflow-auto" style="max-height:320px"><?php echo e(json_encode($auditEvent->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—'); ?></pre></div>
        </div>
      </div>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journey->isNotEmpty()): ?>
    <div class="card mt-4">
      <div class="card-header"><h5 class="mb-0">User journey</h5></div>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Time</th><th>Action</th><th>Page</th><th>Outcome</th><th></th></tr></thead>
        <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $journey; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr class="<?php echo e($item->id === $auditEvent->id ? 'table-primary' : ''); ?>">
          <td><?php echo e($item->occurred_at?->timezone(config('app.timezone'))->format('H:i:s')); ?></td><td><?php echo e($item->action); ?></td><td><?php echo e($item->route_name ?? $item->path); ?></td><td><?php echo e($item->outcome); ?></td>
          <td><a href="<?php echo e(route('superadmin.audit.show', $item)); ?>" class="btn btn-sm btn-icon btn-outline-secondary"><i class="ti ti-eye"></i></a></td>
        </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
      </table></div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subjectTimeline->count() > 1): ?>
    <div class="card mt-4">
      <div class="card-header"><h5 class="mb-0">Subject history</h5></div>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Outcome</th><th></th></tr></thead>
        <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $subjectTimeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr>
          <td><?php echo e($item->occurred_at?->timezone(config('app.timezone'))->format('d M Y H:i:s')); ?></td><td><?php echo e($item->actor_name ?? 'System'); ?></td><td><?php echo e($item->action); ?></td><td><?php echo e($item->outcome); ?></td>
          <td><a href="<?php echo e(route('superadmin.audit.show', $item)); ?>" class="btn btn-sm btn-icon btn-outline-secondary"><i class="ti ti-eye"></i></a></td>
        </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
      </table></div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\audit\show.blade.php ENDPATH**/ ?>