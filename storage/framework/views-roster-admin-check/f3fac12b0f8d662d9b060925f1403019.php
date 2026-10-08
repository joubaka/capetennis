<div class="draws-workspace">
  <?php echo $__env->make('backend.event.partials.header', [
    'eventWorkspaceActive' => 'draws',
    'eventWorkspaceIcon' => 'ti-tournament',
    'eventWorkspaceSubtitle' => 'Individual draws, schedules and publication',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div><h2 class="h4 mb-1">Tournament draws</h2><p class="text-muted mb-0">Player draws, match schedules and publication in one place.</p></div>
    <div class="draws-event-actions">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isNotEmpty()): ?>
        <button type="button" class="btn draws-button draws-button-secondary" data-bs-toggle="modal" data-bs-target="#drawSettingsModal">
          <i class="ti ti-settings" aria-hidden="true"></i> Draw settings
        </button>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $event)): ?>
          <a class="btn draws-button draws-button-primary" href="<?php echo e(route('backend.event-venue-schedule.index', $event)); ?>"><i class="ti ti-calendar-event me-1"></i> Schedule all draws & matches</a>
        <?php endif; ?>
        <a class="btn draws-button draws-button-secondary" href="<?php echo e(route('headoffice.printOptions', $event)); ?>"><?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'print'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Print options</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button type="button" class="btn draws-button draws-button-primary" data-bs-toggle="modal" data-bs-target="#createDrawModal"><?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'plus'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> New draw</button>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo e(session('success')); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isNotEmpty()): ?>
    <?php
      $drawCount = $event->draws->count();
      $generatedCount = $event->draws->where('draw_fixtures_count', '>', 0)->count();
      $publishedCount = $event->draws->where('published', true)->count();
      $scheduledCount = $event->draws->where('order_of_play_count', '>', 0)->count();
      $schedulePublishedCount = $event->draws->where('oop_published', true)->count();
    ?>
    <section class="card mb-3" aria-labelledby="release-readiness-heading">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <h2 id="release-readiness-heading" class="h5 mb-1">Parent release readiness</h2>
            <p class="text-muted small mb-0">Publishing a draw reveals players and structure. Publishing its schedule separately reveals match times and venues. Neither step happens automatically.</p>
          </div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->published): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('events.show', $event)); ?>" target="_blank" rel="noopener">
              Preview parent page <i class="ti ti-external-link ms-1" aria-hidden="true"></i>
            </a>
          <?php else: ?>
            <span class="badge bg-label-warning">Event page is not published</span>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="row g-2 mt-2">
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Draws generated</small><strong><?php echo e($generatedCount); ?>/<?php echo e($drawCount); ?></strong></div></div>
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Draws published</small><strong><?php echo e($publishedCount); ?>/<?php echo e($drawCount); ?></strong></div></div>
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Schedules prepared</small><strong><?php echo e($scheduledCount); ?>/<?php echo e($drawCount); ?></strong></div></div>
          <div class="col-6 col-lg-3"><div class="border rounded p-2"><small class="text-muted d-block">Schedules published</small><strong><?php echo e($schedulePublishedCount); ?>/<?php echo e($drawCount); ?></strong></div></div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scheduledCount > $schedulePublishedCount): ?>
          <div class="alert alert-warning mt-3 mb-0" role="status">
            <strong><?php echo e($scheduledCount - $schedulePublishedCount); ?> <?php echo e(Str::plural('schedule', $scheduledCount - $schedulePublishedCount)); ?> prepared but not public.</strong>
            Use <em>Publish times</em> to preview time, venue and court details before releasing the draw publicly.
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <section class="card draws-overview" aria-labelledby="draws-heading">
    <div class="draws-toolbar">
      <div>
        <h2 id="draws-heading">Tournament draws <span class="draws-total"><?php echo e($event->draws->count()); ?></span></h2>
        <p>Player draws, match schedules and publication—all in one place.</p>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isNotEmpty()): ?>
        <div class="draws-filters">
          <label class="visually-hidden" for="draw-search">Search draws</label>
          <div class="draws-search"><?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'search'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><input id="draw-search" type="search" class="form-control" placeholder="Search draws or formats…" autocomplete="off"></div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isNotEmpty()): ?>
      <div class="draws-list-controls">
        <div class="draws-status-filters" role="group" aria-label="Filter draws by publication status">
          <input type="hidden" id="draw-status" value="">
          <button type="button" data-draw-filter="" aria-pressed="true">All draws <span><?php echo e($event->draws->count()); ?></span></button>
          <button type="button" data-draw-filter="0" aria-pressed="false"><span class="draws-status-dot is-draft" aria-hidden="true"></span>Draft <span><?php echo e($event->draws->where('published', false)->count()); ?></span></button>
          <button type="button" data-draw-filter="1" aria-pressed="false"><span class="draws-status-dot is-published" aria-hidden="true"></span>Published <span><?php echo e($event->draws->where('published', true)->count()); ?></span></button>
        </div>
        <p id="draw-filter-count" role="status" aria-live="polite">Showing <?php echo e($event->draws->count()); ?> draws</p>
      </div>
      <div class="draws-bulk-actions" aria-label="Actions for selected draws">
        <label class="draws-select-all" for="draw-select-all">
          <input class="form-check-input" id="draw-select-all" type="checkbox">
          <span>Select all visible</span>
        </label>
        <span id="draw-selection-count" class="draws-selection-count" role="status" aria-live="polite">0 selected</span>
        <div class="draws-bulk-buttons">
          <button type="button" class="btn draws-button draws-button-secondary" id="schedule-selected" disabled>
            <?php echo $__env->make('backend.headOffice.partials.draw-icon', ['icon' => 'calendar'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> Schedule selected
          </button>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->contains(fn($draw) => Gate::allows('publish', $draw))): ?>
            <button type="button" class="btn draws-button draws-button-primary" id="publish-selected-draws" disabled>
              <i class="ti ti-eye" aria-hidden="true"></i> Publish draws
            </button>
            <button type="button" class="btn draws-button draws-button-secondary" id="unpublish-selected-draws" disabled>
              <i class="ti ti-eye-off" aria-hidden="true"></i> Unpublish draws
            </button>
            <button type="button" class="btn draws-button draws-button-secondary" id="publish-selected-times" disabled>
              <i class="ti ti-clock" aria-hidden="true"></i> Publish times
            </button>
            <button type="button" class="btn draws-button draws-button-secondary" id="unpublish-selected-times" disabled>
              <i class="ti ti-clock-off" aria-hidden="true"></i> Unpublish times
            </button>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
      <div class="draws-column-headings" aria-hidden="true"><span>Division</span><span>Draw format</span><span>Matches</span><span>Venue</span><span class="draws-actions-label">Manage draw</span></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php echo $__env->make('backend.headOffice.partials.individual-draw-row', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="text-center p-5">
        <h3 class="h5">Create your first draw</h3>
        <p class="text-muted mb-0">Use New draw to name a division, then choose its format and players.</p>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isNotEmpty()): ?>
      <div id="draw-no-results" class="text-center p-4" hidden>
        <p class="mb-2">No draws match these filters.</p>
        <button id="draw-clear-filters" type="button" class="btn btn-sm btn-outline-secondary">Clear filters</button>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\headOffice\partials\individual-draw-overview.blade.php ENDPATH**/ ?>