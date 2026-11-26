<?php $__env->startSection('title', 'Event Clothing Setup'); ?>

<?php $__env->startSection('content'); ?>
<?php
  $selectedRegions = $visibleRegions->filter(fn($region) => $region->usesOnlineClothingOrders());
  $openRegions = $selectedRegions->filter(fn($region) => (bool) $region->clothing_order);
  $readyRegions = $selectedRegions->filter(fn($region) => $region->clothingItems->isNotEmpty());
?>

<div class="container-xxl py-4 clothing-setup">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <div class="text-uppercase text-muted small fw-semibold mb-1"><?php echo e($isEventManager ? 'Event administration' : 'Regional administration'); ?></div>
      <h3 class="mb-1">Clothing orders</h3>
      <p class="text-muted mb-0"><?php echo e($event->name); ?></p>
    </div>
    <a href="<?php echo e($isEventManager ? route('admin.events.overview', $event) : route('backend.team-selection.index', $event)); ?>" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Back to event</a>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success d-flex align-items-center gap-2" role="status"><i class="ti ti-circle-check fs-5"></i><span><?php echo e(session('success')); ?></span></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="card mb-4">
    <div class="card-body py-3">
      <div class="row g-3 align-items-center">
        <div class="col-12 col-lg">
          <h5 class="mb-1">Orders overview</h5>
          <p class="text-muted small mb-0">Track paid orders and ordering status by region. Your saved setup stays in place until you edit it.</p>
        </div>
        <div class="col-12 col-lg-auto">
          <div class="d-flex flex-wrap gap-2 clothing-summary" aria-label="Clothing setup summary">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager): ?><span class="badge bg-label-primary"><strong><?php echo e($selectedRegions->count()); ?></strong> of <?php echo e($visibleRegions->count()); ?> regions selected</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <span class="badge bg-label-info"><strong><?php echo e($readyRegions->count()); ?></strong> catalogues prepared</span>
            <span class="badge bg-label-success"><strong><?php echo e($openRegions->count()); ?></strong> ordering open</span>
            <span class="badge bg-label-secondary"><strong><?php echo e($selectedRegions->count() - $openRegions->count()); ?></strong> ordering closed</span>
            <span class="badge bg-label-primary"><strong><?php echo e($regionalOrders->sum('paid_count')); ?></strong> paid orders</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager): ?>
  <div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h5 class="mb-1">Participating regions</h5>
        <p class="text-muted mb-0"><?php echo e($selectedRegions->isEmpty() ? 'No regions selected yet.' : $selectedRegions->pluck('region_name')->join(', ')); ?></p>
      </div>
      <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#clothing-region-editor" aria-expanded="<?php echo e($errors->any() ? 'true' : 'false'); ?>" aria-controls="clothing-region-editor"><i class="ti ti-edit me-1"></i>Edit regions</button>
    </div>
  </div>
  <div class="collapse <?php echo e($errors->any() ? 'show' : ''); ?>" id="clothing-region-editor">
  <form method="POST" action="<?php echo e(route('backend.event.clothing.regions.update', $event)); ?>" class="card mb-4">
    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
    <div class="card-header d-flex gap-3 align-items-start">
      <div><h5 class="mb-1">Choose regions</h5><p class="text-muted small mb-0">Select the player areas that should offer online clothing orders.</p></div>
    </div>
    <div class="card-body">
      <div class="row g-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $visibleRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="col-12 col-md-6 col-xl-4">
            <label class="region-choice d-flex align-items-start gap-2 border rounded p-3 h-100" for="online-clothing-region-<?php echo e($region->id); ?>">
              <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="region_ids[]" value="<?php echo e($region->id); ?>" id="online-clothing-region-<?php echo e($region->id); ?>" <?php if($errors->any() ? in_array($region->id, old('region_ids', [])) : $region->usesOnlineClothingOrders()): echo 'checked'; endif; ?>>
              <span class="fw-semibold"><?php echo e($region->region_name); ?></span>
            </label>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <p class="text-muted small mt-3 mb-0"><i class="ti ti-info-circle me-1"></i>Deselecting a region hides ordering from its player area. Existing catalogues and paid orders are kept.</p>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <a href="<?php echo e(route('backend.event.clothing.index', $event)); ?>" class="btn btn-outline-secondary me-2">Cancel</a>
      <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save region selection</button>
    </div>
  </form>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <section aria-labelledby="catalogue-heading">
    <div class="d-flex gap-3 align-items-start mb-3 px-1">
      <div><h5 class="mb-1" id="catalogue-heading">Orders by region</h5><p class="text-muted small mb-0">Paid amounts show original receipts, including payment fees, before refunds. Pending orders are awaiting payment.</p></div>
    </div>

    <div class="accordion clothing-regions" id="clothing-regions">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $visibleRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($items = $region->clothingItems); ?>
        <?php ($usesOnlineClothing = $region->usesOnlineClothingOrders()); ?>
        <?php ($recommended = $recommendedSources->get($region->id)); ?>
        <?php ($sizeCount = $items->sum(fn($item) => $item->sizes->count())); ?>
        <?php ($notReady = $items->filter(fn($item) => (float)$item->price <= 0 || $item->sizes->isEmpty())); ?>
        <?php ($statusType = !$usesOnlineClothing ? 'secondary' : ($region->clothing_order ? 'success' : ($items->isNotEmpty() ? 'warning' : 'secondary'))); ?>
        <?php ($statusLabel = !$usesOnlineClothing ? 'Not selected · ordering closed' : ($region->clothing_order ? 'Ordering open' : 'Ordering closed')); ?>
        <?php ($orders = $regionalOrders->get($region->id)); ?>

        <div class="accordion-item border rounded mb-3 overflow-hidden">
          <div class="p-3 border-bottom">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
              <div><h5 class="mb-1"><?php echo e($region->region_name); ?></h5><span class="badge bg-label-<?php echo e($statusType); ?>"><?php echo e($statusLabel); ?></span></div>
              <a class="btn btn-outline-primary btn-sm" href="<?php echo e(route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $event->id])); ?>">View paid orders<i class="ti ti-arrow-right ms-1"></i></a>
            </div>
            <dl class="row g-3 mb-0 regional-order-metrics">
              <div class="col-6 col-lg-3"><dt class="small text-muted fw-normal">Paid orders</dt><dd class="fs-5 fw-semibold mb-0"><?php echo e((int) ($orders?->paid_count ?? 0)); ?></dd></div>
              <div class="col-6 col-lg-3"><dt class="small text-muted fw-normal">Original paid amount</dt><dd class="fs-5 fw-semibold mb-0">R<?php echo e(number_format((float) ($orders?->paid_amount ?? 0), 2)); ?></dd></div>
              <div class="col-6 col-lg-3"><dt class="small text-muted fw-normal">Awaiting payment</dt><dd class="fs-5 fw-semibold mb-0"><?php echo e((int) ($orders?->pending_count ?? 0)); ?></dd></div>
              <div class="col-6 col-lg-3"><dt class="small text-muted fw-normal">Cancelled orders</dt><dd class="fs-5 fw-semibold mb-0"><?php echo e((int) ($orders?->cancelled_count ?? 0)); ?></dd></div>
            </dl>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((float) ($regionalRefunds->get($region->id)?->refunded_amount ?? 0) > 0): ?>
              <p class="small text-muted mb-0 mt-3">Completed refunds paid back: R<?php echo e(number_format((float) $regionalRefunds->get($region->id)->refunded_amount, 2)); ?>. Original paid orders remain in the history.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <h2 class="accordion-header" id="clothing-region-heading-<?php echo e($region->id); ?>">
            <button class="accordion-button collapsed gap-3" type="button" data-bs-toggle="collapse" data-bs-target="#clothing-region-<?php echo e($region->id); ?>" aria-expanded="false" aria-controls="clothing-region-<?php echo e($region->id); ?>">
              <span class="flex-grow-1 text-start">
                <span class="d-block fw-semibold text-body mb-1"><?php echo e($usesOnlineClothing && $isEventManager ? 'Edit catalogue & ordering' : 'Catalogue details'); ?></span>
                <span class="d-block text-muted fw-normal small"><?php echo e($items->count()); ?> <?php echo e(Str::plural('item', $items->count())); ?> · <?php echo e($sizeCount); ?> size <?php echo e(Str::plural('option', $sizeCount)); ?><?php echo e($usesOnlineClothing && $items->isEmpty() ? ' · Needs setup' : ''); ?></span>
              </span>
            </button>
          </h2>
          <div id="clothing-region-<?php echo e($region->id); ?>" class="accordion-collapse collapse" aria-labelledby="clothing-region-heading-<?php echo e($region->id); ?>" data-bs-parent="#clothing-regions">
            <div class="accordion-body border-top">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$usesOnlineClothing): ?>
                <div class="alert alert-light border mb-3"><i class="ti ti-info-circle me-1"></i><?php echo e($isEventManager ? 'Use Edit regions to select this region before setting up or opening its catalogue.' : 'Ask the event administrator to select this region before setting up or opening its catalogue.'); ?></div>
              <?php elseif($items->isNotEmpty()): ?>
                <div class="table-responsive mb-3">
                  <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Order</th><th>Item</th><th>Price</th><th>Sizes</th></tr></thead>
                    <tbody>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $items->sortBy([['ordering','asc'],['item_type_name','asc']]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr><td><?php echo e($item->ordering ?? '—'); ?></td><td><?php echo e($item->item_type_name); ?></td><td>R<?php echo e(number_format((float)$item->price, 0)); ?></td><td><?php echo e($item->sizes->count()); ?></td></tr>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                  </table>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($notReady->isNotEmpty()): ?>
                  <div class="alert alert-warning py-2"><i class="ti ti-alert-triangle me-1"></i><?php echo e($notReady->count()); ?> <?php echo e(Str::plural('item', $notReady->count())); ?> still need a positive price or at least one size before ordering can open.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php elseif($recommended): ?>
                <div class="alert alert-light border">Recommended previous setup: <strong><?php echo e($recommended->region_name); ?></strong> · <?php echo e($recommended->clothing_items_count); ?> items.</div>
              <?php else: ?>
                <div class="alert alert-light border">No matching previous clothing catalogue was found. Choose any saved region catalogue on the management page or create items manually.</div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              <div class="d-flex flex-wrap gap-2 region-actions">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesOnlineClothing && auth()->user()->can('region-clothing.manage', $region)): ?>
                  <a class="btn btn-primary" href="<?php echo e(route('backend.region.clothing.edit', array_filter(['region' => $region->id, 'source_region' => $recommended?->id, 'event_id' => $event->id]))); ?>"><i class="ti ti-settings me-1"></i><?php echo e($items->isEmpty() ? 'Set up clothing' : 'Manage clothing & prices'); ?></a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $event->id])); ?>"><i class="ti ti-list me-1"></i>Paid orders</a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesOnlineClothing && $items->isNotEmpty() && auth()->user()->can('region-clothing.manage', $region)): ?>
                  <form method="POST" action="<?php echo e(route('backend.region.clothing.toggle', $region)); ?>">
                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                    <button class="btn btn-<?php echo e($region->clothing_order ? 'outline-danger' : 'success'); ?>" <?php if(!$region->clothing_order && $notReady->isNotEmpty()): echo 'disabled'; endif; ?>><i class="ti ti-<?php echo e($region->clothing_order ? 'lock' : 'shopping-cart'); ?> me-1"></i><?php echo e($region->clothing_order ? 'Close ordering' : 'Open ordering'); ?></button>
                  </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->denies('region-clothing.manage', $region)): ?>
                <p class="text-muted small mt-3 mb-0">The clothing catalogue and ordering settings are shared across events. Ask an event administrator to change them.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .clothing-setup { max-width: 1440px; }
  .setup-step { align-items: center; background: var(--bs-primary); border-radius: 50%; color: #fff; display: inline-flex; flex: 0 0 2rem; font-size: .875rem; font-weight: 700; height: 2rem; justify-content: center; }
  .clothing-summary .badge { font-size: .8125rem; padding: .55rem .7rem; }
  .region-choice { cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
  .region-choice:has(input:checked) { background: rgba(var(--bs-primary-rgb), .06); border-color: var(--bs-primary) !important; }
  .region-choice:hover { border-color: var(--bs-primary) !important; }
  .clothing-regions .accordion-button { box-shadow: none; min-height: 5rem; padding: 1rem 1.25rem; }
  .clothing-regions .accordion-button:not(.collapsed) { background: rgba(var(--bs-primary-rgb), .04); }
  .clothing-regions .accordion-button::after { margin-left: .5rem; }
  @media (max-width: 575.98px) {
    .clothing-setup { padding-top: 1.25rem !important; }
    .clothing-summary { display: grid !important; grid-template-columns: 1fr; width: 100%; }
    .clothing-summary .badge { text-align: left; }
    .clothing-regions .accordion-button { align-items: flex-start; flex-wrap: wrap; padding: 1rem; }
    .clothing-regions .accordion-button .badge { order: 2; }
    .clothing-regions .accordion-button::after { margin-left: auto; margin-top: .25rem; }
    .clothing-regions .accordion-body { padding: 1rem; }
    .region-actions, .region-actions form, .region-actions .btn { width: 100%; }
  }
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\event-setup.blade.php ENDPATH**/ ?>