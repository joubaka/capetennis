<div class="row g-3">

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isInterprovincialTrials() && auth()->check() && (auth()->user()->hasRole('super-user') || (auth()->user()->hasRole('admin') && auth()->user()->is_event_admin($event->id)))): ?>
  <div class="col-12">
    <div class="card border border-primary h-100">
      <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
          <h5 class="mb-1"><i class="ti ti-user-check me-1 text-primary"></i>Nominations &amp; invitations</h5>
          <p class="text-muted mb-0">Find existing Cape Tennis players, nominate them per trials category, and prepare the reviewed invitation list.</p>
        </div>
        <a class="btn btn-primary flex-shrink-0" href="<?php echo e(route('backend.interprovincial-trials.invitations.index', $event)); ?>">Manage nominations &amp; invitations</a>
      </div>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="col-xl-4 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-world ti-md text-primary"></i>
        <h5 class="mb-0">Results publication</h5>
      </div>
      <div class="card-body d-grid gap-2">
        <p class="text-muted mb-2"><?php echo e($event->results_published == 1 ? 'Final positions are currently visible to the public.' : 'Final positions remain private until you publish them.'); ?></p>
        <form method="POST" action="<?php echo e(route('result.publish', $event->id)); ?>" class="d-inline"
              onsubmit="return confirm('Are you sure you want to <?php echo e($event->results_published == 1 ? 'unpublish' : 'publish'); ?> the results?')">
          <?php echo csrf_field(); ?>
          <button type="submit" class="btn btn-<?php echo e($event->results_published == 1 ? 'danger' : 'success'); ?>">
            <i class="ti ti-<?php echo e($event->results_published == 1 ? 'eye-off' : 'eye'); ?> me-1"></i>
            <?php echo e($event->results_published == 1 ? 'Unpublish Results' : 'Publish Results'); ?>

          </button>
        </form>
      </div>
    </div>
  </div>

  
  <div class="col-xl-8 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-chart-bar ti-md text-info"></i>
        <h5 class="mb-0">Quick Stats</h5>
      </div>

      <div class="card-body">
        <ul class="list-unstyled mb-0 d-grid gap-1">
          <li>
            Categories:
            <span class="fw-semibold float-end"><?php echo e($stats['categories']); ?></span>
          </li>
          <li>
            Entries:
            <span class="fw-semibold float-end"><?php echo e($stats['entries']); ?></span>
          </li>
          <li>
            Matches:
            <span class="fw-semibold float-end">
              <?php echo e($stats['matchesPlayed']); ?> / <?php echo e($stats['matchesTotal']); ?>

            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>

</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\individual\index.blade.php ENDPATH**/ ?>