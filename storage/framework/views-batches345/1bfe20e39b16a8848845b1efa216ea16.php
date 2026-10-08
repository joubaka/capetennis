<div class="row g-3">

  
  <div class="col-xl-4 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-users-group ti-md text-primary"></i>
        <h5 class="mb-0">Team Management</h5>
      </div>

      <div class="card-body d-grid gap-2">

        <a href="<?php echo e(route('admin.events.teams', $event)); ?>"
           class="btn btn-primary">
          <i class="ti ti-users me-1"></i>
          Teams & Regions
        </a>

        
        <a href="<?php echo e(route('backend.team-fixtures.index', ['event_id' => $event->id])); ?>"
           class="btn btn-outline-primary">
          <i class="ti ti-calendar-meet me-1"></i>
          Fixtures (HQ)
        </a>

        <a href="<?php echo e(route('admin.events.transactions', $event)); ?>"
           class="btn btn-outline-success">
          <i class="ti ti-credit-card me-1"></i>
          Team Payments
        </a>

      </div>
    </div>
  </div>

  
  <div class="col-xl-4 col-md-6">
    <div class="card h-100 border-start border-info border-3">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-adjustments ti-md text-info"></i>
        <h5 class="mb-0">Team Setup</h5>
      </div>

      <div class="card-body d-grid gap-2">

        <a href="<?php echo e(route('admin.events.settings', $event)); ?>"
           class="btn btn-outline-info">
          <i class="ti ti-sliders me-1"></i>
          Event Settings
        </a>



        <a href="<?php echo e(route('admin.events.announcements', $event)); ?>"
           class="btn btn-outline-warning">
          <i class="ti ti-megaphone me-1"></i>
          Team Announcements
        </a>

      </div>
    </div>
  </div>

  
 
<div class="col-xl-4 col-md-12">
  <div class="card h-100">
    <div class="card-header d-flex align-items-center gap-2">
      <i class="ti ti-chart-pie ti-md text-success"></i>
      <h5 class="mb-0">
        <?php echo e($event->isTeam() ? 'Team Stats' : 'Event Stats'); ?>

      </h5>
    </div>

    <div class="card-body">
      <ul class="list-unstyled mb-0 d-grid gap-1">

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam()): ?>
        <li>
          Regions
          <span class="fw-semibold float-end">
            <?php echo e($event->regions->count()); ?>

          </span>
        </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam()): ?>
        <li>
          Teams
          <span class="fw-semibold float-end">
            <?php echo e($event->regions->sum(fn ($r) => $r->teams->count())); ?>

          </span>
        </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <li>
          Players
          <span class="fw-semibold float-end">
            <?php echo e($event->isTeam() ? $stats['players'] : $stats['entries']); ?>

          </span>
        </li>

        
        <li>
          Matches
          <span class="fw-semibold float-end">
            <?php echo e($stats['matchesPlayed']); ?> / <?php echo e($stats['matchesTotal']); ?>

          </span>
        </li>

      </ul>
    </div>
  </div>
</div>

</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\admin\home.blade.php ENDPATH**/ ?>