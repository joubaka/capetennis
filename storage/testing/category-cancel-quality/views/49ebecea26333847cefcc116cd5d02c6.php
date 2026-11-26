<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->announcements->isNotEmpty()): ?>
  <?php
    $accountSeen = auth()->check()
      ? app(\App\Services\PlusMorningDigestService::class)->seenRevisions(auth()->user(), $event->announcements->map(fn ($post) => 'announcement:'.$post->id)->all())
      : [];
  ?>
  <section id="event-announcements" class="card event-section-card event-announcements mb-4" data-event-announcements
    data-storage-key="ct:announcements:<?php echo e($event->id); ?>:<?php echo e(auth()->id() ?? 'guest'); ?>"
    <?php if(auth()->guard()->check()): ?> data-read-url="<?php echo e(route('announcement-reads')); ?>" <?php endif; ?>>
    <div class="card-body p-3">
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <i class="ti ti-speakerphone event-announcement-icon fs-4" aria-hidden="true"></i>
        <h5 class="mb-0">Announcements</h5>
        <span class="badge ms-auto" data-announcement-status aria-live="polite">New updates</span>
      </div>
      <details data-announcement-reader>
        <summary class="btn" data-announcement-toggle>View <?php echo e($event->announcements->count()); ?> new <?php echo e($event->announcements->count() === 1 ? 'announcement' : 'announcements'); ?></summary>
        <div class="pt-3" data-announcement-list>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-3" data-announcement-controls hidden>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-announcement-all>View all announcements</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-announcement-mark-all>Mark all as read</button>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-announcement-close>Close</button>
          </div>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <details id="event-announcement-<?php echo e($announcement->id); ?>" class="event-announcement border rounded p-3 mb-2" data-announcement
              data-announcement-id="<?php echo e($announcement->id); ?>"
              data-account-read="<?php echo e(($accountSeen['announcement:'.$announcement->id] ?? null) === $announcement->readRevision() ? 'true' : 'false'); ?>"
              data-posted-at="<?php echo e($announcement->created_at?->getTimestampMs()); ?>"
              data-revision="<?php echo e($announcement->readRevision()); ?>">
              <summary class="event-announcement-title">
                <span><?php echo e(filled($announcement->title) ? $announcement->title : 'Event update'); ?></span>
                <span class="badge bg-label-success" data-announcement-badge>New</span>
              </summary>
              <div class="small text-muted mt-2"><?php echo e(optional($announcement->created_at)->timezone(config('app.timezone'))->format('d M Y, H:i')); ?></div>
              <div class="event-information-content mt-3"><?php echo $announcement->message; ?></div>
              <button type="button" class="btn btn-success mt-3" data-announcement-viewed hidden>Mark as read &amp; close</button>
            </details>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <p class="small text-muted mb-0 mt-3" data-announcement-storage-note hidden>Read status is remembered in this browser.</p>
        </div>
      </details>
      <button type="button" class="btn btn-sm btn-link mt-1 px-0" data-announcement-view-all hidden>View all announcements</button>
    </div>
  </section>
  <?php if (! $__env->hasRenderedOnce('ac0d7ac1-fedc-4490-9e73-213685d967cc')): $__env->markAsRenderedOnce('ac0d7ac1-fedc-4490-9e73-213685d967cc'); ?>
    <style>
      .event-announcements [hidden] { display: none !important; }
      .event-announcements {
        --announcement-accent: #146c43;
        --announcement-tint: #e6f4ec;
        border-top: 4px solid var(--announcement-accent) !important;
        scroll-margin-top: 6rem;
      }
      .event-announcements[data-recent-unread] {
        --announcement-accent: #9c3f00;
        --announcement-tint: #fff0dc;
        background: var(--announcement-tint);
      }
      .event-announcement-icon { color: var(--announcement-accent); }
      .event-announcements [data-announcement-status] {
        color: var(--announcement-accent);
        background: var(--announcement-tint);
        white-space: normal;
      }
      .event-announcements [data-announcement-toggle] {
        --bs-btn-color: #fff;
        --bs-btn-bg: var(--announcement-accent);
        --bs-btn-border-color: var(--announcement-accent);
        --bs-btn-hover-color: #fff;
        --bs-btn-hover-bg: var(--announcement-accent);
        --bs-btn-hover-border-color: var(--announcement-accent);
        --bs-btn-active-color: #fff;
        --bs-btn-active-bg: var(--announcement-accent);
        --bs-btn-active-border-color: var(--announcement-accent);
      }
      .event-announcements [data-announcement-toggle]:hover { text-decoration: underline; }
      .event-announcements [data-announcement-toggle]:focus-visible {
        outline: 2px solid var(--announcement-accent);
        outline-offset: 2px;
      }
      .event-announcements summary { cursor: pointer; }
      .event-announcements [data-announcement-toggle] { white-space: normal; min-height: 44px; text-align: left; }
      .event-announcement-title { overflow-wrap: anywhere; font-weight: 600; }
      .event-announcement { scroll-margin-top: 6rem; }
      .event-announcement-title .badge { margin-left: .5rem; }
      .event-announcements .event-information-content { overflow-wrap: anywhere; overflow-x: auto; }
      .event-announcements .event-information-content img { max-width: 100%; height: auto; }
      .event-announcements button { min-height: 44px; }
      @media (max-width: 575.98px) {
        .event-announcements [data-announcement-toggle] { width: 100%; }
        .event-announcements h5 { font-size: 1rem; }
      }
    </style>
  <?php endif; ?>
  <?php echo $__env->make('layouts.sections.announcement-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\event-announcements.blade.php ENDPATH**/ ?>