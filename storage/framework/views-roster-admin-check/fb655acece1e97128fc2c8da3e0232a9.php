<div class="row g-3 mb-4">

  
  <div class="col-12">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="ti ti-chart-pie ti-md text-success"></i>
        <h5 class="mb-0">
          <?php echo e($event->isTeam() ? 'Team Stats' : 'Event Stats'); ?>

        </h5>
      </div>

      <div class="card-body">
        <ul class="list-unstyled mb-0 d-grid gap-2">

          
          <li class="d-flex justify-content-between">
            <span>Categories</span>
            <span class="badge bg-label-info rounded-pill">
              <?php echo e($stats['categories']); ?>

            </span>
          </li>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam()): ?>
            <li class="d-flex justify-content-between">
              <span>Regions</span>
              <span class="badge bg-label-primary rounded-pill">
                <?php echo e($event->regions->count()); ?>

              </span>
            </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam()): ?>
            <li class="d-flex justify-content-between">
              <span>Teams</span>
              <span class="badge bg-label-primary rounded-pill">
                <?php echo e($event->regions->sum(fn ($r) => $r->teams->count())); ?>

              </span>
            </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <li class="d-flex justify-content-between">
            <span>Players</span>
            <span class="badge bg-label-success rounded-pill">
              <?php echo e($event->isTeam() ? $stats['players'] : $stats['entries']); ?>

            </span>
          </li>

          
          <li class="d-flex justify-content-between">
            <span>Draws Locked</span>
            <span class="badge bg-label-warning rounded-pill">
              <?php echo e($stats['drawsLocked']); ?>

            </span>
          </li>

          
          <li>
            <div class="d-flex justify-content-between mb-1">
              <small>Matches</small>
              <small class="text-muted">
                <?php echo e($stats['matchesPlayed']); ?> / <?php echo e($stats['matchesTotal']); ?>

              </small>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar bg-success"
                   role="progressbar"
                   style="width: <?php echo e($stats['matchesTotal'] > 0 ? round(($stats['matchesPlayed'] / $stats['matchesTotal']) * 100) : 0); ?>%"
                   aria-valuenow="<?php echo e($stats['matchesPlayed']); ?>"
                   aria-valuemin="0"
                   aria-valuemax="<?php echo e($stats['matchesTotal']); ?>">
              </div>
            </div>
          </li>

        </ul>
      </div>
    </div>
  </div>

  
  <div class="col-12">
    <details class="event-overview-disclosure">
      <summary>Setup tools</summary>

      <div class="card-body d-grid gap-2">
        <a class="btn btn-outline-primary" href="<?php echo e(route('backend.event-communications.index', $event)); ?>"><i class="ti ti-mail me-1"></i>Communications &amp; send reports</a>

        <a class="btn btn-primary"
           href="<?php echo e(route('backend.team-selection.index', $event)); ?>">
          <i class="ti ti-user-check me-1"></i>
          Team Selection & Invitations
        </a>

        <a class="btn btn-outline-primary" href="<?php echo e(route('backend.event.clothing.index', $event)); ?>">
          <i class="ti ti-shirt me-1"></i>
          Clothing Setup
        </a>

        <button type="button"
                class="btn btn-outline-success"
                id="sync-team-categories-btn"
                data-url="<?php echo e(url('/backend/event/' . $event->id . '/import-teams')); ?>">
          <i class="ti ti-upload me-1"></i>
          Sync Categories from Teams
        </button>

      </div>
    </details>
  </div>


</div>

<script>
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('#sync-team-categories-btn');
    if (!btn) return;

    const url = btn.getAttribute('data-url');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!url || !token) return;

    if (!confirm('Sync categories from teams for this event?')) return;

    btn.disabled = true;

    fetch(url, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw data;
        AppFeedback.success(data.message || 'Categories synced successfully.');
      })
      .catch((err) => {
        const msg = err?.message || 'Failed to sync categories.';
        AppFeedback.error(msg);
      })
      .finally(() => {
        btn.disabled = false;
      });
  });
</script>

<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\team\index.blade.php ENDPATH**/ ?>