
<div class="row individual-event-view">

  
  <div class="col-xl-8 col-lg-7 col-md-7">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($drawPublicationSummary['total'] ?? 0) > 0): ?>
      <div class="mb-4">
        <a href="#event-draws-match-times" class="btn btn-primary w-100">
          <i class="ti ti-tournament me-1" aria-hidden="true"></i>
          View draws &amp; match times
          <i class="ti ti-arrow-down ms-1" aria-hidden="true"></i>
        </a>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  </div>

  
  <div class="col-xl-4 col-lg-5 col-md-5">

    
    <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->results_published == 1): ?>
    <div class="card mb-4">
      <div class="card-header">
        <small class="text-uppercase">Results</small>
      </div>
      <div class="card-body">
        <a href="<?php echo e(route('events.results', $event->id)); ?>" class="btn bg-label-success btn-sm">
          <i class="ti ti-trophy me-1"></i> View Results
        </a>
      </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="card event-section-card mb-4">
      <div class="card-header d-flex justify-content-between">
        <div>
          <h5 class="mb-1">Event documents</h5>
          <p class="text-muted small mb-0">Downloads supplied by the organiser.</p>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->is_admin($event->id)->count() > 0 || auth()->id() == 584): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addFileModal">
              Upload PDF
            </button>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>

      <div class="card-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div class="event-document d-flex justify-content-between align-items-center gap-2 mb-2 file">
            <a class="d-flex align-items-center gap-2 text-break" href="<?php echo e(route('events.documents.show', [$event, $file])); ?>">
              <i class="ti ti-file-description fs-4 text-primary"></i>
              <span><?php echo e($file->name); ?></span>
            </a>

            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin')): ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->id() == $event->admin || auth()->id() == 584): ?>
                <button
                  class="btn btn-danger btn-sm deleteFileButton"
                  data-id="<?php echo e($file->id); ?>">
                  Delete
                </button>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="text-center py-3">
            <i class="ti ti-file-off fs-2 text-muted"></i>
            <p class="text-muted small mb-0 mt-2">No event documents are available yet.</p>
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>

    <?php echo $__env->make('frontend.event.partials.event-draws', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="mb-1">Players</h5>
        <p class="text-muted small mb-3">Only active entries are shown. Open a category to view its players.</p>
        <label class="visually-hidden" for="event-player-search">Search players</label>
        <input id="event-player-search" type="search" class="form-control mb-3"
               placeholder="Search by player name or category">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $activeRegistrations = $eventCategory->categoryEventRegistrations
              ->where('payment_status_id', 1)
              ->filter(fn($r) => !str_contains(strtolower($r->status ?? ''), 'withdrawn'));
          ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeRegistrations->isNotEmpty()): ?>
          <details class="event-category border rounded p-2 mb-3"
                   data-search="<?php echo e(strtolower($eventCategory->category->name.' '.$activeRegistrations->map(fn($r) => optional(optional($r->registration)->players->first())->full_name)->filter()->implode(' '))); ?>">
            <summary class="d-flex align-items-center justify-content-between gap-2" style="cursor:pointer">
            <span class="badge bg-label-primary">
              <?php echo e($eventCategory->category->name); ?>

              (<?php echo e($activeRegistrations->count()); ?>)
            </span>
            <span class="small text-muted">View players</span>
            </summary>

            <ul class="list-group list-group-flush mt-2">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activeRegistrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cereg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $registration = $cereg->registration;
                  $pivotStatus = strtolower($cereg->status ?? '');
                  $player = $registration ? $registration->players->first() : null;
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$registration || !$player): ?> <?php continue; ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span>
                    <?php echo e($player->name); ?>

                    <?php echo e($player->surname); ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $player->id,'context' => $eventCategory]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($eventCategory)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
                  </span>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->check() && (int)$cereg->user_id === (int)auth()->id() && !empty($canWithdraw) && $canWithdraw): ?>
                    <button type="button"
                            class="btn btn-xs btn-outline-warning move-category-btn"
                            title="Change category"
                            data-bs-toggle="modal"
                            data-bs-target="#moveCategoryModal"
                            data-entry-id="<?php echo e($cereg->id); ?>"
                            data-player="<?php echo e($player->name); ?> <?php echo e($player->surname); ?>"
                            data-current-category="<?php echo e($eventCategory->category->name); ?>"
                            data-current-category-id="<?php echo e($eventCategory->id); ?>">
                      <i class="ti ti-switch-horizontal me-1"></i> Change Category
                    </button>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </li>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
          </details>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var search = document.getElementById('event-player-search');
  if (!search) return;

  search.addEventListener('input', function () {
    var term = search.value.trim().toLowerCase();
    document.querySelectorAll('.event-category[data-search]').forEach(function (category) {
      var match = !term || category.dataset.search.includes(term);
      category.hidden = !match;
      if (term && match) category.open = true;
    });
  });
});
</script>
<div class="modal fade" id="addFileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Upload Document</h5>
        <button type="button"
                class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>

      <form method="POST"
            action="<?php echo e(route('file.store')); ?>"
            enctype="multipart/form-data">

        <?php echo csrf_field(); ?>

        <div class="modal-body">

          
          <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">

          <div class="mb-3">
            <label class="form-label">Select file</label>
            <input type="file"
                   name="myFile"
                   class="form-control"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                   required>
            <small class="text-muted">
              Allowed: PDF, Word, Excel (max 5MB)
            </small>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button"
                  class="btn btn-secondary"
                  data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit"
                  class="btn btn-success">
            Upload
          </button>
        </div>

      </form>

    </div>
  </div>
