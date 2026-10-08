

<?php $__env->startSection('title', $event->name . ' – Categories'); ?>


<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<style>
  .select2-container .select2-selection--multiple {
    min-height: 38px;
    border: 1px solid #d9dee3;
  }

  .fee-input {
    max-width: 120px;
  }
</style>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <?php echo $__env->make('backend.event.partials.header', [
    'eventWorkspaceActive' => 'more',
    'eventWorkspaceIcon' => 'ti-list-details',
    'eventWorkspaceSubtitle' => 'Category setup',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div><h2 class="h4 mb-1">Manage categories</h2><p class="text-muted mb-0">Attach categories, set event fees and remove unused setup.</p></div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger btn-sm" id="cleanupCategoriesBtn"
                data-url="<?php echo e(route('admin.categories.cleanup', $event)); ?>">
          <i class="ti ti-trash me-1"></i>Remove Empty Categories
        </button>
    </div>
  </div>

  <?php
    $attachedCategoryIds = $categoryEvents
      ->pluck('category_id')
      ->values()
      ->all();
  ?>

  
  <div class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0">Add Existing Category</h5>
    </div>

    <div class="card-body">
      <form method="POST"
            action="<?php echo e(route('admin.categories.attach', $event)); ?>"
            class="d-flex gap-2 align-items-start">
        <?php echo csrf_field(); ?>

        <div class="flex-grow-1">
          <select name="category_ids[]"
                  class="form-select select2"
                  multiple
                  data-placeholder="Select categories…">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($cat->id); ?>">
                <?php echo e($cat->name); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>

        <button class="btn btn-primary">
          <i class="ti ti-plus"></i> Add Selected
        </button>
      </form>
    </div>
  </div>

  
  <div class="card mb-4 border-start border-success border-3">
    <div class="card-header">
      <h5 class="mb-0">Create New Category</h5>
    </div>

    <div class="card-body">
      <form method="POST"
            action="<?php echo e(route('admin.categories.create', $event)); ?>"
            class="d-flex gap-2">
        <?php echo csrf_field(); ?>

        <input type="text"
               name="name"
               class="form-control"
               placeholder="e.g. U14 Boys"
               required>

        <button class="btn btn-success">
          <i class="ti ti-plus"></i> Create
        </button>
      </form>
    </div>
  </div>

  
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-striped mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Category</th>
            <th class="text-center">Entries</th>
            <th class="text-center">Entry Fee Override</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><?php echo e($categoryEvent->category->name); ?></td>

              <td class="text-center">
                <?php echo e($categoryEvent->categoryEventRegistrations->count()); ?>

              </td>

              <td class="text-center">
                <div class="d-flex justify-content-center align-items-center gap-2">
                  <input type="number"
                         class="form-control form-control-sm text-end fee-input category-fee-input"
                         data-id="<?php echo e($categoryEvent->id); ?>"
                         step="1"
                         min="0"
                         value="<?php echo e($categoryEvent->entry_fee); ?>"
                         placeholder="Default">

                  <button class="btn btn-sm btn-outline-primary save-fee-btn"
                          data-id="<?php echo e($categoryEvent->id); ?>">
                    <i class="ti ti-device-floppy"></i>
                  </button>
                </div>
              </td>

              <td class="text-end">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryEvent->categoryEventRegistrations->isEmpty()): ?>
                  <button class="btn btn-sm btn-outline-danger delete-category-btn"
                          data-url="<?php echo e(route('admin.category.delete', $categoryEvent)); ?>">
                    <i class="ti ti-trash me-1"></i>Remove
                  </button>
                <?php else: ?>
                  <span class="badge bg-secondary">In use</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-3">
                No categories attached to this event.
              </td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>



<?php $__env->startSection('page-script'); ?>

<script>
window.categoryConfig = {
    attachedIds: <?php echo json_encode($attachedCategoryIds, 15, 512) ?>,
    feeUpdateUrl: "<?php echo e(route('admin.events.category-fee.update', ':id')); ?>"
};
</script>

<script>
if (window.toastr) {
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: "toast-top-right",
        timeOut: 2000
    };
}

(function () {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  async function deleteRequest(url) {
    const res = await fetch(url, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json'
      }
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
      throw new Error(data.message || 'Request failed');
    }

    return data;
  }

  document.addEventListener('click', async function (e) {
    const removeBtn = e.target.closest('.delete-category-btn');
    if (removeBtn) {
      e.preventDefault();
      if (!confirm('Remove this category from the event?')) return;

      try {
        await deleteRequest(removeBtn.dataset.url);
        const row = removeBtn.closest('tr');
        if (row) row.remove();
        if (window.toastr) toastr.success('Category removed successfully.');
      } catch (err) {
        if (window.toastr) toastr.error(err.message || 'Could not remove category.');
      }
      return;
    }

    const cleanupBtn = e.target.closest('#cleanupCategoriesBtn');
    if (cleanupBtn) {
      e.preventDefault();
      if (!confirm('Remove all empty categories from this event?')) return;

      try {
        const result = await deleteRequest(cleanupBtn.dataset.url);
        if (window.toastr) toastr.success(`${result.removed ?? 0} empty categories removed.`);
        window.location.reload();
      } catch (err) {
        if (window.toastr) toastr.error(err.message || 'Cleanup failed.');
      }
    }
  });
})();
</script>

<script src="<?php echo e(asset(mix('js/eventCategories.js'))); ?>"></script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\categories.blade.php ENDPATH**/ ?>