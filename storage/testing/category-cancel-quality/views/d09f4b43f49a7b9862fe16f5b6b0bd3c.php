
<?php $__env->startSection('title', 'Clothing Orders — ' . ($region->region_name ?? 'Region')); ?>
<?php $__env->startSection('content'); ?>
<?php
  $clothingFinancials = $clothingFinancials ?? [
    'received' => round($clothings->sum(fn ($order) => (float) ($order->amount_paid ?? $order->total)), 2),
    'payfast_fees' => round($clothings->sum(fn ($order) => (float) $order->payfast_fee), 2),
    'net' => round($clothings->sum(fn ($order) => (float) ($order->amount_paid ?? $order->total) - (float) $order->payfast_fee), 2),
  ];
?>
<div class="card mb-4 clothing-orders-admin" <?php if(!($clothingEvent ?? null)): ?> data-super-admin-page <?php endif; ?>>
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingEvent ?? null): ?><p class="text-muted mb-1"><?php echo e($clothingEvent->name); ?></p><?php else: ?><p class="text-muted mb-1">All events in this region</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><h5 class="mb-0 text-uppercase">Clothing Orders — <?php echo e($region->region_name ?? ''); ?></h5></div>
    <div class="btn-group mt-2 mt-md-0">
      <a href="<?php echo e(auth()->user()->can('region-clothing.manage', $region) ? route('backend.region.clothing.edit', array_filter(['region' => $region, 'event_id' => request()->integer('event_id') ?: null])) : route('backend.event.clothing.index', $clothingEvent)); ?>" class="btn btn-sm btn-outline-secondary">Back to clothing</a>
      <a href="<?php echo e(route('export.pdf.clothing.order', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null]))); ?>" download="clothing_orders.pdf" data-clothing-download="PDF" class="btn btn-sm btn-outline-danger" <?php if(!($clothingEvent ?? null)): ?> data-super-admin-only <?php endif; ?>>
        <i class="ti ti-file-text"></i> Print / PDF
      </a>
      <a href="<?php echo e(route('export.excel.clothing', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null]))); ?>" download="clothing_orders.xlsx" data-clothing-download="Excel" class="btn btn-sm btn-outline-success" <?php if(!($clothingEvent ?? null)): ?> data-super-admin-only <?php endif; ?>>
        <i class="ti ti-file-spreadsheet"></i> Excel
      </a>
    </div>
  </div>
  <div class="card-body">
    <div class="alert alert-info d-flex align-items-center gap-2" id="clothing-download-status" role="status" aria-live="polite" hidden><span class="spinner-border spinner-border-sm" aria-hidden="true" hidden></span><span data-download-message></span></div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p class="text-muted"><?php echo e($clothings->count()); ?> paid orders · <?php echo e($clothings->sum(fn ($order) => $order->items->count())); ?> item lines. Downloads include all paid orders in the current region and event scope.</p>
    <div class="row g-2 mb-3">
      <div class="col-md-8"><label class="form-label" for="clothing-order-search">Find a player, team, item or size</label><input type="search" class="form-control" id="clothing-order-search" placeholder="Search these orders"></div>
      <div class="col-md-4"><label class="form-label" for="clothing-team-filter">Team</label><select class="form-select" id="clothing-team-filter"><option value="">All teams in <?php echo e($region->region_name); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothings->pluck('team')->filter()->unique('id')->sortBy('name'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($team->id); ?>"><?php echo e($team->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
    </div>
    <p class="small text-muted" id="clothing-order-count" role="status" aria-live="polite"></p>
    <div class="alert alert-light border" id="clothing-order-empty" hidden>No item lines match these filters.</div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingEvent): ?>
      <p class="small">Clothing deadline: <?php echo e($clothingDeadline?->timezone('Africa/Johannesburg')->format('d M Y H:i') ?? 'Not set — only a super-user can refund'); ?> (South Africa). Scoped administrators can refund before this deadline; afterward only a super-user can refund.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user')): ?>
      <details class="mb-3" data-super-admin-only><summary>Set clothing deadline</summary><form method="POST" action="<?php echo e(route('backend.clothing.deadline', [$clothingEvent, $region])); ?>"><?php echo csrf_field(); ?>
        <label for="clothing-deadline" class="form-label">Clothing deadline (South Africa)</label><input type="datetime-local" class="form-control mb-2" id="clothing-deadline" name="clothing_deadline_at" value="<?php echo e($clothingDeadline?->timezone('Africa/Johannesburg')->format('Y-m-d\TH:i')); ?>"><button class="btn btn-outline-primary">Save deadline</button>
      </form></details>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="GET" class="mb-3" id="clothing-order-sort-form">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(request('event_id')): ?><input type="hidden" name="event_id" value="<?php echo e(request('event_id')); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <input type="hidden" name="search" value="<?php echo e(request('search', '')); ?>"><input type="hidden" name="team" value="<?php echo e(request('team', '')); ?>">
      <label for="clothing-order-sort" class="form-label">Sort orders</label>
      <select class="form-select" id="clothing-order-sort" name="sort">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['rank' => 'Team ranking / position', 'date_desc' => 'Order date — newest first', 'date_asc' => 'Order date — oldest first', 'team' => 'Team', 'age_group' => 'Age group', 'name' => 'Player name']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sortValue => $sortLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($sortValue); ?>" <?php if(($orderSort ?? 'rank') === $sortValue): echo 'selected'; endif; ?>><?php echo e($sortLabel); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
      <noscript><button class="btn btn-outline-primary">Sort orders</button></noscript>
    </form>
    <?php $grandTotal = 0; ?>
    <?php
      $groupByTeam = in_array($orderSort ?? 'rank', ['rank', 'team'], true);
      $orderGroups = $groupByTeam ? $clothings->groupBy('team_id') : ($clothings->isEmpty() ? collect() : collect([$clothings]));
    ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $orderGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $teamOrders): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <details class="border rounded p-2 mb-3" data-clothing-team-group open><summary class="fw-semibold"><?php echo e($groupByTeam ? $teamOrders->first()->team?->name : 'All paid orders'); ?> · <?php echo e($teamOrders->count()); ?> orders</summary>
    <div class="table-responsive">
      <table class="table table-striped align-middle clothing-fulfillment-table">
        <thead class="table-light">
          <tr>
            <th>Player</th>
            <th>Team</th>
            <th>Age group</th>
            <th>Order date</th>
            <th>Item</th>
            <th>Size</th>
            <th>Qty</th>
            <th>Status</th>
            <th>Order details</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $teamOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $refundAllowed = app(\App\Services\Clothing\ClothingRefundService::class)->canRefund($order->event, $order, auth()->user());
              $refundDeadline = app(\App\Services\Clothing\ClothingRefundService::class)->deadline($order->event, $order);
            ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $price = (float) $item->price;
                  $qty = (int) ($item->qty ?: 1);
                  $lineTotal = (float) ($item->line_total ?: $price * $qty);
                ?>
                <tr data-clothing-order-line data-team-id="<?php echo e($order->team_id); ?>">
                  <td data-label="Player"><?php $rosterRank = $order->team?->team_players->firstWhere('player_id', $order->player_id)?->rank; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterRank > 0): ?><span class="text-muted">#<?php echo e($rosterRank); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($order->player?->full_name); ?></td>
                  <td data-label="Team"><?php echo e(optional($order->team)->name); ?></td>
                  <td data-label="Age group"><?php echo e($order->team?->category?->category?->name ?? 'Not set'); ?></td>
                  <td data-label="Order date"><div><span class="text-nowrap"><?php echo e($order->created_at?->timezone('Africa/Johannesburg')->format('d M Y') ?? 'Not recorded'); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->created_at): ?><small class="d-block text-muted"><?php echo e($order->created_at->timezone('Africa/Johannesburg')->format('H:i')); ?> SAST</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></td>
                  <td data-label="Item"><?php echo e($item->item_name ?: optional($item->itemType)->item_type_name); ?></td>
                  <td data-label="Size"><div><span data-size-label><?php echo e($item->size_name ?: optional($item->size)->size); ?></span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->refundItems->isEmpty() && $item->itemType?->sizes->isNotEmpty()): ?>
                      <details><summary class="small text-primary">Change size</summary>
                        <form method="POST" action="<?php echo e(route('backend.region.clothing.orders.size', [$region, $order->event_id, $order, $item])); ?>" data-size-change-form>
                          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                          <input type="hidden" name="expected_size_id" value="<?php echo e((int) $item->clothing_item_size); ?>">
                          <input type="hidden" name="expected_size_name" value="<?php echo e($item->size_name); ?>">
                          <label class="visually-hidden" for="order-item-size-<?php echo e($item->id); ?>">New size</label>
                          <select class="form-select form-select-sm" id="order-item-size-<?php echo e($item->id); ?>" name="size_id" required>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $item->itemType->sizes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $availableSize): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($availableSize->id); ?>" <?php if((int) $item->clothing_item_size === (int) $availableSize->id): echo 'selected'; endif; ?>><?php echo e($availableSize->size); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          </select>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($qty > 1): ?><p class="small text-muted mb-1">Applies to all <?php echo e($qty); ?> items on this line.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          <button class="btn btn-sm btn-outline-primary mt-1">Save size</button>
                          <p class="small mb-0" data-size-feedback role="status" aria-live="polite"></p>
                        </form>
                      </details>
                    <?php elseif($item->refundItems->isNotEmpty()): ?>
                      <small class="text-muted d-block">Size retained for refund history</small>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div></td>
                  <td data-label="Qty"><?php echo e($qty); ?></td>
                  <td data-label="Status"><span class="badge bg-label-success">Paid</span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->refunds->isNotEmpty()): ?><a class="d-block small" data-order-refund-link href="<?php echo e(route('backend.clothing.refunds.show', [$order->event_id, $order, 'sort' => $orderSort ?? 'rank', 'search' => request('search', ''), 'team' => request('team', ''), 'all_events' => $clothingEvent ? 0 : 1])); ?>">Refund history</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                  <td data-label="Order details"><div class="clothing-order-actions"><details><summary>Details</summary><dl class="mt-2 mb-0 text-break">
                    <dt>Order</dt><dd>#<?php echo e($order->id); ?></dd>
                    <dt>Payfast ID</dt><dd><?php echo e($order->pf_id); ?></dd>
                    <dt>Unit Price</dt><dd>R<?php echo e(number_format($price, 2)); ?></dd>
                    <dt>Line Total</dt><dd>R<?php echo e(number_format($lineTotal, 2)); ?></dd>
                  </dl></details>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refundAllowed): ?>
                      <a class="btn btn-sm btn-outline-danger" data-order-refund-link href="<?php echo e(route('backend.clothing.refunds.show', [$order->event_id, $order, 'sort' => $orderSort ?? 'rank', 'search' => request('search', ''), 'team' => request('team', ''), 'all_events' => $clothingEvent ? 0 : 1])); ?>">Select items to refund</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div></td>
                </tr>
                <?php
                  $grandTotal += $lineTotal;
                ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="9" class="text-center text-muted py-3">No clothing orders found for this region</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>

      </table>
    </div></details>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothings->isEmpty()): ?><p class="text-muted">No clothing orders found for this region</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <details class="border rounded p-3 mt-3"><summary>Financial summary</summary><div class="row g-3 mt-1">
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Customer payments received</small><strong class="fs-5 text-success">R<?php echo e(number_format($clothingFinancials['received'], 2)); ?></strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">PayFast fees</small><strong class="fs-5 text-warning">− R<?php echo e(number_format($clothingFinancials['payfast_fees'], 2)); ?></strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Net clothing proceeds before supplier costs</small><strong class="fs-5">R<?php echo e(number_format($clothingFinancials['net'], 2)); ?></strong></div></div>
    </div>
    <p class="mt-3 mb-0"><strong>Totals: R<?php echo e(number_format($grandTotal, 2)); ?></strong></p>
    </details>
  </div>
