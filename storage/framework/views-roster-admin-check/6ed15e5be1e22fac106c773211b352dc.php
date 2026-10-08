<?php $__env->startSection('title', 'Interprovincial Trials nominations and invitations'); ?>
<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-style'); ?>
<style>
  .interpro-filter-bar { display:flex; flex-wrap:wrap; gap:.5rem; }
  .interpro-nomination-row { align-items:flex-start; border-top:1px solid #ebeaf0; display:grid; gap:.8rem; grid-template-columns:minmax(12rem,1.4fr) minmax(10rem,1fr) minmax(12rem,1.2fr) auto; padding:1rem 0; }
  .interpro-nomination-row:first-child { border-top:0; }
  .interpro-nomination-row__identity, .interpro-nomination-row__status, .interpro-nomination-row__timeline { min-width:0; }
  .interpro-nomination-row__status, .interpro-nomination-row__timeline { display:flex; flex-direction:column; gap:.25rem; }
  .interpro-nomination-row__actions { align-items:flex-end; display:flex; flex-direction:column; gap:.4rem; }
  .interpro-email { display:inline-block; max-width:100%; overflow-wrap:anywhere; word-break:break-word; }
  .interpro-row-action { min-height:44px; min-width:44px; }
  @media (max-width: 767.98px) {
    .interpro-nomination-row { grid-template-columns:1fr; }
    .interpro-nomination-row__actions { align-items:stretch; flex-direction:row; flex-wrap:wrap; }
    .interpro-nomination-row__actions form, .interpro-nomination-row__actions .interpro-row-action { width:100%; }
  }
</style>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <?php echo $__env->make('backend.event.partials.header', ['event' => $event], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h2 class="mb-1">Nominations &amp; invitations</h2><p class="text-muted mb-0">Nominate players, edit the message, and send all invitations in one step.</p></div>
    <a class="btn btn-outline-secondary" href="<?php echo e(route('admin.events.overview', $event)); ?>">Back to event overview</a>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?> <div class="alert alert-success"><?php echo e(session('success')); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?> <div class="alert alert-danger"><?php echo e($errors->first()); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
        <span class="badge <?php echo e($readiness['message_saved'] ? 'bg-label-success' : 'bg-label-info'); ?>"><?php echo e($readiness['message_saved'] ? 'Message stored' : 'Message ready to edit below'); ?></span>
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
  </section>

  <?php
    $publishedCategoryCount = $event->categoryEvents->where('nominations_published', true)->count();
    $publicationStatus = $publishedCategoryCount === 0 ? 'Private' : ($publishedCategoryCount === $event->categoryEvents->count() ? 'Published' : 'Mixed');
  ?>
  <div class="card mb-4"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div><strong>Public nomination list: <?php echo e($publicationStatus); ?></strong><div class="text-muted small">Publishing deliberately shows nominated player names on the public event page.</div></div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?php echo e(route('events.show', $event)); ?>" target="_blank" rel="noopener">View public page</a>
      <form method="POST" action="<?php echo e(route('backend.interprovincial-trials.nominations.publication', $event)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?><input type="hidden" name="published" value="<?php echo e($publicationStatus === 'Published' ? 0 : 1); ?>"><button class="btn <?php echo e($publicationStatus === 'Published' ? 'btn-outline-warning' : 'btn-outline-primary'); ?>" onclick="return confirm('<?php echo e($publicationStatus === 'Published' ? 'Make every nomination category private?' : 'Publish nominated player names in every category?'); ?>')"><?php echo e($publicationStatus === 'Published' ? 'Unpublish all names' : 'Publish all names'); ?></button></form>
    </div>
  </div></div>

  <div class="card mb-4"><div class="card-header"><h5 class="mb-0">1. Nominate an existing player</h5></div><div class="card-body">
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
            <option value="<?php echo e($categoryEvent->id); ?>"><?php echo e($categoryEvent->category?->name ?? 'Category'); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
      </div>
      <div class="col-lg-2 d-flex align-items-end">
        <button class="btn btn-primary w-100" type="submit" <?php echo e($event->categoryEvents->isEmpty() ? 'disabled' : ''); ?>>Add nominations</button>
      </div>
      <div class="col-12"><div id="nomination-feedback" class="small" role="status" aria-live="polite"></div></div>
    </form>
    <details class="mt-3" <?php if($errors->hasAny(['nominee_name', 'nominee_surname', 'nominee_email'])): ?> open <?php endif; ?>>
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
  </div></div>

  <div class="card mb-4"><div class="card-header"><h5 class="mb-0">2. Review nominations by category</h5></div><div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-3">
      <button type="button" class="btn btn-primary" data-send-preview-mode="new"><i class="ti ti-send me-1"></i>Send to newly nominated</button>
      <button type="button" class="btn btn-outline-primary" data-send-preview-mode="profileless">Send to players without profiles</button>
      <button type="button" class="btn btn-outline-primary" data-send-preview-mode="not_registered"><i class="ti ti-mail-forward me-1"></i>Send to players not registered</button>
    </div>
    <p class="text-muted small mb-2">Showing <?php echo e($nominations->firstItem() ?? 0); ?>–<?php echo e($nominations->lastItem() ?? 0); ?> of <?php echo e($nominations->total()); ?> nominations. Filters apply to this page.</p>
    <div class="interpro-filter-bar mb-3" aria-label="Filter nominated players on this page">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $nominationFilterGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <button type="button" class="btn btn-sm btn-outline-primary <?php echo e($filter['key'] === 'all' ? 'active' : ''); ?>" data-nomination-filter="<?php echo e($filter['key']); ?>" aria-pressed="<?php echo e($filter['key'] === 'all' ? 'true' : 'false'); ?>">
          <?php echo e($filter['label']); ?> <span class="badge bg-label-primary ms-1" data-filter-count="<?php echo e($filter['key']); ?>"><?php echo e($filter['count']); ?></span>
        </button>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div class="row g-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <?php ($categoryNominations = $nominations->getCollection()->where('category_event_id', $categoryEvent->id)); ?>
      <div class="col-12"><div class="border rounded p-3" data-nomination-category="<?php echo e($categoryEvent->id); ?>"><h6><?php echo e($categoryEvent->category?->name ?? 'Category'); ?> <span class="badge bg-label-primary" data-nomination-count><?php echo e($categoryEvent->nominations_count); ?></span></h6><div data-nomination-list>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $categoryNominations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nomination): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
        <?php echo $__env->make('backend.interprovincial-trials._nomination-row', [
          'presentation' => $nominationPresentations->get($nomination->id),
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><div class="text-muted" data-empty-nominations><?php echo e($categoryEvent->nominations_count ? 'No nominations from this category on this page.' : 'No players nominated yet.'); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="col-12"><div class="alert alert-warning mb-0">Add event categories before nominating players.</div></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nominations->hasPages()): ?><div class="mt-3"><?php echo e($nominations->links()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div></div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($historicalInvitations && $historicalInvitations->count()): ?>
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-1">Historical invitations no longer in current nominations</h5><p class="text-muted small mb-0">Audit history is retained. These players are not part of the current nomination list.</p></div>
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

  <div class="card overflow-hidden">
    <div class="card-header"><h5 class="mb-0">3. Send invitation emails</h5></div>
    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($batch): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><span><strong>Latest batch #<?php echo e($batch->id); ?></strong> <span class="badge bg-label-info"><?php echo e(ucfirst($batch->status)); ?></span></span><span class="text-muted small"><?php echo e($readiness['invitation_count']); ?> invitation<?php echo e($readiness['invitation_count'] === 1 ? '' : 's'); ?></span></div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p class="text-muted mb-0">Choose a send action above to preview the exact recipients, blockers, subject, and message before anything is queued through the managed mail service.</p>
    </div>

  </div>
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
      <div class="modal-footer"><span class="me-auto text-muted small" id="interpro-preview-count"></span><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-outline-primary" id="interpro-preview-review">Review rendered email</button><button type="submit" class="btn btn-primary d-none" id="interpro-preview-confirm">Queue reviewed emails</button></div>
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

  const refreshNominationFilters = () => {
    const rows = Array.from(document.querySelectorAll('[data-nomination-row]'));
    document.querySelectorAll('[data-filter-count]').forEach(counter => {
      const key = counter.dataset.filterCount;
      counter.textContent = key === 'all' ? rows.length : rows.filter(row => row.dataset.nominationState === key).length;
    });
  };

  document.querySelectorAll('[data-nomination-filter]').forEach(button => {
    button.addEventListener('click', function () {
      const selected = this.dataset.nominationFilter;
      document.querySelectorAll('[data-nomination-filter]').forEach(filter => {
        const active = filter === this;
        filter.setAttribute('aria-pressed', active ? 'true' : 'false');
        filter.classList.toggle('active', active);
      });
      document.querySelectorAll('[data-nomination-row]').forEach(row => {
        row.classList.toggle('d-none', selected !== 'all' && row.dataset.nominationState !== selected);
      });
    });
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
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Emails could not be queued.');
      previewModal.hide(); AppFeedback.success(data.message); window.location.reload();
    } catch (error) { AppFeedback.fromError(error, 'Emails could not be queued.'); confirm.disabled = false; }
  });

  const showFeedback = (message, isError = false) => {
    feedback.textContent = message;
    feedback.className = `small ${isError ? 'text-danger' : 'text-success'}`;
  };

  const rebuildCategory = data => {
    if ((data.pagination?.last_page || 1) > 1) {
      window.location.reload();
      return;
    }
    const card = document.querySelector(`[data-nomination-category="${data.category_event_id}"]`);
    if (!card) return;
    card.querySelector('[data-nomination-count]').textContent = data.count;
    const list = card.querySelector('[data-nomination-list]');
    list.innerHTML = data.html || '';
    if (!data.nominations.length) {
      const empty = document.createElement('div');
      empty.className = 'text-muted';
      empty.dataset.emptyNominations = '';
      empty.textContent = 'No players nominated yet.';
      list.appendChild(empty);
    } else {
      refreshNominationFilters();
    }
  };
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

  $(form).on('submit', async function (event) {
    event.preventDefault();
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
    }
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

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\interprovincial-trials\invitations.blade.php ENDPATH**/ ?>