</div>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
<div class="modal fade" id="withdrawalDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Withdrawal Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <h6 id="wd-player" class="fw-semibold mb-3"></h6>

        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <small class="text-muted d-block">Withdrawn</small>
            <span id="wd-date"></span>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Refund Method</small>
            <span id="wd-method"></span>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Refund Status</small>
            <span id="wd-status"></span>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Amount</small>
            <span id="wd-gross"></span>
            <small class="text-muted" id="wd-net-wrap"> (net: <span id="wd-net"></span>)</small>
          </li>
          <li class="mb-2">
            <small class="text-muted d-block">Refunded On</small>
            <span id="wd-refunded-at"></span>
          </li>
        </ul>
      </div>

      <div class="modal-footer flex-column gap-2">
        <a id="wd-wallet-link" href="#" class="btn btn-outline-success btn-sm w-100" style="display:none;">
          <i class="ti ti-wallet me-1"></i> View Wallet Transactions
        </a>
        <a id="wd-inquiry-link" href="#" class="btn btn-outline-primary btn-sm w-100">
          <i class="ti ti-mail me-1"></i> Send Inquiry to Support
        </a>
        <button type="button" class="btn btn-secondary btn-sm w-100" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('withdrawalDetailsModal');
  if (!modal) return;

  var baseUrl = <?php echo json_encode(url('/'), 15, 512) ?>;

  modal.addEventListener('show.bs.modal', function (e) {
    // Always reset wallet link first
    var walletLink = document.getElementById('wd-wallet-link');
    walletLink.style.display = 'none';
    walletLink.href = '#';

    var btn = e.relatedTarget;
    if (!btn) return;

    var d = btn.dataset;

    document.getElementById('wd-player').textContent      = d.player       || '';
    document.getElementById('wd-date').textContent         = d.withdrawnAt  || '—';
    document.getElementById('wd-method').textContent       = d.method       || 'None';
    document.getElementById('wd-gross').textContent        = d.gross        || '—';
    document.getElementById('wd-net').textContent          = d.net          || '—';
    document.getElementById('wd-refunded-at').textContent  = d.refundedAt   || '—';

    // Status badge color
    var statusEl = document.getElementById('wd-status');
    var st = (d.refundStatus || 'n/a').toLowerCase();
    var cls = 'bg-label-secondary';
    if (st === 'completed') cls = 'bg-label-success';
    else if (st === 'pending')   cls = 'bg-label-warning';
    statusEl.innerHTML = '<span class="badge ' + cls + '">' + (d.refundStatus || 'N/A') + '</span>';

    // Hide net if no value
    document.getElementById('wd-net-wrap').style.display = (d.net && d.net !== '—') ? '' : 'none';

    // Wallet link — only show when refund method is explicitly 'wallet'
    if (d.showWallet === '1' && d.userId) {
      walletLink.href = baseUrl + '/backend/wallet/' + d.userId;
      walletLink.style.display = '';
    }

    // Inquiry mailto
    var supportEmail = 'support@capetennis.co.za';
    var subject = encodeURIComponent('Withdrawal Inquiry – ' + (d.eventName || '') + ' (Ref #' + (d.regId || '') + ')');
    var body    = encodeURIComponent(
      'Hi,\n\nI would like to enquire about my withdrawal:\n\n'
      + 'Event: ' + (d.eventName || '') + '\n'
      + 'Player: ' + (d.player || '') + '\n'
      + 'Registration Ref: #' + (d.regId || '') + '\n'
      + 'Withdrawn on: ' + (d.withdrawnAt || '') + '\n'
      + 'Refund method: ' + (d.method || '') + '\n'
      + 'Refund status: ' + (d.refundStatus || '') + '\n\n'
      + 'Please advise.\n\nThank you.'
    );
    document.getElementById('wd-inquiry-link').href = 'mailto:' + supportEmail + '?subject=' + subject + '&body=' + body;
  });
});
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
<div class="modal fade" id="moveCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-switch-horizontal me-1"></i> Change Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <p class="mb-1"><strong id="mc-player"></strong></p>
        <p class="text-muted small mb-3">Current: <span id="mc-current-cat" class="badge bg-label-primary"></span></p>

        <label for="mc-new-category" class="form-label">Move to</label>
        <select id="mc-new-category" class="form-select" style="width:100%">
          <option value="">Select category…</option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($ec->id); ?>"><?php echo e($ec->category->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning btn-sm" id="mc-submit-btn">
          <i class="ti ti-switch-horizontal me-1"></i> Move
        </button>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var mcModal = document.getElementById('moveCategoryModal');
  if (!mcModal) return;

  var mcBaseUrl = <?php echo json_encode(url('/'), 15, 512) ?>;
  var mcEntryId = null;
  var mcCurrentCatId = null;

  // Init Select2 when modal opens
  $(mcModal).on('shown.bs.modal', function () {
    $('#mc-new-category').select2({
      dropdownParent: $(mcModal),
      placeholder: 'Select category…',
      width: '100%',
      allowClear: true
    });
  });

  mcModal.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    if (!btn) return;

    var d = btn.dataset;
    mcEntryId = d.entryId;
    mcCurrentCatId = d.currentCategoryId;

    document.getElementById('mc-player').textContent = d.player || '';
    document.getElementById('mc-current-cat').textContent = d.currentCategory || '';

    // Reset select and hide current category option
    var $sel = $('#mc-new-category');
    $sel.val('').trigger('change');
    $sel.find('option').each(function () {
      $(this).prop('disabled', $(this).val() === mcCurrentCatId);
    });
  });

  // Close cleanup
  $(mcModal).on('hidden.bs.modal', function () {
    if ($('#mc-new-category').data('select2')) {
      $('#mc-new-category').select2('destroy');
    }
  });

  document.getElementById('mc-submit-btn').addEventListener('click', function () {
    var newCatId = $('#mc-new-category').val();
    var newCatText = $('#mc-new-category option:selected').text().trim();

    if (!newCatId) {
      toastr.warning('Please select a category');
      return;
    }

    // Close the modal first
    var modalInstance = bootstrap.Modal.getInstance(mcModal);
    if (modalInstance) modalInstance.hide();

    // SweetAlert "Are you sure?" confirmation
    Swal.fire({
      title: 'Change Category?',
      html: 'Move player to <strong>' + newCatText + '</strong>?<br><small class="text-muted">The player will be notified by email.</small>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, move',
      cancelButtonText: 'Cancel',
      customClass: {
        confirmButton: 'btn btn-warning me-2',
        cancelButton: 'btn btn-secondary'
      },
      buttonsStyling: false
    }).then(function (confirmResult) {
      if (!confirmResult.isConfirmed) return;

      Swal.fire({
        title: 'Moving player…',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
      });

      var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

      fetch(mcBaseUrl + '/registrations/' + mcEntryId + '/move-category', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ new_category_event_id: newCatId })
      })
      .then(function (res) {
        return res.json().then(function (body) { return { ok: res.ok, data: body }; });
      })
      .then(function (response) {
        Swal.close();

        if (response.ok && response.data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Category Changed',
            text: response.data.message,
            timer: 2000,
            showConfirmButton: false
          }).then(function () {
            location.reload();
          });
        } else {
          toastr.error(response.data.message || 'Move failed');
        }
      })
      .catch(function (err) {
        Swal.close();
        console.error('Category move error:', err);
        toastr.error('Something went wrong');
      });
    });
  });
});
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\eventTypes\individual.blade.php ENDPATH**/ ?>