<?php $__env->startSection('title', 'Audit Centre'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-shield-search me-2 text-primary"></i>Audit Centre</h4>
      <p class="text-muted mb-0">Append-only user, system, data-change and navigation history.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="<?php echo e(route('superadmin.audit.export', request()->query())); ?>" class="btn btn-outline-primary btn-sm">
        <i class="ti ti-download me-1"></i>Export filtered CSV
      </a>
      <a href="<?php echo e(route('backend.superadmin.index')); ?>" class="btn btn-outline-secondary btn-sm">Super Admin</a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
      ['Today', $stats['today'], 'ti-activity', 'primary'],
      ['Denied · 7 days', $stats['denied_7d'], 'ti-shield-x', 'danger'],
      ['Deletions · 30 days', $stats['deletions_30d'], 'ti-trash', 'warning'],
      ['Active users · 30 days', $stats['users_30d'], 'ti-users', 'info'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $icon, $colour]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body py-3">
          <div class="d-flex justify-content-between align-items-center">
            <div><small class="text-muted"><?php echo e($label); ?></small><div class="fs-4 fw-semibold"><?php echo e(number_format($value)); ?></div></div>
            <i class="ti <?php echo e($icon); ?> ti-lg text-<?php echo e($colour); ?>"></i>
          </div>
        </div></div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Filters</h5></div>
    <div class="card-body">
      <form method="GET" action="<?php echo e(route('superadmin.audit.index')); ?>" class="row g-3">
        <div class="col-12 col-lg-4">
          <label class="form-label" for="audit-search">Search</label>
          <input id="audit-search" name="search" value="<?php echo e(request('search')); ?>" class="form-control" placeholder="User, email, action, page or request ID">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-from">From</label>
          <input id="audit-from" type="date" name="from" value="<?php echo e(request('from')); ?>" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-to">To</label>
          <input id="audit-to" type="date" name="to" value="<?php echo e(request('to')); ?>" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-category">Category</label>
          <select id="audit-category" name="category" class="form-select">
            <option value="">All</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($category); ?>" <?php if(request('category') === $category): echo 'selected'; endif; ?>><?php echo e(ucfirst($category)); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-outcome">Outcome</label>
          <select id="audit-outcome" name="outcome" class="form-select">
            <option value="">All</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['succeeded', 'denied', 'failed', 'attempted']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $outcome): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($outcome); ?>" <?php if(request('outcome') === $outcome): echo 'selected'; endif; ?>><?php echo e(ucfirst($outcome)); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="audit-action">Action</label>
          <select id="audit-action" name="action" class="form-select">
            <option value="">All actions</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($action); ?>" <?php if(request('action') === $action): echo 'selected'; endif; ?>><?php echo e($action); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-actor">User ID</label>
          <input id="audit-actor" type="number" min="1" name="actor_id" value="<?php echo e(request('actor_id')); ?>" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-event">Event ID</label>
          <input id="audit-event" type="number" min="1" name="event_id" value="<?php echo e(request('event_id')); ?>" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-subject-type">Subject type</label>
          <input id="audit-subject-type" name="subject_type" value="<?php echo e(request('subject_type')); ?>" class="form-control" placeholder="Player">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label" for="audit-subject-id">Subject ID</label>
          <input id="audit-subject-id" name="subject_id" value="<?php echo e(request('subject_id')); ?>" class="form-control">
        </div>
        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary"><i class="ti ti-filter me-1"></i>Apply filters</button>
          <a href="<?php echo e(route('superadmin.audit.index')); ?>" class="btn btn-outline-secondary">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Audit events</h5>
      <span class="badge bg-label-secondary"><?php echo e(number_format($events->total())); ?> records</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Date / time</th><th>User</th><th>Action</th><th>Subject</th><th>Page</th><th>Outcome</th><th></th>
        </tr></thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $badge = match($event->outcome) {'succeeded' => 'success', 'denied' => 'danger', 'failed' => 'warning', default => 'secondary'};
              $subjectName = $event->subject_type ? class_basename($event->subject_type) : null;
            ?>
            <tr>
              <td class="text-nowrap">
                <?php echo e($event->occurred_at?->timezone(config('app.timezone'))->format('d M Y H:i:s')); ?>

                <small class="d-block text-muted"><?php echo e($event->occurred_at?->diffForHumans()); ?></small>
              </td>
              <td>
                <span class="fw-medium"><?php echo e($event->actor_name ?? ($event->actor_type === 'system' ? 'System' : 'Anonymous')); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->actor_email): ?><small class="d-block text-muted"><?php echo e($event->actor_email); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td><span class="badge bg-label-primary"><?php echo e($event->category); ?></span><code class="d-block mt-1"><?php echo e($event->action); ?></code></td>
              <td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subjectName): ?>
                  <?php echo e($subjectName); ?> #<?php echo e($event->subject_id); ?>

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->subject_label): ?><small class="d-block text-muted text-truncate" style="max-width:220px"><?php echo e($event->subject_label); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <span class="text-truncate d-block" style="max-width:240px" title="<?php echo e($event->path); ?>"><?php echo e($event->route_name ?? $event->path ?? '—'); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->http_method): ?><small class="text-muted"><?php echo e($event->http_method); ?> · HTTP <?php echo e($event->status_code ?? '—'); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td><span class="badge bg-<?php echo e($badge); ?>"><?php echo e(ucfirst($event->outcome)); ?></span></td>
              <td><a href="<?php echo e(route('superadmin.audit.show', $event)); ?>" class="btn btn-sm btn-icon btn-outline-primary" title="View detail"><i class="ti ti-eye"></i></a></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">No audit events match these filters.</td></tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($events->hasPages()): ?>
      <div class="card-footer"><?php echo e($events->links('pagination::bootstrap-5')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\audit\index.blade.php ENDPATH**/ ?>