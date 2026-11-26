<?php
  // Team checkout is priced from the event fee (plus any region fee), not the
  // category rows used to organise imported team selections.
  $categoryFees = $event->isTeam()
    ? collect()
    : $event->eventCategories
      ->filter(fn ($categoryEvent) => $categoryEvent->category)
      ->map(fn ($categoryEvent) => [
        'category' => $categoryEvent->category->name,
        'fee' => (float) ($categoryEvent->entry_fee ?? $event->entryFee ?? 0),
        'remaining' => $categoryEvent->entry_limit === null ? null : app(\App\Services\DivisionCapacityService::class)->remaining($categoryEvent),
      ])
      ->values();

  $distinctFees = $categoryFees
    ->pluck('fee')
    ->uniqueStrict()
    ->values();

  $hasCategorySpecificFees = $distinctFees->count() > 1 || $categoryFees->contains(fn ($row) => $row['remaining'] !== null);
  $singleEntryFee = $distinctFees->first() ?? (float) ($event->entryFee ?? 0);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasCategorySpecificFees): ?>
  <li class="mb-2">
    <strong>Entry fees:</strong>
    <ul class="list-unstyled ms-3 mt-2 mb-0">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categoryFees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryFee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <span><?php echo e($categoryFee['category']); ?></span>
          <span class="d-inline-flex flex-wrap align-items-center gap-2">
            <span class="badge bg-label-primary">R<?php echo e(number_format($categoryFee['fee'], 2)); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryFee['remaining'] !== null): ?>
              <span class="badge <?php echo e($categoryFee['remaining'] === 0 ? 'bg-label-warning' : 'bg-label-secondary'); ?>">
                <?php echo e($categoryFee['remaining'] === 0 ? 'Full' : $categoryFee['remaining'].' '.\Illuminate\Support\Str::plural('spot', $categoryFee['remaining']).' left'); ?>

              </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </span>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
  </li>
<?php else: ?>
  <li class="mb-2">
    <strong>Entry fee:</strong>
    <span class="badge bg-label-primary">R<?php echo e(number_format($singleEntryFee, 2)); ?></span>
  </li>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\entry-fees.blade.php ENDPATH**/ ?>