<?php ($pickerId = 'event-region-picker-'.$event->id); ?>

<div class="region-team-picker" id="<?php echo e($pickerId); ?>">
  <div class="region-picker-label fw-semibold mb-2">Choose a region</div>
  <ul class="nav nav-tabs region-tab-grid" role="tablist" aria-label="Event regions">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($tabId = 'team-'.$event->id.'-'.$region->id); ?>
      <li class="nav-item" role="presentation">
        <button type="button"
                class="nav-link <?php echo e($idx === 0 ? 'active' : ''); ?>"
                data-bs-toggle="tab"
                data-bs-target="#<?php echo e($tabId); ?>"
                role="tab"
                aria-controls="<?php echo e($tabId); ?>"
                aria-selected="<?php echo e($idx === 0 ? 'true' : 'false'); ?>">
          <i class="ti ti-map-pin me-1" aria-hidden="true"></i>
          <span class="region-name">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = explode('/', $region->region_name); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $namePart): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php echo e($namePart); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($loop->last)): ?>/<wbr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </span>
        </button>
      </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </ul>

  <div class="tab-content pt-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($tabId = 'team-'.$event->id.'-'.$region->id); ?>
      <div class="tab-pane fade <?php echo e($idx === 0 ? 'active show' : ''); ?>" id="<?php echo e($tabId); ?>" role="tabpanel">
        <div class="row">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int)($team->noProfile ?? 0) === 0): ?>
              <?php echo $__env->make('frontend.event.partials.profile-team', ['team' => $team], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php else: ?>
              <?php echo $__env->make('frontend.event.partials.no-profile-team', ['team' => $team], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="col-12">
              <div class="alert alert-secondary mb-0">No teams listed for this region yet.</div>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>

<?php if (! $__env->hasRenderedOnce('be8ec12a-42ca-4467-9030-4f381f3ee924')): $__env->markAsRenderedOnce('be8ec12a-42ca-4467-9030-4f381f3ee924'); ?>
  <style>
      .region-tab-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: .6rem;
        border-bottom: 0;
      }
      .region-tab-grid .nav-item { min-width: 0; }
      .region-tab-grid .nav-link {
        width: 100%;
        height: 100%;
        padding: .75rem .9rem;
        border: 1px solid var(--bs-border-color);
        border-radius: .65rem;
        background: var(--bs-body-bg);
        color: var(--bs-body-color);
        text-align: left;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.25;
      }
      .region-tab-grid .nav-link:hover { border-color: var(--bs-primary); }
      .region-tab-grid .nav-link.active {
        border-color: var(--bs-primary);
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
        box-shadow: 0 .2rem .6rem rgba(0, 0, 0, .08);
      }
      @media (max-width: 767.98px) {
        .region-tab-grid .nav-link {
          min-height: 48px;
          display: flex;
          align-items: center;
        }
      }
      @media (min-width: 768px) {
        .region-tab-grid {
          grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
      }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const target = document.querySelector('[data-team-registration-target="true"]');
      if (!target) return;

      const pane = target.closest('.tab-pane');
      const tab = pane ? document.querySelector('[data-bs-target="#' + pane.id + '"]') : null;
      if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();

      window.setTimeout(function () {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const register = target.querySelector('.team-registration-button');
        if (register) register.focus({ preventScroll: true });
      }, 150);
    });
  </script>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\_region_team_picker.blade.php ENDPATH**/ ?>