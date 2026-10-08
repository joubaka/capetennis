<?php $__env->startSection('title', 'Engine — ' . $draw->drawName); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sortablejs/sortable.js')); ?>"></script>
<script>
$(function () {
  const csrf = $('meta[name="csrf-token"]').attr('content');
  const drawId = <?php echo json_encode($draw->id, 15, 512) ?>;
  const categoryId = <?php echo json_encode($draw->category_event_id, 15, 512) ?>;

  function request(url, data, method = 'POST') {
    return $.ajax({ url, method, data: Object.assign({_token: csrf}, data) });
  }

  function refreshCounts() {
    $('#eligible-count').text($('#eligible-players .draggable-player:visible').length);
    $('#assigned-count').text($('#assigned-players .draggable-player').length);
  }

  $('#player-search').on('input', function () {
    const query = this.value.toLowerCase();
    $('#eligible-players .draggable-player').each(function () {
      $(this).toggle($(this).text().toLowerCase().includes(query));
    });
  });

  $('#add-selected-players').on('click', function () {
    const ids = $('#eligible-players input[name="registration_ids[]"]:checked').map(function () { return this.value; }).get();
    if (!ids.length) return toastr.warning('Select at least one player.');
    request("<?php echo e(route('admin.draws.addPlayerToDraw')); ?>", {draw_id: drawId, player_ids: ids})
      .done(function (res) { toastr.success(res.message); window.location.reload(); })
      .fail(function (xhr) { toastr.error(xhr.responseJSON?.message || 'Could not add players.'); });
  });

  $('#add-all-players').on('click', function () {
    if (!confirm('Add all eligible players from this category to the draw?')) return;
    request("<?php echo e(route('admin.draws.addCategoryPlayers')); ?>", {draw_id: drawId, category_id: categoryId})
      .done(function (res) { toastr.success(res.message); window.location.reload(); })
      .fail(function (xhr) { toastr.error(xhr.responseJSON?.message || 'Could not add category players.'); });
  });

  $(document).on('click', '.remove-player', function () {
    const row = $(this).closest('.draggable-player');
    request("<?php echo e(route('admin.draws.removePlayer')); ?>", {draw_id: drawId, registration_id: row.data('player-id')}, 'DELETE')
      .done(function (res) { row.remove(); refreshCounts(); toastr.success(res.message); })
      .fail(function (xhr) { toastr.error(xhr.responseJSON?.message || 'Could not remove player.'); });
  });

  function saveAssigned() {
    const players = [];
    $('#assigned-players .draggable-player').each(function () {
      players.push($(this).data('player-id'));
    });
    $.post("<?php echo e(route('draws.players.update', $draw->id)); ?>", {
      _token: csrf,
      players: players
    }).done(function (res) {
      toastr.success(res.message || 'Players updated.');
    }).fail(function () {
      toastr.error('Failed to update players.');
    });
  }

  if (typeof Sortable !== 'undefined') {
    ['eligible-players', 'assigned-players'].forEach(function (id) {
      Sortable.create(document.getElementById(id), {
        group: 'players',
        animation: 150,
        onEnd: saveAssigned
      });
    });
  }
});
</script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $draw->isTeamDraw()): ?>
  <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
    <span>Choose how the draw starts, then set up groups or place players directly in a bracket.</span>
    <a class="btn btn-primary btn-sm" href="<?php echo e(route('draw.setup.show', $draw)); ?>">Choose draw format</a>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->usesFlexibleMonrad()): ?><a class="btn btn-outline-primary btn-sm" href="<?php echo e(route('flexible-monrad.show', $draw)); ?>">Open draw editor</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1">
        <i class="ti ti-adjustments me-1 text-danger"></i> Engine — <?php echo e($draw->drawName); ?>

      </h4>
      <small class="text-muted"><?php echo e(optional(optional($draw->categoryEvent)->category)->name ?? ''); ?></small>
    </div>
    <a href="<?php echo e(route('category.manage', $draw->category_event_id)); ?>" class="btn btn-label-secondary">
      <i class="ti ti-arrow-left me-1"></i> Back
    </a>
  </div>

  <ul class="nav nav-tabs mb-4">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#settings">
        <i class="ti ti-settings me-1"></i> Settings
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#players">
        <i class="ti ti-users me-1"></i> Players
      </a>
    </li>
  </ul>

  <div class="tab-content">

    
    <div class="tab-pane fade show active" id="settings">
      <div class="card">
        <div class="card-body" style="max-width: 500px;">
          <form method="POST" action="<?php echo e(route('backend.draw.update-settings', $draw->id)); ?>">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
              <label class="form-label fw-bold">Draw Name</label>
              <input type="text" class="form-control" name="name" value="<?php echo e($draw->drawName); ?>" required>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $draw->usesFlexibleMonrad()): ?>
            <div class="mb-3">
              <label class="form-label fw-bold">Draw Type</label>
              <select class="form-select" name="draw_type">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $drawTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drawType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($drawType->id); ?>" <?php echo e((string) ($draw->drawType_id ?? '') === (string) $drawType->id ? 'selected' : ''); ?>>
                    <?php echo e($drawType->drawTypeName); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
            </div>
            <div class="mb-4">
              <label class="form-label fw-bold">Sets</label>
              <input type="number" name="num_sets" value="<?php echo e(optional($draw->settings)->num_sets ?? 3); ?>" min="1" max="5" class="form-control">
            </div>
            <?php else: ?>
              <p class="text-muted">Starting positions and results are managed in the Monrad editor.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button type="submit" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i> Save Settings
            </button>
          </form>
        </div>
      </div>
    </div>

    
    <div class="tab-pane fade" id="players">
      <div class="row g-4">
        <div class="col-12 col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <h6 class="mb-0"><i class="ti ti-list me-1 text-primary"></i> Eligible Players</h6>
              <span id="eligible-count" class="badge bg-label-primary"><?php echo e($eligibleRegistrations->count()); ?></span>
              <input id="player-search" type="search" class="form-control form-control-sm ms-auto" style="max-width: 180px" placeholder="Search players">
              <button id="add-selected-players" type="button" class="btn btn-sm btn-primary">Add selected</button>
              <button id="add-all-players" type="button" class="btn btn-sm btn-outline-primary">Add all</button>
            </div>
            <div class="card-body p-2">
              <ul id="eligible-players" style="min-height: 60px; list-style: none; padding: 0; margin: 0;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eligibleRegistrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                  <li class="list-group-item list-group-item-action draggable-player mb-1 rounded d-flex align-items-center gap-2" data-player-id="<?php echo e($reg->id); ?>" style="cursor: grab;">
                    <input type="checkbox" name="registration_ids[]" value="<?php echo e($reg->id); ?>" aria-label="Select <?php echo e($reg->players->first()->full_name ?? 'player'); ?>">
                    <i class="ti ti-grip-vertical me-2 text-muted"></i><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $reg->players,'context' => $draw,'fallback' => '—']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reg->players),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'fallback' => '—']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
                  </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                  <li class="list-group-item text-muted text-center">No eligible players</li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
              <h6 class="mb-0"><i class="ti ti-tournament me-1 text-success"></i> Assigned to Draw</h6>
              <span id="assigned-count" class="badge bg-label-success"><?php echo e($draw->registrations->count()); ?></span>
            </div>
            <div class="card-body p-2">
              <ul id="assigned-players" style="min-height: 60px; list-style: none; padding: 0; margin: 0;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draw->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                  <li class="list-group-item list-group-item-action draggable-player mb-1 rounded d-flex align-items-center gap-2" data-player-id="<?php echo e($reg->id); ?>" style="cursor: grab;">
                    <i class="ti ti-grip-vertical me-2 text-muted"></i><?php if (isset($component)) { $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-name','data' => ['players' => $reg->players,'context' => $draw,'fallback' => '—']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-name'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['players' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reg->players),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw),'fallback' => '—']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $attributes = $__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__attributesOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f)): ?>
<?php $component = $__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f; ?>
<?php unset($__componentOriginal70896789f72c5410f4c2dd8c28f2cc2f); ?>
<?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto remove-player">Remove</button>
                  </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                  <li class="list-group-item text-muted text-center">No players assigned yet</li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\manage.blade.php ENDPATH**/ ?>