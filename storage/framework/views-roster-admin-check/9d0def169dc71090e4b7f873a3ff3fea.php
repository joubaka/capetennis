<?php $__env->startSection('title', $event->name); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .interpro-page { padding-top: 3.5rem; }
  .interpro-page .card { border: 1px solid #ebeaf0; border-radius: .45rem; box-shadow: 0 .25rem 1rem rgba(47, 43, 61, .05); }
  .interpro-page .card-header { background: #fff; border-bottom: 1px solid #ebeaf0; padding: 1.1rem 1.2rem; }
  .interpro-page .card-header h5 { color: #625f6d; font-size: 1rem; font-weight: 600; }
  .interpro-page .card-body { padding: 1.1rem 1.2rem; }
  .interpro-page .dashboard-action { display: flex; align-items: center; justify-content: flex-start; gap: .4rem; font-weight: 500; }
  .interpro-page .setup-link { display: flex; align-items: flex-start; gap: .75rem; padding: .8rem; color: #4b465c; border: 1px solid #e3e1e8; border-radius: .5rem; background: #fff; transition: border-color .15s ease, background-color .15s ease, transform .15s ease; }
  .interpro-page .setup-link:hover, .interpro-page .setup-link:focus { color: #4b465c; border-color: #7367f0; background: #f8f7ff; transform: translateY(-1px); }
  .interpro-page .setup-link__icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 2rem; width: 2rem; height: 2rem; color: #7367f0; border-radius: .4rem; background: rgba(115, 103, 240, .12); }
  .interpro-page .setup-link__copy { min-width: 0; }
  .interpro-page .setup-link__title { display: block; font-size: .9rem; font-weight: 600; line-height: 1.25; }
  .interpro-page .setup-link__description { display: block; margin-top: .2rem; color: #7b7882; font-size: .78rem; line-height: 1.35; }
  .interpro-page .setup-link__arrow { margin-left: auto; color: #a8a5ad; line-height: 2rem; }
  .interpro-page .stats-panel li { color: #625f6d; font-size: .9rem; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl interpro-page">
  <?php echo $__env->make('backend.event.partials.header', [
    'event' => $event,
    'eventWorkspaceSubtitle' => 'Manage Interpro nominations, invitations and tournament readiness.',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-1">Interpro Dashboard</h4>
      <p class="text-muted mb-0">A focused workspace for nominations, fast invitations, registration and event operations.</p>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-settings ti-md text-primary"></i>
          <h5 class="mb-0">Operations</h5>
        </div>
        <div class="card-body d-grid gap-2">
          <a href="<?php echo e(route('backend.interprovincial-trials.invitations.index', $event)); ?>" class="btn btn-primary dashboard-action">
            <i class="ti ti-send me-1"></i>Nominations &amp; invitations
          </a>
          <a href="<?php echo e(route('backend.interprovincial-trials.programme.index', $event)); ?>" class="btn btn-outline-primary dashboard-action">Regional payments &amp; team selection</a>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-draw.view', $event)): ?>
            <a href="<?php echo e(route('admin.events.entries.new', $event)); ?>" class="btn btn-outline-primary dashboard-action">
              <i class="ti ti-users me-1"></i>Entries
            </a>
          <?php endif; ?>
          <a href="<?php echo e(route('headOffice.show', $event)); ?>" class="btn btn-outline-primary dashboard-action">
            <i class="ti ti-tournament me-1"></i>Draws
          </a>
          <a href="<?php echo e(route('admin.events.individual.hq', $event)); ?>" class="btn btn-outline-primary dashboard-action">
            <i class="ti ti-list-check me-1"></i>Fixtures &amp; results
          </a>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-6">
      <div class="card h-100 border-start border-warning border-3">
        <div class="card-header d-flex align-items-center gap-2">
          <i class="ti ti-adjustments ti-md text-warning"></i>
          <div>
            <h5 class="mb-0">Event setup</h5>
            <div class="small text-muted mt-1">Event configuration and the fast Interpro invitation flow.</div>
          </div>
        </div>
        <div class="card-body d-grid gap-2">
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.settings.manage', $event)): ?>
            <a href="<?php echo e(route('admin.events.settings', $event)); ?>" class="setup-link">
              <span class="setup-link__icon"><i class="ti ti-settings"></i></span>
              <span class="setup-link__copy"><span class="setup-link__title">Event settings</span><span class="setup-link__description">Visibility, dates, registration and access.</span></span>
              <i class="ti ti-chevron-right setup-link__arrow" aria-hidden="true"></i>
            </a>
          <?php endif; ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-category.manage', $event)): ?>
            <a href="<?php echo e(route('admin.events.categories', $event)); ?>" class="setup-link">
              <span class="setup-link__icon"><i class="ti ti-category"></i></span>
              <span class="setup-link__copy"><span class="setup-link__title">Categories &amp; fees</span><span class="setup-link__description">Manage trials categories and category fees.</span></span>
              <i class="ti ti-chevron-right setup-link__arrow" aria-hidden="true"></i>
            </a>
          <?php endif; ?>
          <a href="<?php echo e(route('backend.interprovincial-trials.invitations.index', $event)); ?>" class="setup-link">
            <span class="setup-link__icon"><i class="ti ti-user-check"></i></span>
            <span class="setup-link__copy"><span class="setup-link__title">Interpro setup</span><span class="setup-link__description">Nominate players, edit the message and use the fast send flow.</span></span>
            <i class="ti ti-chevron-right setup-link__arrow" aria-hidden="true"></i>
          </a>
          <a href="<?php echo e(route('backend.event-venue-schedule.index', $event)); ?>" class="setup-link">
            <span class="setup-link__icon"><i class="ti ti-map-pin"></i></span>
            <span class="setup-link__copy"><span class="setup-link__title">Venues &amp; schedule</span><span class="setup-link__description">Configure courts and schedule assignments.</span></span>
            <i class="ti ti-chevron-right setup-link__arrow" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-12">
      <div class="card h-100 stats-panel">
        <div class="card-header d-flex align-items-center gap-2"><i class="ti ti-chart-bar ti-md text-info"></i><h5 class="mb-0">Quick Stats</h5></div>
        <div class="card-body">
          <ul class="list-unstyled mb-0 d-grid gap-1">
            <li>Categories: <span class="fw-semibold float-end"><?php echo e($interproStats['categories']); ?></span></li>
            <li>Nominations: <span class="fw-semibold float-end"><?php echo e($interproStats['nominations']); ?></span></li>
            <li>Current batch: <span class="fw-semibold float-end"><?php echo e(str($interproStats['batch'])->replace('_', ' ')->title()); ?></span></li>
            <li>Queued / sent: <span class="fw-semibold float-end"><?php echo e($interproStats['queued']); ?> / <?php echo e($interproStats['sent']); ?></span></li>
            <li>Payment pending: <span class="fw-semibold float-end"><?php echo e($interproStats['acceptedPendingPayment']); ?></span></li>
            <li>Paid confirmations: <span class="fw-semibold float-end"><?php echo e($interproStats['paidConfirmed']); ?></span></li>
            <li>Declined / withdrawn: <span class="fw-semibold float-end"><?php echo e($interproStats['declinedOrWithdrawn']); ?></span></li>
            <li>Failed delivery: <span class="fw-semibold float-end"><?php echo e($interproStats['failed']); ?></span></li>
            <li>Nomination list: <span class="fw-semibold float-end"><?php echo e($interproStats['publication']); ?></span></li>
            <li>Registration: <span class="fw-semibold float-end"><?php echo e($interproStats['registration']); ?></span></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\interprovincial-trials\overview.blade.php ENDPATH**/ ?>