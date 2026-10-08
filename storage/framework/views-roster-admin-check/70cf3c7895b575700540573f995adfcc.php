<?php $__env->startSection('title', 'Disciplinary Cases'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($event)): ?>
    <?php echo $__env->make('backend.event.partials.header', [
      'eventWorkspaceActive' => 'more',
      'eventWorkspaceIcon' => 'ti-scale',
      'eventWorkspaceSubtitle' => 'Discipline and incidents',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-scale me-2"></i><?php echo e(isset($event) ? $event->name.' Discipline' : 'Disciplinary Cases'); ?></h4>
      <p class="text-muted mb-0">Incident, panel decision, sanction and appeal workflow</p>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($event) && \App\Models\SiteSetting::disciplinarySystemEnabled()): ?>
      <a class="btn btn-primary" href="<?php echo e(route('backend.events.disciplinary.create', $event)); ?>"><i class="ti ti-plus me-1"></i>Report incident</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (\App\Models\SiteSetting::disciplinarySystemEnabled())): ?>
    <div class="alert alert-warning"><i class="ti ti-lock me-1"></i>The disciplinary case system is disabled. Historical cases remain available for audit, but no workflow actions can be performed.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" class="row g-3 align-items-end">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (isset($event))): ?>
        <div class="col-md-5"><label class="form-label">Event</label><select name="event_id" class="form-select"><option value="">All permitted events</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($item->id); ?>" <?php if(request('event_id') == $item->id): echo 'selected'; endif; ?>><?php echo e($item->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['submitted','triage','awaiting_response','panel_review','decided','appealed','final','dismissed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>><?php echo e(str($status)->replace('_', ' ')->title()); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
        <div class="col-md-3"><button class="btn btn-outline-primary w-100">Filter</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead><tr><th>Case</th><th>Incident</th><th>Event</th><th>Player</th><th>Charge</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $cases; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $case): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <tr>
            <td><strong><?php echo e($case->case_number); ?></strong><br><small class="text-muted"><?php echo e(ucfirst($case->severity)); ?></small></td>
            <td><?php echo e($case->incident_at->format('d M Y H:i')); ?></td>
            <td><?php echo e($case->event?->name); ?></td>
            <td><?php echo e($case->player?->full_name); ?></td>
            <td><?php echo e($case->charges->pluck('rule_title')->join(', ')); ?></td>
            <td><span class="badge bg-label-<?php echo e(in_array($case->status, ['decided','final']) ? 'success' : ($case->status === 'dismissed' ? 'secondary' : 'warning')); ?>"><?php echo e(str($case->status)->replace('_', ' ')->title()); ?></span></td>
            <td><a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.disciplinary.cases.show', $case)); ?>">Open</a></td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <tr><td colspan="7" class="text-center text-muted py-5">No disciplinary cases found.</td></tr>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer"><?php echo e($cases->links()); ?></div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\disciplinary\cases\index.blade.php ENDPATH**/ ?>