</div>
<style>
.clothing-order-actions { display:flex; flex-wrap:wrap; gap:.75rem; align-items:start; }
.clothing-refund-panel { width:100%; max-width:40rem; min-width:0; }
.clothing-refund-panel form { min-width:0; }
.clothing-orders-admin summary { min-height:44px !important; cursor:pointer; align-content:center; }
.clothing-orders-admin .btn { min-height:44px !important; }
.clothing-orders-admin :is(input,select) { min-height:44px !important; }
.clothing-orders-admin [hidden] { display:none !important; }
.clothing-orders-admin .btn-group { display:flex; flex-wrap:wrap; gap:.4rem; }
.clothing-fulfillment-table td { white-space:normal; overflow-wrap:anywhere; }
@media(max-width:767px) {
 .clothing-fulfillment-table thead { display:none; }
 .clothing-fulfillment-table tbody, .clothing-fulfillment-table tr { display:block; }
 .clothing-fulfillment-table tr { border-bottom:1px solid #d9e1eb; padding:.75rem; }
 .clothing-fulfillment-table td { display:grid; grid-template-columns:80px minmax(0,1fr); gap:.5rem; border:0; padding:.35rem 0; }
 .clothing-fulfillment-table td::before { content:attr(data-label); font-weight:600; }
 .clothing-fulfillment-table td[colspan] { display:block; }
 .clothing-fulfillment-table td[colspan]::before { display:none; }
}
</style>
<script>
(() => {
  const downloadStatus = document.getElementById('clothing-download-status');
  const downloadSpinner = downloadStatus.querySelector('.spinner-border');
  const downloadMessage = downloadStatus.querySelector('[data-download-message]');
  const downloadLinks = [...document.querySelectorAll('[data-clothing-download]')];
  let downloadInProgress = false;
  downloadLinks.forEach(link => link.addEventListener('click', async event => {
    event.preventDefault();
    if (downloadInProgress) return;
    downloadInProgress = true;
    downloadLinks.forEach(button => {
      button.setAttribute('aria-disabled', 'true');
      button.setAttribute('aria-busy', 'true');
      button.classList.add('disabled');
    });
    downloadStatus.hidden = false;
    downloadStatus.classList.remove('alert-danger');
    downloadStatus.classList.add('alert-info');
    downloadSpinner.hidden = false;
    downloadMessage.textContent = `Preparing ${link.dataset.clothingDownload} download…`;
    try {
      const response = await fetch(link.href, {credentials: 'same-origin'});
      const type = response.headers.get('Content-Type') || '';
      const expectedType = link.dataset.clothingDownload === 'PDF' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
      if (response.redirected || response.status === 401 || response.status === 419) {
        throw new Error('Your session may have expired. Refresh this page, sign in if needed, and try again.');
      }
      if (!response.ok || !type.toLowerCase().includes(expectedType) || !/attachment/i.test(response.headers.get('Content-Disposition') || '')) {
        throw new Error('The download could not be prepared. Please try again.');
      }
      const file = await response.blob();
      if (!file.size) throw new Error('The download was empty. Please try again.');
      const objectUrl = URL.createObjectURL(file);
      const download = document.createElement('a');
      download.href = objectUrl;
      download.download = link.download;
      document.body.appendChild(download);
      download.click();
      download.remove();
      setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
      downloadMessage.textContent = `${link.dataset.clothingDownload} is ready. Check your browser’s Downloads for the file.`;
    } catch (error) {
      downloadStatus.classList.remove('alert-info');
      downloadStatus.classList.add('alert-danger');
      downloadMessage.textContent = error.message || 'The download failed. Please try again.';
    } finally {
      downloadSpinner.hidden = true;
      downloadInProgress = false;
      downloadLinks.forEach(button => {
        button.removeAttribute('aria-disabled');
        button.removeAttribute('aria-busy');
        button.classList.remove('disabled');
      });
    }
  }));
  document.querySelectorAll('[data-size-change-form]').forEach(form => form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button');
    if (button.disabled) return;
    const feedback = form.querySelector('[data-size-feedback]');
    const sizeSelect = form.querySelector('select');
    button.disabled = true;
    feedback.textContent = 'Saving…';
    try {
      const input = new FormData(form);
      sizeSelect.disabled = true;
      const response = await fetch(form.action, {method: 'POST', body: input, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Size could not be saved.');
      form.closest('td').querySelector('[data-size-label]').textContent = result.size_name;
      form.elements.expected_size_id.value = result.size_id;
      form.elements.expected_size_name.value = result.size_name;
      sizeSelect.value = result.size_id;
      feedback.textContent = result.message;
      filter();
    } catch (error) {
      feedback.textContent = error.message || 'Size could not be saved. Please try again.';
    } finally {
      button.disabled = false;
      sizeSelect.disabled = false;
    }
  }));
  const search = document.getElementById('clothing-order-search');
  const team = document.getElementById('clothing-team-filter');
  const lines = [...document.querySelectorAll('[data-clothing-order-line]')];
  const context = new URLSearchParams(window.location.search);
  search.value = context.get('search') || '';
  team.value = context.get('team') || '';
  const sortForm = document.getElementById('clothing-order-sort-form');
  const preserveFilters = () => {
    sortForm.elements.search.value = search.value.slice(0, 120);
    sortForm.elements.team.value = team.value;
  };
  sortForm.addEventListener('submit', preserveFilters);
  document.getElementById('clothing-order-sort').addEventListener('change', () => {
    preserveFilters();
    sortForm.requestSubmit();
  });

  const filter = () => {
    const term = search.value.trim().toLocaleLowerCase();
    document.querySelectorAll('[data-order-refund-link]').forEach(link => {
      const url = new URL(link.href);
      url.searchParams.set('search', search.value.slice(0, 120));
      url.searchParams.set('team', team.value);
      link.href = url.toString();
    });
    let shown = 0;
    lines.forEach(line => {
      const searchable = line.cloneNode(true);
      searchable.querySelectorAll('[data-size-change-form]').forEach(form => form.remove());
      const matches = (!team.value || line.dataset.teamId === team.value) && searchable.textContent.toLocaleLowerCase().includes(term);
      line.hidden = !matches;
      if (matches) shown++;
    });
    document.querySelectorAll('[data-clothing-team-group]').forEach(group => {
      group.hidden = ![...group.querySelectorAll('[data-clothing-order-line]')].some(line => !line.hidden);
      if (term) group.open = !group.hidden;
    });
    document.getElementById('clothing-order-count').textContent = `${shown} of ${lines.length} item lines shown`;
    document.getElementById('clothing-order-empty').hidden = shown > 0 || lines.length === 0;
  };
  search.addEventListener('input', filter);
  team.addEventListener('change', filter);
  filter();
})();
</script>
<?php echo $__env->make('backend.clothing.partials.refund-completed', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\clothing-index.blade.php ENDPATH**/ ?>