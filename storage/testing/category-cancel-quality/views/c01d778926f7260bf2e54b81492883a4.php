<?php $__env->startSection('title', 'Interprovincial Trials nominations and invitations'); ?>
<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-style'); ?>
<style>
  .ct-backend .interpro-filter-bar .btn:not(.p-0):not(.btn-icon) { min-height:46px; }
  .interpro-tool-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; align-items:start; }
  .interpro-tools { display:block; min-width:0; }
  .interpro-tools[open] { grid-column:1 / -1; }
  .interpro-tools > summary { cursor:pointer; min-height:48px; }
  .interpro-category > summary { cursor:pointer; }
  .interpro-category[open] > summary { border-bottom:1px solid #e6e8ee; }
  .interpro-history > summary { cursor:pointer; min-height:32px; }
  .interpro-history-content { display:flex; flex-direction:column; gap:.25rem; padding-top:.4rem; }
  @media (max-width:575.98px) { .interpro-tool-grid { grid-template-columns:1fr; } }
  .interpro-tools > summary::marker { color:#566a7f; }
  .interpro-filter-bar { display:flex; flex-wrap:wrap; gap:.5rem; }
  .interpro-nomination-table { --nomination-columns:minmax(0,1.15fr) minmax(0,.9fr) minmax(0,1fr) minmax(0,1.2fr); }
  .interpro-nomination-head, .interpro-nomination-row { display:grid; gap:1rem; grid-template-columns:var(--nomination-columns); padding:.8rem 1rem; }
  .interpro-nomination-head { background:#f6f7fa; border-bottom:1px solid #e6e8ee; color:#687185; font-size:.75rem; font-weight:600; }
  .interpro-nomination-row { align-items:start; border-top:1px solid #ebeaf0; }
  .interpro-nomination-row:first-child { border-top:0; }
  .interpro-nomination-row:hover { background:#fbfcfe; }
  .interpro-nomination-row__identity, .interpro-nomination-row__status, .interpro-nomination-row__timeline, .interpro-nomination-row__actions { min-width:0; overflow-wrap:anywhere; }
  .interpro-nomination-row__status, .interpro-nomination-row__timeline { align-items:flex-start; display:flex; flex-direction:column; gap:.3rem; }
  .interpro-nomination-row__status .badge { white-space:normal; text-align:left; line-height:1.3; }
  .interpro-nomination-row__actions { align-items:flex-start; display:flex; flex-wrap:wrap; gap:.4rem; }
  .interpro-nomination-row__actions details { min-width:0; max-width:100%; }
  .interpro-nomination-row__actions details[open] { flex-basis:100%; padding:.6rem; border:1px solid #dce7df; border-radius:.4rem; background:#f7faf8; }
  .interpro-nomination-row__actions details[open] summary { margin-bottom:.25rem; }
  .interpro-nomination-row__actions p { margin-bottom:.5rem; }
  .interpro-cell-label { display:none; }
  .interpro-row-category { display:none; }
  .interpro-category-title { background:#fff; margin:0; padding:.9rem 1rem; font-size:.95rem; }
  .interpro-email { display:inline-block; max-width:100%; overflow-wrap:anywhere; word-break:break-word; }
  .interpro-row-action { min-height:36px; min-width:44px; padding:.35rem .65rem; font-size:.78rem; line-height:1.3; }
  @media (max-width: 991.98px) {
    .interpro-nomination-head { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); clip-path:inset(50%); white-space:nowrap; border:0; }
    .interpro-nomination-row { grid-template-columns:1fr 1fr; gap:.75rem 1rem; }
    .interpro-nomination-row__identity, .interpro-nomination-row__actions { grid-column:1 / -1; }
    .interpro-cell-label { display:block; color:#687185; font-size:.7rem; font-weight:600; margin-bottom:.2rem; }
    .interpro-row-category { display:block; }
    .interpro-row-action { min-height:44px; }
    .interpro-history > summary { min-height:44px; display:flex; align-items:center; }
    .interpro-nomination-row__actions details[open] { max-width:30rem; }
  }
  @media (max-width: 575.98px) {
    .interpro-nomination-row { grid-template-columns:1fr; padding:1rem; }
    .interpro-nomination-row__identity { font-size:1rem; }
    .interpro-nomination-row__actions { gap:.5rem; }
    .interpro-nomination-row__actions > form, .interpro-nomination-row__actions > details { flex:1 1 auto; }
    .interpro-nomination-row__actions > form .interpro-row-action, .interpro-nomination-row__actions > details summary { width:100%; }
    .interpro-nomination-row__actions > a { flex-basis:100%; min-height:44px; display:flex; align-items:center; }
  }
</style>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <?php echo $__env->make('backend.event.partials.header', ['event' => $event], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Nominations &amp; invitations</h2><p class="text-muted mb-0">Manage nominees here. Publishing names and emailing players are separate, optional actions.</p></div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?php echo e(route('admin.events.overview', $event)); ?>">Back to event overview</a>
    </div>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?> <div class="alert alert-success"><?php echo e(session('success')); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?> <div class="alert alert-danger"><?php echo e($errors->first()); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


  <?php
    $publishedCategoryCount = $event->categoryEvents->where('nominations_published', true)->count();
    $publicationStatus = $publishedCategoryCount === 0 ? 'Private' : ($publishedCategoryCount === $event->categoryEvents->count() ? 'Published' : 'Mixed');
  ?>
  <div class="interpro-tool-grid mb-3">
  <details class="card interpro-tools" id="add-nominees" <?php if($errors->any() || request()->filled('source_search') || ($nominationFilterGroups['all']['count'] ?? 0) === 0): ?> open <?php endif; ?>>
    <summary class="p-3 fw-semibold">Add nominees</summary>
    <div class="card-body pt-0">
    <h5 class="mb-3">Nominate existing players</h5>
    <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.bulk-store', $event)); ?>" id="nomination-form" class="row g-3">
      <?php echo csrf_field(); ?>
      <div class="col-lg-6">
        <label class="form-label" for="nomination-player">Player</label>
        <select class="form-select" id="nomination-player" name="player_ids[]" style="width:100%" multiple required aria-describedby="nomination-player-help"></select>
        <div class="form-text" id="nomination-player-help">Search by player name or surname. You may select up to 50 players.</div>
      </div>
      <div class="col-lg-4">
        <label class="form-label" for="nomination-category">Category</label>
        <select class="form-select" id="nomination-category" name="category_event_id" required>
          <option value="">Choose a category</option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($categoryEvent->id); ?>" data-published="<?php echo e((int) $categoryEvent->nominations_published); ?>"><?php echo e($categoryEvent->category?->name ?? 'Category'); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
      </div>
      <div class="col-lg-2 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit" <?php echo e($event->categoryEvents->isEmpty() ? 'disabled' : ''); ?>>Review nominations</button>
      </div>
      <div class="col-12"><div id="nomination-feedback" class="small" role="status" aria-live="polite"></div></div>
      <div class="col-12"><span id="nomination-selected-count">0 players selected</span></div>
      <div class="col-12 d-none" id="nomination-review"><strong id="nomination-review-category"></strong><p id="nomination-review-visibility"></p><ul id="nomination-review-names"></ul><button type="button" id="nomination-save" class="btn btn-primary">Save nominations</button></div>
    </form>
    <details class="mt-3" <?php if($errors->hasAny(['nominee_name', 'nominee_surname', 'nominee_email']) || old('nominee_name') !== null): ?> open <?php endif; ?>>
      <summary class="fw-semibold">Add a player without a profile</summary>
      <p class="text-muted small mt-2">Use the player or parent/guardian email for invitations. Any signed-in user may complete the player profile and pay for registration.</p>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.no-profile', $event)); ?>" class="row g-3">
        <?php echo csrf_field(); ?>
        <div class="col-md-6"><label for="nominee-name" class="form-label">First name</label><input id="nominee-name" name="nominee_name" class="form-control" value="<?php echo e(old('nominee_name')); ?>" maxlength="255" required></div>
        <div class="col-md-6"><label for="nominee-surname" class="form-label">Surname</label><input id="nominee-surname" name="nominee_surname" class="form-control" value="<?php echo e(old('nominee_surname')); ?>" maxlength="255" required></div>
        <div class="col-md-6"><label for="nominee-email" class="form-label">Player or parent/guardian email (optional)</label><input id="nominee-email" name="nominee_email" type="email" class="form-control" value="<?php echo e(old('nominee_email')); ?>" maxlength="255"></div>
        <div class="col-md-6"><label for="nominee-category" class="form-label">Category</label><select id="nominee-category" name="category_event_id" class="form-select" required><option value="">Choose a category</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($categoryEvent->id); ?>" <?php if((string) old('category_event_id') === (string) $categoryEvent->id): echo 'selected'; endif; ?>><?php echo e($categoryEvent->category?->name ?? 'Category'); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
        <div class="col-12"><button class="btn btn-primary" <?php echo e($event->categoryEvents->isEmpty() ? 'disabled' : ''); ?>>Add nomination without profile</button></div>
      </form>
    </details>
    <?php echo $__env->make('backend.interprovincial-trials._previous-results-nominations', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
  </details>

  <details class="card interpro-tools" id="nomination-email-tools">
    <summary class="p-3 fw-semibold">Email tools</summary>
    <div class="card-body pt-0">
      <a class="btn btn-primary mb-3" href="<?php echo e(route('backend.interprovincial-trials.communications.nominees.compose', $event)); ?>">Email nominated players</a>
      <h6>Invitation emails</h6>
      <p class="text-muted small">Review recipients and the message before sending. Saving or publishing nominations does not send emails.</p>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <button type="button" class="btn btn-primary" data-send-preview-mode="new"><i class="ti ti-send me-1"></i>Review emails for newly nominated</button>
      <button type="button" class="btn btn-outline-primary" data-send-preview-mode="profileless">Review emails for players without profiles</button>
      <button type="button" class="btn btn-outline-primary" data-send-preview-mode="not_registered"><i class="ti ti-mail-forward me-1"></i>Review emails for players not registered</button>
    </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch): ?>
        <p class="small mb-0"><strong>Latest batch #<?php echo e($batch->id); ?></strong> · <?php echo e(ucfirst($batch->status)); ?> · <?php echo e($readiness['invitation_count']); ?> invitations</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <details class="mt-3"><summary class="fw-semibold">Email readiness and audit history</summary>
  <section class="card mb-4" aria-labelledby="invitation-readiness-heading" data-testid="invitation-readiness">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h5 class="mb-1" id="invitation-readiness-heading">Invitation readiness</h5>
        <p class="text-muted small mb-0">A read-only view of nominations, the latest email batch, and registration access.</p>
      </div>
      <span class="badge <?php echo e($readiness['batch_status'] === 'reviewed' ? 'bg-label-success' : 'bg-label-info'); ?> text-break">
        <?php echo e($readiness['batch_status'] ? 'Batch '.ucfirst($readiness['batch_status']) : 'No invitation batch'); ?>

      </span>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Categories</div><div class="fs-4 fw-semibold"><?php echo e($readiness['category_count']); ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Nominations</div><div class="fs-4 fw-semibold"><?php echo e($readiness['nomination_count']); ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Ready recipients</div><div class="fs-4 fw-semibold"><?php echo e($readiness['ready_recipient_count']); ?></div></div></div>
        <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted small">Missing email</div><div class="fs-4 fw-semibold"><?php echo e($readiness['blocked_recipient_count']); ?></div></div></div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3" aria-label="Readiness checks">
        <span class="badge <?php echo e($readiness['nomination_count'] > 0 ? 'bg-label-success' : 'bg-label-warning'); ?>"><?php echo e($readiness['nomination_count'] > 0 ? 'Nominations ready' : 'Add nominations'); ?></span>
        <span class="badge <?php echo e($readiness['invitation_count'] > 0 ? 'bg-label-success' : 'bg-label-warning'); ?>"><?php echo e($readiness['invitation_count'] > 0 ? 'Valid recipients can be emailed' : 'No recipients ready'); ?></span>
        <span class="badge <?php echo e($readiness['message_saved'] ? 'bg-label-success' : 'bg-label-info'); ?>"><?php echo e($readiness['message_saved'] ? 'Message stored' : 'Message ready to edit during review'); ?></span>
        <span class="badge <?php echo e($readiness['registration_open'] ? 'bg-label-success' : 'bg-label-danger'); ?>">Registration <?php echo e($readiness['registration_open'] ? 'open' : 'closed'); ?></span>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($readiness['state_counts']->isNotEmpty()): ?>
        <div class="d-flex flex-wrap gap-2 mt-3" aria-label="Invitation status counts">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $readiness['state_counts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <span class="badge bg-label-secondary text-break"><?php echo e(str($status)->replace('_', ' ')->title()); ?>: <?php echo e($count); ?></span>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section></details>

    </div>
  </details>

  <details class="card interpro-tools" id="nomination-publication-tools">
    <summary class="p-3 fw-semibold">Public visibility <span class="badge bg-label-secondary ms-1"><?php echo e($publicationStatus); ?></span></summary>
    <div class="card-body pt-0 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><strong>Public nomination list: <?php echo e($publicationStatus); ?></strong><div class="text-muted small">Publishing shows nominated player names on the public event page. It does not send emails.</div></div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?php echo e(route('events.show', $event)); ?>" target="_blank" rel="noopener">View public page</a>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.publication', $event)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?><input type="hidden" name="published" value="<?php echo e($publicationStatus === 'Published' ? 0 : 1); ?>"><button class="btn <?php echo e($publicationStatus === 'Published' ? 'btn-outline-warning' : 'btn-outline-primary'); ?>" onclick="return confirm('<?php echo e($publicationStatus === 'Published' ? 'Make every nomination category private?' : 'Publish nominated player names in every category?'); ?>')"><?php echo e($publicationStatus === 'Published' ? 'Unpublish all names' : 'Publish all names'); ?></button></form>
    </div>
    </div>
  </details>

  </div>

  <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Nominated players</h5></div><div class="card-body">
    <p class="text-muted small">Use Remove for an unused nomination, Decline to retain the decision, or Mark paid manually to record collection. Existing registrations must be cancelled or withdrawn in Entries.</p>
    <details class="mb-3"><summary class="small text-muted">About nomination history</summary><p class="text-muted small mt-2 mb-0">Includes archived withdrawals and recorded removals. Removal does not mean a player declined.</p></details>
    <p class="text-muted small">Order: Unregistered · Registered · Declined · Withdrawn · Archived / removed</p>
    <p id="nomination-range" class="text-muted small mb-2">Showing <?php echo e($nominations->firstItem() ?? 0); ?>–<?php echo e($nominations->lastItem() ?? 0); ?> of <?php echo e($nominations->total()); ?> matching nominations. Totals cover the whole event.</p>
    <div class="interpro-filter-bar mb-3" aria-label="Filter all nominated players and history">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $nominationFilterGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a class="btn btn-sm btn-outline-primary <?php echo e($filter['key'] === $nominationStatus ? 'active' : ''); ?>" href="<?php echo e(request()->fullUrlWithQuery(['nomination_status' => $filter['key'], 'nominations_page' => 1])); ?>#nomination-range" <?php if($filter['key'] === $nominationStatus): ?> aria-current="true" <?php endif; ?>>
          <?php echo e($filter['label']); ?> <span class="badge bg-label-primary ms-1" data-filter-count="<?php echo e($filter['key']); ?>"><?php echo e($filter['count']); ?></span>
        </a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nominations->isEmpty() && $event->categoryEvents->isNotEmpty()): ?>
      <p class="text-muted">No nominations match this filter on this page. Category totals below cover the whole event.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="row g-3">
    <?php
      $openedNominationCategory = false;
    ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php
        $categoryNominations = $nominations->getCollection()->where('category_event_id', $categoryEvent->id);
        $openNominationCategory = $categoryNominations->isNotEmpty() && ($nominationStatus !== 'all' || !$openedNominationCategory);
        if ($categoryNominations->isNotEmpty()) $openedNominationCategory = true;
      ?>
      <div class="col-12"><details class="border rounded overflow-hidden interpro-category" data-nomination-category="<?php echo e($categoryEvent->id); ?>" <?php if($openNominationCategory): ?> open <?php endif; ?>>
      <summary class="interpro-category-title fw-semibold"><?php echo e($categoryEvent->category?->name ?? 'Category'); ?> <span class="badge bg-label-primary ms-1" data-nomination-count><?php echo e($categoryEvent->nominations_count); ?> total</span><span class="text-muted small fw-normal ms-2"><?php echo e($categoryNominations->count()); ?> on this page</span></summary>
      <div class="interpro-nomination-table" role="table" aria-label="<?php echo e($categoryEvent->category?->name ?? 'Category'); ?> nominations">
      <div class="interpro-nomination-head" role="row"><span role="columnheader">Player</span><span role="columnheader">Status</span><span role="columnheader">Registration history</span><span role="columnheader">Actions</span></div>
      <div role="rowgroup" data-nomination-list>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $categoryNominations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nomination): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
        <?php echo $__env->make('backend.interprovincial-trials._nomination-row', [
          'presentation' => $nominationPresentations->get($nomination->id),
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><div class="text-muted p-3" data-empty-nominations><?php echo e($categoryEvent->nominations_count ? 'No nominations from this category on this page.' : 'No players nominated yet.'); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></div></details></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="col-12"><div class="alert alert-warning mb-0">Add event categories before nominating players.</div></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nominations->hasPages()): ?><div class="mt-3"><?php echo e($nominations->links()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($historicalInvitations && $historicalInvitations->count()): ?>
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-1">Legacy invitations without nomination history</h5><p class="text-muted small mb-0">These invitation records have no recoverable nomination snapshot and are outside the nomination totals above.</p></div>
      <div class="card-body py-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $historicalInvitations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $presentation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php ($invitation = $presentation['invitation']); ?>
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom py-2">
            <div><span class="fw-semibold"><?php echo e($invitation->player?->name); ?> <?php echo e($invitation->player?->surname); ?></span><div class="small text-muted"><?php echo e($invitation->categoryEvent?->category?->name ?? 'Category unavailable'); ?> · <?php echo e($invitation->recipient_email ?: 'No linked account email'); ?></div></div>
            <span class="badge bg-label-<?php echo e($presentation['tone']); ?>"><?php echo e($presentation['label']); ?></span>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($historicalInvitations->hasPages()): ?><div class="mt-3"><?php echo e($historicalInvitations->links()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


</div>

<div class="modal fade" id="interpro-send-preview-modal" tabindex="-1" aria-labelledby="interpro-send-preview-title" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="interpro-send-preview-title">Review exact invitation recipients</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form id="interpro-send-preview-form">
      <div class="modal-body">
        <div id="interpro-send-preview-feedback" class="alert d-none" role="status"></div>
        <div class="mb-3"><strong>Exact recipients</strong><div id="interpro-send-preview-recipients" class="list-group mt-2"></div></div>
        <div class="mb-3 d-none" id="interpro-send-preview-blockers-wrap"><strong class="text-warning">Skipped because no email is available</strong><div id="interpro-send-preview-blockers" class="list-group mt-2"></div></div>
        <div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="interpro-preview-from-address">From address</label><input type="email" class="form-control" id="interpro-preview-from-address" required></div><div class="col-md-6"><label class="form-label" for="interpro-preview-from-name">From name</label><input class="form-control" id="interpro-preview-from-name" maxlength="100" required></div><div class="col-12"><label class="form-label" for="interpro-preview-reply-to">Reply-to address</label><input type="email" class="form-control" id="interpro-preview-reply-to" required><div class="form-text" id="interpro-preview-sender-warning"></div></div></div>
        <div class="mb-3"><label class="form-label" for="interpro-preview-subject">Subject</label><input class="form-control" id="interpro-preview-subject" maxlength="150" required></div>
        <div class="mb-3"><label class="form-label" for="interpro-preview-body">Message</label><textarea class="form-control" id="interpro-preview-body" rows="6" maxlength="5000" required></textarea></div>
        <div class="border rounded p-3 bg-light d-none" id="interpro-rendered-preview"><div class="small text-uppercase text-muted mb-2">Rendered email preview</div><div id="interpro-rendered-preview-body"></div></div>
        <input type="hidden" id="interpro-preview-mode"><input type="hidden" id="interpro-preview-invitation"><input type="hidden" id="interpro-preview-token"><input type="hidden" id="interpro-preview-hash"><input type="hidden" id="interpro-preview-composition-hash"><input type="hidden" id="interpro-preview-proof"><input type="hidden" id="interpro-preview-expires">
      </div>
      <div class="modal-footer"><span class="me-auto text-muted small" id="interpro-preview-count"></span><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-outline-primary" id="interpro-preview-review">Review rendered email</button><button type="submit" class="btn btn-primary d-none" id="interpro-preview-confirm">Send reviewed emails</button></div>
    </form>
  </div></div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
$(function () {
  const player = $('#nomination-player');
  const form = document.getElementById('nomination-form');
  const feedback = document.getElementById('nomination-feedback');
  const csrf = form.querySelector('input[name="_token"]').value;
  const resultsForm = document.getElementById('previous-results-form');
  const resultsFeedback = document.getElementById('previous-results-feedback');
  const resultsReview = document.getElementById('previous-results-review');
  let resultsToken = null;
  let resultsGeneration = 0;
  const invalidateResultsReview = () => { resultsGeneration++; resultsToken = null; resultsReview.classList.add('d-none'); };
  resultsForm.addEventListener('input', invalidateResultsReview);
  resultsForm.addEventListener('change', invalidateResultsReview);
  const resultsRequest = async (url, payload) => {
    const response = await fetch(url, {method:'POST', headers:{'X-CSRF-TOKEN':csrf, Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to load previous results.');
    return data;
  };
  resultsForm.addEventListener('submit', async event => {
    event.preventDefault(); resultsToken = null; resultsReview.classList.add('d-none'); resultsFeedback.textContent = 'Checking saved final positions…';
    const generation = ++resultsGeneration;
    const button = resultsForm.querySelector('button'); button.disabled = true;
    try {
      const data = await resultsRequest(<?php echo json_encode(route('backend.interprovincial-trials.nominations.previous-results.preview', $event), 512) ?>, Object.fromEntries(new FormData(resultsForm)));
      if (generation !== resultsGeneration) { resultsFeedback.textContent = 'Selection changed. Preview the updated selection.'; return; }
      const preview = data.preview; resultsToken = data.token;
      document.getElementById('previous-results-summary').textContent = `${preview.source_event} (${preview.source_date}) · ${preview.source_category} · age-group year ${preview.age_group_year} → ${preview.category}. Review all ${preview.rows.length} profiles. Saving keeps names private and sends no emails.`;
      document.getElementById('previous-results-rows').innerHTML = preview.rows.map(row => `<tr><td>${row.rank}</td><td>${escapeHtml(row.name)}</td><td>#${row.player_id}</td><td>${data.existing_player_ids.map(Number).includes(row.player_id) ? 'Already nominated — skip' : 'Add privately'}</td></tr>`).join('');
      resultsFeedback.textContent = ''; resultsReview.classList.remove('d-none');
    } catch(error) { resultsFeedback.textContent = error.message; }
    finally { button.disabled = false; }
  });
  document.getElementById('previous-results-save').addEventListener('click', async function () {
    if (!resultsToken) return;
    this.disabled = true;
    try {
      const data = await resultsRequest(<?php echo json_encode(route('backend.interprovincial-trials.nominations.previous-results.store', $event), 512) ?>, {token:resultsToken});
      resultsFeedback.textContent = data.message; resultsToken = null; resultsReview.classList.add('d-none');
      // Reload authoritative totals and both category tables after an import.
      window.location.reload();
    } catch(error) { resultsFeedback.textContent = error.message; }
    finally { this.disabled = false; }
  });

  const previewModalElement = document.getElementById('interpro-send-preview-modal');
  const previewModal = previewModalElement ? bootstrap.Modal.getOrCreateInstance(previewModalElement) : null;
  const previewForm = document.getElementById('interpro-send-preview-form');
  document.addEventListener('click', async event => {
    const trigger = event.target.closest('[data-send-preview-mode]');
    if (!trigger) return;
    trigger.disabled = true;
    try {
      const response = await fetch(<?php echo json_encode(route('backend.interprovincial-trials.invitations.send-preview', $event), 512) ?>, {
        method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'},
        body: JSON.stringify({mode: trigger.dataset.sendPreviewMode, invitation_id: trigger.dataset.invitationId || null})
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Preview could not be loaded.');
      document.getElementById('interpro-preview-mode').value = data.mode;
      document.getElementById('interpro-preview-invitation').value = trigger.dataset.invitationId || '';
      document.getElementById('interpro-preview-token').value = data.request_token;
      document.getElementById('interpro-preview-hash').value = data.recipient_hash;
      document.getElementById('interpro-preview-subject').value = data.subject;
      document.getElementById('interpro-preview-body').value = data.body;
      document.getElementById('interpro-preview-from-address').value = data.from_address;
      document.getElementById('interpro-preview-from-name').value = data.from_name;
      document.getElementById('interpro-preview-reply-to').value = data.reply_to;
      document.getElementById('interpro-preview-sender-warning').textContent = data.sender_warning;
      document.getElementById('interpro-preview-confirm').classList.add('d-none');
      document.getElementById('interpro-rendered-preview').classList.add('d-none');
      document.getElementById('interpro-send-preview-recipients').innerHTML = data.recipients.map(row => `<div class="list-group-item"><strong>${escapeHtml(row.name)}</strong><div class="small text-muted">${escapeHtml(row.email)} · ${escapeHtml(row.category || '')} · ${escapeHtml(row.status)}</div></div>`).join('') || '<div class="text-muted">No eligible recipients.</div>';
      document.getElementById('interpro-send-preview-blockers').innerHTML = data.blockers.map(row => `<div class="list-group-item text-warning">${escapeHtml(row.name)} — ${escapeHtml(row.reason)}</div>`).join('');
      document.getElementById('interpro-send-preview-blockers-wrap').classList.toggle('d-none', data.blockers.length === 0);
      document.getElementById('interpro-preview-count').textContent = `${data.recipients.length} email${data.recipients.length === 1 ? '' : 's'} ready`;
      document.getElementById('interpro-preview-confirm').disabled = data.recipients.length === 0;
      previewModal.show();
    } catch (error) { AppFeedback.fromError(error, 'Preview could not be loaded.'); }
    finally { trigger.disabled = false; }
  });
  const escapeHtml = value => $('<div>').text(value ?? '').html();
  document.getElementById('interpro-preview-review')?.addEventListener('click', async () => {
    const payload = {mode:document.getElementById('interpro-preview-mode').value, invitation_id:document.getElementById('interpro-preview-invitation').value||null, subject:document.getElementById('interpro-preview-subject').value, body:document.getElementById('interpro-preview-body').value, from_address:document.getElementById('interpro-preview-from-address').value, from_name:document.getElementById('interpro-preview-from-name').value, reply_to:document.getElementById('interpro-preview-reply-to').value};
    try { const response=await fetch(<?php echo json_encode(route('backend.interprovincial-trials.invitations.send-preview', $event), 512) ?>,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json','Content-Type':'application/json'},body:JSON.stringify(payload)}); const data=await response.json(); if(!response.ok) throw new Error(data.message||Object.values(data.errors||{}).flat()[0]); document.getElementById('interpro-preview-token').value=data.request_token; document.getElementById('interpro-preview-hash').value=data.recipient_hash; document.getElementById('interpro-preview-composition-hash').value=data.composition_hash; document.getElementById('interpro-preview-proof').value=data.review_proof; document.getElementById('interpro-preview-expires').value=data.review_expires_at; document.getElementById('interpro-send-preview-recipients').innerHTML=data.recipients.map(row=>`<div class="list-group-item"><strong>${escapeHtml(row.name)}</strong><div class="small text-muted">${escapeHtml(row.email)} · ${escapeHtml(row.category||'')}</div></div>`).join('')||'<div class="text-muted">No eligible recipients.</div>'; document.getElementById('interpro-send-preview-blockers').innerHTML=data.blockers.map(row=>`<div class="list-group-item text-warning">${escapeHtml(row.name)} — ${escapeHtml(row.reason)}</div>`).join(''); document.getElementById('interpro-send-preview-blockers-wrap').classList.toggle('d-none',!data.blockers.length); document.getElementById('interpro-preview-count').textContent=`${data.recipients.length} email${data.recipients.length===1?'':'s'} ready`; document.getElementById('interpro-rendered-preview-body').innerHTML=data.rendered_body; document.getElementById('interpro-rendered-preview').classList.remove('d-none'); document.getElementById('interpro-preview-confirm').classList.remove('d-none'); } catch(error){ AppFeedback.fromError(error,'Email could not be reviewed.'); }
  });
  ['interpro-preview-subject','interpro-preview-body','interpro-preview-from-address','interpro-preview-from-name','interpro-preview-reply-to'].forEach(id => document.getElementById(id)?.addEventListener('input',()=>document.getElementById('interpro-preview-confirm').classList.add('d-none')));
  previewForm?.addEventListener('submit', async event => {
    event.preventDefault();
    const confirm = document.getElementById('interpro-preview-confirm');
    confirm.disabled = true;
    try {
      const payload = {
        mode: document.getElementById('interpro-preview-mode').value,
        invitation_id: document.getElementById('interpro-preview-invitation').value || null,
        request_token: document.getElementById('interpro-preview-token').value,
        recipient_hash: document.getElementById('interpro-preview-hash').value,
        composition_hash: document.getElementById('interpro-preview-composition-hash').value,
        review_proof: document.getElementById('interpro-preview-proof').value,
        review_expires_at: document.getElementById('interpro-preview-expires').value,
        subject: document.getElementById('interpro-preview-subject').value,
        body: document.getElementById('interpro-preview-body').value,
        from_address: document.getElementById('interpro-preview-from-address').value,
        from_name: document.getElementById('interpro-preview-from-name').value,
        reply_to: document.getElementById('interpro-preview-reply-to').value,
      };
      const response = await fetch(<?php echo json_encode(route('backend.interprovincial-trials.invitations.send-preview.queue', $event), 512) ?>, {
        method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'}, body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Emails could not be submitted for sending.');
      previewModal.hide(); AppFeedback.success(data.message); window.location.reload();
    } catch (error) { AppFeedback.fromError(error, 'Emails could not be submitted for sending.'); confirm.disabled = false; }
  });

  const showFeedback = (message, isError = false) => {
    feedback.textContent = message;
    feedback.className = `small ${isError ? 'text-danger' : 'text-success'}`;
  };

  const rebuildCategory = data => { window.location.reload(); };
  player.select2({
    width: '100%',
    placeholder: 'Search by player name or surname',
    allowClear: true,
    closeOnSelect: false,
    minimumInputLength: 2,
    language: {
      inputTooShort: () => 'Type at least two characters to search.',
      searching: () => 'Searching players…',
      noResults: () => 'No existing players found.'
    },
    ajax: {
      url: <?php echo json_encode(route('backend.interprovincial-trials.players.index', $event), 512) ?>,
      dataType: 'json',
      delay: 250,
      cache: true,
      data: params => ({ q: params.term, page: params.page || 1 }),
      processResults: data => data
    }
  });

  player.on('select2:select', function () {
    window.setTimeout(() => {
      const instance = player.data('select2');
      const searchFields = instance.$selection.add(instance.$dropdown).find('.select2-search__field');
      searchFields.val('').trigger('input');
      searchFields.filter(':visible').first().trigger('focus');
    }, 0);
  });

  const invalidateReview = () => {
    const selectedCount = (player.val() || []).length;
    document.getElementById('nomination-selected-count').textContent = `${selectedCount} player${selectedCount === 1 ? '' : 's'} selected`;
    document.getElementById('nomination-review').classList.add('d-none');
  };
  player.on('change', invalidateReview);
  document.getElementById('nomination-category').addEventListener('change', invalidateReview);
  $(form).on('submit', function (event) {
    event.preventDefault();
    const category = document.getElementById('nomination-category').selectedOptions[0];
    document.getElementById('nomination-review-category').textContent = category.textContent;
    document.getElementById('nomination-review-visibility').textContent = category.dataset.published === '1'
      ? 'Warning: this category is published. Saving will immediately make these names public. No emails will be sent.'
      : 'These nominations will be saved privately. No emails will be sent.';
    document.getElementById('nomination-review-names').innerHTML = player.select2('data').map(row => `<li>${escapeHtml(row.text)}</li>`).join('');
    document.getElementById('nomination-review').classList.remove('d-none');
  });
  document.getElementById('nomination-save').addEventListener('click', async function () {
    this.disabled = true;
    showFeedback('Adding nominations…');
    try {
      const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(form) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Nominations could not be added.'));
      rebuildCategory(data);
      player.val(null).trigger('change');
      showFeedback(data.message);
    } catch (error) {
      showFeedback(error.message || 'Nominations could not be added.', true);
    } finally { this.disabled = false; }
  });

  document.addEventListener('submit', async event => {
    const removeForm = event.target.closest('.nomination-remove-form');
    if (!removeForm || event.defaultPrevented) return;
    event.preventDefault();
    if (!confirm('Remove this nomination?')) return;
    showFeedback('Removing nomination…');
    try {
      const response = await fetch(removeForm.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(removeForm) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Nomination could not be removed.');
      rebuildCategory(data);
      showFeedback(data.message);
    } catch (error) {
      showFeedback(error.message || 'Nomination could not be removed.', true);
    }
  });
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\interprovincial-trials\invitations.blade.php ENDPATH**/ ?>