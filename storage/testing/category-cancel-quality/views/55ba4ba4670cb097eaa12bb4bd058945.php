  <div class="roster-player-filters">
    <div class="roster-filters roster-primary-filters">
      <div><label class="visually-hidden" for="roster-search">Find a player or team</label><input id="roster-search" class="form-control" type="search" placeholder="Find a player or team" data-roster-search></div>
      <div><label class="form-label" for="roster-payment">Payment</label><select id="roster-payment" class="form-select" data-roster-filter="payment"><option value="">All payments</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select></div>
    </div>
    <details data-roster-more-filters>
      <summary class="btn btn-outline-secondary">More filters <span data-roster-advanced-count></span></summary>
      <div class="roster-filters roster-advanced-filters mt-2">
        <div><label class="form-label" for="roster-category">Category</label><select id="roster-category" class="form-select" data-roster-filter="category"><option value="">All categories</option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($category->id); ?>"><?php echo e($category->category?->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select></div>
        <div><label class="form-label" for="roster-profile">Profile</label><select id="roster-profile" class="form-select" data-roster-filter="profile"><option value="">All players</option><option value="linked">Linked profile</option><option value="imported">Imported / unlinked</option></select></div>
        <div><label class="form-label" for="roster-publication">Visibility</label><select id="roster-publication" class="form-select" data-roster-filter="publication"><option value="">All teams</option><option value="published">Published</option><option value="draft">Unpublished</option></select></div>
      </div>
      <p class="small text-muted mb-0 mt-2">Filters apply to the selected region. <?php echo e(($regionalScoped ?? false) ? 'Exports include only your assigned regions' : 'Exports cover the whole event'); ?>; reserves stay in the selection queue.</p>
    </details>
    <div class="roster-filter-actions d-flex flex-wrap gap-2">
      <div class="dropdown">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Tools</button>
        <div class="dropdown-menu">
          <button type="button" class="dropdown-item" data-roster-expand="true">Expand all</button>
          <button type="button" class="dropdown-item" data-roster-expand="false">Collapse all</button>
          <button type="button" class="dropdown-item" data-workspace-refresh>Refresh roster</button>
          <span class="dropdown-item-text small text-muted"><?php echo e(($regionalScoped ?? false) ? 'Export assigned region roster - occupied places only' : 'Export event roster - all regions - occupied places only'); ?></span>
          <a class="dropdown-item" href="<?php echo e(route('event.players.exportPdf', $event->id)); ?>" target="_blank" rel="noopener">Download PDF</a>
          <a class="dropdown-item" href="<?php echo e(route('event.players.exportExcel', $event->id)); ?>">Download Excel</a>
        </div>
      </div>
      <button type="button" class="btn btn-outline-secondary" data-roster-clear aria-label="Clear filters" title="Clear filters"><i class="ti ti-filter-off" aria-hidden="true"></i></button>
    </div>
  </div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_roster-filters.blade.php ENDPATH**/ ?>